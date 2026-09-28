<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Releases\ReleaseCatalog;
use App\Models\Plan;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

/**
 * The public site.
 *
 * Server-rendered rather than a separate static build, deliberately: pricing
 * reads the real `plans` table, so changing a row changes the page with no
 * deploy. A static site could not, and would fork the design system in the
 * process.
 */
final class PageController
{
    public function home(): View
    {
        return view('marketing.home', [
            'trialDays' => Plan::query()->where('is_active', true)->max('trial_days') ?? 14,
            'seo' => new Seo(
                title: 'Inventory and point of sale for shops that sell serialised goods',
                description: 'Lorapok Retail freezes the cost of every item at the moment it sells, '
                    .'so profit is a number you can read. Own subdomain, own database, works offline.',
                path: '/',
                schema: [$this->organisation(), $this->softwareApplication()],
            ),
        ]);
    }

    public function features(): View
    {
        return view('marketing.features', [
            'seo' => new Seo(
                title: 'Features',
                description: 'An append-only stock ledger, margin frozen per sale line, '
                    .'server-owned pricing, roles that actually restrict, and a till that installs to the home screen.',
                path: '/features',
            ),
        ]);
    }

    public function pricing(): View
    {
        return view('marketing.pricing', [
            // Ordered by the column the operator controls, so reordering the
            // page is a data change rather than a deploy.
            'plans' => Plan::query()
                ->with('limits')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('price_minor')
                ->get(),
            'seo' => new Seo(
                title: 'Pricing',
                description: 'One shop, one price. Every plan includes your own subdomain, '
                    .'your own database and unlimited sales. Pay by bKash, Nagad or bank transfer.',
                path: '/pricing',
                schema: [$this->organisation()],
            ),
        ]);
    }

    public function download(ReleaseCatalog $catalog): View
    {
        return view('marketing.download', [
            'catalog' => $catalog,
            'latest' => $catalog->latest(),
            'platforms' => $catalog->byPlatform(),
            'seo' => new Seo(
                title: 'Download',
                description: 'Lorapok Retail for Android, Windows, macOS and Linux — or run '
                    .'the container image on your own hardware. The web app installs without any of it.',
                path: '/download',
            ),
        ]);
    }

    public function privacy(): View
    {
        return view('marketing.legal', [
            'title' => 'Privacy',
            'updated' => 'September 2026',
            // Legal pages are real but should not compete in search results.
            'seo' => new Seo(
                title: 'Privacy',
                description: 'What Lorapok Retail collects, why, and how long it is kept.',
                path: '/legal/privacy',
                indexable: false,
            ),
            'sections' => [
                'What we hold' => [
                    'Your shop\'s data — products, stock movements, sales, customers and staff '
                        .'accounts — lives in a database belonging only to your shop. It is not in a '
                        .'shared table alongside other shops.',
                    'We also hold the contact details you give us when you apply or write to us.',
                ],
                'What we do with it' => [
                    'We use it to run the service and to contact you about your account. We do not '
                        .'sell it, and we do not use your shop\'s trading data for anything other than '
                        .'showing it back to you.',
                ],
                'Who can see it' => [
                    'Our operators can see your shop\'s name, plan and billing status. Reaching your '
                        .'shop\'s own data requires an impersonation session, which records who did it, '
                        .'when, and the reason they gave.',
                ],
                'Getting it back, or getting it deleted' => [
                    'Ask and we will export your shop\'s data or delete it. Deleting removes the shop\'s '
                        .'database; that cannot be undone, so we will confirm first.',
                ],
            ],
        ]);
    }

    public function terms(): View
    {
        return view('marketing.legal', [
            'title' => 'Terms',
            'updated' => 'September 2026',
            'seo' => new Seo(
                title: 'Terms',
                description: 'The terms of using Lorapok Retail.',
                path: '/legal/terms',
                indexable: false,
            ),
            'sections' => [
                'The service' => 'We provide Lorapok Retail as a hosted service. You are responsible '
                    .'for what your staff do with it and for the accuracy of what you put in it.',
                'Payment' => 'Plans are invoiced directly. Nothing renews automatically and there is no '
                    .'card on file. An unpaid account is suspended, not deleted — your data stays until '
                    .'you ask us to remove it.',
                'Your data is yours' => 'You can export it or have it deleted at any time. We do not '
                    .'claim ownership of anything you put into the system.',
                'Ending it' => 'You can stop at any time. We will give notice before withdrawing the '
                    .'service, and an export before anything is removed.',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function organisation(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Lorapok Labs',
            'url' => config('app.url'),
            'logo' => rtrim((string) config('app.url'), '/').'/android-chrome-512x512.png',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Dhaka',
                'addressCountry' => 'BD',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function softwareApplication(): array
    {
        $cheapest = Plan::query()->where('is_active', true)->orderBy('price_minor')->first();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('app.name'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android, iOS',
            // Only claimed when there is a real plan to quote. An Offer with a
            // price of zero because the table is empty is a lie to a crawler.
            'offers' => $cheapest === null ? null : [
                '@type' => 'Offer',
                'price' => number_format($cheapest->price_minor / 100, 2, '.', ''),
                'priceCurrency' => $cheapest->currency,
            ],
        ]);
    }
}
