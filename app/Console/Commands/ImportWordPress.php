<?php

namespace App\Console\Commands;

use App\Import\TagDivPageParser;
use App\Models\Article;
use App\Models\Course;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Testimonial;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Pool as GuzzlePool;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Copies the content of the old WordPress site into this app: courses,
 * tutorials, posts, testimonials, partners, the fixed pages and every image
 * they use. Safe to run again: records are matched on their WordPress ID /
 * slug and updated, so it also works as a "sync" (it overwrites edits made in
 * the admin to imported records).
 *
 * The old server is slow and drops connections, so every response is cached
 * in storage/app/wp-import and images download in parallel. Use --refresh to
 * ignore the cache.
 */
class ImportWordPress extends Command
{
    protected $signature = 'wp:import
        {--base=https://teamitcambodia.com : WordPress site to import from}
        {--refresh : Ignore cached responses and download everything again}
        {--no-media : Keep images on the old server instead of downloading them}';

    protected $description = 'Import the content and images of the old WordPress site';

    private const FIELDS = 'id,slug,title,content,excerpt,featured_media,date,menu_order,status';

    private string $base;

    private string $host;

    private TagDivPageParser $parser;

    /** @var array<string, string> remote image URL => path on the public disk */
    private array $images = [];

    public function handle(): int
    {
        $this->base = rtrim($this->option('base'), '/');
        $this->host = parse_url($this->base, PHP_URL_HOST);
        $this->parser = new TagDivPageParser;

        try {
            $home = $this->fetch('/');
            $this->importCourses($home);
            $this->importArticles('tutorial', '/wp-json/wp/v2/tdcpt_location');
            $this->importArticles('news', '/wp-json/wp/v2/posts'); // WordPress posts -> News
            $this->importTestimonials();
            $this->importPages($home);
            $this->importSettings($home);
            $this->downloadImages();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('Responses fetched so far are cached; run the command again to continue.');

            return self::FAILURE;
        }

        $this->info('Import finished.');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- content

    private function importCourses(string $home): void
    {
        $this->info('Courses');
        $cards = $this->courseCards($home);
        $items = $this->collection('/wp-json/wp/v2/tdcpt_service');
        $featured = $this->featuredImages($items);

        foreach ($items as $i => $item) {
            $slug = urldecode($item['slug']);
            $detail = $this->courseDetail($this->fetch('/tdcpt_service/'.rawurlencode($slug).'/'));
            $card = $cards[$slug] ?? [];

            $course = Course::updateOrCreate(['wp_id' => $item['id']], [
                'title' => $this->plain($item['title']['rendered']),
                'slug' => $slug,
                'summary' => $this->plain($item['excerpt']['rendered'] ?? ''),
                'body' => $this->cleanHtml($item['content']['rendered']),
                'image' => $this->image($featured[$item['featured_media']] ?? null),
                'icon' => $this->image($card['icon'] ?? null),
                'badge' => $card['badge'] ?? null,
                'price_prefix' => $detail['price_prefix'] ?? null,
                'price' => $detail['price'] ?? null,
                'price_suffix' => $detail['price_suffix'] ?? null,
                'currency' => str_contains((string) ($detail['price'] ?? ''), '៛') ? 'KHR' : 'USD',
                'features' => $detail['features'] ?? [],
                'cta_label' => $detail['cta_label'] ?? null,
                'cta_url' => $this->localLink($detail['cta_url'] ?? null),
                'sort_order' => $card['order'] ?? $i,
                'is_published' => true,
            ]);

            // "$49" + "Per year" -> a plan of 49 for 365 days, unless the admin already set plans.
            $amount = $this->priceAmount($detail['price'] ?? null);
            if ($amount && $course->plans()->doesntExist()) {
                $course->plans()->create(['days' => $this->accessDays($detail['price_suffix'] ?? null), 'price' => $amount, 'position' => 1]);
            }
            $this->line('  '.$slug);
        }
    }

    private function importArticles(string $type, string $endpoint): void
    {
        $this->info(Article::TYPES[$type]);
        $items = collect($this->collection($endpoint))
            ->reject(fn ($item) => $item['slug'] === 'hello-world') // WordPress sample post
            ->values()->all();
        $featured = $this->featuredImages($items);

        foreach ($items as $item) {
            $slug = urldecode($item['slug']);
            Article::updateOrCreate(['type' => $type, 'wp_id' => $item['id']], [
                'title' => $this->plain($item['title']['rendered']),
                'slug' => $slug,
                'excerpt' => Str::limit($this->plain($item['excerpt']['rendered'] ?? ''), 300),
                'body' => $this->cleanHtml($item['content']['rendered']),
                'image' => $this->image($featured[$item['featured_media']] ?? null),
                'published_at' => $item['date'],
                'is_published' => ($item['status'] ?? 'publish') === 'publish',
            ]);
            $this->line('  '.$slug);
        }
    }

    private function importTestimonials(): void
    {
        $this->info('Testimonials');
        $items = $this->collection('/wp-json/wp/v2/tdcpt_testimonial');
        $featured = $this->featuredImages($items);

        foreach ($items as $i => $item) {
            $quote = preg_replace(['~<br\s*/?>~i', '~</p>~i'], ["\n", "\n\n"], $item['content']['rendered']);
            Testimonial::updateOrCreate(['wp_id' => $item['id']], [
                'name' => $this->plain($item['title']['rendered']),
                'quote' => trim(preg_replace("/\n{3,}/u", "\n\n", html_entity_decode(strip_tags($quote), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'photo' => $this->image($featured[$item['featured_media']] ?? null),
                'sort_order' => $i,
                'is_published' => true,
            ]);
            $this->line('  '.$this->plain($item['title']['rendered']));
        }
    }

    private function importPages(string $home): void
    {
        $this->info('Pages');

        // Home
        $segments = $this->segments($this->parser->parse($home));
        $sections = [];
        foreach (['hero', 'courses', 'lessons', 'intro', 'corporate', 'testimonials', 'partners', 'founder'] as $i => $key) {
            $sections[$key] = $this->sectionFrom($segments[$i] ?? []);
        }
        // The introduction row carries two images: the founder photo and the
        // illustration that sits beside the corporate-training text.
        if (empty($sections['corporate']['image']) && ! empty($sections['intro']['image2'])) {
            $sections['corporate']['image'] = $sections['intro']['image2'];
        }
        unset($sections['intro']['image2']);
        $sections['hero']['eyebrow'] = $sections['hero']['eyebrow'] ?? 'Microsoft Excel';
        $this->savePage('home', 'home', setting('site_name', 'TEAMIT Cambodia'), $sections);

        $this->importPartners($segments[6] ?? []);

        // About
        $segments = $this->segments($this->parser->parse($this->fetch('/about/')));
        $about = [];
        foreach (['intro', 'who', 'mission', 'benefits'] as $i => $key) {
            $about[$key] = $this->sectionFrom($segments[$i] ?? []);
        }
        $this->savePage('about', 'about', $about['intro']['title'] ?? 'About', $about);

        // Contact (its buttons hold the phone, email and Telegram details, see importSettings)
        $segments = $this->segments($this->parser->parse($this->fetch('/contact/')));
        $contact = [];
        foreach (['intro', 'phone', 'email', 'chat'] as $i => $key) {
            $contact[$key] = Arr::only($this->sectionFrom($segments[$i] ?? []), ['title', 'text', 'button_label', 'button_url']);
        }
        $this->savePage('contact', 'contact', $contact['intro']['title'] ?? 'Contact', $contact);

        // Listing headers
        $segments = $this->segments($this->parser->parse($this->fetch('/tutorials/')));
        $tutorials = ['intro' => Arr::only($this->sectionFrom($segments[0] ?? []), ['title', 'text'])];
        $this->savePage('tutorials', 'listing', $tutorials['intro']['title'] ?? 'Tutorials', $tutorials);

        $courses = ['intro' => ['title' => $sections['courses']['title'] ?? 'Courses', 'text' => $sections['courses']['text'] ?? null]];
        $this->savePage('courses', 'listing', $courses['intro']['title'], $courses);
        $this->savePage('blog', 'listing', 'Blog', ['intro' => ['title' => 'Blog']]);

        // Coming soon
        $comingSoon = $this->fetch('/coming-soon/');
        $title = preg_match('~<title>([^<|]+)~u', $comingSoon, $m) ? trim(html_entity_decode($m[1])) : 'Coming soon';
        $this->savePage('coming-soon', 'coming-soon', $title, ['intro' => ['title' => $title]]);
    }

    private function importPartners(array $segment): void
    {
        $this->info('Partners');
        $clients = array_values(array_filter($segment, fn ($b) => $b['type'] === 'client'));

        foreach ($clients as $i => $client) {
            // Matched on the link so a name corrected in the admin survives a re-import.
            $partner = Partner::firstOrNew(['url' => $client['url']]);
            $partner->name ??= $this->partnerName($client);
            $partner->fill([
                'logo' => $this->image($client['logo']),
                'sort_order' => $i,
                'is_published' => true,
            ])->save();
            $this->line('  '.$partner->name);
        }
    }

    private function importSettings(string $home): void
    {
        $this->info('Settings');
        $contact = Page::where('slug', 'contact')->first();
        $values = [
            'site_name' => 'TEAMIT Cambodia',
            'logo_text' => 'TEAMIT',
            'logo_text_accent' => 'Cambodia',
            'header_button_label' => null,
            'header_button_url' => '/contact',
            'copyright' => '© :year TEAMIT Cambodia',
        ];

        // Header logo text and button, e.g. "TEAMIT" + "Cambodia".
        $dom = $this->dom($home);
        $xpath = new DOMXPath($dom);
        $logo = $xpath->query('//*[contains(@class,"tdb_header_logo")]//*[contains(@class,"tdb-logo-text-title")]')->item(0);
        $accent = $xpath->query('//*[contains(@class,"tdb_header_logo")]//*[contains(@class,"tdb-logo-text-tagline")]')->item(0);
        if ($logo && trim($logo->textContent) !== '') {
            $values['logo_text'] = trim($logo->textContent);
            $values['logo_text_accent'] = $accent ? trim($accent->textContent) : null;
            $values['site_name'] = trim($values['logo_text'].' '.$values['logo_text_accent']);
            $values['copyright'] = '© :year '.$values['site_name'];
        }
        $button = $xpath->query('//*[contains(@class,"tdc-header-template")]//*[contains(@class,"tdm_block_button")]//a')->item(0);
        if ($button instanceof DOMElement) {
            $values['header_button_label'] = trim(preg_replace('/\s+/u', ' ', $button->textContent));
            $values['header_button_url'] = $this->localLink($button->getAttribute('href'));
        }
        $icon = $xpath->query('//link[@rel="apple-touch-icon"]')->item(0) ?? $xpath->query('//link[@rel="icon"]')->item(0);
        if ($icon instanceof DOMElement) {
            $values['favicon'] = $this->image($icon->getAttribute('href'));
        }
        $description = $xpath->query('//meta[@name="description"]')->item(0);
        if ($description instanceof DOMElement) {
            $values['tagline'] = $description->getAttribute('content');
        }

        // Contact details come from the buttons on the contact page.
        foreach ((array) ($contact?->sections ?? []) as $card) {
            $url = trim((string) ($card['button_url'] ?? ''));
            $label = trim((string) ($card['button_label'] ?? ''));
            if (str_starts_with($url, 'tel:')) {
                $values['phone'] = $label ?: substr($url, 4);
            } elseif (filter_var($label, FILTER_VALIDATE_EMAIL) || str_starts_with($url, 'mailto:')) {
                $values['email'] = filter_var($label, FILTER_VALIDATE_EMAIL) ? $label : substr($url, 7);
            } elseif (str_contains($url, 't.me/')) {
                $values['telegram_url'] = $url;
            }
        }

        Setting::put(array_filter($values, fn ($v) => $v !== null));
    }

    // ------------------------------------------------------- page structure

    /** Split parsed blocks into sections, each starting at a title block. */
    private function segments(array $blocks): array
    {
        $segments = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'title' || ! $segments) {
                $segments[] = [];
            }
            $segments[count($segments) - 1][] = $block;
        }

        return $segments;
    }

    /** Turn one segment's blocks into the field names used by config/page_templates.php. */
    private function sectionFrom(array $segment): array
    {
        $section = [];
        $buttons = 0;
        $images = 0;

        foreach ($segment as $block) {
            switch ($block['type']) {
                case 'title':
                    $section['title'] ??= $block['text'];
                    break;
                case 'text':
                    $section['text'] = trim(($section['text'] ?? '').$this->cleanHtml($block['html']));
                    break;
                case 'button':
                    $prefix = $buttons++ ? 'button2' : 'button';
                    $section[$prefix.'_label'] = $block['label'];
                    $section[$prefix.'_url'] = $this->localLink($block['url']);
                    break;
                case 'image':
                    $section[$images++ ? 'image2' : 'image'] = $this->image($block['url']);
                    break;
                case 'list':
                    $section = array_merge($section, $this->splitChecklist($block['items'], isset($section['text'])));
                    break;
            }
        }

        // Plain-text fields hold the inline text without markup.
        foreach (['text'] as $key) {
            if (isset($section[$key]) && ! str_contains($section[$key], '<')) {
                $section[$key] = html_entity_decode($section[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $section;
    }

    /**
     * The old lists mix paragraphs and "✅" items. Items before the first ✅
     * become the intro text, ✅ items the checklist, and the rest "after".
     */
    private function splitChecklist(array $items, bool $hasText): array
    {
        $before = $points = $after = [];
        foreach ($items as $item) {
            if (preg_match('/^\s*(✅|✔️?|☑️?)\s*(.+)$/u', $item, $m)) {
                $points[] = trim($m[2]);
            } elseif ($points) {
                $after[] = $item;
            } else {
                $before[] = $item;
            }
        }

        $result = ['points' => $points ?: $before];
        if ($points && $before && ! $hasText) {
            $result['text'] = '<p>'.implode('</p><p>', array_map('e', $before)).'</p>';
        }
        if ($after) {
            $result['after'] = implode("\n\n", $after);
        }

        return $result;
    }

    private function savePage(string $slug, string $template, string $title, array $sections): void
    {
        Page::updateOrCreate(['slug' => $slug], [
            'template' => $template,
            'title' => $title,
            'sections' => $sections,
            'is_published' => true,
        ]);
        $this->line('  '.$slug);
    }

    /** Badge number and icon shown on each course card on the old home page, keyed by slug. */
    private function courseCards(string $home): array
    {
        $xpath = new DOMXPath($this->dom($home));
        $cards = [];

        foreach ($xpath->query('//*[contains(@class,"tdb_module_title")]//a[contains(@href,"/tdcpt_service/")]') as $order => $link) {
            $slug = urldecode(basename(rtrim(parse_url($link->getAttribute('href'), PHP_URL_PATH), '/')));
            $card = $link;
            while ($card && ! ($card instanceof DOMElement && str_contains(' '.$card->getAttribute('class').' ', ' td-module-container '))) {
                $card = $card->parentNode;
            }
            if (isset($cards[$slug]) || ! $card) {
                continue;
            }
            $badge = $xpath->query('.//*[contains(@class,"tdb-sacff-txt")]', $card)->item(0);
            $icon = $xpath->query('.//img[contains(@class,"tdb-sacff-img")]', $card)->item(0);
            $cards[$slug] = [
                'order' => $order,
                'badge' => $badge ? trim($badge->textContent) : null,
                'icon' => $icon instanceof DOMElement ? ($icon->getAttribute('data-img-url') ?: $icon->getAttribute('src')) : null,
            ];
        }

        return $cards;
    }

    /** Price box, checklist and button from a course's own page. */
    private function courseDetail(string $html): array
    {
        $xpath = new DOMXPath($this->dom($html));
        $root = $xpath->query('//*[contains(@class,"td-main-content-wrap")]')->item(0);
        $text = fn ($node) => $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent)) : null;
        $detail = [];

        // Custom fields: price (with its "$" prefix in .tdb-cf-add-txt), then the period.
        $fields = [];
        foreach ($xpath->query('.//*[contains(@class,"tdb_single_custom_field")]', $root) as $field) {
            $prefix = $xpath->query('.//*[contains(@class,"tdb-cf-add-txt")]', $field)->item(0);
            $value = $xpath->query('.//*[contains(@class,"tdb-sacff-txt")]', $field)->item(0);
            if ($value) {
                $fields[] = $text($prefix).$text($value);
            }
        }
        $detail['price'] = $fields[0] ?? null;
        $detail['price_suffix'] = $fields[1] ?? null;
        $prefix = $xpath->query('.//*[contains(@class,"tdm_block_inline_text")]//*[contains(@class,"tdm-descr")]', $root)->item(0);
        $detail['price_prefix'] = $detail['price'] ? $text($prefix) : null;

        $list = $xpath->query('.//*[contains(@class,"tdm_block_list")]', $root)->item(0);
        $detail['features'] = [];
        if ($list) {
            foreach ($xpath->query('.//li', $list) as $li) {
                if ($item = $text($li)) {
                    $detail['features'][] = $item;
                }
            }
        }

        $button = $xpath->query('.//*[contains(@class,"tdm_block_button")]//a', $root)->item(0);
        if ($button instanceof DOMElement) {
            $detail['cta_label'] = $text($button);
            $detail['cta_url'] = $button->getAttribute('href');
        }

        return $detail;
    }

    private function priceAmount(?string $price): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)/', (string) $price, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    /** "Per year" -> 365, "Per month" -> 30 (Khmer words too); null = lifetime. */
    private function accessDays(?string $suffix): ?int
    {
        $suffix = mb_strtolower((string) $suffix);

        return match (true) {
            str_contains($suffix, 'year') || str_contains($suffix, 'ឆ្នាំ') => 365,
            str_contains($suffix, 'month') || str_contains($suffix, 'ខែ') => 30,
            str_contains($suffix, 'week') || str_contains($suffix, 'សប្តាហ៍') => 7,
            default => null,
        };
    }

    /** A starting name for a partner logo; the image titles are unreliable, so use the link. */
    private function partnerName(array $client): string
    {
        $url = (string) $client['url'];
        $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        $name = str_contains($host, 'facebook.com') && $path !== ''
            ? Str::before($path, '/')
            : Str::before($host, '.');

        return $name !== '' ? Str::headline($name) : 'Partner';
    }

    // ------------------------------------------------------------ HTML/media

    /** Clean WordPress HTML: local images, relative internal links, no empty paragraphs. */
    private function cleanHtml(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = $this->dom('<div id="wp-import-root">'.$html.'</div>');
        $xpath = new DOMXPath($dom);
        $root = $dom->getElementById('wp-import-root');

        foreach ($xpath->query('.//img', $root) as $img) {
            $src = $img->getAttribute('data-img-url') ?: $img->getAttribute('src');
            foreach (['srcset', 'sizes', 'data-img-url', 'data-type'] as $attr) {
                $img->removeAttribute($attr);
            }
            if ($path = $this->image($src)) {
                $img->setAttribute('src', $this->publicPath($path));
            }
            $img->setAttribute('loading', 'lazy');
        }

        foreach ($xpath->query('.//a[@href]', $root) as $a) {
            $href = $a->getAttribute('href');
            if (str_contains($href, '/wp-content/uploads/')) {
                // Image blocks link to the full-size file; point them at the local copy we already have.
                $img = $xpath->query('.//img', $a)->item(0);
                $a->setAttribute('href', $img instanceof DOMElement ? $img->getAttribute('src') : $this->publicPath($this->image($href)));
            } else {
                $a->setAttribute('href', $this->localLink($href));
            }
        }

        foreach ($xpath->query('.//p[not(normalize-space()) and not(.//img) and not(.//iframe)]', $root) as $p) {
            $p->parentNode->removeChild($p);
        }
        foreach ($xpath->query('.//*[@class="wp-block-paragraph"]', $root) as $el) {
            $el->removeAttribute('class');
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /** Point links at this site: "https://teamitcambodia.com/about/" -> "/about". */
    private function localLink(?string $url): ?string
    {
        if (blank($url)) {
            return $url;
        }
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^www\./', '', $parts['host'] ?? ''));

        // Some old buttons still point at the local demo copy of the site
        // (http://localhost/demo-teamitcambodia.com/contact/); keep the last part.
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $parts['path'] = '/'.basename(rtrim($parts['path'] ?? '/', '/'));
        } elseif ($host === '' || $host !== strtolower(preg_replace('/^www\./', '', $this->host))) {
            return $url;
        }

        $path = '/'.trim(rawurldecode($parts['path'] ?? '/'), '/');
        $path = match (true) {
            $path === '/coming-soon' => '/courses',
            str_starts_with($path, '/tdcpt_service/') => '/courses/'.substr($path, 15),
            str_starts_with($path, '/tdcpt_location/') => '/tutorials/'.substr($path, 16),
            default => $path,
        };

        return $path.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /** Queue an image for download and return its path on the public disk. */
    private function image(?string $url): ?string
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, 'data:')) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (str_starts_with($url, '/')) {
            $url = $this->base.$url;
        }
        if ($this->option('no-media')) {
            return $url;
        }

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $relative = str_contains($path, '/wp-content/uploads/') ? Str::after($path, '/wp-content/uploads/') : 'misc/'.basename($path);

        return $this->images[$url] = 'wp/'.preg_replace('~[^A-Za-z0-9/._-]+~', '-', $relative);
    }

    private function publicPath(?string $path): ?string
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return '/storage/'.$path;
    }

    /**
     * Download queued images that are not on disk yet. The old server is slow
     * and drops connections mid-file, so downloads run in parallel and an
     * interrupted file is resumed (HTTP Range) in the next round.
     */
    private function downloadImages(): void
    {
        $disk = Storage::disk('public');
        // Different URLs (http/https, www, encodings) can map to the same file: fetch each file once.
        $pending = array_unique(array_filter($this->images, fn ($path) => ! $disk->exists($path)));
        $this->info('Images: '.count(array_unique($this->images)).' used, '.count($pending).' to download');

        $client = new Client([
            'headers' => ['User-Agent' => $this->userAgent()],
            'connect_timeout' => 30,
            'timeout' => 900,
            'http_errors' => false,
        ]);

        for ($round = 1; $pending && $round <= 8; $round++) {
            if ($round > 1) {
                $this->line("  Round $round: ".count($pending).' image(s) left');
            }
            $status = [];
            $sizes = [];
            $finished = [];

            $requests = function () use ($client, $disk, $pending, &$status, &$sizes) {
                foreach ($pending as $url => $path) {
                    $part = $disk->path($path).'.part';
                    File::ensureDirectoryExists(dirname($part));
                    $offset = is_file($part) ? filesize($part) : 0;

                    // Built here, not inside the arrow function below: arrow functions
                    // capture by value, which would detach $status from this round.
                    $options = [
                        'headers' => $offset ? ['Range' => 'bytes='.$offset.'-'] : [],
                        'sink' => $part.'.chunk',
                        'on_headers' => function (ResponseInterface $response) use (&$status, &$sizes, $url) {
                            $status[$url] = $response->getStatusCode();
                            // Full size: "Content-Range: bytes 100-999/1000" on a resume, else Content-Length.
                            $sizes[$url] = preg_match('~/(\d+)$~', $response->getHeaderLine('Content-Range'), $m)
                                ? (int) $m[1]
                                : ((int) $response->getHeaderLine('Content-Length') ?: null);
                        },
                    ];

                    yield $url => fn () => $client->getAsync($url, $options);
                }
            };

            (new GuzzlePool($client, $requests(), [
                'concurrency' => 12,
                'fulfilled' => function (ResponseInterface $response, string $url) use (&$finished) {
                    $finished[$url] = $response->getStatusCode();
                },
            ]))->promise()->wait();

            foreach ($pending as $url => $path) {
                $target = $disk->path($path);
                $part = $target.'.part';
                $chunk = $part.'.chunk';
                $code = $status[$url] ?? null;

                // Keep what arrived: append a ranged reply, replace on a full one.
                if (is_file($chunk)) {
                    if ($code === 206) {
                        file_put_contents($part, file_get_contents($chunk), FILE_APPEND);
                    } elseif ($code === 200) {
                        copy($chunk, $part);
                    }
                    @unlink($chunk);
                }

                $done = $finished[$url] ?? null;
                clearstatcache(true, $part);
                $complete = is_file($part) && filesize($part) > 0
                    && (empty($sizes[$url]) || $done === 416 || filesize($part) >= $sizes[$url]);
                if ((in_array($done, [200, 206], true) || $done === 416) && $complete) {
                    rename($part, $target);
                    unset($pending[$url]);
                    $this->line('  ✓ '.$path);
                } elseif ($done !== null && ! in_array($done, [200, 206, 416], true)) {
                    @unlink($part);
                    unset($pending[$url]);
                    $this->warn("  ✗ $url (HTTP $done)");
                }
            }
        }

        if ($pending) {
            $this->warn(count($pending).' image(s) did not finish. Run the command again to resume them.');
        }
    }

    // ------------------------------------------------------------------ HTTP

    /** All items of a REST collection (follows pages). */
    private function collection(string $endpoint): array
    {
        $items = [];
        for ($page = 1; $page < 50; $page++) {
            $batch = $this->json($endpoint.'?per_page=100&page='.$page.'&_fields='.self::FIELDS);
            $items = array_merge($items, $batch);
            if (count($batch) < 100) {
                break;
            }
        }

        return $items;
    }

    /** featured_media ID => image URL (a large size when WordPress has one). */
    private function featuredImages(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_column($items, 'featured_media'))));
        $map = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $media = $this->json('/wp-json/wp/v2/media?per_page=100&_fields=id,source_url,media_details&include='.implode(',', $chunk));
            foreach ($media as $m) {
                $sizes = $m['media_details']['sizes'] ?? [];
                $map[$m['id']] = $sizes['td_1068x0']['source_url'] ?? $sizes['large']['source_url'] ?? $m['source_url'];
            }
        }

        return $map;
    }

    private function json(string $path): array
    {
        $data = json_decode($this->fetch($path), true);
        if (! is_array($data) || isset($data['code'])) {
            throw new RuntimeException("Unexpected response from $path");
        }

        return $data;
    }

    private function fetch(string $path): string
    {
        $url = Str::startsWith($path, 'http') ? $path : $this->base.$path;
        $cache = storage_path('app/wp-import/'.sha1($url));

        if (! $this->option('refresh') && is_file($cache)) {
            return file_get_contents($cache);
        }

        $this->line('  GET '.$url);
        $response = Http::withUserAgent($this->userAgent())
            ->timeout(900)->connectTimeout(30)
            ->retry(4, 5000, throw: false)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} for $url");
        }

        File::ensureDirectoryExists(dirname($cache));
        file_put_contents($cache, $response->body());

        return $response->body();
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TeamIT-Laravel-Importer';
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return $dom;
    }

    private function plain(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
