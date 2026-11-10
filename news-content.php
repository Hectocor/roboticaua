<?php
declare(strict_types=1);

function clean_news_html(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('El servidor necesita la extensión PHP DOM para publicar noticias.');
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $source = new DOMDocument('1.0', 'UTF-8');
        $source->loadHTML('<?xml encoding="UTF-8"><div id="news-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        $root = $source->getElementById('news-root');
        if (!$root) {
            return '';
        }

        $output = new DOMDocument('1.0', 'UTF-8');
        $container = $output->createElement('div');
        $output->appendChild($container);
        foreach ($root->childNodes as $child) {
            append_clean_news_node($output, $container, $child);
        }

        $result = '';
        foreach ($container->childNodes as $child) {
            $result .= $output->saveHTML($child);
        }
        return $result;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function append_clean_news_node(DOMDocument $output, DOMNode $parent, DOMNode $source): void
{
    if ($source instanceof DOMText) {
        $parent->appendChild($output->createTextNode($source->textContent));
        return;
    }
    if (!($source instanceof DOMElement)) {
        return;
    }

    $tag = strtolower($source->tagName);
    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'form', 'math'], true)) {
        return;
    }
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'span', 'a', 'img'];
    if (!in_array($tag, $allowed, true)) {
        foreach ($source->childNodes as $child) {
            append_clean_news_node($output, $parent, $child);
        }
        return;
    }

    if ($tag === 'img') {
        $src = trim($source->getAttribute('src'));
        if (!preg_match('~^/?uploads/news/[a-f0-9]{32}\.(?:jpe?g|png|webp|gif)$~i', $src)) {
            return;
        }
        $image = $output->createElement('img');
        $image->setAttribute('src', '/' . ltrim($src, '/'));
        $image->setAttribute('alt', mb_substr($source->getAttribute('alt'), 0, 220, 'UTF-8'));
        $image->setAttribute('loading', 'lazy');
        $parent->appendChild($image);
        return;
    }

    $element = $output->createElement($tag);
    if ($tag === 'a') {
        $href = trim($source->getAttribute('href'));
        if (filter_var($href, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }
    if ($tag === 'li' && in_array($source->getAttribute('data-list'), ['bullet', 'ordered'], true)) {
        $element->setAttribute('data-list', $source->getAttribute('data-list'));
    }
    if ($tag === 'span') {
        $style = $source->getAttribute('style');
        if (preg_match('/(?:^|;)\s*background-color\s*:\s*(#[a-f0-9]{3,6}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*(?:;|$)/i', $style, $match)) {
            $element->setAttribute('style', 'background-color: ' . $match[1]);
        }
    }
    foreach ($source->childNodes as $child) {
        append_clean_news_node($output, $element, $child);
    }
    $parent->appendChild($element);
}
