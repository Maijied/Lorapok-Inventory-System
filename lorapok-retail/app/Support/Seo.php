<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The SEO description for one public page.
 *
 * A value object rather than a pile of Blade variables, because the parts have
 * to agree: the canonical URL, the Open Graph URL and the entry in sitemap.xml
 * are the same address, and when they drift a crawler picks whichever it likes.
 * Built in one place, they cannot.
 *
 * @implements Arrayable<string, mixed>
 */
final class Seo implements Arrayable
{
    /**
     * @param  array<int, array<string, mixed>>  $schema  JSON-LD blocks
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $path = '/',
        public readonly ?string $image = null,
        public readonly bool $indexable = true,
        public readonly array $schema = [],
    ) {}

    /**
     * The absolute, canonical address of this page.
     *
     * Always built from config('app.url') rather than from the incoming
     * request: a page reached at a stray host or with tracking parameters
     * attached must still declare one canonical address, or every variant
     * competes with the original in search results.
     */
    public function canonical(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($this->path, '/');
    }

    public function imageUrl(): string
    {
        return $this->image ?? rtrim((string) config('app.url'), '/').'/og-image.png';
    }

    /** The full title, including the product name. */
    public function fullTitle(): string
    {
        $app = (string) config('app.name');

        return str_contains($this->title, $app)
            ? $this->title
            : "{$this->title} — {$app}";
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->fullTitle(),
            'description' => $this->description,
            'canonical' => $this->canonical(),
            'image' => $this->imageUrl(),
            'indexable' => $this->indexable,
            'schema' => $this->schema,
        ];
    }
}
