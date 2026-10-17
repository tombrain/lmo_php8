<?php

declare(strict_types=1);

namespace Lmo\Web\Controller;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Der Kalender einer Liga oder eines Pokals (action=cal): ein Monat mit den Spieltagen (bzw.
 * Runden) an ihren Tagen oder ein Jahr mit ihnen in ihren Monaten. Vorlage kalender.html.twig.
 *
 * Parameter der Anfrage:
 *  - cal  Zeitraum: "MMJJJJ" ein Monat, "JJJJ" ein Jahr (ohne: der Monat des ersten Spiels des
 *         gewaehlten Spieltags, ohne Anstosszeiten der aktuelle Monat)
 *  - st   gewaehlter Spieltag (ohne: der aktuelle der Liga)
 */
final class KalenderController implements Controller
{
    /** englische Monatsnamen fuer strtotime(), Index 1 bis 12 */
    private const MONATE = ['0', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /** @var int Zeitpunkt "jetzt" (fuer die Markierung von heute und den Link auf den aktuellen Monat) */
    private int $jetzt;

    /** @param int|null $jetzt Zeitpunkt "jetzt"; null: time() */
    public function __construct(?int $jetzt = null)
    {
        $this->jetzt = $jetzt ?? time();
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && $kontext->action == 'cal';
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        // die Liga kann den Kalender abschalten
        if (!$e->mitKalender()) {
            return new Seite('');
        }
        $text = $kontext->text;
        $anfrage = $kontext->anfrage;
        $kalenderLink = $kontext->adresse . '?action=cal&amp;file=' . $geladen->datei . '&amp;cal=';
        $spieltagLink = $kontext->adresse . '?action=results&amp;file=' . $geladen->datei . '&amp;st=';
        $me = self::MONATE;

        //Anzeigezeitraum festlegen
        $cal = $anfrage->gesetzt('cal') ? $anfrage->text('cal') : null;
        if ($cal !== null) {  //Zeitraum vorgegeben
            if (strlen($cal) > 4) {
                $basis = '1 ' . $me[intval(substr($cal, 0, 2))] . ' ' . substr($cal, 2);
                [$vorher, $dieser, $danach] = [strtotime($basis . ' -1 month'), strtotime($basis), strtotime($basis . ' +1 month')];
            } else {
                $basis = '1 ' . $me[1] . ' ' . substr($cal, 2);
                [$vorher, $dieser, $danach] = [strtotime($basis . ' -1 year'), strtotime($basis), strtotime($basis . ' +1 year')];
            }
        } else {
            // Anstosszeiten des gewaehlten Spieltags; Spiele ohne Anstoss zaehlen nicht
            $termine = [];
            foreach ($liga->spiele($anfrage->zahl('st', $e->aktuellerSpieltag())) as $spiel) {
                if ($liga->istPokal() ? $spiel->anstoss !== null : $spiel->anstoss > 0) {
                    $termine[] = $spiel->anstoss;
                }
            }
            if ($termine !== []) {
                $datum = explode('.', date('d.m.Y', (int) min($termine)));
                $basis = $datum[0] . ' ' . $me[intval($datum[1])] . ' ' . $datum[2];
                [$vorher, $dieser, $danach] = [strtotime($basis . ' -1 month'), strtotime($basis), strtotime($basis . ' +1 month')];
            } else {
                [$vorher, $dieser, $danach] = [strtotime('-1 month', $this->jetzt), $this->jetzt, strtotime('+1 month', $this->jetzt)];
            }
        }
        [$vorher, $dieser, $danach] = [(int) $vorher, (int) $dieser, (int) $danach];
        $cal ??= date('mY', $dieser);
        $monat = strlen($cal) > 4;

        //Datenformate generieren: $md ist der angezeigte Zeitraum, wie date() ihn fuer ein Spiel schreibt
        if ($monat) {
            $md = date('F Y', $dieser);
            $mj = ' ' . $me[intval(date('m', $dieser))] . ' ' . date('Y', $dieser);
            $eintraege = $this->eintraege($liga, $text, $spieltagLink, $md, 'F Y', 'd', 32);
        } else {
            $md = date('Y', $dieser);
            $mj = '';
            $eintraege = $this->eintraege($liga, $text, $spieltagLink, $md, 'Y', 'm', 13);
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'kalender.html.twig', [
            'text' => $text,
            'monat' => $monat,
            'link' => $kalenderLink,
            'zurueck' => date($monat ? 'mY' : 'Y', $vorher),
            'weiter' => date($monat ? 'mY' : 'Y', $danach),
            'heute' => date('mY', $this->jetzt),
            'jahr' => date('Y', $dieser),
            'titel' => $monat ? self::uebersetzt($md, $text) : $md,
            'wochen' => $monat ? $this->wochen($mj, $eintraege) : [],
            'monate' => $monat ? [] : $this->monate($eintraege),
        ]));
    }

    /**
     * Die Links auf die Spieltage je Tag (Monat) bzw. je Monat (Jahr) des Zeitraums $md.
     *
     * @param array<int|string, mixed> $text
     * @return array<int, string> Index Tag bzw. Monat => fertiges HTML
     */
    private function eintraege(Liga $liga, array $text, string $spieltagLink, string $md, string $regel, string $regel2, int $anzahl): array
    {
        $pokal = $liga->istPokal();
        $runden = $liga->einstellungen()->spieltage();
        $links = array_fill(0, $anzahl, '');
        // $j: Spieltag ab 0
        for ($j = 0; $j < $runden; $j++) {
            $titel = array_fill(0, $anzahl, '');
            if ($pokal) {
                //Bezeichnungen generieren: die letzten vier Runden heissen Finale, Halbfinale usw.
                $vomEnde = $runden - 1 - $j;
                $runde = $vomEnde <= 3 ? $text[374 - $vomEnde] : ($j + 1) . '. ' . $text[370];
                // die Paarungen der Runde und ihre Spiele; $n: Spiel ab 0
                foreach ($liga->paarungen($j + 1) as $paarung) {
                    foreach ($paarung->spiele as $n => $spiel) {
                        if ($spiel->anstoss !== null && date($regel, $spiel->anstoss) == $md) { //konkretes Spieldatum vorhanden
                            $a = intval(date($regel2, $spiel->anstoss));
                            if ($paarung->heim != 0 && $paarung->gast != 0) {
                                $titel[$a] = ($titel[$a] != '' ? $titel[$a] . ', &#10;' : $runde . ': &#10;')
                                    . self::html($paarung->heimMannschaft()->anzeigename()) . ' - ' . self::html($paarung->gastMannschaft()->anzeigename());
                                if (count($paarung->spiele) > 1) {
                                    $titel[$a] .= ' &#10;(' . ($n + 1) . '. ' . $text[375] . ')';
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($liga->spiele($j + 1) as $spiel) {
                    if ($spiel->anstoss > 0 && date($regel, $spiel->anstoss) == $md) { //konkretes Spieldatum vorhanden
                        $a = intval(date($regel2, $spiel->anstoss));
                        if ($spiel->heim != 0 && $spiel->gast != 0) {
                            $titel[$a] = ($titel[$a] != '' ? $titel[$a] . ', &#10;' : ($j + 1) . '. ' . $text[2] . ': &#10;')
                                . self::html($spiel->heimMannschaft()->anzeigename()) . ' - ' . self::html($spiel->gastMannschaft()->anzeigename());
                        }
                    }
                }
            }

            //Spieltagsdatum: ohne Spiele mit Anstoss im Zeitraum markieren erster bis letzter Tag den Spieltag
            $spieltag = $liga->spieltage[$j + 1] ?? null;
            $von = $spieltag?->ersterTag();
            $bis = $spieltag?->letzterTag();
            $ohneSpiele = array_filter($titel) === [];
            $zeitraum = ($j + 1) . '. ' . $text[2] . ' &#10;(' . $text[155] . ')';
            if ($von && $ohneSpiele && date($regel, $von) == $md) {  //Nur Von... vorhanden
                $a = intval(date($regel2, $von));
                $titel[$a] = $zeitraum;
                if ($bis && $bis > $von) { //Von ... bis ... vorhanden
                    for ($k = $a; $k <= intval(date($regel2, $bis)); $k++) {
                        $titel[$k] = $zeitraum;
                    }
                }
            }
            if ($bis && $ohneSpiele && date($regel, $bis) == $md) {  //Nur ...Bis vorhanden
                $titel[intval(date($regel2, $bis))] = $zeitraum;
            }

            //Links generieren
            for ($i = 0; $i < count($titel); $i++) {
                if (!empty($titel[$i])) {
                    $links[$i] = ($links[$i] ?? '') . '&nbsp;&nbsp;<a href="' . $spieltagLink . ($j + 1) . '" title="' . $titel[$i] . '">' . ($j + 1) . '. ' . $text[145] . '</a><br>';
                }
            }
        }

        return $links;
    }

    /**
     * Die Wochen eines Monats, Montag bis Sonntag; Tage vor dem 1. und nach dem letzten sind leer.
     *
     * @param string $mj " Monat Jahr" fuer strtotime()
     * @param array<int, string> $links Index Tag => Links auf die Spieltage
     * @return list<list<array{leer: bool, tag?: int, klasse?: string, links?: string}>>
     */
    private function wochen(string $mj, array $links): array
    {
        $erster = getdate((int) strtotime('1' . $mj));
        $heute = getdate($this->jetzt);
        $wochen = [];
        $woche = array_fill(0, (($erster['wday'] + 6) % 7), ['leer' => true]);
        for ($i = 1; $i <= 31; $i++) {
            $tag = getdate((int) strtotime($i . $mj));
            if ($tag['mon'] != $erster['mon']) {
                continue;
            }
            $wochentag = $tag['wday'] == 0 ? 7 : $tag['wday'];
            $klassen = [];
            if ($tag['mday'] == $heute['mday'] && $tag['mon'] == $heute['mon'] && $tag['year'] == $heute['year']) {
                $klassen[] = 'lmoBackMarkierung';
            }
            if ($wochentag >= 6) {
                $klassen[] = 'lmoFrontMarkierung';
            }
            $woche[] = ['leer' => false, 'tag' => $i, 'klasse' => implode(' ', $klassen), 'links' => $links[$i] ?? ''];
            if ($wochentag == 7) {
                $wochen[] = $woche;
                $woche = [];
            }
        }
        if ($woche !== []) {
            $wochen[] = array_pad($woche, 7, ['leer' => true]);
        }

        return $wochen;
    }

    /**
     * Die Monate eines Jahres in zwei Zeilen zu sechs.
     *
     * @param array<int, string> $links Index Monat => Links auf die Spieltage
     * @return list<list<array{nr: string, index: int, links: string}>> nr zweistellig fuer den Link
     */
    private function monate(array $links): array
    {
        $monate = [];
        for ($i = 1; $i <= 12; $i++) {
            $monate[] = ['nr' => sprintf('%02d', $i), 'index' => $i, 'links' => $links[$i] ?? ''];
        }

        return array_chunk($monate, 6);
    }

    /**
     * Englische Wochentags- und Monatsnamen (von date()) in der Sprache der Sprachtexte
     * (lang/date/), wie $trans_lang in lmo-functions.php.
     *
     * @param array<int|string, mixed> $text
     */
    public static function uebersetzt(string $datum, array $text): string
    {
        $namen = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun',
            'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $uebersetzung = [];
        foreach ($namen as $nr => $name) {
            // "May" gibt es lang und kurz; wie bisher gilt die kurze Form (Text 30)
            if ($nr == 18 || !isset($text['date'][$nr])) {
                continue;
            }
            $uebersetzung[$name] = $text['date'][$nr];
        }

        return strtr($datum, $uebersetzung);
    }

    private static function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
    }
}
