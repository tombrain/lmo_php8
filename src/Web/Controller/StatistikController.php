<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Web\Controller;

use Closure;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Liga\Tabelle;
use Lmo\Liga\Tabellenzeile;
use Lmo\Web\Darstellung;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Statistikdatei;
use Lmo\Web\Vorlagen;

/**
 * Die Statistik einer Liga (action=stats, nicht im Pokal): Vergleich zweier Mannschaften (Chance,
 * Platz, Punkte, Tore, Siege, Serien), ihr Restprogramm und darunter die Statistik der Saison aus
 * der Statistikdatei. Vorlage statistik.html.twig.
 *
 * Parameter der Anfrage:
 *  - stat1, stat2  die beiden Mannschaften (ohne: die der Fieberkurve, kurve1/kurve2); stat2 ohne
 *                  stat1 rueckt an die erste Stelle
 *  - tabtype       Tabellenart fuer den Vergleich (wie auf der Tabellenseite)
 */
final class StatistikController implements Controller
{
    /** @var (Closure(Liga, string, array<int|string, string|array<int, string>>): void)|null */
    private ?Closure $statistikErzeugen;

    /**
     * @param (Closure(Liga, string, array<int|string, string|array<int, string>>): void)|null $statistikErzeugen
     *        schreibt die Statistikdatei einer Liga neu (Liga, Ligadatei, Sprachtexte wie im alten Code;
     *        lmo-zustat.php); null: nie
     */
    public function __construct(?Closure $statistikErzeugen = null)
    {
        $this->statistikErzeugen = $statistikErzeugen;
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && !$kontext->liga->liga->istPokal() && $kontext->action == 'stats';
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        // die Liga kann die Statistik abschalten
        if (!$e->mitLigastatistik()) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $erste = $anfrage->gesetzt('stat1') ? $anfrage->zahl('stat1') : $e->fieberkurveErste();
        $zweite = $anfrage->gesetzt('stat2') ? $anfrage->zahl('stat2') : $e->fieberkurveZweite();
        if ($erste == 0 && $zweite != 0) {
            $erste = $zweite;
            $zweite = 0;
        }

        $mannschaften = [];
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            $mannschaften[] = [
                'nr' => $nr,
                'name' => $mannschaft->anzeigename(),
                'kurz' => $mannschaft->kurzname(),
                'wappen' => $kontext->darstellung->wappen($geladen->datei, $mannschaft->anzeigename(), " alt=''"),
            ];
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'statistik.html.twig', [
            'text' => $kontext->text,
            'link' => $kontext->adresse . '?action=stats&amp;file=' . $geladen->datei . '&amp;stat1=',
            'erste' => $erste,
            'zweite' => $zweite,
            'mannschaften' => $mannschaften,
            'vergleich' => $erste != 0 ? $this->vergleich($kontext, $erste, $zweite) : null,
            'saison' => $kontext->konfiguration->schalter('einzustats') ? $this->saison($kontext) : null,
        ]));
    }

    /** @return array<string, mixed> Kopf, Zeilen und Restprogramm des Vergleichs */
    private function vergleich(Kontext $kontext, int $erste, int $zweite): array
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        $text = $kontext->text;
        $mitZweiter = $zweite > 0 && $erste != $zweite;

        // die Tabelle der ganzen Saison und die Zeilen der beiden gewaehlten Mannschaften
        $tabelle = Tabelle::fuerAnsicht($liga, $kontext->anfrage->zahl('tabtype'), $e->spieltage());
        $zeile1 = $tabelle->zeilen[$erste] ?? new Tabellenzeile(0);
        $zeile2 = $tabelle->zeilen[$zweite] ?? new Tabellenzeile(0);
        // Platz in der Tabelle; fuehrt die Liga ein Handicap, gilt in der Gesamttabelle dessen Reihenfolge
        $plaetze = $tabelle->reihenfolge;
        if ($e->mitHandicap() && $kontext->anfrage->zahl('tabtype') == 0) {
            $runde = $kontext->anfrage->hat('st') || $kontext->anfrage->hat('endtab') ? $tabelle->bis : $e->aktuellerSpieltag();
            $plaetze = $tabelle->reihenfolgeNachHandicap(isset($liga->spieltage[$runde]) ? ($liga->spieltage[$runde]->werte['HS'] ?? '0') : '0');
        }
        $platz = static function (Tabellenzeile $zeile) use ($plaetze): string {
            $x = array_search($zeile->mannschaft, $plaetze, true);

            return $x === false ? '' : (string) ($x + 1);
        };

        $pf = $e->punktfaktor();
        $tf = $e->torfaktor();
        $f = static fn (int|float|string $wert, int $faktor): string => (string) Darstellung::mitFaktor($wert, $faktor);
        $prozent = static fn (int $anzahl, int $spiele): string => $spiele ? $anzahl . ' (' . number_format($anzahl * 100 / $spiele, 2, ',', '.') . '%)' : '';
        $jeSpiel = static fn (Tabellenzeile $z): string => $z->spiele ? $f(number_format($z->punkte / $z->spiele, 2), $pf) : '';
        $toreJeSpiel = static fn (Tabellenzeile $z): string => $z->spiele
            ? number_format((float) Darstellung::mitFaktor($z->tore, $tf) / $z->spiele, 2) . ':' . number_format((float) Darstellung::mitFaktor($z->gegentore, $tf) / $z->spiele, 2)
            : '';
        // je Zeile: links, Ueberschrift, rechts als ganze Zelle (null: ohne Zelle), Ueberschrift oben
        $zeile = static fn (string $links, string $titel, ?string $rechts, bool $oben = false): array => [
            'links' => $links, 'titel' => $titel, 'rechts' => $mitZweiter && $rechts !== null ? '<td align="left">' . $rechts . '</td>' : null, 'oben' => $oben,
        ];
        $zeilen = [];
        if ($mitZweiter) {
            [$chance1, $chance2] = self::chancen($zeile1, $zeile2);
            $zeilen[] = $zeile($chance1 . '%', $text[60], $chance2 . '%');
        }
        $zeilen[] = $zeile($platz($zeile1), $text[61], $platz($zeile2));
        // Minuspunkte zeigte die Seite nie an (der Ausdruck dafuer hatte kein echo)
        $zeilen[] = $zeile($f($zeile1->punkte, $pf), $text[37], $f($zeile2->punkte, $pf));
        $zeilen[] = $zeile((string) $zeile1->spiele, $text[63], (string) $zeile2->spiele);
        // ohne Spiele der zweiten Mannschaft steht hier wie bisher nur das Ende ihrer Zelle
        $jeSpielZeile = $zeile($jeSpiel($zeile1), $text[37] . $text[64], $jeSpiel($zeile2));
        if ($mitZweiter && !$zeile2->spiele) {
            $jeSpielZeile['rechts'] = '</td>';
        }
        $zeilen[] = $jeSpielZeile;
        $zeilen[] = $zeile($f($zeile1->tore, $tf) . ':' . $f($zeile1->gegentore, $tf), $text[38], $f($zeile2->tore, $tf) . ':' . $f($zeile2->gegentore, $tf));
        $zeilen[] = $zeile($toreJeSpiel($zeile1), $text[38] . $text[64], $toreJeSpiel($zeile2));
        $zeilen[] = $zeile($prozent($zeile1->siege, $zeile1->spiele), $text[67], $prozent($zeile2->siege, $zeile2->spiele));
        $zeilen[] = $zeile($zeile1->hoechsterSieg->alsText($tf, $text[72], $text[73], $text[74]), $text[68], $zeile2->hoechsterSieg->alsText($tf, $text[72], $text[73], $text[74]), true);
        $zeilen[] = $zeile($prozent($zeile1->niederlagen, $zeile1->spiele), $text[69], $prozent($zeile2->niederlagen, $zeile2->spiele));
        $zeilen[] = $zeile($zeile1->hoechsteNiederlage->alsText($tf, $text[72], $text[73], $text[74]), $text[70], $zeile2->hoechsteNiederlage->alsText($tf, $text[72], $text[73], $text[74]), true);
        $zeilen[] = $zeile(self::serie($zeile1, $text), $text[71], self::serie($zeile2, $text), true);

        $name = static fn (int $nr): string => $nr == 0 ? '___' : $liga->mannschaftsname($nr);

        return [
            'name1' => $name($erste),
            'name2' => $mitZweiter ? $name($zweite) : null,
            'wappen1' => $kontext->darstellung->wappen($geladen->datei, $name($erste), " alt=''"),
            'wappen2' => $mitZweiter ? $kontext->darstellung->wappen($geladen->datei, $name($zweite), " alt=''") : null,
            'zeilen' => $zeilen,
            'restprogramm' => $this->restprogramm($liga, $text, $erste, $zweite),
        ];
    }

    /**
     * Die Chance jeder Mannschaft gegen die andere in Prozent, aus Sieg- und Niederlagenquote und
     * den Toren je Spiel; "k.A.", solange eine noch nicht gespielt hat.
     *
     * @return array{string, string}
     */
    private static function chancen(Tabellenzeile $a, Tabellenzeile $b): array
    {
        if (empty($a->spiele) || empty($b->spiele)) {
            return ['k.A.', 'k.A.'];
        }
        $ax = (100 * $a->siege / $a->spiele) + (100 * $b->niederlagen / $b->spiele);
        $bx = (100 * $b->siege / $b->spiele) + (100 * $a->niederlagen / $a->spiele);
        $cx = ($a->tore / $a->spiele) + ($b->gegentore / $b->spiele);
        $dx = ($b->tore / $b->spiele) + ($a->gegentore / $a->spiele);
        $ex = $ax + $bx;
        $fx = $cx + $dx;
        if (!($ex > 0) || !($fx > 0)) {
            return ['k.A.', 'k.A.'];
        }
        $ax = round(10000 * $ax / $ex);
        $bx = round(10000 * $bx / $ex);
        $cx = round(10000 * $cx / $fx);
        $dx = round(10000 * $dx / $fx);

        return [number_format(($ax + $cx) / 200, 2, ',', '.'), number_format(($bx + $dx) / 200, 2, ',', '.')];
    }

    /**
     * Die laufende Serie: Siege und ungeschlagen, Niederlagen und sieglos, sonst die laengere von
     * ungeschlagen und sieglos.
     *
     * @param array<int, string> $text
     */
    private static function serie(Tabellenzeile $z, array $text): string
    {
        if ($z->serieSiege > 0) {
            return $z->serieSiege . ' ' . $text[474] . '<br>' . $z->serieUngeschlagen . ' ' . $text[75];
        }
        if ($z->serieNiederlagen > 0) {
            return $z->serieNiederlagen . ' ' . $text[475] . '<br>' . $z->serieSieglos . ' ' . $text[76];
        }
        if ($z->serieUngeschlagen >= $z->serieSieglos) {
            return $z->serieUngeschlagen . ' ' . $text[75];
        }

        return $z->serieSieglos . ' ' . $text[76];
    }

    /**
     * Das Restprogramm der beiden Mannschaften: ihre offenen Spiele mit dem Tabellenplatz des
     * Gegners und die Punkte, die ihre Gegner bisher geholt und abgegeben haben (bisher ueber die
     * Classlib gerechnet: Gesamttabelle aller Spieltage, Minuspunkte wie bei 2-Punkte-Wertung).
     *
     * @param array<int, string> $text
     * @return array<string, mixed>|null null ohne gueltige erste Mannschaft
     */
    private function restprogramm(Liga $liga, array $text, int $erste, int $zweite): ?array
    {
        $anzahl = $liga->einstellungen()->mannschaften();
        if ($erste <= 0 || $erste > $anzahl) {
            return null;
        }
        $tabelle = self::restTabelle($liga);
        $mitZweiter = $erste != $zweite && $zweite > 0 && $zweite <= $anzahl;
        $a = self::offeneSpiele($liga, $tabelle, $erste);
        $b = $mitZweiter ? self::offeneSpiele($liga, $tabelle, $zweite) : null;
        $html = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);

        $linksHtml = '';
        foreach ($a['spiele'] as [$heim, $gegner, $pos]) {
            $linksHtml .= ($heim ? $text[73] : $text[74]) . '&nbsp;' . $html($gegner) . '&nbsp;(' . $pos . '.)<br>';
        }
        $rechtsHtml = '';
        foreach ($b['spiele'] ?? [] as [$heim, $gegner, $pos]) {
            $rechtsHtml .= '(' . $pos . '.)&nbsp;' . $html($gegner) . '&nbsp;' . ($heim ? $text[73] : $text[74]) . '<br>';
        }

        $vergleich = null;
        if ($b !== null) {
            $da = $a['plus'] - $a['minus'];
            $db = $b['plus'] - $b['minus'];
            [$textA, $textB] = $da < $db ? [$text[4025], $text[4026]] : ($da > $db ? [$text[4026], $text[4025]] : [$text[4024], $text[4024]]);
            $vergleich = [
                'links' => self::restWert($a['plus'], $a['minus']),
                'rechts' => self::restWert($b['plus'], $b['minus']),
                'text_links' => $textA,
                'text_rechts' => $textB,
            ];
        }

        return ['links' => $linksHtml, 'rechts' => $rechtsHtml, 'vergleich' => $vergleich];
    }

    /**
     * Die Gesamttabelle fuer das Restprogramm wie bisher in der Classlib (liga::calcTable): alle
     * Spieltage, Minuspunkte auch fuer Ligen ohne sie (dann wie bei 2-Punkte-Wertung, ohne
     * Strafminuspunkte); fuehrt die Liga ein Handicap, gilt das des aktuellen Spieltags.
     *
     * @return list<array{nr: int, punkte: int, minus: int}> vom ersten zum letzten Platz
     */
    private static function restTabelle(Liga $liga): array
    {
        $e = $liga->einstellungen();
        $tabelle = Tabelle::berechne($liga, Tabelle::GESAMT, 1, $e->spieltage());
        $minus = $tabelle;
        if (!$e->mitMinuspunkten()) {
            $kopie = unserialize(serialize($liga));
            assert($kopie instanceof Liga);
            $kopie->optionen['MinusPoints'] = '2';
            foreach ($kopie->mannschaften as $mannschaft) {
                unset($mannschaft->werte['SM']);
            }
            $minus = Tabelle::berechne($kopie, Tabelle::GESAMT, 1, $e->spieltage());
        }
        $reihenfolge = $tabelle->reihenfolge;
        if ($e->mitHandicap()) {
            $runde = max(1, $e->aktuellerSpieltag());
            $reihenfolge = $tabelle->reihenfolgeNachHandicap(isset($liga->spieltage[$runde]) ? (string) ($liga->spieltage[$runde]->werte['HS'] ?? '') : '');
        }
        $zeilen = [];
        foreach ($reihenfolge as $nr) {
            $zeilen[] = ['nr' => $nr, 'punkte' => $tabelle->zeilen[$nr]->punkte, 'minus' => ($minus->zeilen[$nr] ?? $tabelle->zeilen[$nr])->minuspunkte];
        }

        return $zeilen;
    }

    /**
     * Die offenen Spiele einer Mannschaft nach Anstoss (ohne Anstoss zuerst), dann nach Spieltag,
     * mit dem Platz des Gegners, und die Summe der Punkte und Minuspunkte der Gegner.
     *
     * @param list<array{nr: int, punkte: int, minus: int}> $tabelle
     * @return array{spiele: list<array{bool, string, int}>, plus: int, minus: int} je Spiel: Heimspiel, Gegner, Platz
     */
    private static function offeneSpiele(Liga $liga, array $tabelle, int $nr): array
    {
        $offen = [];
        foreach ($liga->alleSpiele() as $spiel) {
            if (($spiel->heim == $nr || $spiel->gast == $nr) && self::offen($spiel)) {
                $offen[] = $spiel;
            }
        }
        usort($offen, static fn (Spiel $x, Spiel $y): int => [$x->anstoss ?? -1, $x->spieltag] <=> [$y->anstoss ?? -1, $y->spieltag]);

        $spiele = [];
        $plus = $minus = 0;
        foreach ($offen as $spiel) {
            $heim = $spiel->heim == $nr;
            $gegner = $heim ? $spiel->gast : $spiel->heim;
            // Platz des Gegners; steht er nicht in der Tabelle, zaehlt wie bisher die letzte Zeile
            $pos = 1;
            $zeile = end($tabelle) ?: ['punkte' => 0, 'minus' => 0];
            foreach ($tabelle as $kandidat) {
                if ($kandidat['nr'] == $gegner) {
                    $zeile = $kandidat;
                    break;
                }
                $pos++;
            }
            $plus += $zeile['punkte'];
            $minus += $zeile['minus'];
            $spiele[] = [$heim, $liga->mannschaftsname($gegner), $pos];
        }

        return ['spiele' => $spiele, 'plus' => $plus, 'minus' => $minus];
    }

    /** Punkte und Minuspunkte der Gegner im Restprogramm, mit der Differenz: "12:8 (+4)". */
    private static function restWert(int $plus, int $minus): string
    {
        $differenz = $plus - $minus;

        return $plus . ':' . $minus . ' (' . ($differenz > 0 ? '+' . $differenz : $differenz) . ')';
    }

    /** Noch nicht gespielt und nicht am gruenen Tisch gewertet (Classlib: partie::valuateGame() == -1). */
    private static function offen(Spiel $spiel): bool
    {
        return $spiel->wertung == 0 && ($spiel->toreHeim === null || $spiel->toreGast === null);
    }

    /**
     * Die Statistik der Saison aus der Statistikdatei; fehlt sie oder ist sie aelter als die
     * Ligadatei, wird sie vorher neu geschrieben.
     *
     * @return array<string, mixed>
     */
    private function saison(Kontext $kontext): array
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $pfad = $kontext->pfade->ausgabe($kontext->konfiguration, str_replace('.l98', '.l98.php', basename($geladen->datei)));
        $ligadatei = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner() . $geladen->datei;
        if ($this->statistikErzeugen !== null && (!is_file($pfad) || filemtime($pfad) < filemtime($ligadatei))) {
            ($this->statistikErzeugen)($geladen->liga, $geladen->datei, $kontext->text + $kontext->weitereTexte);
            clearstatcache();
        }
        $statistik = Statistikdatei::lies($pfad) ?? new Statistikdatei([]);
        $tf = $geladen->liga->einstellungen()->torfaktor();
        // Tore mit dem Faktor der Liga; fehlt der Wert in der Datei: leer
        $f = static fn (string $name): string => $statistik->hat($name) ? (string) Darstellung::mitFaktor($statistik->zahl($name), $tf) : '';
        $html = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);

        $gesamt = (int) ($statistik->zahl('gzusieg1') + $statistik->zahl('gzusieg2') + $statistik->zahl('gzuunent') + $statistik->zahl('gbeide'));
        $anteil = static fn (string $name): string => $gesamt > 0 ? ' (' . round($statistik->zahl($name) / $gesamt * 100) . '%)' : '';
        $gdstore = $statistik->zahl('gdstore');
        $heimAnteil = $gdstore > 0 ? round($statistik->zahl('dsheimtore') / $gdstore * 100) : null;

        // die Serien: Name je Zeile, wie bisher mit <br> (verloren: <br />)
        $serien = null;
        if ($statistik->hat('akt_gewonnen')) {
            $namen = static fn (string $name, bool $xhtml = false): string => nl2br($html($statistik->text($name)), $xhtml);
            $serien = [];
            foreach ([['gewonnen', 4028, 'lmoTabelleMeister'], ['ungeschlagen', 4029, 'lmoTabelleUefa'], ['unentschieden', 200, ''], ['sieglos', 4031, 'lmoTabelleRelegation'], ['verloren', 4032, 'lmoTabelleAbsteiger']] as [$art, $titel, $klasse]) {
                $serien[] = [
                    'art' => $art,
                    'titel' => $kontext->text[$titel],
                    'klasse' => $klasse,
                    'akt' => $statistik->text('akt_' . $art),
                    'max' => $statistik->text('max_' . $art),
                    'team_akt' => $namen('team_akt_' . $art, $art === 'verloren'),
                    'team_max' => $namen('team_max_' . $art, $art === 'verloren'),
                ];
            }
        }

        return [
            'gesamt' => $gesamt,
            'heimsiege' => $statistik->text('gzusieg1'),
            'heimsiege_anteil' => $anteil('gzusieg1'),
            'unentschieden' => $statistik->text('gzuunent'),
            'unentschieden_anteil' => $anteil('gzuunent'),
            'auswaertssiege' => $statistik->text('gzusieg2'),
            'auswaertssiege_anteil' => $anteil('gzusieg2'),
            'beide' => (int) $statistik->zahl('gbeide'),
            'beide_anteil' => $anteil('gbeide'),
            'tore' => $f('gzutore'),
            'tore_je_spiel' => $f('gdstore'),
            'heimtore' => $f('gheimtore'),
            'heimtore_anteil' => $heimAnteil !== null ? ' (' . $heimAnteil . '% ' . $kontext->text[517] . $f('dsheimtore') . ')' : '',
            'gasttore' => $f('ggasttore'),
            'gasttore_anteil' => $heimAnteil !== null ? ' (' . (100 - $heimAnteil) . '% ' . $kontext->text[517] . $f('dsgasttore') . ')' : '',
            // hoechster Heimsieg, hoechster Auswaertssieg, torreichstes Spiel: Bedingung, je Spiel
            // Heim, Gast, Tore Heim, Tore Gast, Spieltag, und wie viele es mit dem Ergebnis noch gab
            'heimsieg' => self::rekord($statistik, $tf, 'hheimsiegtor', ['hheimsieg', 'hgastsieg', 'hheimsiegtor', 'hgastsiegtor', 'spieltagflag'], 'hheimsiegtor1', ['hheimsieg1', 'hgastsieg1', 'hheimsiegtor1', 'hgastsiegtor1', 'spieltagflag1'], 'counteranz'),
            'auswaertssieg' => self::rekord($statistik, $tf, 'agastsiegtor', ['aheimsieg', 'agastsieg', 'aheimsiegtor', 'agastsiegtor', 'spieltagflag2'], 'agastsiegtor1', ['aheimsieg1', 'agastsieg1', 'aheimsiegtor1', 'agastsiegtor1', 'spieltagflag3'], 'counteranz1'),
            'torreich' => self::rekord($statistik, $tf, 'spieltagflag4', ['htorreichm1', 'htorreichm2', 'htorreicht1', 'htorreicht2', 'spieltagflag4'], 'spieltagflag5', ['htorreichm3', 'htorreichm4', 'htorreicht3', 'htorreicht4', 'spieltagflag5'], 'counteranz5'),
            'serien' => $serien,
        ];
    }

    /**
     * Ein Rekordspiel (hoechster Heimsieg, Auswaertssieg, torreichstes Spiel) und das zweite, wenn
     * es eins gibt; null ohne. Die Namen sind die der Variablen in der Statistikdatei.
     *
     * @param array{string, string, string, string, string} $erstes Heim, Gast, Tore Heim, Tore Gast, Spieltag
     * @param array{string, string, string, string, string} $zweites dasselbe fuer das zweite Spiel
     * @param string $weitere Anzahl der Spiele mit diesem Ergebnis
     * @return array<string, mixed>|null
     */
    private static function rekord(Statistikdatei $s, int $tf, string $bedingung, array $erstes, string $bedingung2, array $zweites, string $weitere): ?array
    {
        if (!($s->zahl($bedingung) > 0)) {
            return null;
        }
        $zweitesSpiel = $s->zahl($bedingung2) > 0 ? self::rekordSpiel($s, $tf, $zweites) : null;

        return [
            'erstes' => self::rekordSpiel($s, $tf, $erstes),
            'zweites' => $zweitesSpiel,
            'weitere' => $zweitesSpiel !== null && $s->zahl($weitere) > 2 ? $s->zahl($weitere) - 2 : null,
        ];
    }

    /**
     * Ein Rekordspiel aus der Statistikdatei; die Tore mit dem Faktor der Liga, fehlende leer.
     *
     * @param array{string, string, string, string, string} $namen Heim, Gast, Tore Heim, Tore Gast, Spieltag
     * @return array{heim: string, gast: string, tore: string, spieltag: string}
     */
    private static function rekordSpiel(Statistikdatei $s, int $tf, array $namen): array
    {
        $tore = static fn (string $name): string => $s->hat($name) ? (string) Darstellung::mitFaktor($s->zahl($name), $tf) : '';

        return [
            'heim' => $s->text($namen[0]),
            'gast' => $s->text($namen[1]),
            'tore' => $tore($namen[2]) . ':' . $tore($namen[3]),
            'spieltag' => $s->text($namen[4]),
        ];
    }
}
