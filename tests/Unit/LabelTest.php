<?php

use Meva\Entities\Catalogue\Label;

it('splits a description with headings, as a set has', function () {
    $html = '<p>Ekcem može ozbiljno narušiti komfor.</p>'
        .'<h3>Uputstvo za upotrebu</h3><h3><strong>Šampon</strong></h3><p>Naneti i isprati.</p>'
        .'<h3>SASTAV</h3><h3><strong>Šampon</strong></h3><p>Aqua, Glycerin</p>';

    $parts = Label::splitHtml($html);

    expect($parts['description'])->toBe('<p>Ekcem može ozbiljno narušiti komfor.</p>')
        ->and($parts['usage'])->toBe('<h3><strong>Šampon</strong></h3><p>Naneti i isprati.</p>')
        ->and($parts['ingredients'])->toBe('<h3><strong>Šampon</strong></h3><p>Aqua, Glycerin</p>');
});

it('splits a description with bold lead-ins, as a single product has', function () {
    $html = '<p>Šampon za kosu je savršen izbor.</p> <strong>Sastav:</strong> <ul><li>Aqua</li></ul> <strong>Način upotrebe:</strong> <p>Naneti na mokru kosu.</p>';

    $parts = Label::splitHtml($html);

    expect($parts['description'])->toBe('<p>Šampon za kosu je savršen izbor.</p>')
        ->and($parts['ingredients'])->toBe('<ul><li>Aqua</li></ul>')
        ->and($parts['usage'])->toBe('<p>Naneti na mokru kosu.</p>');
});

it('leaves a description without the marks alone', function () {
    expect(Label::splitHtml('<p>Samo opis.</p>'))->toBe(['description' => '<p>Samo opis.</p>', 'ingredients' => '', 'usage' => '']);
});

it('splits the short description written as plain lines, and lists the ingredients', function () {
    $text = "Šampon za kosu je savršen izbor.\n\nSastav:\n\n - Aqua\n\n - Glycerin\n\nNačin upotrebe: Naneti na mokru kosu.";

    $parts = Label::splitText($text);

    expect($parts['description'])->toBe('Šampon za kosu je savršen izbor.')
        ->and(Label::textToHtml($parts['ingredients']))->toBe('<ul><li>Aqua</li><li>Glycerin</li></ul>')
        ->and(Label::textToHtml($parts['usage']))->toBe('<p>Naneti na mokru kosu.</p>');
});

it('takes the trailing facts out of the directions as tidy paragraphs', function () {
    $usage = '<p>Nanesite tanki sloj.</p><p><b>MIRIS:</b> Ima prijatan miris, kokos i badem. AMBALAŽA: Bela, plastična Pakovanje: 200ml</p>';

    $tidy = Label::tidyUsage($usage);

    expect($tidy['usage'])->toBe('<p>Nanesite tanki sloj.</p>')
        ->and($tidy['facts'])->toBe('<p><strong>Miris:</strong> Ima prijatan miris, kokos i badem.</p><p><strong>Ambalaža:</strong> Bela, plastična.</p><p><strong>Pakovanje:</strong> 200ml.</p>')
        ->and($tidy['ingredients'])->toBe('');
});

it('leaves dosage lines in the directions, and pulls a stray ingredient list out', function () {
    $usage = '<p>Odrasli: svaki dan. Deca 7+: svako treće veče.</p><p>Sastojci:</p><ul><li>Aqua (Voda)</li></ul>';

    $tidy = Label::tidyUsage($usage);

    expect($tidy['usage'])->toBe('<p>Odrasli: svaki dan. Deca 7+: svako treće veče.</p>')
        ->and($tidy['ingredients'])->toBe('<ul><li>Aqua (Voda)</li></ul>')
        ->and($tidy['facts'])->toBe('');
});

it('leaves clean directions alone', function () {
    expect(Label::tidyUsage('<p>Nanesite uveče.</p>'))->toBe(['usage' => '<p>Nanesite uveče.</p>', 'facts' => '', 'ingredients' => '']);
});
