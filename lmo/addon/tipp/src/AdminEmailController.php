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

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Newsletter/Reminder in der Administration des Tippspiels (todo=tippemail, nur Hauptadmin):
 * ein Newsletter an alle Tipper (emailart=0), eine Mail an einen Tipper (2) oder die Erinnerung
 * an die noch nicht getippten Spiele einer Liga bzw. aller Ligen (1). Verschickt wird noch mit
 * dem alten Code (lmo-tippemail.php). Bis 4.5 lmo-admintippemail.php mit lmo-tippselectemail.php
 * und lmo-tippnewdir.php (iptype reminder).
 */
final class AdminEmailController implements Controller
{
    /**
     * @param string $ordner Ordner des Addons (addon/tipp)
     * @param string $daten Datenordner des Tippspiels (daten/tipp)
     * @param Closure(string, array<string, mixed>): string $alterCode bindet eine Datei nach alter Art im globalen Bereich ein (mit diesen Variablen), gibt ihre Ausgabe zurueck
     */
    public function __construct(private string $ordner, private string $daten, private Closure $alterCode)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'tippemail' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $a = $kontext->anfrage;
        $t = $kontext->texteVon('tipp');
        $cfg = $kontext->konfiguration->addon('tipp');
        $save = $a->text('save', '0');
        $message = $a->text('message');
        $betreff = $a->text('betreff') != '' ? $a->text('betreff') : ($t[0] ?? '');
        $liganr = $a->text('liganr', '-1');
        $emailart = $a->text('emailart', '-1');
        $ligen = $a->werte('liga1');
        $spieltage = $a->werte('st1');
        // die Liga der Erinnerung (Dateiname mit .l98); null: keine (alle Ligen)
        $liga = '';
        $st = '';
        // der Text der Erinnerung fuer das Formular, schon als HTML (Zeilenumbrueche als &#10;): der
        // gespeicherte, mit &#10; und &quot; (bis 4.5 zeigte das Formular immer den vorgegebenen).
        // Ohne Anfuehrungszeichen gespeichert (bis 4.5) endet er nach der ersten Zeile mit "&#10": dann
        // der vorgegebene.
        $gespeichert = $cfg['textreminder1'] ?? '';
        $erinnerung = str_ends_with($gespeichert, '&#10') ? '' : self::html(html_entity_decode($gespeichert, ENT_QUOTES));

        $ausgabe = '';
        if ($save == 1) {
            $variablen = ['save' => $save, 'message' => $message, 'betreff' => $betreff, 'emailart' => $emailart, 'adressat' => $a->text('adressat'),
                'start' => $a->text('start', '0'), 'ende' => $a->text('ende', '0'), 'tage' => $a->text('tage', '4')];
            if ($emailart == 1) {
                if ($liganr == 0) {
                    $liga = null;
                    $variablen += ['st' => 0, 'file' => '', 'viewermode' => 1];
                } else {
                    $st = $spieltage[(int) $liganr - 1] ?? '';
                    $liga = $ligen[(int) $liganr - 1] ?? '';
                    $variablen += ['st' => $st, 'liga' => $liga, 'viewermode' => 0];
                }
                if ($message != '') {
                    // gespeichert als neuer Text der Erinnerung (lmo-tippemail.php)
                    $erinnerung = self::html($message);
                }
            }
            $ausgabe = ($this->alterCode)($this->ordner . '/lmo-tippemail.php', $variablen);
        }
        if ($erinnerung == '') {
            $erinnerung = $t[174] ?? '';
        }

        // die Tipper fuer die Auswahl, nach ihrer Zeile sortiert
        $tipper = Tipperdatei::lies($this->daten . '/' . ($cfg['tippauthtxt'] ?? 'lmo-tippauth.txt'))->alle();
        usort($tipper, static fn (Tipper $x, Tipper $y): int => strcmp($x->zeile(), $y->zeile()));

        return new Seite($ausgabe . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_email.html.twig', [
            'text' => $kontext->text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'menue' => AdminMenue::html($kontext, 'tippemail'),
            'emailart' => $emailart,
            'erinnerung' => $erinnerung,
            'tipper' => $tipper,
            'ligen' => $this->ligen($kontext, $liga, $st),
            'alleLigen' => $liganr == 0,
            'start' => $save == 1 ? $a->text('start', '0') : '1',
            'ende' => $save == 1 ? $a->text('ende', '0') : (string) count($tipper),
            'tage' => $a->text('tage', '4'),
            'betreff' => $save == 1 ? $betreff : $a->text('betreff1'),
            'nachricht' => $emailart == 1 ? $erinnerung : ($message != '' ? htmlspecialchars($message, ENT_QUOTES) : ($t[295] ?? '')),
        ]));
    }

    /**
     * Die getippten Ligen fuer die Erinnerung, je mit der Auswahl der Spieltage bzw. Runden. Gewaehlt
     * ist die Liga der Erinnerung (sonst die erste) und ihr Spieltag, sonst der aktuelle.
     *
     * @param string|null $liga die Liga der Erinnerung (Dateiname mit .l98), null: alle Ligen
     * @return list<array{datei: string, name: string, gewaehlt: bool, pokal: bool, alle: bool, spieltage: list<array{wert: int, name: string, gewaehlt: bool}>}>
     */
    private function ligen(Kontext $kontext, ?string $liga, string $st): array
    {
        $text = $kontext->text;
        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $ligen = [];
        foreach (Tippligen::tippbar($kontext) as $nr => $tippliga) {
            $datei = $tippliga['datei'] . '.l98';
            $optionen = LigaDatei::liesAbschnitt($ordner . $datei, 'Options');
            $rohdaten = new Liga();
            $rohdaten->optionen = $optionen;
            $pokal = ($optionen['Type'] ?? '') == '1';
            $aktuell = $optionen['Actual'] ?? '';
            if ($liga == $datei && $st > 0) {
                $aktuell = $st;
            }
            $runden = strlen(decbin((int) ($optionen['Teams'] ?? '') - 1));
            $spieltage = [];
            for ($y = 1; $y <= $rohdaten->einstellungen()->spieltage(); $y++) {
                $spieltage[] = ['wert' => $y, 'name' => $pokal ? match ($y) {
                    $runden => $text[374] ?? '',
                    $runden - 1 => $text[373] ?? '',
                    $runden - 2 => $text[372] ?? '',
                    $runden - 3 => $text[371] ?? '',
                    $runden - 4 => $text[370] ?? '',
                    default => $y . '. ' . ($text[370] ?? ''),
                } : $y . '. ' . ($text[2] ?? ''), 'gewaehlt' => $y == $aktuell];
            }
            $ligen[] = [
                'datei' => $datei,
                'name' => $tippliga['name'],
                'gewaehlt' => $liga === '' && $nr == 0 || $liga === $datei,
                'pokal' => $pokal,
                'alle' => $aktuell == 0,
                'spieltage' => $spieltage,
            ];
        }

        return $ligen;
    }

    /** Ein Text fuer das Formular als HTML, die Zeilenumbrueche als &#10; (bis 4.5 ohne Maskierung). */
    private static function html(string $text): string
    {
        return str_replace(["\r\n", "\n", "\r"], '&#10;', htmlspecialchars($text, ENT_QUOTES));
    }
}
