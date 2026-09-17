<?php

namespace Meva\Entities\Search;

/**
 * The reading pages, as things that can be found.
 *
 * Somebody who types "vracanje novca" or "koliko traje pakovanje" into the
 * search box is asking a question, not looking for a jar, and until now the
 * box had nothing to give them. These are the answers already written on the
 * storefront's own pages, word for word, each with the page and the anchor it
 * lives at so the result goes straight to it.
 *
 * They are written here rather than read from the storefront because the
 * storefront is a JavaScript bundle this application cannot read. When one of
 * those pages changes, this changes with it.
 *
 * @see resources -- src/views/help/*.vue in the storefront repository
 */
class SearchablePages
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Česta pitanja ────────────────────────────────────────────
            self::faq('Koliko traje jedno pakovanje?', 'Šampon od 200 ml traje otprilike dva meseca uz pranje svaki drugi dan. Losion od 100 ml, uz svakodnevnu upotrebu, oko šest nedelja.'),
            self::faq('Kada se vide prvi rezultati?', 'Kod seboreje i peruti obično posle deset do četrnaest dana redovne upotrebe. Kod psorijaze i ekcema sporije, računajte na tri do četiri nedelje.'),
            self::faq('Mogu li da koristim uz terapiju koju mi je propisao lekar?', 'Naši preparati su kozmetika, ne lek, i ne zamenjuju terapiju. Ako ste na lokalnoj terapiji za kožu, pitajte svog dermatologa.'),
            self::faq('Ima li sulfata, parabena ili silikona?', 'Nema. Ceo sastav je ispisan punim imenom na svakoj etiketi i na stranici proizvoda.'),
            self::faq('Da li je bezbedno u trudnoći?', 'Većina preparata jeste, ali neki sadrže eterična ulja koja se u trudnoći izbegavaju. Pišite nam koji vas zanima i reći ćemo tačno.'),
            self::faq('Kako se preparati čuvaju?', 'Na sobnoj temperaturi, van direktnog sunca. Ne treba ih držati u frižideru. Rok upotrebe je odštampan na pakovanju.'),
            self::faq('Moram li da otvorim nalog?', 'Ne. Možete poručiti kao gost — potrebni su samo ime, adresa i telefon.'),
            self::faq('Kako znam da je porudžbina primljena?', 'Odmah dobijete broj porudžbine na ekranu i na mejl, i možete ga uneti na stranici za praćenje.'),
            self::faq('Mogu li da promenim ili otkažem porudžbinu?', 'Dok paket nije predat kuriru, možete. Pozovite nas ili pišite što pre, sa brojem porudžbine.'),
            self::faq('Da li izdajete račun?', 'Da, račun ide u paketu. Za račun na firmu napišite podatke u napomeni pri poručivanju.'),

            // ── Dostava ──────────────────────────────────────────────────
            self::page('delivery', 'Dostava', 'Cena dostave', 'Besplatna — za svaku porudžbinu, u celoj Srbiji, bez minimalnog iznosa.'),
            self::page('delivery', 'Dostava', 'Plaćanje pouzećem', 'Plaćate kuriru kad paket stigne. Ništa se ne plaća unapred i ne čuvamo podatke o kartici.'),
            self::page('delivery', 'Dostava', 'Rok isporuke', 'Zavisi od kurirske službe i vašeg mesta. Kurir vas pozove pre isporuke.'),
            self::page('delivery', 'Dostava', 'Dostava u inostranstvo', 'Šaljemo i u Crnu Goru, Bosnu i Hercegovinu i zemlje EU. Pišite nam pre porudžbine da dogovorimo dostavu.'),
            self::page('delivery', 'Dostava', 'Praćenje porudžbine', 'Broj porudžbine dobijete odmah i možete ga uneti na stranici za praćenje u bilo kom trenutku.'),

            // ── Povrat i reklamacije ─────────────────────────────────────
            self::page('returns', 'Povrat i reklamacije', 'Odustajanje od kupovine', 'Imate 14 dana od prijema paketa da odustanete, bez objašnjenja. Proizvod treba da bude neotvoren; troškove vraćanja snosi kupac.'),
            self::page('returns', 'Povrat i reklamacije', 'Reklamacija', 'Ako je proizvod stigao oštećen ili pogrešan, ne plaćate ništa. Odgovaramo u roku od 8 dana, pa šaljemo zamenu ili vraćamo novac.'),
            self::page('returns', 'Povrat i reklamacije', 'Vraćanje novca', 'Novac vraćamo na tekući račun, najkasnije 14 dana od dana kada roba stigne nazad kod nas.'),

            // ── Priča ────────────────────────────────────────────────────
            self::page('story', 'Naša priča', 'Ko pravi Mevu', 'Ručno rađena prirodna kozmetika iz Novog Pazara, od 2010. Preko 90.000 porudžbina do sada.'),
            self::page('story', 'Naša priča', 'Ispitivanje u laboratoriji', 'Preparate ispituje Institut za javno zdravlje Vojvodine, a sastav analizira Superlab.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function faq(string $question, string $answer): array
    {
        return [
            'kind' => 'faq',
            'route' => 'faq',
            'page' => 'Česta pitanja',
            'title' => $question,
            'question' => $question,
            'answer' => $answer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function page(string $route, string $page, string $title, string $answer): array
    {
        return [
            'kind' => 'page',
            'route' => $route,
            'page' => $page,
            'title' => $title,
            'question' => $title,
            'answer' => $answer,
        ];
    }
}
