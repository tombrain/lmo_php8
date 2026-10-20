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

use Lmo\Liga\Rohdaten\Liga;

/**
 * Die Sprachtexte des LMO (lang/lang-<Sprache>.txt, Zeilen "Nummer=Text"), wie lmo-langload.php
 * sie liest: erst Deutsch, darueber die Standardsprache, darueber die Sprache des Besuchers; so
 * bleiben bei unvollstaendigen Uebersetzungen die deutschen Texte stehen.
 *
 * Getrennt davon die Texte der Unterordner von lang/ (lang/<name>/lang-<Sprache>.txt): die Namen
 * von Wochentagen und Monaten (date) und die Texte der Addons. Im alten Code stehen sie im selben
 * Array unter ihrem Namen ($text['tipp'][1]).
 */
final class Sprachtexte
{
    /**
     * @param array<int, string> $kern Nummer => Text
     * @param array<string, array<int, string>> $weitere Name des Unterordners => (Nummer => Text)
     */
    public function __construct(
        public readonly array $kern,
        public readonly array $weitere = [],
    ) {
    }

    /**
     * Liest die Sprachdateien.
     *
     * @param string $ordner Ordner der Sprachdateien (PATH_TO_LANGDIR)
     * @param string $standard Standardsprache der Installation (deflang)
     * @param string|null $besucher vom Besucher gewaehlte Sprache, null ohne Wahl
     */
    public static function lies(string $ordner, string $standard, ?string $besucher = null): self
    {
        $kern = [];
        self::leseDatei($kern, $ordner . '/lang-Deutsch.txt');
        self::leseDatei($kern, $ordner . '/lang-' . $standard . '.txt');
        if ($besucher !== null && $besucher != $standard) {
            self::leseDatei($kern, $ordner . '/lang-' . $besucher . '.txt');
        }
        // Unterordner in fester (alphabetischer) Reihenfolge
        $weitere = [];
        foreach (scandir($ordner) ?: [] as $name) {
            if ($name == '.' || $name == '..' || !is_dir($ordner . '/' . $name)) {
                continue;
            }
            $texte = [];
            self::leseDatei($texte, $ordner . '/' . $name . '/lang-' . $standard . '.txt');
            if ($besucher !== null) {
                self::leseDatei($texte, $ordner . '/' . $name . '/lang-' . $besucher . '.txt');
            }
            if ($texte !== []) {
                $weitere[$name] = $texte;
            }
        }

        return new self($kern, $weitere);
    }

    /**
     * Die Texte aus dem Array des alten Codes ($text aus lmo-langload.php): Nummern sind die
     * Kerntexte, Namen die Texte der Unterordner.
     *
     * @param array<int|string, mixed> $text
     */
    public static function ausAltemText(array $text): self
    {
        $kern = [];
        $weitere = [];
        foreach ($text as $schluessel => $wert) {
            if (is_int($schluessel) && is_scalar($wert)) {
                $kern[$schluessel] = (string) $wert;
            } elseif (is_string($schluessel) && is_array($wert)) {
                $texte = [];
                foreach ($wert as $nr => $einzeln) {
                    if (is_int($nr) && is_scalar($einzeln)) {
                        $texte[$nr] = (string) $einzeln;
                    }
                }
                $weitere[$schluessel] = $texte;
            }
        }

        return new self($kern, $weitere);
    }

    /**
     * Die Texte eines Unterordners von lang/ (z. B. "date" oder der Name eines Addons); [] ohne.
     *
     * @return array<int, string>
     */
    public function von(string $name): array
    {
        return $this->weitere[$name] ?? [];
    }

    /**
     * Die Texte fuer die Seiten einer Liga: Eine eigene Bezeichnung der Liga fuer Punkte und Tore
     * (namePkt, nameTor) ersetzt die Texte 37 und 38; im Pokal nicht (wie lmo-openfile.php).
     */
    public function fuerLiga(Liga $liga): self
    {
        if ($liga->istPokal()) {
            return $this;
        }
        $kern = $this->kern;
        if (($liga->optionen['namePkt'] ?? '') != '') {
            $kern[37] = $liga->optionen['namePkt'];
        }
        if (($liga->optionen['nameTor'] ?? '') != '') {
            $kern[38] = $liga->optionen['nameTor'];
        }

        return new self($kern, $this->weitere);
    }

    /**
     * Alle Texte wieder im Format des alten Codes (fuer Dateien, die noch eingebunden werden).
     *
     * @return array<int|string, string|array<int, string>>
     */
    public function alsAltesArray(): array
    {
        return $this->kern + $this->weitere;
    }

    /**
     * Liest eine Sprachdatei in $text; eine fehlende Datei aendert nichts.
     *
     * @param array<int, string> $text
     */
    private static function leseDatei(array &$text, string $datei): void
    {
        $zeilen = is_file($datei) ? file($datei) : false;
        if ($zeilen === false) {
            return;
        }
        foreach ($zeilen as $zeile) {
            $paar = explode('=', trim($zeile), 2);
            if (is_numeric($paar[0])) {
                $text[intval($paar[0])] = $paar[1] ?? '';
            }
        }
    }
}
