<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Course;
use Illuminate\Support\Str;

/** Structured data (schema.org JSON-LD) and share-image helpers for the public site. */
class Seo
{
    /** <script type="application/ld+json"> for one or more schema objects. */
    public static function jsonLd(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    public static function absolute(?string $url): ?string
    {
        return $url ? url($url) : null;
    }

    /** Fallback image for link previews: admin share image, then logo, then favicon. */
    public static function defaultImage(): ?string
    {
        return self::absolute(media_url(setting('share_image')) ?? media_url(setting('logo')) ?? media_url(setting('favicon')));
    }

    public static function organization(): array
    {
        $address = setting('address');

        return array_filter([
            '@type' => 'EducationalOrganization',
            '@id' => url('/').'#organization',
            'name' => setting('site_name', config('app.name')),
            'url' => url('/'),
            'logo' => self::absolute(media_url(setting('logo')) ?? media_url(setting('favicon'))),
            'description' => setting('tagline'),
            'telephone' => setting('phone'),
            'email' => setting('email'),
            'address' => $address ? ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => 'KH'] : null,
            'sameAs' => array_column(social_links(), 'url') ?: null,
        ]);
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => setting('site_name', config('app.name')),
            'url' => url('/'),
            'inLanguage' => app()->getLocale(),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/search').'?s={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param  list<array{0: string, 1: string}>  $trail  [name, url] pairs after "Home" */
    public static function breadcrumbs(array $trail): array
    {
        $items = array_merge([[__('site.home'), url('/')]], $trail);

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    public static function course(Course $course): array
    {
        return array_filter([
            '@type' => 'Course',
            'name' => $course->title,
            'description' => Str::limit($course->summary ?: trim(strip_tags((string) $course->body)), 300),
            'url' => $course->url(),
            'image' => self::absolute($course->mediaUrl()),
            'inLanguage' => app()->getLocale(),
            'provider' => ['@type' => 'EducationalOrganization', 'name' => setting('site_name', config('app.name')), 'sameAs' => url('/')],
            'offers' => match (true) {
                $course->isFree() => [['@type' => 'Offer', 'category' => 'Free', 'price' => '0', 'priceCurrency' => $course->currency ?: 'USD', 'url' => $course->url()]],
                $course->isPurchasable() => $course->plans->map(fn ($plan) => [
                    '@type' => 'Offer',
                    'category' => 'Paid',
                    'name' => $plan->periodLabel(),
                    'price' => number_format($plan->currentPrice(), 2, '.', ''),
                    'priceCurrency' => $plan->currency(),
                    'availability' => 'https://schema.org/InStock',
                    'url' => $course->url(),
                ])->all(),
                default => null,
            },
            'timeRequired' => $course->duration ?: null,
            'isAccessibleForFree' => $course->isFree() ?: null,
            'hasCourseInstance' => [[
                '@type' => 'CourseInstance',
                'courseMode' => 'Online',
            ]],
        ]);
    }

    public static function article(Article $article): array
    {
        $publisher = ['@type' => 'Organization', 'name' => setting('site_name', config('app.name')), 'url' => url('/')];
        if ($logo = self::absolute(media_url(setting('logo')) ?? media_url(setting('favicon')))) {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }

        return array_filter([
            '@type' => $article->type === 'news' ? 'NewsArticle' : 'Article',
            'headline' => Str::limit($article->title, 110, ''),
            'description' => $article->excerptText(200),
            'image' => ($image = self::absolute($article->mediaUrl())) ? [$image] : null,
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified' => $article->updated_at?->toAtomString(),
            'inLanguage' => app()->getLocale(),
            'author' => ['@type' => 'Organization', 'name' => setting('site_name', config('app.name')), 'url' => url('/')],
            'publisher' => $publisher,
            'mainEntityOfPage' => $article->url(),
        ]);
    }
}
