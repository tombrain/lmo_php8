<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Liga\LigaDatei;
use Lmo\Web\Kontext;

/**
 * Die Ligen des Tippspiels: welche getippt werden duerfen und wo ihre Auswertungen stehen. Bis 4.5
 * lmo-tippnewdir.php und die Listen in lmo-tippauth.php und lmo-tipppad.php.
 */
final class Tippligen
{
    /**
     * Die Ligen im Ligenordner, die getippt werden duerfen (immeralle, sonst ligenzutippen), nach
     * Dateinamen; Ligen ohne Namen heissen "Liga 1", "Liga 2" ... Ein angemeldeter Hilfsadmin
     * sieht nur seine Ligen.
     *
     * @return list<array{datei: string, name: string}> datei ohne .l98
     */
    public static function tippbar(Kontext $kontext): array
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $zuTippen = explode(',', $cfg['ligenzutippen'] ?? '');
        $alle = ($cfg['immeralle'] ?? '') == 1;
        $hilfsadmin = $kontext->sitzung->zahl('lmouserok') == 1 ? explode(',', $kontext->sitzung->text('lmouserfile')) : null;
        $dateien = [];
        foreach (is_dir($ordner) ? (scandir($ordner) ?: []) : [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $ligen = [];
        $ohneNamen = 0;
        foreach ($dateien as $datei) {
            $liga = substr($datei, 0, -4);
            if ($hilfsadmin !== null && !in_array($liga, $hilfsadmin, true)) {
                continue;
            }
            $name = LigaDatei::liesAbschnitt($ordner . $datei, 'Options')['Name'] ?? '';
            if ($name == '') {
                $name = ($kontext->text[507] ?? '') . ++$ohneNamen;
            }
            if ($alle || in_array($liga, $zuTippen, true)) {
                $ligen[] = ['datei' => $liga, 'name' => $name];
            }
        }

        return $ligen;
    }

    /**
     * Die Auswertungen zu den Ligen: je Liga (ausser nurgesamt) und, bei mehreren Ligen, die
     * Gesamtwertung (gesamt), jeweils mit der Zeit der letzten Auswertung.
     *
     * @param list<array{datei: string, name: string}> $ligen datei ohne .l98
     * @return list<array{link: string, name: string, stand: string|null, gesamt: bool}> link: Wert fuer file=
     */
    public static function wertungen(Kontext $kontext, array $ligen, string $tipps): array
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $auswertung = static function (string $name) use ($tipps): ?string {
            $datei = $tipps . 'auswert/' . $name . '.aus';

            return file_exists($datei) ? date('d.m.Y H:i', (int) filemtime($datei)) : null;
        };
        $nurGesamt = ($cfg['nurgesamt'] ?? '') == 1;
        $wertungen = [];
        if (!$nurGesamt) {
            foreach ($ligen as $liga) {
                $wertungen[] = ['link' => $liga['datei'] . '.l98', 'name' => $liga['name'], 'stand' => $auswertung($liga['datei']), 'gesamt' => false];
            }
        }
        if (($cfg['gesamt'] ?? '') == 1 && (count($ligen) > 1 || $nurGesamt && count($ligen) == 1)) {
            $wertungen[] = ['link' => '&amp;all=1', 'name' => $kontext->texteVon('tipp')[25] ?? '', 'stand' => $auswertung('gesamt'), 'gesamt' => true];
        }

        return $wertungen;
    }
}
