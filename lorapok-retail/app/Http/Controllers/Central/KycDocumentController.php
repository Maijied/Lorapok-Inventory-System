<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Domain\Central\AuditLog;
use App\Models\ShopVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve one KYC document, once, to one operator.
 *
 * Streamed through PHP rather than exposed on a public disk, so three things
 * hold that a public URL cannot offer:
 *
 *   - The link expires. It is signed for five minutes.
 *   - The viewer is known. The route is behind the operator guard, so a
 *     leaked link is still useless to anyone not signed in.
 *   - The view is recorded. Who looked at somebody's national ID is the
 *     question that matters afterwards, and it cannot be answered by
 *     auditing writes alone.
 *
 * `inline` rather than `attachment`: a reviewer comparing a licence against a
 * form should not end up with a folder of other people's identity documents
 * on their laptop.
 */
final class KycDocumentController
{
    public function __invoke(Request $request, ShopVerification $verification, string $kind): StreamedResponse
    {
        $path = $verification->documents()[$kind] ?? null;

        abort_if(! is_string($path) || $path === '', 404);

        $disk = Storage::disk('kyc');

        abort_unless($disk->exists($path), 404);

        // Recorded here as well as when the link was minted: a signed URL can
        // be opened more than once inside its window, and each opening is a
        // separate look at somebody's identity document.
        AuditLog::record(
            'kyc.document_opened',
            $verification,
            $request->user(),
            after: ['kind' => $kind],
            tenant: $verification->tenant,
        );

        return $disk->response($path, null, [
            'Content-Disposition' => 'inline',
            // Never cached by a proxy, never stored on disk by the browser.
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
