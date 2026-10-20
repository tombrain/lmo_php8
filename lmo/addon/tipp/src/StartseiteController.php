<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Liga\LigaDatei;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Startseite eines angemeldeten Tippers (action=tipp ohne todo): seine Ligen zum Tippen, die
 * Auswertungen und sein Konto. Bis 4.5 lmo-tipppad.php mit lmo-tippdir.php.
 */
final class StartseiteController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Tipps */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == ''
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->sitzung->zahl('lmotipperok') == 5;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $nick = $kontext->sitzung->text('lmotippername');
        $tipps = $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/');
        $ligenordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $zuTippen = explode(',', $cfg['ligenzutippen'] ?? '');

        // die Ligen, fuer die der Tipper eine Tippdatei hat (<liga>_<nick>.tip), nach Namen der Datei
        $dateien = [];
        foreach (is_dir($tipps) ? (scandir($tipps) ?: []) : [] as $datei) {
            if (substr($datei, -5 - strlen($nick)) == '_' . $nick . '.tip') {
                $dateien[] = substr($datei, 0, -5 - strlen($nick)) . '.l98';
            }
        }
        sort($dateien);
        $ligen = [];
        $ohneNamen = 0;
        foreach ($dateien as $datei) {
            if (!file_exists($ligenordner . $datei)) {
                continue;
            }
            $optionen = LigaDatei::liesAbschnitt($ligenordner . $datei, 'Options');
            $name = $optionen['Name'] ?? '';
            if ($name == '') {
                $name = ($text[507] ?? '') . ++$ohneNamen;
            }
            if (($cfg['immeralle'] ?? '') != 1 && !in_array(substr($datei, 0, -4), $zuTippen, true)) {
                continue;
            }
            $tippdatei = $tipps . substr($datei, 0, -4) . '_' . str_replace(' ', '_', $nick) . '.tip';
            $ligen[] = [
                'datei' => $datei,
                'name' => $name,
                'getippt' => file_exists($tippdatei) ? date($kontext->konfiguration->datumsformat(), (int) filemtime($tippdatei)) . self::spieltag($kontext, $optionen) : null,
            ];
        }

        // die Auswertungen der Ligen und, bei mehreren Ligen, die Gesamtwertung
        $auswertung = static function (string $name) use ($tipps): ?string {
            $datei = $tipps . 'auswert/' . $name . '.aus';

            return file_exists($datei) ? date('d.m.Y H:i', (int) filemtime($datei)) : null;
        };
        $nurGesamt = ($cfg['nurgesamt'] ?? '') == 1;
        $wertungen = [];
        if (!$nurGesamt) {
            foreach ($ligen as $liga) {
                $wertungen[] = ['link' => substr($liga['datei'], 0, -4) . '.l98', 'name' => $liga['name'], 'stand' => $auswertung(substr($liga['datei'], 0, -4)), 'gesamt' => false];
            }
        }
        if (($cfg['gesamt'] ?? '') == 1 && (count($ligen) > 1 || $nurGesamt && count($ligen) == 1)) {
            $wertungen[] = ['link' => '&amp;all=1', 'name' => $kontext->texteVon('tipp')[25] ?? '', 'stand' => $auswertung('gesamt'), 'gesamt' => true];
        }
        $verein = $kontext->sitzung->text('lmotipperverein');

        return new Seite($this->rahmen->html($kontext, Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'startseite.html.twig', [
            'text' => $text,
            't' => $kontext->texteVon('tipp'),
            'tipper' => $nick . ($verein != '' ? ' - ' . $verein : ''),
            'adda' => $kontext->adresse . '?action=tipp&amp;todo=',
            'ligen' => $ligen,
            'tippen' => ($cfg['sttipp'] ?? '') != -1,
            'viewer' => ($cfg['viewertipp'] ?? '') == 1 ? ($cfg['viewertage'] ?? '') : null,
            'wertungen' => $wertungen,
            'team' => (int) ($cfg['tipperimteam'] ?? -1) >= 0,
        ])));
    }

    /**
     * Der aktuelle Spieltag hinter dem Stand der Tipps: " / 5. Spieltag", im Pokal die Runde
     * ("Finale", "Halbfinale" ...); '' ohne aktuellen Spieltag.
     *
     * @param array<string, string> $optionen Abschnitt [Options] der Liga
     */
    private static function spieltag(Kontext $kontext, array $optionen): string
    {
        $text = $kontext->text;
        $aktuell = $optionen['Actual'] ?? '';
        if ($aktuell == '') {
            return '';
        }
        if (($optionen['Type'] ?? '') != '1') {
            return ' / ' . $aktuell . '. ' . ($text[2] ?? '');
        }
        $runden = strlen(decbin((int) ($optionen['Teams'] ?? 0) - 1));
        foreach ([374 => 0, 373 => 1, 372 => 2, 371 => 3, 370 => 4] as $nr => $vorEnde) {
            if ($aktuell == $runden - $vorEnde) {
                return ' / ' . ($text[$nr] ?? '');
            }
        }

        return ' / ' . $aktuell . '. ' . ($text[370] ?? '');
    }
}
