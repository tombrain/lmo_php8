<?php

declare(strict_types=1);

namespace Lmo\Addon\Spieler;

/**
 * Die Spielerstatistik einer Liga aus addon/spieler/stats/<liga>.stat: eine Zeile mit den
 * Spaltennamen, bei Formelspalten eine Zeile mit den Formeln, dann je Spieler eine Zeile, die
 * Felder an "#" getrennt. Die Datei schreibt der Admin (lmo-statadmin.php).
 */
final class Statistik
{
    /** Markiert im Spaltennamen eine Formelspalte */
    private const FORMEL = '*_*-*';

    /**
     * @param list<string> $spalten Spaltennamen (ohne Formel-Markierung)
     * @param list<list<string|null>> $zeilen Zeilen der Spieler, die gezeigt werden (vereinsweise: nur die des Vereins)
     * @param array<int, true> $textspalten Spalten mit mindestens einem Wert, der keine Zahl ist
     */
    private function __construct(
        public readonly array $spalten,
        public readonly array $zeilen,
        public readonly array $textspalten,
    ) {
    }

    /**
     * Liest die Statistik; null, wenn die Datei fehlt oder nicht lesbar ist.
     *
     * @param string $verein Verein, dessen Spieler gezeigt werden ('' = alle)
     * @param bool $vereinsweise vereinsweise anzeigen (Einstellung); sonst gilt $verein nicht
     * @param string $vereinsspalte Name der Spalte mit dem Verein (Sprachtext)
     */
    public static function lies(string $datei, string $verein, bool $vereinsweise, string $vereinsspalte): ?self
    {
        if (!is_readable($datei) || !($zeiger = fopen($datei, 'r+b'))) {
            return null;
        }
        $spalten = [];
        $formel = false;
        $vereinsnr = null;
        foreach (fgetcsv($zeiger, separator: '#', escape: '') ?: [] as $i => $spalte) {
            $spalte = (string) $spalte;
            if (str_contains($spalte, self::FORMEL)) {
                $formel = true;
                $spalte = substr($spalte, 0, strlen($spalte) - 5);
            }
            if ($spalte == $vereinsspalte) {
                $vereinsnr = $i;
            }
            $spalten[] = $spalte;
        }
        if ($formel) {
            fgetcsv($zeiger, separator: '#', escape: '');  // Zeile mit Formeln
        }

        $zeilen = [];
        $textspalten = [];
        while ($zeile = fgetcsv($zeiger, separator: '#', escape: '')) {
            if (($vereinsnr !== null && isset($zeile[$vereinsnr]) && $vereinsweise && $verein == $zeile[$vereinsnr]) || $verein == '') {
                foreach ($zeile as $i => $wert) {
                    if (!is_numeric($wert)) {
                        $textspalten[$i] = true;
                    }
                }
                $zeilen[] = array_values($zeile);
            }
        }
        fclose($zeiger);

        return new self($spalten, $zeilen, $textspalten);
    }

    /**
     * Die Zeilen sortiert: Zahlenspalten nach Wert, Textspalten nach dem Text ohne Akzente.
     *
     * @param int $spalte Sortierspalte
     * @param bool $absteigend Richtung 1
     * @param bool $nullwerte Zeilen mit 0 in einer Zahlenspalte zeigen
     * @return list<list<string|null>>
     */
    public function sortiert(int $spalte, bool $absteigend, bool $nullwerte): array
    {
        $zeilen = $this->zeilen;
        $text = isset($this->textspalten[$spalte]);
        if (!$nullwerte && !$text) {
            $zeilen = array_values(array_filter($zeilen, static fn (array $zeile): bool => ($zeile[$spalte] ?? null) != 0));  // Nullwerte ausfiltern
        }
        if ($text) {
            usort($zeilen, static fn (array $a, array $b): int => ($absteigend ? -1 : 1) * strnatcasecmp(self::ohneAkzente($a[$spalte] ?? ''), self::ohneAkzente($b[$spalte] ?? '')));
        } else {
            usort($zeilen, static function (array $a, array $b) use ($spalte, $absteigend): int {
                $wertA = $a[$spalte] ?? null;
                $wertB = $b[$spalte] ?? null;
                if ($wertB == $wertA) {
                    return 0;
                }

                return ($wertA > $wertB) === $absteigend ? -1 : 1;
            });
        }

        return $zeilen;
    }

    private static function ohneAkzente(string $wert): string
    {
        return strtr($wert, '¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ', 'YuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
    }
}
