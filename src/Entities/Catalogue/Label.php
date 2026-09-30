<?php

namespace Meva\Entities\Catalogue;

/**
 * Reads a product's label out of the one block the old shop kept it in.
 *
 * The old shop wrote the whole label into the description: what the product
 * is, then "Sastav" and the ingredients, then "Način upotrebe" (or
 * "Uputstvo za upotrebu") and the directions -- as headings, as bold
 * lead-ins, or as plain lines in the short description. This finds those
 * two marks and hands back the three parts, so each can live in a field of
 * its own.
 */
class Label
{
    /**
     * Where a heading or a bold lead-in names one of the two parts.
     */
    private const HTML_MARK = '~<(h[1-6]|p|strong|b)[^>]*>\s*(?:<(?:strong|b)>\s*)?(sastav|sastojci|na[čc]in upotrebe|uputstvo za upotrebu|upotreba)\b[^<]{0,40}?(?:</(?:strong|b)>\s*)?:?\s*</\1>\s*:?~iu';

    /**
     * The same marks written as plain text, at the start of a line.
     */
    private const TEXT_MARK = '~(?:^|\n)\s*(sastav|sastojci|na[čc]in upotrebe|uputstvo za upotrebu)\s*:\s*~iu';

    /**
     * Split HTML into what stays a description and the two parts cut out of it.
     *
     * @return array{description: string, ingredients: string, usage: string}
     */
    public static function splitHtml(string $html): array
    {
        return self::split($html, self::HTML_MARK, 2, fn (string $chunk): bool => trim(strip_tags(str_replace('&nbsp;', ' ', $chunk))) !== '');
    }

    /**
     * Split plain text the same way.
     *
     * @return array{description: string, ingredients: string, usage: string}
     */
    public static function splitText(string $text): array
    {
        return self::split(str_replace("\r", '', $text), self::TEXT_MARK, 1, fn (string $chunk): bool => trim($chunk) !== '');
    }

    /**
     * @return array{description: string, ingredients: string, usage: string}
     */
    private static function split(string $source, string $pattern, int $group, callable $hasContent): array
    {
        preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $out = ['description' => $source, 'ingredients' => '', 'usage' => ''];

        if ($matches === []) {
            return $out;
        }

        $out['description'] = trim(substr($source, 0, $matches[0][0][1]));

        foreach ($matches as $i => $match) {
            $start = $match[0][1] + strlen($match[0][0]);
            $end = $matches[$i + 1][0][1] ?? strlen($source);
            $chunk = trim(substr($source, $start, $end - $start));
            $kind = preg_match('~sastav|sastojci~iu', $match[$group][0]) ? 'ingredients' : 'usage';

            if ($hasContent($chunk)) {
                $out[$kind] .= ($out[$kind] === '' ? '' : "\n").$chunk;
            }
        }

        return $out;
    }

    /**
     * The facts that trail the directions on some labels -- scent, packaging,
     * size, storage -- written as shouted labels: "AMBALAŽA: Bela, plastična".
     */
    private const FACT_MARK = '~(?:<(?:strong|b|p|h[1-6]|span)[^>]*>\s*)*(miris|ambala[žz]a|pakovanje|na[čc]in [čc]uvanja|[čc]uvanje|rok trajanja)\s*:\s*(?:</(?:strong|b|span)>\s*)?~iu';

    /**
     * Take the trailing facts, and any ingredient list, out of the directions.
     *
     * The facts come back as tidy paragraphs for the end of the description
     * ("Miris: …", "Pakovanje: 50 ml"); the ingredients as HTML for the rows.
     *
     * @return array{usage: string, facts: string, ingredients: string}
     */
    public static function tidyUsage(string $usage): array
    {
        $parts = self::splitHtml('<p>x</p>'.$usage);
        $ingredients = $parts['ingredients'];
        $usage = $parts['description'] === '<p>x</p>' ? $usage : trim(substr($parts['description'], strlen('<p>x</p>')));
        $usage = $parts['usage'] !== '' ? $usage.$parts['usage'] : $usage;

        preg_match_all(self::FACT_MARK, $usage, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        if ($matches === []) {
            return ['usage' => trim($usage), 'facts' => '', 'ingredients' => $ingredients];
        }

        $kept = trim(substr($usage, 0, $matches[0][0][1]));
        $facts = [];

        foreach ($matches as $i => $match) {
            $start = $match[0][1] + strlen($match[0][0]);
            $end = $matches[$i + 1][0][1] ?? strlen($usage);
            $text = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(substr($usage, $start, $end - $start)), ENT_QUOTES | ENT_HTML5, 'UTF-8')), " \t.");

            if ($text !== '') {
                $label = mb_convert_case(mb_strtolower($match[1][0]), MB_CASE_TITLE, 'UTF-8');
                $facts[] = '<p><strong>'.e($label).':</strong> '.e($text).'.</p>';
            }
        }

        return ['usage' => self::stripEmpty($kept), 'facts' => implode('', $facts), 'ingredients' => $ingredients];
    }

    /**
     * Drop the empty paragraphs a cut leaves behind.
     */
    private static function stripEmpty(string $html): string
    {
        return trim(preg_replace('~<(p|div|span|strong|b)[^>]*>(?:\s|&nbsp;|<br\s*/?>)*</\1>~iu', '', $html));
    }

    /**
     * Plain text lines as simple HTML: an ingredient list becomes a list.
     */
    public static function textToHtml(string $text): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('~\n+~', $text)), fn (string $l): bool => $l !== ''));

        if ($lines === []) {
            return '';
        }

        $items = array_filter($lines, fn (string $l): bool => str_starts_with($l, '-'));

        if (count($items) === count($lines)) {
            return '<ul>'.implode('', array_map(fn (string $l): string => '<li>'.e(ltrim($l, "- \t")).'</li>', $lines)).'</ul>';
        }

        return implode('', array_map(fn (string $l): string => '<p>'.e(ltrim($l, "- \t")).'</p>', $lines));
    }
}
