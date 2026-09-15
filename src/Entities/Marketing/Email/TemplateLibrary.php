<?php

namespace Meva\Entities\Marketing\Email;

/**
 * The campaigns a shop actually needs, in the order a customer meets them.
 *
 * These are not decorations. Each one is a message with a job: the welcome
 * earns the first order, the education keeps the second, the win-back rescues
 * the customer who drifted. They are laid out here in lifecycle order so the
 * picker teaches the sequence as it offers it -- which is also what the
 * knowledge base in the editor explains.
 */
class TemplateLibrary
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::welcome(),
            self::introduction(),
            self::education(),
            self::socialProof(),
            self::reminder(),
            self::replenishment(),
            self::offer(),
            self::abandonedCart(),
            self::bundle(),
            self::newProduct(),
            self::reviewRequest(),
            self::winback(),
            self::loyalty(),
            self::seasonal(),
            self::blank(),
        ];
    }

    /**
     * One template by key, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        return null;
    }

    /** Fresh blocks for a template, each with its own id. */
    public static function blocks(string $key): array
    {
        $template = self::find($key) ?? self::blank();

        return array_map(
            fn (array $block): array => BlockLibrary::make($block['type'], array_diff_key($block, ['type' => null])),
            $template['blocks'],
        );
    }

    protected static function welcome(): array
    {
        return [
            'key' => 'welcome',
            'stage' => 'Prvi kontakt',
            'name' => 'Dobrodošlica',
            'subject' => 'Drago nam je što ste tu, {ime}',
            'preheader' => 'Mala priča o tome kako Meva nastaje — i popust za prvu porudžbinu.',
            'summary' => 'Prvi mejl posle prijave. Otvara ga 50–60% ljudi, više nego bilo koji drugi.',
            'advice' => 'Šaljite ga u roku od pet minuta. Ispričajte ko ste, ne šta prodajete. Jedan poziv na akciju.',
            'accent' => '#8A9A8B',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'DOBRO DOŠLI U MEVU', 'heading' => 'Drago nam je što ste tu', 'text' => "Zdravo {ime},\n\nMeva nastaje u Novom Pazaru od 2010. Kuvamo u malim serijama, rukom, i svaka tegla ima datum kad je napravljena.", 'button_label' => 'Pogledajte preparate', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'benefits', 'heading' => 'Šta možete da očekujete'],
                ['type' => 'divider'],
                ['type' => 'testimonial'],
                ['type' => 'cta', 'text' => 'Ako ne znate odakle da počnete, javite nam kakvu kožu imate i preporučićemo vam.', 'button_label' => 'Pišite nam', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'ps', 'text' => 'P.S. Dostava je besplatna u celoj Srbiji, a plaćate kuriru kad paket stigne.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function introduction(): array
    {
        return [
            'key' => 'introduction',
            'stage' => 'Prvi kontakt',
            'name' => 'Predstavljanje proizvoda',
            'subject' => 'Ovo je preparat od kog sve počinje',
            'preheader' => 'Jedan proizvod, jedan problem koji rešava.',
            'summary' => 'Predstavlja jedan proizvod do kraja: problem, sastav, način upotrebe, dokaz.',
            'advice' => 'Jedan proizvod po mejlu. Kad ponudite tri, kupac ne bira nijedan.',
            'accent' => '#C56D59',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'UPOZNAJTE', 'heading' => 'Za kožu glave koja svrbi', 'text' => 'Ako ste probali sve i ništa nije pomoglo, ovo je razlog zašto smo ga napravili.', 'button_label' => 'Pogledaj', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'product'],
                ['type' => 'benefits', 'heading' => 'Šta je unutra'],
                ['type' => 'steps', 'heading' => 'Kako se koristi'],
                ['type' => 'testimonial'],
                ['type' => 'ps', 'text' => 'P.S. Prvi rezultati se vide posle desetak dana. Ako ne budete zadovoljni, javite nam se.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function education(): array
    {
        return [
            'key' => 'education',
            'stage' => 'Poverenje',
            'name' => 'Saveti i edukacija',
            'subject' => 'Zašto se perut vraća (i šta tu pomaže)',
            'preheader' => 'Tri stvari koje smo naučili za petnaest godina rada.',
            'summary' => 'Mejl koji ne prodaje. Gradi razlog zbog kog će sledeći mejl biti otvoren.',
            'advice' => 'Na svaka tri prodajna mejla pošaljite jedan koji samo pomaže. Bez toga lista se troši.',
            'accent' => '#8A9A8B',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'SAVET', 'heading' => 'Zašto se perut vraća', 'text' => 'Kratko objašnjenje, bez reklame — i šta možete da uradite već večeras.', 'button_label' => '', 'button_url' => ''],
                ['type' => 'text', 'heading' => '', 'text' => "Zdravo {ime},\n\nnajčešća greška je pranje prejakim šamponom. Koža se isuši, odbrani se mašću, i krug počinje ispočetka."],
                ['type' => 'steps', 'heading' => 'Šta da probate ove nedelje'],
                ['type' => 'quote', 'text' => 'Nega koja rešava, ne prikriva.'],
                ['type' => 'cta', 'text' => 'Ako želite da vidite šta koristimo u ovakvim slučajevima:', 'button_label' => 'Pogledaj preparate', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function socialProof(): array
    {
        return [
            'key' => 'social-proof',
            'stage' => 'Poverenje',
            'name' => 'Iskustva kupaca',
            'subject' => 'Šta kažu ljudi koji su probali',
            'preheader' => 'Tri iskustva, bez ulepšavanja.',
            'summary' => 'Tuđe reči rade ono što vaše ne mogu. Najjači mejl pred odluku o kupovini.',
            'advice' => 'Koristite puna imena i ime proizvoda. Anonimna pohvala ne ubeđuje nikoga.',
            'accent' => '#B3617E',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'ISKUSTVA', 'heading' => 'Ne verujte nama', 'text' => 'Evo šta su nam pisali ljudi koji su preparate koristili mesec dana.', 'button_label' => '', 'button_url' => ''],
                ['type' => 'testimonial'],
                ['type' => 'testimonial', 'quote' => 'Perut je nestala posle druge nedelje. Prvi put da nešto zaista radi.', 'author' => 'Milica', 'product' => 'Losion za kožu glave'],
                ['type' => 'testimonial', 'quote' => 'Miris je prirodan, ne parfemski. Cela porodica koristi.', 'author' => 'Adnan', 'product' => 'Sapun od maslinovog ulja'],
                ['type' => 'cta', 'text' => '', 'button_label' => 'Probajte i vi', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function reminder(): array
    {
        return [
            'key' => 'reminder',
            'stage' => 'Podsticaj',
            'name' => 'Podsetnik',
            'subject' => 'Još uvek vas čeka',
            'preheader' => 'Kratko podsećanje — bez pritiska.',
            'summary' => 'Drugi pokušaj ka istim ljudima. Isti sadržaj, drugi naslov, tri dana kasnije.',
            'advice' => 'Šaljite samo onima koji nisu otvorili prvi mejl. Otvaranja rastu za 20–30%.',
            'accent' => '#C56D59',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'PODSETNIK', 'heading' => 'Još uvek vas čeka', 'text' => "Zdravo {ime},\n\nposlali smo vam nedavno poruku i možda se izgubila u gomili. Evo je ukratko.", 'button_label' => 'Pogledaj', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'product'],
                ['type' => 'ps', 'text' => 'P.S. Ako vam ovo nije zanimljivo, slobodno se odjavite — nećemo se ljutiti.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function replenishment(): array
    {
        return [
            'key' => 'replenishment',
            'stage' => 'Podsticaj',
            'name' => 'Vreme je za dopunu',
            'subject' => 'Verovatno vam je pri kraju',
            'preheader' => 'Prošlo je oko dva meseca od poslednje porudžbine.',
            'summary' => 'Najisplativiji mejl u prodavnici kozmetike. Šalje se kad pakovanje treba da se potroši.',
            'advice' => 'Merite koliko traje pakovanje i šaljite nekoliko dana pre kraja, ne posle.',
            'accent' => '#8A9A8B',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'DOPUNA', 'heading' => 'Verovatno vam je pri kraju', 'text' => "Zdravo {ime},\n\nprošlo je oko dva meseca od vaše poslednje porudžbine. Ako koristite svakodnevno, pakovanje bi trebalo da je pri kraju.", 'button_label' => 'Naruči ponovo', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'product'],
                ['type' => 'bundle'],
                ['type' => 'ps', 'text' => 'P.S. Dostava je i dalje besplatna, plaćate kuriru.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function offer(): array
    {
        return [
            'key' => 'offer',
            'stage' => 'Ponuda',
            'name' => 'Ponuda sa rokom',
            'subject' => '−15% do nedelje',
            'preheader' => 'Kod važi na sve preparate, do nedelje u ponoć.',
            'summary' => 'Popust sa jasnim rokom. Pomera odluku sa „nekad" na „danas".',
            'advice' => 'Rok mora biti istinit. Ako ga produžite, sledeći put vam niko neće verovati.',
            'accent' => '#C56D59',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'SAMO OVE NEDELJE', 'heading' => '−15% na sve', 'text' => 'Bez uslova i bez minimalne porudžbine.', 'button_label' => '', 'button_url' => ''],
                ['type' => 'offer'],
                ['type' => 'products', 'heading' => 'Najtraženije'],
                ['type' => 'testimonial'],
                ['type' => 'ps', 'text' => 'P.S. Kod važi do nedelje u ponoć i ne može da se kombinuje sa drugim popustima.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function abandonedCart(): array
    {
        return [
            'key' => 'abandoned-cart',
            'stage' => 'Ponuda',
            'name' => 'Napuštena korpa',
            'subject' => 'Ostavili ste nešto u korpi',
            'preheader' => 'Sačuvali smo je za vas.',
            'summary' => 'Mejl sa najvećim prihodom po poslatoj poruci. Šalje se sat vremena posle napuštanja.',
            'advice' => 'Prvi u roku od sat vremena, drugi posle 24 sata. Popust tek u trećem, ako uopšte.',
            'accent' => '#B3617E',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'VAŠA KORPA', 'heading' => 'Ostavili ste nešto', 'text' => "Zdravo {ime},\n\nsačuvali smo vašu korpu. Treba vam još samo jedan klik.", 'button_label' => 'Nastavi poručivanje', 'button_url' => 'https://meva.life/korpa'],
                ['type' => 'product'],
                ['type' => 'benefits', 'heading' => 'Zašto da završite porudžbinu'],
                ['type' => 'testimonial'],
                ['type' => 'ps', 'text' => 'P.S. Ako ste se predomislili, recite nam zašto — pomaže nam.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function bundle(): array
    {
        return [
            'key' => 'bundle',
            'stage' => 'Ponuda',
            'name' => 'Set koji se kupuje zajedno',
            'subject' => 'Ovo dvoje ide zajedno',
            'preheader' => 'Kombinacija koju naši kupci najčešće poručuju.',
            'summary' => 'Podiže vrednost porudžbine bez popusta — predlaže, ne obara cenu.',
            'advice' => 'Predlog izvucite iz stvarnih porudžbina, ne iz osećaja. Alat „Šta ide uz šta" vam daje parove.',
            'accent' => '#8A9A8B',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'IDE ZAJEDNO', 'heading' => 'Bolje rade u paru', 'text' => 'Kupci koji uzmu jedno, skoro uvek se vrate po drugo. Evo zašto.', 'button_label' => '', 'button_url' => ''],
                ['type' => 'bundle'],
                ['type' => 'steps', 'heading' => 'Kako da ih koristite zajedno'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function newProduct(): array
    {
        return [
            'key' => 'new-product',
            'stage' => 'Ponuda',
            'name' => 'Novo u ponudi',
            'subject' => 'Nešto novo iz naše kuhinje',
            'preheader' => 'Prva serija je mala — javljamo vama prvima.',
            'summary' => 'Najava novog preparata. Radi najbolje kad je serija ograničena i to se kaže.',
            'advice' => 'Pošaljite prvo najboljim kupcima, dan ranije. Osećaj prednosti vredi više od popusta.',
            'accent' => '#B3617E',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'NOVO', 'heading' => 'Nešto novo iz naše kuhinje', 'text' => 'Prva serija je mala, pa javljamo vama prvima.', 'button_label' => 'Pogledaj', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'product'],
                ['type' => 'benefits', 'heading' => 'Zašto smo ga napravili'],
                ['type' => 'quote', 'text' => 'Sve što stavljamo u teglu, stavili bismo i sebi na kožu.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function reviewRequest(): array
    {
        return [
            'key' => 'review-request',
            'stage' => 'Posle kupovine',
            'name' => 'Molba za utisak',
            'subject' => 'Kako vam se pokazalo?',
            'preheader' => 'Dve rečenice su nam dovoljne.',
            'summary' => 'Traži utisak nekoliko nedelja posle porudžbine, kad se rezultat već vidi.',
            'advice' => 'Tražite malo — „dve rečenice". Velika molba dobija manje odgovora.',
            'accent' => '#8A9A8B',
            'blocks' => [
                ['type' => 'text', 'heading' => 'Kako vam se pokazalo?', 'text' => "Zdravo {ime},\n\nprošlo je nekoliko nedelja od vaše porudžbine i sad bi trebalo da se vidi razlika.\n\nAko imate minut, napišite nam dve rečenice. Pomaže ljudima koji se dvoume."],
                ['type' => 'cta', 'text' => '', 'button_label' => 'Napiši utisak', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'ps', 'text' => 'P.S. Ako nešto nije bilo kako treba, odgovorite na ovaj mejl i rešićemo.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function winback(): array
    {
        return [
            'key' => 'winback',
            'stage' => 'Povratak',
            'name' => 'Vraćanje kupca',
            'subject' => 'Nedostajete nam, {ime}',
            'preheader' => 'Prošlo je dosta vremena — evo šta se u međuvremenu promenilo.',
            'summary' => 'Poslednji pokušaj ka kupcima koji su nestali. Jeftinije nego naći novog kupca.',
            'advice' => 'Budite iskreni da je prošlo dosta vremena. Ponudite izlaz — odjavu — i lista ostaje zdrava.',
            'accent' => '#C56D59',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'DAVNO SE NISMO ČULI', 'heading' => 'Nedostajete nam', 'text' => "Zdravo {ime},\n\nprošlo je dosta od vaše poslednje porudžbine. U međuvremenu smo dodali nekoliko preparata i poboljšali stare recepture.", 'button_label' => 'Vidi šta je novo', 'button_url' => 'https://meva.life/proizvodi'],
                ['type' => 'offer', 'heading' => 'Dobrodošlica nazad: −20%', 'code' => 'NAZAD20', 'deadline' => 'Kod važi narednih sedam dana.'],
                ['type' => 'products', 'heading' => 'Ono što se najviše traži'],
                ['type' => 'ps', 'text' => 'P.S. Ako vam više nisu zanimljive naše poruke, odjavite se jednim klikom dole. Bez ljutnje.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function loyalty(): array
    {
        return [
            'key' => 'loyalty',
            'stage' => 'Povratak',
            'name' => 'Hvala vernim kupcima',
            'subject' => 'Hvala vam, iskreno',
            'preheader' => 'Mali znak pažnje za ljude koji nam se stalno vraćaju.',
            'summary' => 'Ide najboljim kupcima. Ne traži ništa — zato i radi.',
            'advice' => 'Šaljite listi najvernijih iz alata za kupce. Nagrada bez uslova gradi vezanost.',
            'accent' => '#B3617E',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'HVALA VAM', 'heading' => 'Vi ste razlog zašto ovo radimo', 'text' => "Zdravo {ime},\n\nvi ste među ljudima koji nam se najviše vraćaju. To nam mnogo znači i hteli smo da to kažemo naglas.", 'button_label' => '', 'button_url' => ''],
                ['type' => 'offer', 'heading' => 'Poklon: −20% kad god poželite', 'text' => 'Bez roka i bez uslova. Kod je samo vaš.', 'code' => 'HVALA20', 'deadline' => ''],
                ['type' => 'quote', 'text' => 'Petnaest godina, jedna kuhinja, iste ruke.'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function seasonal(): array
    {
        return [
            'key' => 'seasonal',
            'stage' => 'Povratak',
            'name' => 'Sezonska poruka',
            'subject' => 'Zima je stigla, koža to zna',
            'preheader' => 'Šta menjamo u nezi kad padne temperatura.',
            'summary' => 'Vezuje proizvod za nešto što se kupcu upravo dešava. Uvek ima razlog za slanje.',
            'advice' => 'Povod mora biti stvaran za kupca, ne za vas. Vreme i godišnja doba uvek rade.',
            'accent' => '#8A9A8B',
            'blocks' => [
                ['type' => 'hero', 'eyebrow' => 'SEZONA', 'heading' => 'Zima je stigla, koža to zna', 'text' => 'Suv vazduh i grejanje rade isto: izvlače vlagu. Evo šta menjamo u nezi.', 'button_label' => '', 'button_url' => ''],
                ['type' => 'steps', 'heading' => 'Tri promene za ovu sezonu'],
                ['type' => 'products', 'heading' => 'Za ove mesece'],
                ['type' => 'footer'],
            ],
        ];
    }

    protected static function blank(): array
    {
        return [
            'key' => 'blank',
            'stage' => 'Od nule',
            'name' => 'Prazna kampanja',
            'subject' => '',
            'preheader' => '',
            'summary' => 'Počnite sa praznim listom i složite mejl sam.',
            'advice' => 'Držite se redosleda: kuka, korist, dokaz, poziv na akciju, P.S.',
            'accent' => '#6B7A71',
            'blocks' => [
                ['type' => 'hero'],
                ['type' => 'footer'],
            ],
        ];
    }
}
