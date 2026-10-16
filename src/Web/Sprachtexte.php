<?php

namespace Lmo\Web;

use Lmo\Liga\Rohdaten\Liga;

/**
 * Die Sprachtexte des LMO (lang/lang-<Sprache>.txt, Zeilen "Nummer=Text"), wie lmo-langload.php
 * sie liest: erst Deutsch, darueber die Standardsprache, darueber die Sprache des Besuchers; so
 * bleiben bei unvollstaendigen Uebersetzungen die deutschen Texte stehen. Die Texte der Addons
 * (lang/<addon>/lang-<Sprache>.txt) stehen unter dem Namen des Addons.
 */
final class Sprachtexte
{
    /**
     * @param string $ordner Ordner der Sprachdateien (PATH_TO_LANGDIR)
     * @param string $standard Standardsprache der Installation (deflang)
     * @param string|null $besucher vom Besucher gewaehlte Sprache, null ohne Wahl
     * @return array<int|string, mixed> Nummer => Text, Addon => (Nummer => Text)
     */
    public static function lies(string $ordner, string $standard, ?string $besucher = null): array
    {
        $text = [];
        self::leseDatei($text, $ordner . '/lang-Deutsch.txt');
        self::leseDatei($text, $ordner . '/lang-' . $standard . '.txt');
        if ($besucher !== null && $besucher != $standard) {
            self::leseDatei($text, $ordner . '/lang-' . $besucher . '.txt');
        }
        // Addons in fester (alphabetischer) Reihenfolge
        foreach (scandir($ordner) ?: [] as $addon) {
            if ($addon == '.' || $addon == '..' || !is_dir($ordner . '/' . $addon)) {
                continue;
            }
            $addonText = [];
            self::leseDatei($addonText, $ordner . '/' . $addon . '/lang-' . $standard . '.txt');
            if ($besucher !== null) {
                self::leseDatei($addonText, $ordner . '/' . $addon . '/lang-' . $besucher . '.txt');
            }
            if ($addonText !== []) {
                $text[$addon] = $addonText;
            }
        }

        return $text;
    }

    /**
     * Die Texte fuer die Seiten einer Liga: Eine eigene Bezeichnung der Liga fuer Punkte und Tore
     * (namePkt, nameTor) ersetzt die Texte 37 und 38; im Pokal nicht (wie lmo-openfile.php).
     *
     * @param array<int|string, mixed> $text
     * @return array<int|string, mixed>
     */
    public static function fuerLiga(array $text, Liga $liga): array
    {
        if ($liga->istPokal()) {
            return $text;
        }
        if (($liga->optionen['namePkt'] ?? '') != '') {
            $text[37] = $liga->optionen['namePkt'];
        }
        if (($liga->optionen['nameTor'] ?? '') != '') {
            $text[38] = $liga->optionen['nameTor'];
        }

        return $text;
    }

    /**
     * Liest eine Sprachdatei in $text; eine fehlende Datei aendert nichts.
     *
     * @param array<int|string, mixed> $text
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
