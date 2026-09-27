<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Requests\ContactRequest;
use App\Http\Requests\ShopApplicationRequest;
use App\Models\ContactMessage;
use App\Models\Plan;
use App\Models\ShopApplication;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The two forms on the public site.
 *
 * Both write straight to central tables. Neither sends mail from the request:
 * a shop owner who has just filled in a form should not wait on an SMTP
 * handshake, and a mail outage must not lose the lead. The row is the record;
 * notification is a queued job on top of it.
 */
final class LeadController
{
    public function contact(): View
    {
        return view('marketing.contact', [
            'seo' => new Seo(
                title: 'Contact',
                description: 'Questions about Lorapok Retail, pricing, or moving an existing '
                    .'shop onto it. We reply in Bangla or English.',
                path: '/contact',
            ),
        ]);
    }

    public function storeContact(ContactRequest $request): RedirectResponse
    {
        ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'message']),
            'ip' => $request->ip(),
            'referrer' => substr((string) $request->headers->get('referer'), 0, 255) ?: null,
        ]);

        return redirect()
            ->route('marketing.thanks', ['kind' => 'contact'])
            // Redirect after POST so a refresh cannot submit twice.
            ->with('status', 'Message received.');
    }

    public function apply(): View
    {
        return view('marketing.apply', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'seo' => new Seo(
                title: 'Start your shop',
                description: 'Tell us your shop name and we will set it up on its own address, '
                    .'with its own database. You will be selling the same day.',
                path: '/start',
            ),
        ]);
    }

    public function storeApplication(ShopApplicationRequest $request): RedirectResponse
    {
        ShopApplication::create([
            ...$request->safe()->only([
                'shop_name', 'slug', 'owner_name', 'owner_email', 'owner_phone', 'city',
            ]),
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('marketing.thanks', ['kind' => 'apply'])
            ->with('status', 'Application received.');
    }

    public function thanks(string $kind): View
    {
        $isApplication = $kind === 'apply';

        return view('marketing.thanks', [
            'heading' => $isApplication ? 'Your shop is on the way' : 'Message received',
            'body' => $isApplication
                ? 'We will set it up and email you the address and your sign-in details. '
                    .'That usually happens the same day.'
                : 'We read every message and reply within a working day.',
            'seo' => new Seo(
                title: $isApplication ? 'Application received' : 'Message received',
                description: 'Thank you — we will be in touch shortly.',
                path: "/thanks/{$kind}",
                // A thank-you page has nothing to offer a search result, and
                // indexing it invites people to land here having sent nothing.
                indexable: false,
            ),
        ]);
    }
}
