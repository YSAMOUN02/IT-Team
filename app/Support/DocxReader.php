<?php

namespace App\Support;

use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads a Word (.docx) manual into simple blocks (headings, paragraphs, list
 * items, pictures, tables) and turns runs of blocks into lesson HTML.
 * Used to build a course from a training document.
 */
class DocxReader
{
    private const NS = [
        'w' => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
        'r' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'v' => 'urn:schemas-microsoft-com:vml',
        'mc' => 'http://schemas.openxmlformats.org/markup-compatibility/2006',
    ];

    private ZipArchive $zip;

    private DOMXPath $xp;

    /** @var array<string, string> relationship id => media path inside the zip */
    private array $media = [];

    /** @var array<string, string> style id => lower-case style name */
    private array $styles = [];

    /** @var array<string, array<int, array{0: string, 1: int}>> numId => [level => [numFmt, left indent]] */
    private array $listFormats = [];

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Cannot open Word file: $path");
        }

        foreach ($this->xml('word/_rels/document.xml.rels')?->getElementsByTagName('Relationship') ?? [] as $rel) {
            if (str_contains($rel->getAttribute('Type'), '/image')) {
                $this->media[$rel->getAttribute('Id')] = 'word/'.ltrim($rel->getAttribute('Target'), '/');
            }
        }

        if ($styles = $this->xml('word/styles.xml')) {
            $xp = $this->xpath($styles);
            foreach ($xp->query('//w:style') as $style) {
                $name = $xp->query('w:name', $style)->item(0)?->getAttribute('w:val');
                $this->styles[$style->getAttribute('w:styleId')] = mb_strtolower($name ?? $style->getAttribute('w:styleId'));
            }
        }

        if ($numbering = $this->xml('word/numbering.xml')) {
            $xp = $this->xpath($numbering);
            $abstract = [];
            foreach ($xp->query('//w:abstractNum') as $node) {
                foreach ($xp->query('w:lvl', $node) as $level) {
                    $ind = $xp->query('w:pPr/w:ind', $level)->item(0);
                    $abstract[$node->getAttribute('w:abstractNumId')][(int) $level->getAttribute('w:ilvl')] = [
                        $xp->query('w:numFmt', $level)->item(0)?->getAttribute('w:val') ?? 'bullet',
                        (int) ($ind?->getAttribute('w:left') ?: $ind?->getAttribute('w:start')),
                    ];
                }
            }
            foreach ($xp->query('//w:num') as $num) {
                $this->listFormats[$num->getAttribute('w:numId')] = $abstract[$xp->query('w:abstractNumId', $num)->item(0)?->getAttribute('w:val')] ?? [];
            }
        }

        $document = $this->xml('word/document.xml') ?? throw new RuntimeException('Not a Word document.');
        $this->xp = $this->xpath($document);
    }

    /**
     * The document body in order.
     *
     * @return list<array{type: string, level: int, ordered: bool, html: string, text: string, bold: bool, images: list<string>, rows?: list<list<string>>}>
     */
    public function blocks(): array
    {
        $blocks = [];
        foreach ($this->xp->query('/w:document/w:body/*') as $node) {
            foreach ($node->localName === 'sdt' ? $this->xp->query('w:sdtContent/*', $node) : [$node] as $el) {
                if ($el->localName === 'tbl') {
                    $rows = [];
                    foreach ($this->xp->query('w:tr', $el) as $tr) {
                        $rows[] = array_map(
                            fn ($tc) => implode('<br>', array_filter(array_map(fn ($p) => $this->paragraph($p)['html'], iterator_to_array($this->xp->query('w:p', $tc))))),
                            iterator_to_array($this->xp->query('w:tc', $tr))
                        );
                    }
                    $blocks[] = ['type' => 'table', 'level' => 0, 'indent' => 0, 'list' => '', 'ordered' => false, 'html' => '', 'text' => '', 'bold' => false, 'images' => [], 'rows' => $rows];
                } elseif ($el->localName === 'p') {
                    $block = $this->paragraph($el);
                    if ($block['text'] !== '' || $block['images']) {
                        $blocks[] = $block;
                    }
                }
            }
        }

        return $blocks;
    }

    /** Bytes of a picture referenced by a block. */
    public function image(string $path): string
    {
        return (string) $this->zip->getFromName($path);
    }

    /**
     * Save a picture to public storage (scaled to at most 1400 px wide) and
     * return its URL, or null when browsers cannot show it (EMF, WMF…).
     */
    public function storeImage(string $path, string $folder, string $name = 'image'): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $bytes = $this->image($path);
        if (! in_array($extension, ['jpg', 'png', 'gif', 'webp'], true) || $bytes === '' || ! @imagecreatefromstring($bytes)) {
            return null;
        }

        return Media::url(Media::put(Media::shrink($bytes, $extension, 1400), $folder, $name, $extension));
    }

    /** Small clean-ups that make a converted manual read better. */
    public static function polish(string $html): string
    {
        // "Option name: what it does" items get the option name in bold.
        $html = preg_replace('/<li>(?!<strong>)([A-Za-z%][^<:៖]{0,70}?)\s*([:៖])/u', '<li><strong>$1</strong>$2', $html);

        // Steps under "របៀបធ្វើ៖" / "Steps:" are numbered.
        return preg_replace('/((?:របៀបធ្វើ៖|Steps:)(?:<\/strong>)?<\/p>)<ul>(.*?)<\/ul>/su', '$1<ol class="steps">$2</ol>', $html);
    }

    /**
     * Lesson HTML for a run of blocks. $imageUrl turns a picture path in the
     * file into the URL it was saved at. $subheading can mark extra list items
     * as sub-headings (on top of short, fully bold ones).
     *
     * @param  list<array>  $blocks
     */
    public static function html(array $blocks, callable $imageUrl, ?callable $subheading = null): string
    {
        $out = '';
        $stack = []; // open lists, outermost first: [['tag' => 'ul'|'ol', 'li' => bool, 'indent' => int], ...]
        $counters = []; // numbered lists keep counting across interruptions

        $closeTop = function () use (&$out, &$stack) {
            $list = array_pop($stack);
            $out .= ($list['li'] ? '</li>' : '').'</'.$list['tag'].'>';
        };
        $closeAll = function () use (&$stack, $closeTop) {
            while ($stack) {
                $closeTop();
            }
        };
        $figure = function (array $images) use ($imageUrl) {
            $imgs = implode('', array_map(fn ($img) => '<img src="'.e($imageUrl($img)).'" alt="" loading="lazy">', $images));

            return '<figure class="lesson-shot'.(count($images) > 1 ? ' is-pair' : '').'">'.$imgs.'</figure>';
        };

        foreach ($blocks as $block) {
            $images = $block['images'] ? $figure($block['images']) : '';

            if ($block['type'] === 'heading') {
                $closeAll();
                $out .= '<h2>'.$block['html'].'</h2>'.$images;
            } elseif ($block['type'] === 'table') {
                $closeAll();
                $rows = $block['rows'];
                $head = array_shift($rows) ?? [];
                $out .= '<div class="table-scroll"><table><thead><tr>'.implode('', array_map(fn ($c) => "<th>$c</th>", $head)).'</tr></thead><tbody>'
                    .implode('', array_map(fn ($r) => '<tr>'.implode('', array_map(fn ($c) => "<td>$c</td>", $r)).'</tr>', $rows))
                    .'</tbody></table></div>';
            } elseif ($block['type'] === 'item' && (self::isSubheading($block) || ($subheading && $subheading($block)))) {
                $closeAll();
                $out .= '<h3>'.strip_tags($block['html']).'</h3>'.$images;
            } elseif ($block['type'] === 'item') {
                // Word marks nesting by indentation: deeper indent = list inside the previous item.
                $tag = $block['ordered'] ? 'ol' : 'ul';
                while ($stack && end($stack)['indent'] > $block['indent']) {
                    $closeTop();
                }
                if ($stack && end($stack)['indent'] === $block['indent'] && end($stack)['tag'] !== $tag) {
                    $closeTop();
                }
                $number = $block['ordered'] ? ($counters[$block['list']] = ($counters[$block['list']] ?? 0) + 1) : 0;
                if (! $stack || end($stack)['indent'] < $block['indent']) {
                    $out .= $number > 1 ? "<ol start=\"$number\">" : "<$tag>";
                    $stack[] = ['tag' => $tag, 'li' => false, 'indent' => $block['indent']];
                }
                $top = array_key_last($stack);
                $out .= ($stack[$top]['li'] ? '</li>' : '').'<li>'.$block['html'].$images;
                $stack[$top]['li'] = true;
            } elseif ($block['text'] === '' && $stack && end($stack)['li']) {
                $out .= $images; // a screenshot under a list item belongs to it
            } else {
                $closeAll();
                $out .= ($block['text'] !== '' ? '<p>'.$block['html'].'</p>' : '').$images;
            }
        }
        $closeAll();

        return $out;
    }

    /** A short, fully bold list item without a full stop reads as a sub-heading ("Grand Totals:"). */
    private static function isSubheading(array $block): bool
    {
        return $block['bold'] && $block['text'] !== ''
            && mb_strlen($block['text']) <= 110 && ! preg_match('/[។.]\s*$/u', $block['text']);
    }

    private function paragraph(DOMElement $p): array
    {
        $styleId = $this->xp->query('w:pPr/w:pStyle', $p)->item(0)?->getAttribute('w:val');
        $style = $this->styles[$styleId] ?? mb_strtolower((string) $styleId);
        $numId = $this->xp->query('w:pPr/w:numPr/w:numId', $p)->item(0)?->getAttribute('w:val');
        $level = (int) ($this->xp->query('w:pPr/w:numPr/w:ilvl', $p)->item(0)?->getAttribute('w:val') ?? 0);

        $html = '';
        $text = '';
        $allBold = true;
        $openBold = false;
        $runs = $this->xp->query('.//w:r[not(ancestor::mc:Fallback) and not(ancestor::w:drawing) and not(ancestor::w:pict) and not(ancestor::w:txbxContent)]', $p);
        foreach ($runs as $run) {
            $piece = '';
            foreach ($run->childNodes as $child) {
                $piece .= match ($child->localName) {
                    't' => $child->textContent,
                    'tab' => ' ',
                    'br', 'cr' => "\n",
                    default => '',
                };
            }
            if ($piece === '') {
                continue;
            }
            $b = $this->xp->query('w:rPr/w:b', $run)->item(0);
            $bold = $b && ! in_array($b->getAttribute('w:val'), ['0', 'false'], true);
            if (trim($piece) !== '' && ! $bold) {
                $allBold = false;
            }
            if ($bold !== $openBold && trim($piece) !== '') {
                $html .= $bold ? '<strong>' : '</strong>';
                $openBold = $bold;
            }
            $html .= nl2br(e($piece), false);
            $text .= $piece;
        }
        if ($openBold) {
            $html .= '</strong>';
        }

        $images = [];
        foreach ($this->xp->query('.//a:blip[not(ancestor::mc:Fallback)]/@r:embed | .//v:imagedata[not(ancestor::mc:Fallback)]/@r:id', $p) as $attr) {
            if (isset($this->media[$attr->value]) && ! in_array($this->media[$attr->value], $images, true)) {
                $images[] = $this->media[$attr->value];
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $html = trim(str_replace('<strong></strong>', '', $html));
        $heading = str_starts_with($style, 'heading') || $style === 'title';
        [$format, $listIndent] = $this->listFormats[$numId ?? ''][$level] ?? ['bullet', 0];
        $ind = $this->xp->query('w:pPr/w:ind', $p)->item(0);
        $ownIndent = $ind ? ($ind->getAttribute('w:left') ?: $ind->getAttribute('w:start')) : '';

        return [
            'type' => $heading ? 'heading' : ($numId && $numId !== '0' ? 'item' : 'p'),
            'level' => $level,
            'indent' => $ownIndent !== '' ? (int) $ownIndent : ($listIndent ?: 720 + $level * 360),
            'list' => (string) $numId,
            'ordered' => ! in_array($format, ['bullet', 'none'], true),
            'html' => $html,
            'text' => $text,
            'bold' => $text !== '' && $allBold,
            'images' => $images,
        ];
    }

    private function xml(string $name): ?\DOMDocument
    {
        $content = $this->zip->getFromName($name);
        if ($content === false) {
            return null;
        }
        $doc = new \DOMDocument;
        $doc->loadXML($content, LIBXML_PARSEHUGE | LIBXML_NONET);

        return $doc;
    }

    private function xpath(\DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }

        return $xp;
    }
}
