<?php

namespace Meva\Entities\Marketing\Email;

use Illuminate\Support\Str;
use Meva\Entities\Marketing\Models\EmailCampaign;

/**
 * Turns a campaign's blocks into the HTML that gets sent.
 *
 * Mail clients are not browsers. Outlook renders with Word, Gmail strips the
 * document head on forwards, and a third of people never load the images. So
 * the output here is deliberately old-fashioned: nested tables instead of flex,
 * every style inline, a fixed 600px column, and buttons drawn twice -- once in
 * VML for Outlook and once in HTML for everyone else.
 *
 * The editor's preview calls this same method, which is the point: what the
 * admin sees in the preview pane is byte-for-byte what leaves the server.
 */
class EmailRenderer
{
    /** The shop's palette, repeated inline because <style> cannot be trusted. */
    public const CREAM = '#F2EFE8';

    public const PAPER = '#FFFFFF';

    public const FOREST = '#24342C';

    public const TERRACOTTA = '#C56D59';

    public const POWDER = '#B3617E';

    public const SAGE = '#8A9A8B';

    public const INK = '#2E3B34';

    public const MUTED = '#6B7A71';

    public const LINE = '#E3DED3';

    /** Sans stack that degrades sanely on Windows and iOS. */
    public const SANS = "-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif";

    public const SERIF = "Georgia,'Times New Roman',serif";

    /**
     * Values substituted into any text field at render time.
     *
     * @var array<string, string>
     */
    protected array $tokens = [];

    protected ProductResolver $products;

    public function __construct(?ProductResolver $products = null)
    {
        $this->products = $products ?? new ProductResolver;
    }

    /**
     * The whole message for one campaign.
     *
     * @param  array<string, string>  $tokens  Recipient values: ime, email, odjava…
     */
    public function render(EmailCampaign $campaign, array $tokens = []): string
    {
        $this->tokens = array_merge([
            'ime' => 'draga/i',
            'email' => '',
            'odjava' => rtrim(config('meva.storefront_url', 'https://meva.life'), '/').'/odjava',
            'prodavnica' => rtrim(config('meva.storefront_url', 'https://meva.life'), '/'),
            'godina' => date('Y'),
        ], array_filter($tokens, fn ($v): bool => $v !== null));

        $blocks = $campaign->blocks ?? [];
        $body = '';

        foreach ($blocks as $block) {
            $body .= $this->block(is_array($block) ? $block : (array) $block);
        }

        return $this->document(
            $this->text($campaign->subject ?? ''),
            $this->text($campaign->preheader ?? ''),
            $body,
        );
    }

    /**
     * The plain-text alternative, built from the same blocks.
     *
     * Every message needs one: clients that cannot render HTML fall back to it,
     * and spam filters read an HTML-only message as a worse signal.
     */
    public function renderText(EmailCampaign $campaign, array $tokens = []): string
    {
        $this->tokens = array_merge(['ime' => 'draga/i'], array_filter($tokens, fn ($v): bool => $v !== null));

        $lines = [];

        foreach ($campaign->blocks ?? [] as $block) {
            $block = is_array($block) ? $block : (array) $block;

            foreach (['eyebrow', 'heading', 'text', 'quote', 'benefit', 'code', 'deadline'] as $field) {
                if (! empty($block[$field])) {
                    $lines[] = $this->text((string) $block[$field]);
                }
            }

            foreach ($block['items'] ?? [] as $item) {
                $lines[] = '- '.$this->text(($item['title'] ?? '').': '.($item['text'] ?? ''));
            }

            if (! empty($block['button_url'])) {
                $lines[] = $this->text((string) ($block['button_label'] ?? 'Otvori')).': '.$this->text((string) $block['button_url']);
            }

            $lines[] = '';
        }

        $lines[] = 'Odjava: '.($this->tokens['odjava'] ?? 'https://meva.life/odjava');

        return trim(implode("\n", $lines));
    }

    /**
     * The document shell: head, preheader, background table, column.
     */
    protected function document(string $subject, string $preheader, string $body): string
    {
        $cream = self::CREAM;
        $paper = self::PAPER;
        $muted = self::MUTED;
        $sans = self::SANS;
        $title = e($subject);

        // Repeated no-break spaces push the recipient's inbox preview past the
        // preheader, so the first line of the message does not appear twice.
        $spacer = str_repeat('&#847;&zwnj;&nbsp;', 60);
        $pre = e($preheader);

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="sr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="color-scheme" content="light" />
<meta name="supported-color-schemes" content="light" />
<title>{$title}</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style type="text/css">
  body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
  table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
  img{-ms-interpolation-mode:bicubic;border:0;outline:none;text-decoration:none;display:block}
  body{margin:0!important;padding:0!important;width:100%!important}
  a{color:{$muted}}
  .m-pad{padding-left:32px;padding-right:32px}
  @media only screen and (max-width:620px){
    .m-full{width:100%!important;max-width:100%!important}
    .m-pad{padding-left:20px!important;padding-right:20px!important}
    .m-stack{display:block!important;width:100%!important;max-width:100%!important}
    .m-center{text-align:center!important}
    .m-h1{font-size:28px!important;line-height:34px!important}
  }
  @media (prefers-color-scheme:dark){
    .m-shell{background:{$cream}!important}
    .m-card{background:{$paper}!important}
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:{$cream};">
<div style="display:none;font-size:1px;color:{$cream};line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{$pre}{$spacer}</div>
<table role="presentation" class="m-shell" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$cream};width:100%;">
<tr><td align="center" style="padding:24px 12px;font-family:{$sans};">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" class="m-full m-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:{$paper};border-radius:18px;overflow:hidden;">
{$body}
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * One block, dispatched by type.
     *
     * @param  array<string, mixed>  $block
     */
    protected function block(array $block): string
    {
        return match ($block['type'] ?? '') {
            'hero' => $this->hero($block),
            'text' => $this->textBlock($block),
            'product' => $this->product($block),
            'products' => $this->productGrid($block),
            'bundle' => $this->bundle($block),
            'benefits' => $this->benefits($block),
            'steps' => $this->steps($block),
            'testimonial' => $this->testimonial($block),
            'offer' => $this->offer($block),
            'cta' => $this->cta($block),
            'image' => $this->image($block),
            'quote' => $this->quote($block),
            'divider' => $this->divider(),
            'spacer' => $this->spacer($block),
            'ps' => $this->ps($block),
            'footer' => $this->footer($block),
            default => '',
        };
    }

    protected function hero(array $b): string
    {
        $out = '';
        $image = $this->field($b, 'image');

        if ($image !== '') {
            $out .= '<tr><td style="padding:0;"><img src="'.e($image).'" width="600" alt="'.e($this->field($b, 'heading')).'" style="display:block;width:100%;max-width:600px;height:auto;border:0;" /></td></tr>';
        }

        $inner = '';
        $eyebrow = $this->field($b, 'eyebrow');
        $heading = $this->field($b, 'heading');
        $text = $this->field($b, 'text');

        if ($eyebrow !== '') {
            $inner .= $this->eyebrow($eyebrow);
        }

        if ($heading !== '') {
            $inner .= '<h1 class="m-h1" style="margin:0 0 14px;font-family:'.self::SERIF.';font-size:34px;line-height:40px;font-weight:400;color:'.self::FOREST.';">'.e($heading).'</h1>';
        }

        if ($text !== '') {
            $inner .= $this->paragraphs($text);
        }

        if ($this->field($b, 'button_url') !== '') {
            $inner .= $this->button($this->field($b, 'button_label', 'Pogledaj'), $this->field($b, 'button_url'), self::FOREST);
        }

        $out .= '<tr><td class="m-pad" align="center" style="padding:36px 32px 8px;text-align:center;">'.$inner.'</td></tr>';

        return $out;
    }

    protected function textBlock(array $b): string
    {
        $inner = '';
        $heading = $this->field($b, 'heading');

        if ($heading !== '') {
            $inner .= '<h2 style="margin:0 0 12px;font-family:'.self::SERIF.';font-size:24px;line-height:30px;font-weight:400;color:'.self::FOREST.';">'.e($heading).'</h2>';
        }

        $inner .= $this->paragraphs($this->field($b, 'text'));

        return '<tr><td class="m-pad" style="padding:20px 32px;">'.$inner.'</td></tr>';
    }

    protected function product(array $b): string
    {
        $product = $this->products->find($this->field($b, 'product_slug'));

        if ($product === null) {
            return $this->placeholder('Izaberite proizvod za ovaj blok.');
        }

        // The shop's own description is the whole label, ingredient list and
        // all. In an e-mail that is a wall of text nobody reads, so the
        // fallback is cut to its opening thought and the editor is expected to
        // write one benefit in its place.
        $benefit = $this->field($b, 'benefit') ?: $this->opening($product['short_description'] ?? '');
        $label = $this->field($b, 'button_label', 'Poruči');

        $image = $product['image']
            ? '<tr><td style="padding:0 0 18px;"><a href="'.e($product['url']).'" style="text-decoration:none;"><img src="'.e($product['image']).'" width="536" alt="'.e($product['name']).'" style="display:block;width:100%;max-width:536px;height:auto;border-radius:12px;border:0;" /></a></td></tr>'
            : '';

        $price = $product['price'] !== null
            ? '<p style="margin:0 0 18px;font-family:'.self::SANS.';font-size:20px;line-height:26px;color:'.self::TERRACOTTA.';font-weight:700;">'.e($product['price']).'</p>'
            : '';

        return '<tr><td class="m-pad" align="center" style="padding:16px 32px;text-align:center;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            .$image
            .'<tr><td align="center" style="text-align:center;">'
            .'<h2 style="margin:0 0 8px;font-family:'.self::SERIF.';font-size:26px;line-height:32px;font-weight:400;color:'.self::FOREST.';">'.e($product['name']).'</h2>'
            .($benefit !== '' ? '<p style="margin:0 0 14px;font-family:'.self::SANS.';font-size:16px;line-height:26px;color:'.self::MUTED.';">'.e($benefit).'</p>' : '')
            .$price
            .$this->buttonHtml($label, $product['url'], self::TERRACOTTA)
            .'</td></tr></table></td></tr>';
    }

    /**
     * The first sentence of a description, at most a line and a half.
     */
    protected function opening(string $text, int $limit = 150): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return '';
        }

        // Stop at the first full stop that is not part of an abbreviation or a
        // decimal, and never mid-word.
        if (preg_match('/^(.{40,'.$limit.'}?[.!?])\s/u', $text, $match) === 1) {
            return rtrim($match[1], ' .').'.';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), ' ,;:').'…';
    }

    protected function productGrid(array $b): string
    {
        $slugs = array_filter((array) ($b['product_slugs'] ?? []));
        $items = array_values(array_filter(array_map(fn ($s) => $this->products->find($s), $slugs)));

        if ($items === []) {
            return $this->placeholder('Dodajte proizvode u ovaj blok.');
        }

        $heading = $this->field($b, 'heading');
        $out = '<tr><td class="m-pad" style="padding:24px 32px 4px;">';

        if ($heading !== '') {
            $out .= '<h2 style="margin:0 0 16px;font-family:'.self::SERIF.';font-size:24px;line-height:30px;font-weight:400;color:'.self::FOREST.';text-align:center;">'.e($heading).'</h2>';
        }

        // Two to a row: three columns at 600px leaves images too small to sell.
        $rows = array_chunk($items, 2);
        $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';

        foreach ($rows as $row) {
            $out .= '<tr>';

            foreach ($row as $i => $product) {
                $pad = $i === 0 ? 'padding:0 8px 22px 0;' : 'padding:0 0 22px 8px;';
                $out .= '<td class="m-stack" width="50%" valign="top" style="width:50%;'.$pad.'">'
                    .$this->card($product)
                    .'</td>';
            }

            if (count($row) === 1) {
                $out .= '<td class="m-stack" width="50%" style="width:50%;">&nbsp;</td>';
            }

            $out .= '</tr>';
        }

        return $out.'</table></td></tr>';
    }

    /** One product tile inside a grid. */
    protected function card(array $product): string
    {
        $image = $product['image']
            ? '<a href="'.e($product['url']).'"><img src="'.e($product['image']).'" width="260" alt="'.e($product['name']).'" style="display:block;width:100%;max-width:260px;height:auto;border-radius:10px;border:0;" /></a>'
            : '';

        $price = $product['price'] !== null
            ? '<div style="font-family:'.self::SANS.';font-size:15px;line-height:22px;color:'.self::TERRACOTTA.';font-weight:700;">'.e($product['price']).'</div>'
            : '';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            .'<tr><td style="padding:0 0 10px;">'.$image.'</td></tr>'
            .'<tr><td style="padding:0 0 4px;font-family:'.self::SANS.';font-size:16px;line-height:22px;color:'.self::FOREST.';font-weight:600;">'
            .'<a href="'.e($product['url']).'" style="color:'.self::FOREST.';text-decoration:none;">'.e($product['name']).'</a></td></tr>'
            .'<tr><td style="padding:0 0 6px;">'.$price.'</td></tr>'
            .'<tr><td><a href="'.e($product['url']).'" style="font-family:'.self::SANS.';font-size:14px;color:'.self::TERRACOTTA.';text-decoration:underline;">Poruči &rarr;</a></td></tr>'
            .'</table>';
    }

    protected function bundle(array $b): string
    {
        $slugs = array_filter((array) ($b['product_slugs'] ?? []));
        $items = array_values(array_filter(array_map(fn ($s) => $this->products->find($s), $slugs)));

        if ($items === []) {
            return $this->placeholder('Izaberite proizvode koji idu zajedno.');
        }

        $names = implode(' + ', array_map(fn ($p) => $p['name'], $items));
        $total = array_sum(array_map(fn ($p) => $p['minor'] ?? 0, $items));

        $thumbs = '';

        foreach ($items as $i => $product) {
            if ($i > 0) {
                $thumbs .= '<td width="28" align="center" valign="middle" style="width:28px;font-family:'.self::SANS.';font-size:22px;color:'.self::TERRACOTTA.';">+</td>';
            }

            $thumbs .= '<td align="center" valign="middle" style="padding:0;">'
                .($product['image'] ? '<img src="'.e($product['image']).'" width="150" alt="'.e($product['name']).'" style="display:block;width:100%;max-width:150px;height:auto;border-radius:10px;border:0;" />' : '&nbsp;')
                .'</td>';
        }

        return '<tr><td class="m-pad" style="padding:20px 32px;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:'.self::CREAM.';border-radius:16px;">'
            .'<tr><td style="padding:24px 24px 6px;" align="center"><table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>'.$thumbs.'</tr></table></td></tr>'
            .'<tr><td align="center" style="padding:14px 24px 24px;text-align:center;">'
            .'<h2 style="margin:0 0 6px;font-family:'.self::SERIF.';font-size:22px;line-height:28px;font-weight:400;color:'.self::FOREST.';">'.e($this->field($b, 'heading', 'Ide jedno uz drugo')).'</h2>'
            .'<p style="margin:0 0 6px;font-family:'.self::SANS.';font-size:15px;line-height:24px;color:'.self::MUTED.';">'.e($names).'</p>'
            .($this->field($b, 'text') !== '' ? '<p style="margin:0 0 12px;font-family:'.self::SANS.';font-size:15px;line-height:24px;color:'.self::MUTED.';">'.e($this->field($b, 'text')).'</p>' : '')
            .($total > 0 ? '<p style="margin:0 0 16px;font-family:'.self::SANS.';font-size:19px;line-height:26px;color:'.self::TERRACOTTA.';font-weight:700;">Zajedno '.e(\Meva\Entities\Catalogue\Money::format($total) ?? '').'</p>' : '')
            .$this->buttonHtml($this->field($b, 'button_label', 'Uzmi oba'), $this->field($b, 'button_url', $this->tokens['prodavnica'] ?? '#'), self::FOREST)
            .'</td></tr></table></td></tr>';
    }

    protected function benefits(array $b): string
    {
        $items = (array) ($b['items'] ?? []);

        if ($items === []) {
            return '';
        }

        $rows = '';

        foreach ($items as $item) {
            $title = $this->text((string) ($item['title'] ?? ''));
            $text = $this->text((string) ($item['text'] ?? ''));

            $rows .= '<tr>'
                .'<td width="34" valign="top" style="width:34px;padding:0 0 18px;font-family:'.self::SANS.';font-size:18px;line-height:24px;color:'.self::SAGE.';">&#10003;</td>'
                .'<td valign="top" style="padding:0 0 18px;">'
                .'<div style="font-family:'.self::SANS.';font-size:16px;line-height:24px;color:'.self::FOREST.';font-weight:600;">'.e($title).'</div>'
                .'<div style="font-family:'.self::SANS.';font-size:15px;line-height:24px;color:'.self::MUTED.';">'.e($text).'</div>'
                .'</td></tr>';
        }

        $heading = $this->field($b, 'heading');

        return '<tr><td class="m-pad" style="padding:20px 32px;">'
            .($heading !== '' ? '<h2 style="margin:0 0 16px;font-family:'.self::SERIF.';font-size:23px;line-height:29px;font-weight:400;color:'.self::FOREST.';">'.e($heading).'</h2>' : '')
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'.$rows.'</table>'
            .'</td></tr>';
    }

    protected function steps(array $b): string
    {
        $items = (array) ($b['items'] ?? []);

        if ($items === []) {
            return '';
        }

        $rows = '';

        foreach ($items as $i => $item) {
            $rows .= '<tr>'
                .'<td width="42" valign="top" style="width:42px;padding:0 0 18px;">'
                .'<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" width="30" height="30" style="width:30px;height:30px;background-color:'.self::POWDER.';border-radius:15px;font-family:'.self::SANS.';font-size:14px;line-height:30px;color:#FFFFFF;font-weight:700;">'.($i + 1).'</td></tr></table>'
                .'</td>'
                .'<td valign="top" style="padding:2px 0 18px;">'
                .'<div style="font-family:'.self::SANS.';font-size:16px;line-height:24px;color:'.self::FOREST.';font-weight:600;">'.e($this->text((string) ($item['title'] ?? ''))).'</div>'
                .'<div style="font-family:'.self::SANS.';font-size:15px;line-height:24px;color:'.self::MUTED.';">'.e($this->text((string) ($item['text'] ?? ''))).'</div>'
                .'</td></tr>';
        }

        $heading = $this->field($b, 'heading');

        return '<tr><td class="m-pad" style="padding:20px 32px;">'
            .($heading !== '' ? '<h2 style="margin:0 0 18px;font-family:'.self::SERIF.';font-size:23px;line-height:29px;font-weight:400;color:'.self::FOREST.';">'.e($heading).'</h2>' : '')
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'.$rows.'</table>'
            .'</td></tr>';
    }

    protected function testimonial(array $b): string
    {
        $author = $this->field($b, 'author');
        $product = $this->field($b, 'product');
        $meta = trim($author.($product !== '' ? ' &middot; '.$product : ''));

        return '<tr><td class="m-pad" style="padding:20px 32px;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:'.self::CREAM.';border-radius:16px;">'
            .'<tr><td style="padding:26px 26px 22px;">'
            .'<div style="font-family:'.self::SANS.';font-size:15px;line-height:20px;color:'.self::TERRACOTTA.';letter-spacing:2px;">&#9733;&#9733;&#9733;&#9733;&#9733;</div>'
            .'<p style="margin:10px 0 12px;font-family:'.self::SERIF.';font-size:19px;line-height:29px;color:'.self::FOREST.';font-style:italic;">&ldquo;'.e($this->field($b, 'quote')).'&rdquo;</p>'
            .'<div style="font-family:'.self::SANS.';font-size:14px;line-height:20px;color:'.self::MUTED.';">'.$meta.'</div>'
            .'</td></tr></table></td></tr>';
    }

    protected function offer(array $b): string
    {
        $code = $this->field($b, 'code');
        $deadline = $this->field($b, 'deadline');

        $codeRow = $code !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 14px;"><tr><td style="padding:12px 26px;background-color:#FFFFFF;border:2px dashed '.self::TERRACOTTA.';border-radius:10px;font-family:'.self::SANS.';font-size:20px;line-height:24px;letter-spacing:3px;color:'.self::FOREST.';font-weight:700;">'.e($code).'</td></tr></table>'
            : '';

        return '<tr><td class="m-pad" style="padding:20px 32px;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:'.self::FOREST.';border-radius:16px;">'
            .'<tr><td align="center" style="padding:30px 26px;text-align:center;">'
            .'<h2 style="margin:0 0 10px;font-family:'.self::SERIF.';font-size:28px;line-height:34px;font-weight:400;color:'.self::CREAM.';">'.e($this->field($b, 'heading')).'</h2>'
            .($this->field($b, 'text') !== '' ? '<p style="margin:0 0 16px;font-family:'.self::SANS.';font-size:15px;line-height:24px;color:#C9D4CB;">'.e($this->field($b, 'text')).'</p>' : '')
            .$codeRow
            .$this->buttonHtml($this->field($b, 'button_label', 'Iskoristi'), $this->field($b, 'button_url', '#'), self::TERRACOTTA)
            .($deadline !== '' ? '<p style="margin:14px 0 0;font-family:'.self::SANS.';font-size:13px;line-height:20px;color:'.self::SAGE.';">'.e($deadline).'</p>' : '')
            .'</td></tr></table></td></tr>';
    }

    protected function cta(array $b): string
    {
        $text = $this->field($b, 'text');

        return '<tr><td class="m-pad" align="center" style="padding:14px 32px 26px;text-align:center;">'
            .($text !== '' ? '<p style="margin:0 0 16px;font-family:'.self::SANS.';font-size:16px;line-height:26px;color:'.self::MUTED.';">'.e($text).'</p>' : '')
            .$this->buttonHtml($this->field($b, 'button_label', 'Pogledaj'), $this->field($b, 'button_url', '#'), self::TERRACOTTA)
            .'</td></tr>';
    }

    protected function image(array $b): string
    {
        $src = $this->field($b, 'image');

        if ($src === '') {
            return $this->placeholder('Dodajte sliku.');
        }

        $img = '<img src="'.e($src).'" width="600" alt="'.e($this->field($b, 'alt')).'" style="display:block;width:100%;max-width:600px;height:auto;border:0;" />';
        $url = $this->field($b, 'button_url');

        return '<tr><td style="padding:0;">'.($url !== '' ? '<a href="'.e($url).'">'.$img.'</a>' : $img).'</td></tr>';
    }

    protected function quote(array $b): string
    {
        return '<tr><td class="m-pad" align="center" style="padding:22px 42px;text-align:center;">'
            .'<p style="margin:0;font-family:'.self::SERIF.';font-size:22px;line-height:32px;color:'.self::POWDER.';font-style:italic;">'.e($this->field($b, 'text')).'</p>'
            .'</td></tr>';
    }

    protected function divider(): string
    {
        return '<tr><td class="m-pad" style="padding:8px 32px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="1" style="height:1px;line-height:1px;font-size:0;background-color:'.self::LINE.';">&nbsp;</td></tr></table></td></tr>';
    }

    protected function spacer(array $b): string
    {
        $size = max(4, min(96, (int) ($b['size'] ?? 24)));

        return '<tr><td height="'.$size.'" style="height:'.$size.'px;line-height:'.$size.'px;font-size:0;">&nbsp;</td></tr>';
    }

    protected function ps(array $b): string
    {
        return '<tr><td class="m-pad" style="padding:6px 32px 24px;">'
            .'<p style="margin:0;font-family:'.self::SANS.';font-size:15px;line-height:25px;color:'.self::FOREST.';">'.e($this->field($b, 'text')).'</p>'
            .'</td></tr>';
    }

    protected function footer(array $b): string
    {
        $unsub = $this->tokens['odjava'] ?? '#';

        return '<tr><td style="padding:0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:'.self::CREAM.';">'
            .'<tr><td class="m-pad" align="center" style="padding:26px 32px 30px;text-align:center;">'
            .'<div style="font-family:'.self::SERIF.';font-size:17px;line-height:24px;color:'.self::FOREST.';letter-spacing:3px;">MEVA</div>'
            .'<p style="margin:8px 0 12px;font-family:'.self::SANS.';font-size:13px;line-height:21px;color:'.self::MUTED.';">'.e($this->field($b, 'text')).'</p>'
            .'<p style="margin:0;font-family:'.self::SANS.';font-size:12px;line-height:20px;color:'.self::MUTED.';">'
            .'Dobijate ovaj mejl jer ste kupovali kod nas ili se prijavili na listu.<br />'
            .'<a href="'.e($unsub).'" style="color:'.self::MUTED.';text-decoration:underline;">Odjavite se</a>'
            .'</p></td></tr></table></td></tr>';
    }

    /**
     * A bulletproof button: VML for Outlook, a padded anchor for everyone else.
     */
    protected function button(string $label, string $url, string $colour): string
    {
        return $this->buttonHtml($label, $url, $colour);
    }

    protected function buttonHtml(string $label, string $url, string $colour): string
    {
        $label = e($label);
        $href = e($url);

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:6px auto 0;"><tr><td align="center" bgcolor="'.$colour.'" style="border-radius:999px;">'
            .'<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'.$href.'" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="50%" stroke="f" fillcolor="'.$colour.'"><w:anchorlock/><center style="color:#ffffff;font-family:'.self::SANS.';font-size:16px;font-weight:600;">'.$label.'</center></v:roundrect><![endif]-->'
            .'<!--[if !mso]><!-- --><a class="m-btn" href="'.$href.'" style="display:inline-block;padding:15px 34px;font-family:'.self::SANS.';font-size:16px;line-height:18px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:999px;background-color:'.$colour.';mso-hide:all;">'.$label.'</a><!--<![endif]-->'
            .'</td></tr></table>';
    }

    protected function eyebrow(string $text): string
    {
        return '<div style="font-family:'.self::SANS.';font-size:12px;line-height:18px;letter-spacing:3px;text-transform:uppercase;color:'.self::POWDER.';margin:0 0 12px;">'.e($text).'</div>';
    }

    /** Split a text field on blank lines so paragraphs keep their air. */
    protected function paragraphs(string $text): string
    {
        $text = $this->text($text);

        if (trim($text) === '') {
            return '';
        }

        $out = '';

        foreach (preg_split('/\n{2,}/', trim($text)) as $paragraph) {
            $out .= '<p style="margin:0 0 14px;font-family:'.self::SANS.';font-size:16px;line-height:27px;color:'.self::MUTED.';">'
                .nl2br(e(trim($paragraph)))
                .'</p>';
        }

        return $out;
    }

    /** A field with tokens substituted, never null. */
    protected function field(array $block, string $key, string $fallback = ''): string
    {
        $value = $block[$key] ?? null;

        if ($value === null || $value === '') {
            return $fallback;
        }

        return $this->text((string) $value);
    }

    /** Replace {ime}-style tokens with the recipient's values. */
    protected function text(string $value): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $m): string {
            return (string) ($this->tokens[$m[1]] ?? $m[0]);
        }, $value) ?? $value;
    }

    /** Shown in the editor when a block is not finished yet. */
    protected function placeholder(string $message): string
    {
        return '<tr><td class="m-pad" align="center" style="padding:22px 32px;text-align:center;">'
            .'<div style="border:1px dashed '.self::LINE.';border-radius:12px;padding:22px;font-family:'.self::SANS.';font-size:14px;color:'.self::MUTED.';">'.e($message).'</div>'
            .'</td></tr>';
    }
}
