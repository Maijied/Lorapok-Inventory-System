<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Help\HelpRepository;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

/**
 * The handbook, public.
 *
 * The same Markdown the in-app help drawer renders. Public because support
 * conversations need a link to send, and because a shop deciding whether to
 * move onto this can read how it actually works first.
 */
final class DocsController
{
    public function __construct(private readonly HelpRepository $help) {}

    public function index(): View
    {
        return view('marketing.docs-index', [
            'sections' => $this->help->index(),
            'seo' => new Seo(
                title: 'Handbook',
                description: 'How Lorapok Retail works: selling, products, reports, '
                    .'and what an operator can and cannot do.',
                path: '/docs',
            ),
        ]);
    }

    public function show(string $section, string $page): View
    {
        $slug = "{$section}/{$page}";

        // pathFor() refuses anything that is not a plain slug, so a crafted
        // URL cannot walk out of the docs tree.
        abort_if($this->help->pathFor($slug) === null, 404);

        $title = $this->help->titleFor($slug) ?? ucfirst(str_replace('-', ' ', $page));

        return view('marketing.docs-page', [
            'title' => $title,
            'section' => $section,
            'body' => $this->help->render($slug),
            'seo' => new Seo(
                title: $title,
                description: "How {$title} works in Lorapok Retail.",
                path: "/docs/{$slug}",
            ),
        ]);
    }
}
