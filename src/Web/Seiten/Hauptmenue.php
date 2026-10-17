<?php

declare(strict_types=1);

namespace Lmo\Web\Seiten;

use Lmo\Web\Kontext;

/**
 * Das Menue ueber den Seiten einer Liga: Kalender, Ergebnisse und Tabelle (getrennt oder als
 * eine Seite, je nach tabonres), Spielplan, Kreuztabelle, Fieberkurve, Statistik und rechts
 * Info; im Pokal nur Kalender, Ergebnisse und Spielplan. Die gerade gezeigte Seite steht ohne
 * Link da. Ausgeschaltete Seiten der Liga fehlen.
 */
final class Hauptmenue
{
    /**
     * Die Menuepunkte als fertiges HTML, je Teil des Rahmens ('' wenn er fehlt); ohne Liga alle leer.
     *
     * @return array{Kalender: string, Ergebnisse: string, Tabelle: string, Spielplan: string, Kreuztabelle: string, Fieberkurve: string, Ligastatistik: string, Info: string}
     */
    public static function teile(Kontext $kontext): array
    {
        $teile = ['Kalender' => '', 'Ergebnisse' => '', 'Tabelle' => '', 'Spielplan' => '', 'Kreuztabelle' => '', 'Fieberkurve' => '', 'Ligastatistik' => '', 'Info' => ''];
        $geladen = $kontext->liga;
        if ($geladen === null) {
            return $teile;
        }
        $e = $geladen->liga->einstellungen();
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $action = $kontext->action;
        $basis = $kontext->adresse . '?file=' . $geladen->datei . '&amp;action=';
        // gewaehlter Spieltag: aus der Anfrage, sonst der aktuelle aus der Datei; fuer die
        // Ergebnisse gilt ein gewaehlter Tabellenstand (endtab) vor ihm
        $st = $anfrage->gesetzt('st') ? $anfrage->text('st') : (string) ($geladen->liga->optionen['Actual'] ?? '1');
        $ste = $anfrage->hat('endtab') ? $anfrage->text('endtab') : $st;

        // ein Menuepunkt: als Link, ausser es ist die gezeigte Seite
        $punkt = static fn (bool $gezeigt, string $link, string $titel, string $name): string => ($gezeigt ? $name : '<a href="' . $basis . $link . '" title="' . $titel . '">' . $name . '</a>') . '&nbsp;&nbsp;';

        if ($e->mitKalender()) {
            $teile['Kalender'] = $punkt($action == 'cal', 'cal&amp;st=' . $st, $text[141], $text[140]);
        }
        if ($e->istPokal()) {
            if ($e->mitErgebnissen()) {
                $teile['Ergebnisse'] = $punkt($action == 'results', 'results&amp;st=' . $ste, $text[11], $text[10]);
            }
            if ($e->mitSpielplan()) {
                $teile['Spielplan'] = $punkt($action == 'program', 'program', $text[13], $text[12]);
            }
        } else {
            if ($kontext->konfiguration->mitAnzeigeoptionen($anfrage)->tabonres() == 0) {
                if ($e->mitErgebnissen()) {
                    $teile['Ergebnisse'] = $punkt($action == 'results', 'results&amp;st=' . $ste, $text[11], $text[10]);
                }
                if ($e->mitTabelle()) {
                    $teile['Tabelle'] = $punkt($action == 'table', 'table', $text[17], $text[16]);
                }
            } elseif ($e->mitErgebnissen()) {
                // Ergebnisse und Tabelle als eine Seite
                $teile['Ergebnisse'] = $punkt($action == 'results' || $action == 'table', 'results', $text[104], $text[10] . '/' . $text[16]);
            }
            if ($e->mitKreuztabelle()) {
                $teile['Kreuztabelle'] = $punkt($action == 'cross', 'cross', $text[15], $text[14]);
            }
            if ($e->mitSpielplan()) {
                $teile['Spielplan'] = $punkt($action == 'program', 'program', $text[13], $text[12]);
            }
            if ($e->mitFieberkurve()) {
                $teile['Fieberkurve'] = $punkt($action == 'graph', 'graph', $text[134], $text[133]);
            }
            if ($e->mitLigastatistik()) {
                // die beiden Mannschaften aus der Anfrage, sonst die der Fieberkurve; zweimal dieselbe
                // oder nur die zweite gewaehlt: sie wird als erste gefuehrt
                $stat1 = $anfrage->gesetzt('stat1') ? $anfrage->zahl('stat1') : $e->fieberkurveErste();
                $stat2 = $anfrage->gesetzt('stat2') ? $anfrage->zahl('stat2') : $e->fieberkurveZweite();
                if ($stat1 > 0 && $stat1 == $stat2) {
                    $stat2 = 0;
                }
                if ($stat1 == 0 && $stat2 > 0) {
                    $stat1 = $stat2;
                    $stat2 = 0;
                }
                $teile['Ligastatistik'] = $punkt($action == 'stats', 'stats&amp;stat1=' . $stat1 . '&amp;stat2=' . $stat2, $text[19], $text[18]);
            }
        }
        // Info ohne Abstand dahinter
        $teile['Info'] = $action != 'info' ? '<a href="' . $basis . 'info" title="' . $text[21] . '">' . $text[20] . '</a>' : $text[20];

        return $teile;
    }
}
