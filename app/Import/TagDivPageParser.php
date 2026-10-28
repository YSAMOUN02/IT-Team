<?php

namespace App\Import;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reads a page rendered by the tagDiv (Newspaper) page builder and returns its
 * content blocks in document order, e.g.
 *   ['type' => 'title', 'text' => '...']
 *   ['type' => 'text', 'html' => '...']
 *   ['type' => 'image', 'url' => '...', 'link' => null]
 *   ['type' => 'list', 'items' => ['...']]
 *   ['type' => 'button', 'label' => '...', 'url' => '...']
 *   ['type' => 'loop', 'links' => [...]]       (post/course/testimonial lists)
 * The site header and footer templates are skipped.
 */
class TagDivPageParser
{
    private DOMXPath $xpath;

    public function parse(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);

        $root = $this->first('//*[contains(concat(" ", normalize-space(@class), " "), " td-main-content-wrap ")]')
            ?? $this->first('//body');

        $blocks = [];
        if ($root) {
            $this->walk($root, $blocks);
        }

        return $blocks;
    }

    private function walk(DOMNode $node, array &$blocks): void
    {
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $class = ' '.$child->getAttribute('class').' ';

            if (str_contains($class, ' tdc-header-template ') || str_contains($class, ' td-footer-template-wrap ')) {
                continue;
            }
            $block = $this->block($child, $class);
            if ($block === false) {
                $this->walk($child, $blocks);
            } elseif ($block !== null) {
                $blocks[] = $block;
            }
        }
    }

    /** @return array|null|false  array = block, null = skip element, false = not a block (descend) */
    private function block(DOMElement $el, string $class): array|null|false
    {
        if (str_contains($class, ' tdm_block_column_title ')) {
            $title = $this->first('.//*[contains(@class, "tdm-title")]', $el) ?? $el;

            return ['type' => 'title', 'text' => $this->text($title), 'level' => (int) substr($title->nodeName, 1) ?: 2];
        }
        if (str_contains($class, ' tdm_block_inline_text ')) {
            $descr = $this->first('.//*[contains(@class, "tdm-descr")]', $el) ?? $el;

            return ['type' => 'text', 'html' => $this->innerHtml($descr)];
        }
        if (str_contains($class, ' tdm_block_inline_image ') || str_contains($class, ' td_block_single_image ')) {
            $img = $this->first('.//img', $el);
            $url = $img ? ($img->getAttribute('data-img-url') ?: $img->getAttribute('src')) : $this->backgroundUrl($el);
            $link = $this->first('.//a[@href]', $el)?->getAttribute('href');

            return $url ? ['type' => 'image', 'url' => $url, 'link' => $link] : null;
        }
        if (str_contains($class, ' tdm_block_list ')) {
            $items = [];
            foreach ($this->xpath->query('.//li', $el) as $li) {
                $text = $this->text($li);
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            return ['type' => 'list', 'items' => $items];
        }
        if (str_contains($class, ' tdm_block_button ')) {
            $a = $this->first('.//a', $el);

            return $a ? ['type' => 'button', 'label' => $this->text($a), 'url' => $a->getAttribute('href')] : null;
        }
        if (str_contains($class, ' tdm_block_client ')) {
            $img = $this->first('.//img', $el);
            $url = $img ? ($img->getAttribute('data-img-url') ?: $img->getAttribute('src')) : null;

            return $url ? [
                'type' => 'client',
                'name' => $img->getAttribute('title') ?: $img->getAttribute('alt'),
                'logo' => $url,
                'url' => $this->first('.//a[@href]', $el)?->getAttribute('href'),
            ] : null;
        }
        if (str_contains($class, ' tdm_block_popup ') || str_contains($class, ' td_block_raw_css ')) {
            return null;
        }
        if (preg_match('/ (td_flex_block_\d+|tdb_loop|td_block_\d+|tdb_[a-z_]*loop[a-z_]*|tdcpt_[a-z_]+) /', $class)) {
            $links = [];
            foreach ($this->xpath->query('.//a[@href]', $el) as $a) {
                $href = $a->getAttribute('href');
                if (str_contains($href, '/tdcpt_') || preg_match('~/\d{4}/\d{2}/\d{2}/~', $href)) {
                    $links[$href] = true;
                }
            }

            return ['type' => 'loop', 'class' => trim($class), 'links' => array_keys($links)];
        }

        return false;
    }

    private function backgroundUrl(DOMElement $el): ?string
    {
        foreach ($this->xpath->query('.//*[@data-img-url]|.//*[@style]', $el) as $node) {
            if ($url = $node->getAttribute('data-img-url')) {
                return $url;
            }
            if (preg_match('/background-image:\s*url\(([\'"]?)([^\'")]+)\1\)/', $node->getAttribute('style'), $m)) {
                return $m[2];
            }
        }

        return null;
    }

    private function first(string $query, ?DOMNode $context = null): ?DOMElement
    {
        $node = $this->xpath->query($query, $context)->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return trim($html);
    }
}
