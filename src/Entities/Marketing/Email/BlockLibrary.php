<?php

namespace Meva\Entities\Marketing\Email;

/**
 * The blocks a campaign is built from.
 *
 * Each one is a small, well-behaved piece of an e-mail: a table, inline
 * styles, a width that survives Outlook, and a job it does in the message.
 * The "why" on each block is shown in the editor, so whoever writes the
 * campaign knows what the block is for rather than guessing.
 */
class BlockLibrary
{
    /**
     * Everything the editor can add, in the order it offers them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'type' => 'hero',
                'label' => 'Naslovna slika',
                'group' => 'Početak',
                'why' => 'Prvo što se vidi. Jedna slika, jedna poruka, jedno dugme — ne tri.',
                'fields' => ['image', 'eyebrow', 'heading', 'text', 'button_label', 'button_url'],
                'defaults' => [
                    'eyebrow' => 'MEVA KOZMETIKA',
                    'heading' => 'Koža glave koja se konačno smirila',
                    'text' => 'Ručno rađeni preparati iz Novog Pazara, od 2010.',
                    'button_label' => 'Pogledaj preparate',
                    'button_url' => 'https://meva.life/proizvodi',
                    'image' => null,
                ],
            ],
            [
                'type' => 'text',
                'label' => 'Tekst',
                'group' => 'Osnovno',
                'why' => 'Piši kao čoveku, ne kao listi. Kratki pasusi, „vi" umesto „mi".',
                'fields' => ['heading', 'text'],
                'defaults' => [
                    'heading' => '',
                    'text' => "Zdravo {ime},\n\nhvala što ste deo Meve.",
                ],
            ],
            [
                'type' => 'product',
                'label' => 'Jedan proizvod',
                'group' => 'Prodaja',
                'why' => 'Jedan proizvod, velika slika, jedna korist i cena. Najbolje radi kad je poruka o jednom problemu.',
                'fields' => ['product_slug', 'benefit', 'button_label'],
                'defaults' => ['product_slug' => null, 'benefit' => '', 'button_label' => 'Poruči'],
            ],
            [
                'type' => 'products',
                'label' => 'Više proizvoda',
                'group' => 'Prodaja',
                'why' => 'Najviše tri u redu. Više od šest ukupno i niko ne bira — bira se ništa.',
                'fields' => ['heading', 'product_slugs'],
                'defaults' => ['heading' => 'Iz naše ponude', 'product_slugs' => []],
            ],
            [
                'type' => 'bundle',
                'label' => 'Set koji se kupuje zajedno',
                'group' => 'Prodaja',
                'why' => 'Predlog iz stvarnih korpi. Kupac koji je uzeo jedno skoro uvek uzme i drugo.',
                'fields' => ['heading', 'text', 'product_slugs', 'button_label', 'button_url'],
                'defaults' => [
                    'heading' => 'Ide jedno uz drugo',
                    'text' => 'Ovo dvoje se kod nas najčešće poručuju zajedno.',
                    'product_slugs' => [],
                    'button_label' => 'Uzmi oba',
                    'button_url' => 'https://meva.life/proizvodi',
                ],
            ],
            [
                'type' => 'benefits',
                'label' => 'Tri razloga',
                'group' => 'Ubeđivanje',
                'why' => 'Korist, ne osobina. „Perut se ispira" je korist; „sadrži salicilnu kiselinu" je osobina.',
                'fields' => ['heading', 'items'],
                'defaults' => [
                    'heading' => 'Zašto baš ovo',
                    'items' => [
                        ['title' => 'Bez sulfata', 'text' => 'Ne razdražuje kožu glave koja je već osetljiva.'],
                        ['title' => 'Ručno rađeno', 'text' => 'Male serije, svaka sa datumom proizvodnje.'],
                        ['title' => 'Besplatna dostava', 'text' => 'U celoj Srbiji, plaćanje pouzećem.'],
                    ],
                ],
            ],
            [
                'type' => 'testimonial',
                'label' => 'Reč kupca',
                'group' => 'Ubeđivanje',
                'why' => 'Tuđe reči ubeđuju više od vaših. Ime i proizvod čine ih verodostojnim.',
                'fields' => ['quote', 'author', 'product'],
                'defaults' => [
                    'quote' => 'U životu nisam koristila bolji šampon za kosu. Sastav mu je odličan.',
                    'author' => 'Anastasija',
                    'product' => 'Šampon za kosu',
                ],
            ],
            [
                'type' => 'steps',
                'label' => 'Kako se koristi',
                'group' => 'Ubeđivanje',
                'why' => 'Kad čovek vidi koliko je jednostavno, nestaje strah da neće znati.',
                'fields' => ['heading', 'items'],
                'defaults' => [
                    'heading' => 'Tri koraka',
                    'items' => [
                        ['title' => 'Uveče', 'text' => 'Nanesite direktno na kožu glave.'],
                        ['title' => 'Ne ispira se', 'text' => 'Ostavite da deluje preko noći.'],
                        ['title' => 'Svaki dan', 'text' => 'Prvi rezultati posle desetak dana.'],
                    ],
                ],
            ],
            [
                'type' => 'offer',
                'label' => 'Ponuda sa rokom',
                'group' => 'Ubeđivanje',
                'why' => 'Rok pomera odluku sa „nekad" na „danas". Rok mora biti istinit.',
                'fields' => ['heading', 'text', 'code', 'deadline', 'button_label', 'button_url'],
                'defaults' => [
                    'heading' => '−15% do nedelje',
                    'text' => 'Kod unesite u polju za napomenu pri poručivanju.',
                    'code' => 'MEVA15',
                    'deadline' => 'Važi do nedelje u ponoć.',
                    'button_label' => 'Iskoristi popust',
                    'button_url' => 'https://meva.life/proizvodi',
                ],
            ],
            [
                'type' => 'cta',
                'label' => 'Dugme',
                'group' => 'Osnovno',
                'why' => 'Jedno dugme, glagol u prvom licu. „Poruči" radi bolje od „Klikni ovde".',
                'fields' => ['text', 'button_label', 'button_url'],
                'defaults' => [
                    'text' => '',
                    'button_label' => 'Pogledaj preparate',
                    'button_url' => 'https://meva.life/proizvodi',
                ],
            ],
            [
                'type' => 'image',
                'label' => 'Slika',
                'group' => 'Osnovno',
                'why' => 'Uvek sa opisom (alt) — kod trećine ljudi slike se ne učitavaju odmah.',
                'fields' => ['image', 'alt', 'button_url'],
                'defaults' => ['image' => null, 'alt' => '', 'button_url' => ''],
            ],
            [
                'type' => 'quote',
                'label' => 'Istaknuta rečenica',
                'group' => 'Osnovno',
                'why' => 'Oko staje na kratku rečenicu koja stoji sama. Koristi je za jednu misao.',
                'fields' => ['text'],
                'defaults' => ['text' => 'Nega koja rešava, ne prikriva.'],
            ],
            [
                'type' => 'divider',
                'label' => 'Linija',
                'group' => 'Razmak',
                'why' => 'Razdvaja teme. Bez nje se mail čita kao jedan dugačak blok.',
                'fields' => [],
                'defaults' => [],
            ],
            [
                'type' => 'spacer',
                'label' => 'Prazan prostor',
                'group' => 'Razmak',
                'why' => 'Vazduh oko poruke. Zbijen mail deluje kao reklama.',
                'fields' => ['size'],
                'defaults' => ['size' => 24],
            ],
            [
                'type' => 'ps',
                'label' => 'P.S.',
                'group' => 'Kraj',
                'why' => 'Posle naslova, P.S. je najčitaniji deo mejla. Tu ide najjači razlog.',
                'fields' => ['text'],
                'defaults' => ['text' => 'P.S. Dostava je besplatna, plaćate kuriru kad paket stigne.'],
            ],
            [
                'type' => 'footer',
                'label' => 'Podnožje',
                'group' => 'Kraj',
                'why' => 'Adresa i odjava su zakonska obaveza — i znak da niste spam.',
                'fields' => ['text'],
                'defaults' => ['text' => 'Meva Kozmetika · Miloša Obilića 20, 36300 Novi Pazar'],
            ],
        ];
    }

    /**
     * A block of the given type, filled with its defaults.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function make(string $type, array $overrides = []): array
    {
        $definition = collect(self::all())->firstWhere('type', $type);

        return array_merge(
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => $type],
            $definition['defaults'] ?? [],
            $overrides,
        );
    }
}
