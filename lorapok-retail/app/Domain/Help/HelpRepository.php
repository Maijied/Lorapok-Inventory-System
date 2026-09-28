<?php

declare(strict_types=1);

namespace App\Domain\Help;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The help written for each screen.
 *
 * Content lives in Markdown under `resources/docs/` rather than in a database
 * or in Blade. It is reviewable in a diff, it travels with the code that it
 * describes, and it cannot drift out of sync with a deploy.
 *
 * The route-to-document mapping is explicit rather than derived from the route
 * name. A convention would silently produce "no help" for any route that did
 * not happen to match it, and a missing help page is exactly the thing nobody
 * notices until a shop asks.
 */
final class HelpRepository
{
    /**
     * Which document answers questions about which screen.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'tenant.dashboard' => 'shop/overview',
        'tenant.pos' => 'shop/pos',
        'tenant.products' => 'shop/products',
        'tenant.products.create' => 'shop/products',
        'tenant.products.edit' => 'shop/products',
        'tenant.reports' => 'shop/reports',

        'central.shops' => 'operator/shops',
        'central.audit' => 'operator/impersonation',
        'central.verifications' => 'operator/shops',
    ];

    /** Routes that deliberately have no help: sign-in pages and redirects. */
    private const EXEMPT = [
        'tenant.login',
        'tenant.logout',
        'tenant.manifest',
        'tenant.icon',
        'central.login',
        'central.logout',
        'central.kyc.document',
    ];

    /** @return array<string, string> */
    public static function map(): array
    {
        return self::MAP;
    }

    /** @return array<int, string> */
    public static function exempt(): array
    {
        return self::EXEMPT;
    }

    public function hasHelpFor(?string $routeName): bool
    {
        return $routeName !== null && isset(self::MAP[$routeName]);
    }

    /**
     * The rendered help for a screen, or null if it has none.
     */
    public function forRoute(?string $routeName): ?string
    {
        if (! $this->hasHelpFor($routeName)) {
            return null;
        }

        return $this->render(self::MAP[$routeName]);
    }

    /**
     * Render one document to HTML.
     *
     * Cached because it never changes between deploys, and parsing Markdown on
     * every page load to produce identical output is waste. The key carries
     * the file's modification time, so editing a document invalidates it
     * without anyone remembering to clear a cache.
     */
    public function render(string $slug): ?string
    {
        $path = $this->pathFor($slug);

        if ($path === null) {
            return null;
        }

        return Cache::remember(
            'help:'.$slug.':'.filemtime($path),
            now()->addDay(),
            fn (): string => Str::markdown((string) file_get_contents($path)),
        );
    }

    /**
     * Resolve a slug to a file, refusing anything that escapes the docs tree.
     */
    public function pathFor(string $slug): ?string
    {
        // A slug reaches this from a URL. Without this, `../../.env` is a
        // readable file.
        if (preg_match('/^[a-z0-9]+(?:[\/-][a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        $path = resource_path("docs/{$slug}.md");

        return is_file($path) ? $path : null;
    }

    /**
     * A document's own title, taken from its first heading.
     *
     * Derived from the file rather than the slug, because a slug makes a poor
     * title: `pos` becomes "Pos" and `shops` becomes "Shops" when the page is
     * actually about provisioning. The heading is what the author wrote.
     */
    public function titleFor(string $slug): ?string
    {
        $path = $this->pathFor($slug);

        if ($path === null) {
            return null;
        }

        foreach (file($path) ?: [] as $line) {
            if (str_starts_with($line, '# ')) {
                return trim(substr($line, 2));
            }
        }

        return Str::of($slug)->afterLast('/')->replace('-', ' ')->title()->value();
    }

    /**
     * Every document that exists, grouped by section, for the public index.
     *
     * @return array<string, array<int, string>>
     */
    public function index(): array
    {
        $documents = [];

        foreach (glob(resource_path('docs/*/*.md')) ?: [] as $path) {
            $section = basename(dirname($path));
            $documents[$section][] = basename($path, '.md');
        }

        ksort($documents);

        foreach ($documents as &$slugs) {
            sort($slugs);
        }

        return $documents;
    }
}
