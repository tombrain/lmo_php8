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

namespace Lmo\Addon\Spieler;

/**
 * Die Einstellungen der Spielerstatistik einer Liga: die des Addons (config/spieler/cfg.txt),
 * ersetzt durch die der Liga (addon/spieler/stats/<liga>.cfg, vom Admin geschrieben). In der
 * Datei der Liga heissen die Einstellungen wie die Sprachtexte des Addons (z. B. "Anzeige pro
 * Seite=10"), wie bisher in lmo-statloadconfig.php.
 */
final class Einstellungen
{
    /**
     * @param int $sortierung Sortierspalte ohne Angabe in der Adresse (Vorsortierung User)
     * @param int $richtung Richtung ohne Angabe in der Adresse: 1 absteigend, 0 aufsteigend
     * @param bool $vereinsweise Spieler eines Vereins auswaehlbar
     * @param int $proSeite Spieler je Seite, 0 = alle
     * @param bool $nullwerte Spieler mit 0 in der Sortierspalte zeigen
     * @param bool $extraSortierspalte Sortieren in einer eigenen Spalte statt in den Spaltenkoepfen
     * @param string $ligalink Text des Menuepunkts
     */
    private function __construct(
        public readonly int $sortierung,
        public readonly int $richtung,
        public readonly bool $vereinsweise,
        public readonly int $proSeite,
        public readonly bool $nullwerte,
        public readonly bool $extraSortierspalte,
        public readonly string $ligalink,
    ) {
    }

    /**
     * @param string $datei die .cfg-Datei der Liga (darf fehlen)
     * @param array<string, string> $addon Konfiguration des Addons (Konfiguration::addon('spieler'))
     * @param array<int, string> $texte Sprachtexte des Addons
     */
    public static function lies(string $datei, array $addon, array $texte): self
    {
        $werte = [
            'sortierung' => $addon['standard_sortierung'] ?? '',
            'richtung' => $addon['standard_richtung'] ?? '',
            'vereinsweise' => $addon['vereinsweise_anzeigen'] ?? '',
            'proSeite' => $addon['anzeige_pro_seite'] ?? '',
            'nullwerte' => $addon['nullwerte_anzeigen'] ?? '',
            'extraSortierspalte' => $addon['extra_sortierspalte'] ?? '',
            'ligalink' => $texte[18] ?? '',
        ];
        // Name in der Datei der Liga (Sprachtext) => Einstellung
        $namen = [21 => 'sortierung', 13 => 'richtung', 50 => 'vereinsweise', 22 => 'proSeite', 23 => 'nullwerte', 24 => 'extraSortierspalte', 41 => 'ligalink'];
        if (is_readable($datei) && $zeilen = file($datei)) {
            foreach ($zeilen as $zeile) {
                $teile = explode('=', $zeile, 2);
                foreach ($namen as $nr => $name) {
                    if (isset($texte[$nr]) && $teile[0] == $texte[$nr]) {
                        $werte[$name] = $teile[1] ?? '';
                        break;
                    }
                }
            }
        }

        return new self(
            (int) $werte['sortierung'],
            (int) $werte['richtung'],
            (int) $werte['vereinsweise'] == 1,
            (int) $werte['proSeite'],
            (int) $werte['nullwerte'] != 0,
            (int) $werte['extraSortierspalte'] == 1,
            $werte['ligalink'],
        );
    }

    /** Die .cfg-Datei einer Liga im Ordner der Statistiken. */
    public static function datei(string $ordner, string $ligadatei): string
    {
        return $ordner . '/' . substr(basename($ligadatei), 0, -4) . '.cfg';
    }
}
