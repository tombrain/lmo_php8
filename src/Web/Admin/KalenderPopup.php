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

namespace Lmo\Web\Admin;

use Lmo\Web\Vorlagen;

/**
 * Der Kalender zur Datumswahl (lmo-admincal.php): ein Pop-up aus den Datumsfeldern der
 * Administration. Ein Klick auf einen Tag traegt ihn als TT.MM.JJJJ in das Feld feld des Formulars
 * abs im aufrufenden Fenster ein. calshow waehlt den Monat ("March 2025"), ohne den aktuellen.
 * Vorlage admin_kalender.html.twig.
 */
final class KalenderPopup
{
    private const MONATE = ['January' => 14, 'February' => 15, 'March' => 16, 'April' => 17, 'May' => 18, 'June' => 19, 'July' => 20,
        'August' => 21, 'September' => 22, 'October' => 23, 'November' => 24, 'December' => 25];

    /** @param array<int|string, mixed> $text Sprachtexte (mit 'date') */
    public function __construct(private array $text, private string $vorlagen)
    {
    }

    /**
     * @param array<string, mixed> $anfrage die Parameter der Adresse (abs, feld, calshow)
     * @param string $selbst Adresse dieser Seite (PHP_SELF)
     * @param int $jetzt Zeitstempel fuer heute
     */
    public function html(array $anfrage, string $selbst, int $jetzt): string
    {
        $wert = static fn (string $name): string => isset($anfrage[$name]) && is_string($anfrage[$name]) ? $anfrage[$name] : '';
        $heute = getdate($jetzt);
        $monat = $wert('calshow') !== '' ? $wert('calshow') : $heute['month'] . ' ' . $heute['year'];
        $zeit = static fn (string $angabe): int => (int) strtotime($angabe);
        $erster = getdate($zeit('1 ' . $monat));
        $davor = getdate($zeit('1 ' . $monat . ' -1 month'));
        $danach = getdate($zeit('1 ' . $monat . ' +1 month'));

        // die Tage: wie bis 4.5 beginnt jeder Montag eine Zeile und jeder Sonntag schliesst sie
        $tage = [];
        $letzterSonntag = false;
        for ($i = 1; $i <= 31; $i++) {
            $tag = getdate($zeit($i . ' ' . $monat));
            if ($tag['mon'] != $erster['mon']) {
                continue;
            }
            $wochentag = $tag['wday'] == 0 ? 7 : $tag['wday'];
            $istHeute = $heute['mday'] . '.' . $heute['mon'] . '.' . $heute['year'] == $tag['mday'] . '.' . $tag['mon'] . '.' . $tag['year'];
            $wochenende = $wochentag == 6 || $wochentag == 7;
            $tage[] = [
                'zeileAuf' => $wochentag == 1,
                'zeileZu' => $wochentag == 7,
                'stil' => $istHeute ? ($wochenende ? 'calhe' : 'calht') : ($wochenende ? 'calwe' : 'calat'),
                'datum' => date('d.m.Y', $zeit($i . ' ' . $monat)),
                'tag' => ($i <= 9 ? '0' : '') . $i,
            ];
            if ($wochentag == 7) {
                $letzterSonntag = true;
            }
        }
        $beginn = $erster['wday'] == 0 ? 7 : $erster['wday'];
        $datum = is_array($this->text['date'] ?? null) ? $this->text['date'] : [];

        return Vorlagen::zeige($this->vorlagen, 'admin_kalender.html.twig', [
            'text' => $this->text,
            'datum' => $datum,
            'abs' => $wert('abs'),
            'feld' => $wert('feld'),
            'adresse' => $selbst . '?abs=' . $wert('abs') . '&amp;feld=' . $wert('feld'),
            'davor' => $davor['month'] . ' ' . $davor['year'],
            'danach' => $danach['month'] . ' ' . $danach['year'],
            'titel' => ($datum[self::MONATE[$erster['month']]] ?? '') . ' ' . $erster['year'],
            'leerVorn' => $beginn - 1,
            'tage' => $tage,
            // bis 4.5 blieb $j auf 7 stehen, sobald ein Sonntag kam: dann keine Fuellung hinten
            'leerHinten' => $letzterSonntag ? 0 : 7,
        ]);
    }
}
