<?php

declare(strict_types=1);

namespace Lmo\Web\Seiten;

use Lmo\Web\Kontext;

/**
 * Die Fusszeile des Rahmens: Link zurueck zur Ligenuebersicht, Zeit der letzten Auswertung der
 * Liga und die Berechnungszeit der Seite.
 */
final class Fusszeile
{
    /**
     * Die Teile als fertiges HTML ('' wenn einer fehlt).
     *
     * @param bool $ligenuebersicht Link zur Ligenuebersicht zeigen (Konfiguration backlink)
     * @param float|null $sekunden Berechnungszeit der Seite in Sekunden, null: nicht zeigen (calctime)
     * @return array{Ligenuebersicht: string, LetzteAuswertung: string, Berechnungszeit: string}
     */
    public static function teile(Kontext $kontext, bool $ligenuebersicht, ?float $sekunden): array
    {
        $text = $kontext->text;
        $datei = $kontext->liga !== null ? $kontext->liga->datei : '';
        $teile = ['Ligenuebersicht' => '', 'LetzteAuswertung' => '', 'Berechnungszeit' => ''];

        // zurueck zur Ligenuebersicht, bei einer Liga im Unterordner zu diesem
        if ($ligenuebersicht && ($datei !== '' || $kontext->action == 'tipp')) {
            $ziel = basename($datei) == $datei ? $kontext->adresse : $kontext->adresse . '?subdir=' . dirname($datei) . '/';
            $teile['Ligenuebersicht'] = '<a href="' . $ziel . '" title="' . $text[392] . '">' . $text[391] . '</a>&nbsp;&nbsp;&nbsp;';
        }
        if ($kontext->liga !== null) {
            $teile['LetzteAuswertung'] = $text[406] . ':&nbsp;' . $kontext->liga->stand;
        }
        if ($sekunden !== null) {
            $teile['Berechnungszeit'] = $text[471] . ': ' . number_format($sekunden, 4, '.', ',') . ' ' . $text[799] . '.<br>';
        }

        return $teile;
    }
}
