<?php

declare(strict_types=1);

namespace App\Domain\Branding;

use App\Domain\Central\AuditLog;
use App\Models\Tenant;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A shop's own logo.
 *
 * `tenants.logo_path` has existed since Phase 1 with nothing writing to it, so
 * every installed app has shown a generated initial in the shop's accent
 * colour. That is a decent default and a poor substitute for a shop that has
 * a logo already.
 *
 * Two rules:
 *
 *   - **SVG is refused.** An SVG can carry script, and this file is rendered
 *     as an app icon on someone's home screen. The upside over a 512px PNG
 *     for an icon is nil, and the downside is a stored XSS vector.
 *
 *   - **Everything is re-encoded.** The uploaded bytes are never served. A
 *     file that is a valid PNG *and* something else — a polyglot — stops
 *     being that after GD reads and rewrites it.
 */
final class LogoService
{
    private const DISK = 'logos';

    /** What a maskable app icon needs; anything larger is wasted bytes. */
    private const SIZE = 512;

    /**
     * Fraction of the canvas the mark is allowed to occupy.
     *
     * Android crops a maskable icon to whatever shape the launcher uses, so
     * the corners are not ours to draw in. Reserving the outer 20% is what
     * lets the manifest claim `maskable` without lying — the generated icon
     * beside this one reserves the same band.
     */
    private const SAFE = 0.8;

    /**
     * Store a shop's logo, re-encoded to a square PNG.
     */
    public function store(Tenant $tenant, UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new DomainException(
                'Use a PNG, JPG or WEBP. SVG is not accepted — it can carry code, and '
                .'this ends up as an icon on someone\'s home screen.'
            );
        }

        $png = $this->square($file->getRealPath(), $mime);

        $previous = $tenant->logo_path;

        // Named from the content, so re-uploading the same file is a no-op
        // rather than an accumulating pile of near-identical images.
        $path = "shops/{$tenant->id}/".substr(hash('sha256', $png), 0, 32).'.png';

        Storage::disk(self::DISK)->put($path, $png);

        $tenant->forceFill(['logo_path' => $path])->save();

        // Replacing removes the old one rather than orphaning it.
        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            $this->forget($previous);
        }

        AuditLog::record('shop.logo_updated', $tenant, null, after: ['bytes' => strlen($png)]);

        return $path;
    }

    /**
     * Drop a shop's logo and fall back to the generated icon.
     */
    public function remove(Tenant $tenant): void
    {
        $path = $tenant->logo_path;

        $tenant->forceFill(['logo_path' => null])->save();

        if (is_string($path) && $path !== '') {
            $this->forget($path);
        }

        AuditLog::record('shop.logo_removed', $tenant);
    }

    /** The stored PNG, or null when the shop has none. */
    public function bytesFor(Tenant $tenant): ?string
    {
        $path = $tenant->logo_path;

        if (! is_string($path) || $path === '' || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->get($path);
    }

    /**
     * Re-encode to a centred square PNG on transparency, inside the safe area.
     *
     * Centred rather than stretched: a wide wordmark squashed into a square is
     * worse than the generated initial it replaces.
     */
    private function square(string $sourcePath, string $mime): string
    {
        $source = match ($mime) {
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($source === false) {
            // The extension and the mime agreed and GD still could not read
            // it, so the file is not the image it claims to be.
            throw new DomainException('That file could not be read as an image.');
        }

        try {
            $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

            $width = imagesx($source);
            $height = imagesy($source);
            $box = self::SIZE * self::SAFE;
            $scale = min($box / $width, $box / $height);

            $targetWidth = (int) round($width * $scale);
            $targetHeight = (int) round($height * $scale);

            imagecopyresampled(
                $canvas, $source,
                (int) round((self::SIZE - $targetWidth) / 2),
                (int) round((self::SIZE - $targetHeight) / 2),
                0, 0,
                $targetWidth, $targetHeight,
                $width, $height,
            );

            ob_start();
            imagepng($canvas, null, 9);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($source);

            if (isset($canvas)) {
                imagedestroy($canvas);
            }
        }
    }

    private function forget(string $path): void
    {
        try {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (Throwable) {
            // An orphaned file is untidy, not broken. Never let it fail the
            // request that replaced it.
        }
    }
}
