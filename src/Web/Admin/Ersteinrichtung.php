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

use Lmo\Web\Meldung;
use Lmo\Web\Vorlagen;

/**
 * Die Ersteinrichtung der Administration (bis 4.5 lmo_has_admin() und lmo_create_admin() in
 * lmo-setup.php, das Formular in lmo-adminsetup.php): solange es kein Konto gibt
 * (config/lmo-auth.php), legt das Formular den Hauptadmin an. Vorlage admin_einrichtung.html.twig.
 */
final class Ersteinrichtung
{
    /** @param string $kontendatei config/lmo-auth.php */
    public function __construct(private string $kontendatei)
    {
    }

    /** Gibt es schon ein Konto? */
    public function hatKonto(): bool
    {
        return is_file($this->kontendatei);
    }

    /**
     * Legt den Hauptadmin an (Rang 2).
     *
     * @return int 0 bei Erfolg, sonst die Nummer der Fehlermeldung (Sprachtexte 5018 bis 5023)
     */
    public function legeAn(string $name, string $kennwort, string $wiederholt): int
    {
        if ($this->hatKonto()) {
            return 5022;
        }
        if (!preg_match('/^[A-Za-z0-9_.@-]{3,40}$/', $name)) {
            return 5018;
        }
        if (strlen($kennwort) < 8) {
            return 5019;
        }
        if (str_contains($kennwort, '|') || str_contains($kennwort, "\n")) {
            return 5020;
        }
        if ($kennwort !== $wiederholt) {
            return 5021;
        }
        $hash = password_hash($kennwort, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        // 'x': schlaegt fehl, falls zwei Anfragen gleichzeitig das erste Konto anlegen wollen
        $datei = @fopen($this->kontendatei, 'x');
        if ($datei === false) {
            return 5023;
        }
        fwrite($datei, "<?php exit(); ?>\n" . $name . '|' . $hash . "|2|||\n");
        fclose($datei);

        return 0;
    }

    /**
     * Das Formular.
     *
     * @param array<int|string, mixed> $text Sprachtexte
     * @param string $selbst Adresse der Seite (PHP_SELF)
     * @param string $name der eingegebene Name (vorgegeben admin)
     * @param int $fehler die Nummer der Fehlermeldung, 0 ohne
     * @param string $sprachwahl die Sprachwahl ('' ohne)
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public function formular(array $text, string $selbst, string $name, int $fehler, string $sprachwahl, string $bilder, string $vorlagen): string
    {
        $meldung = $text[$fehler] ?? '';

        return Vorlagen::zeige($vorlagen, 'admin_einrichtung.html.twig', [
            'text' => $text,
            'selbst' => $selbst,
            'name' => $name,
            'fehler' => $fehler !== 0 ? Meldung::html($bilder, is_scalar($meldung) ? (string) $meldung : '', true) : '',
            'sprachwahl' => $sprachwahl,
        ]);
    }
}
