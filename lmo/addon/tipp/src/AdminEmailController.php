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
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Newsletter/Reminder in der Administration des Tippspiels (todo=tippemail, nur Hauptadmin):
 * ein Newsletter an alle Tipper (emailart=0), eine Mail an einen Tipper (2) oder die Erinnerung
 * an die noch nicht getippten Spiele einer Liga bzw. aller Ligen (1), mit Rundmail; der Text der
 * Erinnerung wird gespeichert (tipp/cfg.txt textreminder1). Bis 4.5 lmo-admintippemail.php mit
 * lmo-tippselectemail.php, lmo-tippnewdir.php (iptype reminder) und lmo-tippemail.php.
 */
final class AdminEmailController implements Controller
{
    /**
     * @param string $daten Datenordner des Tippspiels (daten/tipp)
     * @param string $konfigurationsordner Ordner der Konfiguration (PATH_TO_CONFIGDIR) mit tipp/cfg.txt
     * @param Closure(string, string, string): ?string $versende schickt eine Mail (Adresse, Betreff, Text); null oder der Fehler
     * @param string $herkunft Schema und Host der Seite (fuer den Link bei [pass])
     */
    public function __construct(private string $daten, private string $konfigurationsordner, private Closure $versende, private string $herkunft)
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
            if ($emailart == 1) {
                if ($liganr == 0) {
                    $liga = null;
                } else {
                    $st = $spieltage[(int) $liganr - 1] ?? '';
                    $liga = $ligen[(int) $liganr - 1] ?? '';
                }
                if ($message != '') {
                    // gespeichert als neuer Text der Erinnerung
                    $erinnerung = self::html($message);
                }
            }
            if ($message != '') {
                $ausgabe = $this->verschicke($kontext, $cfg, $emailart, $betreff, $message, $liga, $liganr == 0 ? '0' : $st);
            }
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
     * Verschickt die Mails (Rundmail); bei der Erinnerung wird vorher ihr Text gespeichert. Zurueck
     * kommen die Meldungen.
     *
     * @param array<string, string> $cfg Einstellungen des Tippspiels
     * @param string|null $liga die Liga der Erinnerung (Dateiname mit .l98); null: alle Ligen
     */
    private function verschicke(Kontext $kontext, array $cfg, string $emailart, string $betreff, string $message, ?string $liga, string $st): string
    {
        $a = $kontext->anfrage;
        $t = $kontext->texteVon('tipp');
        $bilder = $kontext->pfade->bilder;
        $rundmail = new Rundmail(
            $this->daten . '/' . ($cfg['tippauthtxt'] ?? 'lmo-tippauth.txt'),
            $this->daten . '/' . ($cfg['dirtipp'] ?? ''),
            $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner(),
            $cfg,
            new Tippzeit((int) ($cfg['tippBis'] ?? 0), ($cfg['tippohne'] ?? '') == 1, $kontext->konfiguration->wert('deftime'), (int) ($cfg['imvorraus'] ?? -1)),
            $kontext->text,
            $t,
            $kontext->texteVon('date'),
            $this->herkunft . $kontext->adresse . '?action=tipp&todo=getpass&xtippername2=',
            fn (string $an, string $text): ?string => ($this->versende)($an, $betreff, $text),
            time()
        );
        $von = $a->zahl('start');
        $bis = $a->zahl('ende');
        if ($emailart == 0) {
            [$anzahl, $fehler] = $rundmail->newsletter($message, $von, $bis);

            return $fehler . Meldung::html($bilder, $anzahl . ' ' . ($t[175] ?? ''));
        }
        if ($emailart == 1) {
            // der Text der Erinnerung fuer das naechste Mal, die Zeilenumbrueche als &#10;
            $neu = array_intersect_key(['textreminder1' => str_replace(["\r\n", "\n", "\r"], '&#10;', $message)], $cfg);
            $ausgabe = $kontext->konfiguration->mit(['tipp' => $neu])->speichere($this->konfigurationsordner)
                ? Meldung::html($bilder, $kontext->text[138] ?? '')
                : Meldung::html($bilder, $kontext->text[283] ?? '', true);
            [$anzahl, $fehler] = $rundmail->erinnerung($message, $a->text('tage', '4'), $von, $bis, $liga, $st);

            return $ausgabe . $fehler . Meldung::html($bilder, $anzahl . ' ' . ($t[175] ?? ''));
        }
        if ($emailart == 2 && $a->text('adressat') != '') {
            // wie bis 4.5 ohne Meldung
            return $rundmail->einzeln($message, $a->text('adressat'))[1];
        }

        return '';
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
