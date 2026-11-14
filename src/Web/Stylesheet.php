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

namespace Lmo\Web;

/**
 * Die Stylesheets (lmo-style.php, lmo-style-nc.php): die Farben, Rahmen und Schriften aus der
 * Konfiguration (Administration > Design) in den Vorlagen style.css.twig und style-nc.css.twig.
 * Eine eigene template/style.css wird am Ende angehaengt, wie bis 4.5 per include (sie darf also
 * auch PHP enthalten).
 */
final class Stylesheet
{
    /**
     * @param string $vorlage style.css.twig oder style-nc.css.twig
     * @param array<string, mixed> $konfiguration die Hauptkonfiguration (cfgarray)
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     * @param string $eigene die eigene Datei (template/style.css), es muss sie nicht geben
     */
    public static function css(string $vorlagen, string $vorlage, array $konfiguration, string $bilder, string $eigene): string
    {
        $wert = [];
        $gesetzt = [];
        foreach ($konfiguration as $name => $inhalt) {
            if (is_scalar($inhalt)) {
                $wert[$name] = (string) $inhalt;
                // wie empty() im alten Code: '' und '0' gelten als nicht gesetzt
                $gesetzt[$name] = !empty($inhalt);
            }
        }

        return Vorlagen::zeige($vorlagen, $vorlage, [
            'wert' => $wert,
            'gesetzt' => $gesetzt,
            'bilder' => $bilder,
            'eigene' => self::eigene($eigene),
        ]);
    }

    /** Was die eigene Datei ausgibt; '' ohne */
    private static function eigene(string $datei): string
    {
        if (!is_file($datei)) {
            return '';
        }
        ob_start();
        (static function (string $lmo_datei): void {
            include $lmo_datei;
        })($datei);

        return (string) ob_get_clean();
    }
}
