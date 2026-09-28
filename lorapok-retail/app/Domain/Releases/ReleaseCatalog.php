<?php

declare(strict_types=1);

namespace App\Domain\Releases;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What is downloadable, and which version.
 *
 * Read live from the GitHub Releases API so the page is correct the moment a
 * release is published rather than at the next deploy — but backed by a file
 * CI commits, which is what answers when GitHub is unreachable or rate
 * limiting us.
 *
 * Neither source alone is enough. The API alone means a download page that
 * 500s because GitHub is having a bad morning; the committed file alone means
 * a page that is stale between deploys, and for a tag-deployed site,
 * permanently one release behind.
 */
final class ReleaseCatalog
{
    private const CACHE_KEY = 'releases.latest';

    /** Fresh for an hour. */
    private const FRESH = 3600;

    /** Served stale for a day while it refreshes behind the request. */
    private const STALE = 86400;

    /**
     * @return array{tag: string, published_at: ?string, notes_url: ?string, assets: array<int, array{name: string, url: string, size: int}>}
     */
    public function latest(): array
    {
        return Cache::flexible(self::CACHE_KEY, [self::FRESH, self::STALE], fn (): array => $this->fetch() ?? $this->committed());
    }

    /** Whether there is anything to download at all. */
    public function hasRelease(): bool
    {
        return $this->latest()['tag'] !== '';
    }

    /**
     * Group assets by what they are, so the page can label them.
     *
     * @return array<string, array<int, array{name: string, url: string, size: int}>>
     */
    public function byPlatform(): array
    {
        $grouped = [];

        foreach ($this->latest()['assets'] as $asset) {
            $grouped[$this->platformOf($asset['name'])][] = $asset;
        }

        return $grouped;
    }

    private function platformOf(string $filename): string
    {
        return match (true) {
            str_ends_with($filename, '.apk'), str_ends_with($filename, '.aab') => 'Android',
            str_ends_with($filename, '.msi'), str_ends_with($filename, '.exe') => 'Windows',
            str_ends_with($filename, '.dmg') => 'macOS',
            str_ends_with($filename, '.deb'), str_ends_with($filename, '.AppImage') => 'Linux',
            str_contains($filename, 'brand') => 'Brand pack',
            str_contains($filename, 'self-host') => 'Self-hosting',
            default => 'Other',
        };
    }

    /** @return array{tag: string, published_at: ?string, notes_url: ?string, assets: array<int, array{name: string, url: string, size: int}>}|null */
    private function fetch(): ?array
    {
        $repository = config('releases.repository');

        if (! is_string($repository) || $repository === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->retry(1, 200)
                ->withHeaders(array_filter([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Authorization' => config('releases.token') ? 'Bearer '.config('releases.token') : null,
                ]))
                ->get("https://api.github.com/repos/{$repository}/releases/latest");

            if (! $response->successful()) {
                // 403 with x-ratelimit-remaining: 0 is the rate-limit signal,
                // and it is handled the same as any other failure: fall back,
                // never throw. A download page must not fail.
                Log::warning('Release API returned {status}', [
                    'status' => $response->status(),
                    'ratelimit_remaining' => $response->header('x-ratelimit-remaining'),
                ]);

                return null;
            }

            return $this->normalise($response->json());
        } catch (Throwable $e) {
            Log::warning('Release API unreachable: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The copy CI committed. Always present, always parseable, possibly stale.
     *
     * @return array{tag: string, published_at: ?string, notes_url: ?string, assets: array<int, array{name: string, url: string, size: int}>}
     */
    private function committed(): array
    {
        $empty = ['tag' => '', 'published_at' => null, 'notes_url' => null, 'assets' => []];

        $path = resource_path('releases.json');

        if (! is_file($path)) {
            return $empty;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? array_merge($empty, $decoded) : $empty;
        } catch (Throwable) {
            // A malformed file must not take the page down with it.
            return $empty;
        }
    }

    /**
     * @param  mixed  $release
     * @return array{tag: string, published_at: ?string, notes_url: ?string, assets: array<int, array{name: string, url: string, size: int}>}
     */
    private function normalise($release): array
    {
        $release = is_array($release) ? $release : [];

        return [
            'tag' => (string) ($release['tag_name'] ?? ''),
            'published_at' => $release['published_at'] ?? null,
            'notes_url' => $release['html_url'] ?? null,
            'assets' => array_values(array_map(fn (array $asset): array => [
                'name' => (string) ($asset['name'] ?? ''),
                'url' => (string) ($asset['browser_download_url'] ?? ''),
                'size' => (int) ($asset['size'] ?? 0),
            ], $release['assets'] ?? [])),
        ];
    }
}
