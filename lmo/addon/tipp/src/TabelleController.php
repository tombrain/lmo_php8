<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Liga\Rohdaten\Mannschaft;
use Lmo\Liga\Rohdaten\Spieltag;
use Lmo\Liga\Spiel;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Seiten\TabellenSeite;
use Lmo\Web\Vorlagen;

/**
 * Die Tabelle nach den Tipps (todo=tabelle): so saehe die Tabelle der Liga mit den Tipps eines
 * Tippers aus (nick, ohne den eigenen) oder mit den Tipps aller (nick leer), gesamt, heim,
 * auswaerts, Hin- oder Rueckrunde. Darunter die Wertung nach Vereinen. Auch fuer nicht angemeldete
 * Besucher. Bis 4.5 lmo-tipptabelle.php mit lmo-tippopenfileall.php, lmo-tippcalctable.php,
 * lmo-tippcalctable1.php und lmo-tippcalcwertverein.php.
 */
final class TabelleController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Tipps */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == 'tabelle'
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->liga !== null;
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite($this->rahmen->auchFuerGaeste(
            $kontext,
            fn (bool $angemeldet): string => $kontext->liga !== null && ($kontext->konfiguration->addon('tipp')['tipptabelle1'] ?? '') == 1 ? $this->tabelle($kontext, $kontext->liga, $angemeldet) : ''
        ));
    }

    private function tabelle(Kontext $kontext, GeladeneLiga $geladen, bool $angemeldet): string
    {
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $anfrage = $kontext->anfrage;
        $datei = $geladen->datei;
        $tipps = $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/');
        $ergebnismodus = ($cfg['tippmodus'] ?? '') == 1;
        $st = $anfrage->gesetzt('st') ? (int) $anfrage->text('st') : $e->aktuellerSpieltag();
        $art = $anfrage->zahl('tabtype');
        $sitzungsnick = $kontext->sitzung->text('lmotippername');
        $nick = $anfrage->gesetzt('nick') ? $anfrage->text('nick') : $sitzungsnick;
        $bis = $st;
        $stand = '';
        if ($bis == 0) {
            $bis = $e->spieltage();
        } else {
            $stand = $bis . '. ' . ($text[2] ?? '');
        }

        // welche Spiele noch nicht zaehlen: bis zum Tippschluss (einsichterst=1), bis zum Ergebnis (2)
        $tippzeit = new Tippzeit((int) ($cfg['tippBis'] ?? 0), ($cfg['tippohne'] ?? '') == 1, $kontext->konfiguration->wert('deftime'), (int) ($cfg['imvorraus'] ?? -1));
        $einsichterst = (int) ($cfg['einsichterst'] ?? 0);
        $verborgen = static function (Spiel $spiel, int $j) use ($einsichterst, $tippzeit, $liga, $st, $e): bool {
            $tag = $liga->spieltage[$j + 1] ?? new Spieltag();

            return match ($einsichterst) {
                1 => $tippzeit->aenderbar((int) $spiel->anstoss, $tag->ersterTagText(), $tag->letzterTagText(), $st, $e->aktuellerSpieltag(), 0),
                2 => !$spiel->istGespielt(),
                default => false,
            };
        };

        $meldung = '';
        $vorher = null;
        if ($nick != '') {
            $tipper = [$this->tipps($tipps . substr($datei, 0, -4) . '_' . $nick . '.tip', $nick)];
            if ($bis > 1 && $art == 0 && $stand != '') {
                $vorher = Tipptabelle::rechne($liga, $tipper, $verborgen, $art, $bis - 1);
            }
        } else {
            $tipper = [];
            if (($cfg['tipptabelle'] ?? '') == 1) {
                $stand = '';
                foreach (self::dateien($tipps, substr($datei, 0, -4), '.tip') as $pfad) {
                    $tipper[] = Tippdatei::lies($pfad)->alleRunden();
                }
                if ($tipper === []) {
                    // keine Tipper
                    $meldung = Tipprahmen::meldung($kontext, $t[17] ?? '', true);
                }
            }
            if ($tipper === []) {
                // weder ein Tipper noch die Tipps aller: keine Tabelle (bis 4.5 eine Tabelle voller Nullen
                // mit Warnungen; wie upstream 7c1228b)
                return $meldung;
            }
        }
        $tabelle = Tipptabelle::rechne($liga, $tipper, $verborgen, $art, $bis);
        $bis = $tabelle->bis;

        $nickLink = rawurlencode($nick);
        $basis = $kontext->adresse . '?action=tipp&amp;todo=tabelle&amp;file=' . $datei;
        // ohne Stand wie bis 4.5 mit "&ampnick"
        $artLink = $stand == '' ? $basis . '&ampnick=' . $nickLink . '&amp;tabtype=' : $basis . '&amp;endtab=' . $bis . '&amp;nick=' . $nickLink . '&amp;tabtype=';
        $spieltagLink = $basis . '&amp;tabtype=' . $art . '&amp;nick=' . $nickLink . '&amp;st=';
        $tipperLink = $basis . '&amp;endtab=&amp;nick=';

        $seite = new TabellenSeite($liga, $text, $kontext->darstellung, $kontext->pfade->vorlagen, $kontext->pfade->bilder);
        $zeilen = [];
        $ab = 1;
        foreach ($tabelle->reihenfolge as $index => $nr) {
            $platz = $index + 1;
            $mannschaft = $liga->mannschaften[$nr] ?? new Mannschaft();
            $name = $mannschaft->anzeigename();
            $url = $mannschaft->werte['URL'] ?? '';
            [$klasse, $ab] = $seite->farbe($platz, $art, $ab);
            $w = $tabelle->werte[$nr] ?? null;
            $zeilen[] = [
                'platz' => $platz,
                'klasse' => $klasse,
                'fett' => $nr == $e->lieblingsmannschaft(),
                'pfeil' => $vorher !== null ? self::pfeil($platz, $vorher->platz($nr)) : 0,
                'symbol' => $kontext->darstellung->wappen($datei, $name, ' alt=""'),
                'name' => $name,
                'url' => $url != '' && $e->mitMannschaftslinks() ? $url : null,
                'notiz' => $seite->notiz($mannschaft, $datei),
                'spiele' => $w['spiele'] ?? 0,
                'siege' => $w['siege'] ?? 0,
                'unent' => $w['unent'] ?? 0,
                'nieder' => $w['nieder'] ?? 0,
                'punkte' => Darstellung::mitFaktor($w['punkte'] ?? 0, $e->punktfaktor()),
                'negativ' => Darstellung::mitFaktor($w['negativ'] ?? 0, $e->punktfaktor()),
                'etore' => Darstellung::mitFaktor($w['etore'] ?? 0, $e->torfaktor()),
                'atore' => Darstellung::mitFaktor($w['atore'] ?? 0, $e->torfaktor()),
                'dtore' => Darstellung::mitFaktor($w['dtore'] ?? 0, $e->torfaktor()),
                'quote' => number_format(($w['quote'] ?? 0) / 100, 2, '.', ','),
            ];
        }

        $verein = $kontext->sitzung->text('lmotipperverein');
        $daten = [
            'text' => $text,
            't' => $t,
            'bilder' => $kontext->pfade->bilder,
            'nick' => $nick,
            'titel' => $angemeldet ? $sitzungsnick . ($verein != '' ? ' - ' . $verein : '') : ($t[158] ?? ''),
            'spieltagsmenue' => $nick != '' && $art != 3 && $art != 4 ? (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga, $st, $spieltagLink) : null,
            'ueberschrift' => $nick == $sitzungsnick && $nick != '' ? ($t[173] ?? '') : ($nick != '' ? ($t[181] ?? '') . ' ' . $nick : ($t[184] ?? '')),
            'arten' => $this->arten($text, $art, $artLink, $e->mitHinRueckrundentabelle()),
            'stand' => $stand,
            'art' => $art,
            'punkteAttr' => $e->mitMinuspunkten() ? ' colspan="3" align="center"' : ' align="right"',
            'minus' => $e->mitMinuspunkten(),
            'ohneUnentschieden' => $e->ohneUnentschieden(),
            'tabpkt' => $kontext->konfiguration->tabpkt(),
            'ergebnismodus' => $ergebnismodus,
            'zeilen' => $zeilen,
            'zurueck' => $stand != '' && $st > 1 ? $spieltagLink . ($st - 1) : null,
            'weiter' => $stand != '' && $st < $e->spieltage() ? $spieltagLink . ($st + 1) : null,
            'vereine' => ($cfg['wertverein'] ?? '') == 1 && $art == 0 ? $this->vereine($kontext, $geladen, $nick, $bis, $stand, $tipps, $ergebnismodus) : null,
            'meineTabelle' => $nick != $sitzungsnick && $sitzungsnick != '' ? $tipperLink . rawurlencode($sitzungsnick) : null,
            'alleTipper' => $nick != '' && ($cfg['tipptabelle'] ?? '') == 1 ? $tipperLink : null,
        ];

        return $meldung . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'tabelle.html.twig', $daten);
    }

    /**
     * Die Wertung nach Vereinen unter der Tabelle: fuer einen Tipper aus seiner Auswertung, ohne
     * Nick aus denen aller Tipper.
     *
     * @return array{meldungen: string, stand: string, zeilen: list<array<string, mixed>>}
     */
    private function vereine(Kontext $kontext, GeladeneLiga $geladen, string $nick, int $bis, string $stand, string $tipps, bool $ergebnismodus): array
    {
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        $ordner = $tipps . 'auswert/vereine/';
        $meldungen = '';
        $wertung = null;
        $vorher = null;
        if ($nick != '') {
            $pfad = $ordner . substr($geladen->datei, 0, -4) . '_' . $nick . '.ver';
            $vorherRechnen = $bis > 1 && $stand != '';
            if (self::sichererNick($nick) && is_file($pfad)) {
                $vorher = $vorherRechnen ? Vereinswertung::lies([$pfad], $bis - 1, $e->spieltage(), $ergebnismodus) : null;
                $wertung = Vereinswertung::lies([$pfad], $bis, $e->spieltage(), $ergebnismodus);
            } else {
                // wie bis 4.5 je Rechnung eine Meldung (mit Stand vom Spieltag davor zwei)
                $meldung = Tipprahmen::meldung($kontext, $kontext->texteVon('tipp')[17] ?? '', true);
                $meldungen = $vorherRechnen ? $meldung . $meldung : $meldung;
            }
        } elseif (($kontext->konfiguration->addon('tipp')['tipptabelle'] ?? '') == 1) {
            $stand = '';
            $wertung = Vereinswertung::lies(self::dateien($ordner, substr($geladen->datei, 0, -4), '.ver'), $bis, $e->spieltage(), $ergebnismodus);
        }

        $zeilen = [];
        if ($wertung !== null) {
            $erster = $wertung->reihenfolge[0] ?? 0;
            $pfeile = $bis > 1 && $stand != '' && ($wertung->werte[$erster]['punkte'] ?? 0) > 0;
            foreach ($wertung->reihenfolge as $index => $nr) {
                $w = $wertung->werte[$nr] ?? ['spiele' => 0, 'punkte' => 0];
                $zeilen[] = [
                    'platz' => $index + 1,
                    'fett' => $nr == $e->lieblingsmannschaft(),
                    'pfeil' => $pfeile && $vorher !== null ? self::pfeil($index + 1, $vorher->platz($nr)) : 0,
                    'name' => ($liga->mannschaften[$nr] ?? new Mannschaft())->anzeigename(),
                    'spiele' => $w['spiele'],
                    'punkte' => $w['punkte'],
                    'quote' => Vereinswertung::quote($w['punkte'], $w['spiele'], $ergebnismodus),
                ];
            }
        }

        return ['meldungen' => $meldungen, 'stand' => $stand, 'zeilen' => $zeilen];
    }

    /**
     * Auswahl der Tabellenart ueber der Tabelle: Gesamt, Heim, Auswaerts, dazu Hin- und Rueckrunde.
     *
     * @param array<int|string, mixed> $text
     * @return list<array{text: mixed, titel: mixed, link: string|null}>
     */
    private function arten(array $text, int $art, string $link, bool $hinRueck): array
    {
        $arten = [[0, 16, 27], [1, 41, 28], [2, 42, 29]];
        if ($hinRueck) {
            $arten[] = [4, 4003, 4003];
            $arten[] = [3, 4002, 4002];
        }
        $auswahl = [];
        foreach ($arten as [$nr, $name, $titel]) {
            $auswahl[] = ['text' => $text[$name] ?? '', 'titel' => $text[$titel] ?? '', 'link' => $art != $nr ? $link . $nr : null];
        }

        return $auswahl;
    }

    /**
     * Die Tipps eines Tippers; leer, wenn seine Datei fehlt (bis 4.5 brach die Seite dann ab) oder
     * der Nick aus dem Ordner fuehrt.
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function tipps(string $pfad, string $nick): array
    {
        return self::sichererNick($nick) ? Tippdatei::lies($pfad)->alleRunden() : [];
    }

    private static function sichererNick(string $nick): bool
    {
        return !str_contains($nick, '/') && !str_contains($nick, '\\') && !str_contains($nick, '..');
    }

    /**
     * Die Dateien einer Liga in einem Ordner (<liga>_<nick><endung>) in der Reihenfolge des Ordners.
     *
     * @return list<string>
     */
    private static function dateien(string $ordner, string $liga, string $endung): array
    {
        $dateien = [];
        $verz = @opendir($ordner);
        if ($verz === false) {
            return [];
        }
        while (($name = readdir($verz)) !== false) {
            $strich = strrpos($name, '_');
            if ($strich !== false && strtolower(substr($name, 0, $strich)) == strtolower($liga) && strtolower(substr($name, -4)) == $endung) {
                $dateien[] = $ordner . $name;
            }
        }
        closedir($verz);

        return $dateien;
    }

    /** Pfeil fuer die Veraenderung des Platzes: 1 hoch, 2 runter, 0 gleich. */
    private static function pfeil(int $jetzt, int $vorher): int
    {
        return $jetzt < $vorher ? 1 : ($jetzt > $vorher ? 2 : 0);
    }
}
