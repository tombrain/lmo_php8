<?php

declare(strict_types=1);

namespace Lmo\Addon\Spieler;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Spielerstatistik einer Liga (action=spieler), wenn die Liga Spielerstatistiken hat
 * (Option mittore): sortierbar, in Seiten, wahlweise nur die Spieler eines Vereins. Bis 4.5
 * lmo-statshow.php.
 */
final class StatistikController implements Controller
{
    /** @param string $ordner Ordner der Statistiken (addon/spieler/stats) */
    public function __construct(private string $ordner)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'spieler' && $kontext->liga !== null && $kontext->liga->liga->einstellungen()->mitToren();
    }

    public function zeige(Kontext $kontext): Seite
    {
        $liga = $kontext->liga;
        if ($liga === null) {
            return new Seite('');
        }
        $text = $kontext->text;
        $t = $kontext->texteVon('spieler');
        $datei = $liga->datei;
        $einstellungen = Einstellungen::lies(Einstellungen::datei($this->ordner, $datei), $kontext->konfiguration->addon('spieler'), $t);
        $anfrage = $kontext->anfrage;

        // Zahlen aus der Anfrage als Zahlen behandeln: sie werden zum Rechnen und in Links verwendet
        $sort = $anfrage->gesetzt('sort') ? (int) $anfrage->text('sort') : $einstellungen->sortierung;
        $begin = max(0, $anfrage->gesetzt('begin') ? (int) $anfrage->text('begin') : 0);
        $direction = $anfrage->gesetzt('direction') ? (int) $anfrage->text('direction') : $einstellungen->richtung;
        $team = $anfrage->hat('team') ? urldecode($anfrage->text('team')) : '';
        $teamLink = htmlspecialchars(urlencode($team));

        $statistik = Statistik::lies($this->ordner . '/' . substr(basename($datei), 0, -4) . '.stat', $team, $einstellungen->vereinsweise, $t[25] ?? '');
        if ($statistik === null) {
            return new Seite('<p class="error"><img src="' . $kontext->pfade->bilder . '/wrong.svg" border="0" width="20" alt=""> ' . ($t[14] ?? '') . '</p>');
        }
        $spalten = $statistik->spalten;
        // Sortierspalte aus der Anfrage oder Konfiguration muss es in der Statistik geben
        if (!isset($spalten[$sort])) {
            $sort = 0;
        }
        $linkspalte = array_search($t[32] ?? '', $spalten, true);      // Linkunterstuetzung aktiviert?
        $data = $statistik->sortiert($sort, $direction == 1, $einstellungen->nullwerte);
        $zeile = count($data);
        $spaltenzahl = count($spalten);
        $proSeite = $einstellungen->proSeite;
        if ($begin + $proSeite > $zeile) {
            $maxdisplay = $zeile - $begin;
        } else {
            $maxdisplay = $proSeite;
        }
        if ($proSeite <= 0) {
            $maxdisplay = $zeile;
            $begin = 0;
        }

        // Namen, Kurznamen und Homepages der Mannschaften nach Nummer; Platz 0 ist wie bisher "___"
        $namen = ['___'];
        $kurz = ['___'];
        $urls = [''];
        foreach ($liga->liga->alleMannschaften() as $nr => $mannschaft) {
            $namen[$nr] = $mannschaft->anzeigename();
            $kurz[$nr] = $mannschaft->kurzname();
            $urls[$nr] = (string) ($mannschaft->werte['URL'] ?? '');
        }
        $urls[] = '';

        $basis = $kontext->adresse . '?file=' . $datei . '&amp;action=' . $kontext->action;
        $link = static fn (int $anfang, int $spalte, int $richtung, ?string $verein = null): string => $basis . '&amp;begin=' . $anfang . '&amp;sort=' . $spalte . '&amp;direction=' . $richtung . ($verein !== null ? '&amp;team=' . $verein : '');
        $bilder = $kontext->pfade->bilder;

        $vereine = [];
        if ($einstellungen->vereinsweise) {
            foreach ($namen as $i => $name) {
                $vereine[] = [
                    'link' => $name != $team ? $link(0, $sort, $direction, $name) : null,
                    'kurz' => $kurz[$i],
                    'wappen' => $kontext->darstellung->wappen($datei, $name, ' alt=""'),
                ];
            }
        }

        $koepfe = [];
        foreach ($spalten as $i => $spalte) {
            if ($spalte == ($t[32] ?? '')) {
                continue;
            }
            //Anfang der Suche nach Bildern fuer die Spaltenueberschriften
            $titel = '&nbsp;' . $spalte . '&nbsp;';
            foreach (['png', 'gif'] as $endung) {
                if (file_exists($kontext->pfade->lmo . '/img/spieler/' . $spalte . '.' . $endung)) {
                    $titel = '&nbsp;<acronym title="' . $spalte . '"><img border="0" src="' . $bilder . '/spieler/' . rawurlencode($spalte) . '.' . $endung . '" alt="' . $spalte . '"></acronym>&nbsp;';
                    break;
                }
            } //Ende der Suche nach Bildern fuer die Spaltenueberschriften
            $koepfe[] = ['nr' => $i, 'name' => $spalte, 'titel' => $titel];
        }

        $zeilen = [];
        for ($j1 = $begin; $j1 < $begin + $maxdisplay; $j1++) {
            $zellen = '';
            for ($j2 = 0; $j2 < $spaltenzahl; $j2++) {
                $data[$j1][$j2] = stripslashes((string) ($data[$j1][$j2] ?? ''));
                $wert = $data[$j1][$j2];
                $klasse = $j2 == $sort ? ' class="lmoBackMarkierung nobr"' : ' class="nobr"';
                if ($j2 == 0) {
                    $platz = '';
                    if (!isset($data[$j1 - 1][$sort]) || $data[$j1][$sort] !== $data[$j1 - 1][$sort] && $j1 != $begin) {
                        $platz .= ($j1 + 1) . '. ';
                    }
                    if ($j1 > 0 && $j1 == $begin) {
                        for ($x = $begin - 1; $x >= 0; $x--) {
                            if (($data[$x][$sort] ?? null) != ($data[$j1][$sort] ?? null)) {
                                $platz .= ($x + 2) . '. ';
                                break;
                            }
                            if ($x == 0) {
                                $platz .= '1. ';
                            }
                        }
                    }
                    //Spielerbild
                    $zellen .= '<td align="right" class="lmoBackMarkierung"><strong>' . $platz . '</strong></td>'
                        . "\n" . '<td align="left" class="lmoBackMarkierung">' . $kontext->darstellung->spielerbild($wert) . '</td>';
                }

                //Vereinslinks
                if ($spalten[$j2] == ($t[25] ?? '')) {
                    $zellen .= "\n" . '<td' . $klasse . ' align="left">' . $kontext->darstellung->wappen($datei, $wert) . str_replace(' ', '&nbsp;', $wert) . '&nbsp;</td>';
                    $pos = array_search($wert, $urls, true);
                    if (!empty($pos) && $urls[$pos] != '' && $liga->liga->einstellungen()->mitMannschaftslinks()) {
                        $zellen .= '<a href="' . $urls[$pos] . '" target="_blank" title="' . ($text[46] ?? '') . '"><img border="0" width="11" src="' . $bilder . '/url.png" alt="" title="' . ($text[46] ?? '') . '"></a></td>';
                    }

                //Spielerlinks
                } elseif ($j2 == 0 && $linkspalte !== false && ($data[$j1][$linkspalte] ?? null) != ($t[43] ?? '')) {
                    $zellen .= "\n" . '<td' . $klasse . ' align="left">&nbsp;' . $wert
                        . '<a href="' . ($data[$j1][$linkspalte] ?? '') . '" title="' . ($t[34] ?? '') . '"><img border="0" width="11" src="' . $bilder . '/url.png" alt="' . ($text[564] ?? '') . '"></a></td>';

                //sonst. Spalten
                } elseif ($spalten[$j2] != ($t[32] ?? '')) {
                    $zellen .= "\n" . '<td' . $klasse . ' align="' . (is_numeric($wert) ? 'center' : 'left') . '">&nbsp;' . str_replace(' ', '&nbsp;', $wert) . '&nbsp;</td>';
                }
            }
            $zeilen[] = $zellen;
        }

        $fuss = null;
        if ($proSeite > 0) {
            $fuss = [
                'zurueck' => $begin == 0 ? null : $link(max(0, $begin - $proSeite), $sort, $direction, $teamLink),
                'anfang' => $link(0, $sort, $direction, $teamLink),
                'weiter' => $begin + $maxdisplay < $zeile ? $link($begin + $maxdisplay, $sort, $direction, $teamLink) : null,
            ];
        }

        $druck = $kontext->pfade->adresse . '/addon/spieler/lmo-statprint.php?file=' . $datei;

        return new Seite(Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'statistik.html.twig', [
            't' => $t,
            'bilder' => $bilder,
            'vereinsweise' => $einstellungen->vereinsweise,
            'alle' => $team != '' ? $link(0, $sort, $direction) : null,
            'vereine' => $vereine,
            'koepfe' => $koepfe,
            'spalten' => $spalten,
            'pfeile' => !$einstellungen->extraSortierspalte,
            'sortierspalte' => $einstellungen->extraSortierspalte,
            'link' => [
                'ab' => array_map(static fn (int $i): string => $link(0, $i, 1, $teamLink), array_keys($spalten)),
                'auf' => array_map(static fn (int $i): string => $link(0, $i, 0, $teamLink), array_keys($spalten)),
                'ab_hier' => array_map(static fn (int $i): string => $link($begin, $i, 1, $teamLink), array_keys($spalten)),
                'auf_hier' => array_map(static fn (int $i): string => $link($begin, $i, 0, $teamLink), array_keys($spalten)),
            ],
            'spaltenzahl' => $spaltenzahl,
            'fuss' => $fuss,
            'pro_seite' => $proSeite,
            'zeilen' => $zeilen,
            'druck' => $druck . '&amp;begin=' . $begin . '&amp;sort=' . $sort . '&amp;direction=' . $direction . '&amp;team=' . $teamLink,
            'druck_alle' => $proSeite != 0 ? $druck . '&amp;sort=' . $sort . '&amp;direction=' . $direction . '&amp;team=' . $teamLink : null,
        ]));
    }
}
