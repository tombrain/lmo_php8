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

namespace Lmo\Addon;

/**
 * Laedt die Klassen der Addons aus ihrem Ordner, ohne Eintrag in composer.json: eine Klasse
 * Lmo\Addon\<Addon>\<Klasse> liegt in addon/<addon>/src/<Klasse>.php (Ordnername klein,
 * Unterordner wie Unternamensraeume). Ein Addon ist so mit seinem Ordner komplett.
 */
final class Autoloader
{
    private const PRAEFIX = 'Lmo\\Addon\\';

    /** @var array<string, true> Ordner, fuer die der Autoloader schon registriert ist */
    private static array $registriert = [];

    /**
     * Registriert den Autoloader (einmal je Ordner).
     *
     * @param string $ordner Ordner der Addons (PATH_TO_ADDONDIR)
     */
    public static function registriere(string $ordner): void
    {
        if (isset(self::$registriert[$ordner])) {
            return;
        }
        self::$registriert[$ordner] = true;
        spl_autoload_register(static function (string $klasse) use ($ordner): void {
            $datei = self::datei($ordner, $klasse);
            if ($datei !== null) {
                require $datei;
            }
        });
    }

    /**
     * Die Datei einer Klasse; null, wenn sie keine Klasse eines Addons ist oder die Datei fehlt.
     * Die Klassen des Kerns direkt in Lmo\Addon (z. B. Lmo\Addon\Addons) laedt Composer.
     */
    public static function datei(string $ordner, string $klasse): ?string
    {
        if (!str_starts_with($klasse, self::PRAEFIX)) {
            return null;
        }
        $teile = explode('\\', substr($klasse, strlen(self::PRAEFIX)));
        if (count($teile) < 2) {
            return null;
        }
        $addon = strtolower(array_shift($teile));
        $datei = $ordner . '/' . $addon . '/src/' . implode('/', $teile) . '.php';

        return is_file($datei) ? $datei : null;
    }
}
