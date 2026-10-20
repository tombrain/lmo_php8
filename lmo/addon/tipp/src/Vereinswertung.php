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

/**
 * Die Tipp-Punkte, die Tipper bei Spielen einer Mannschaft geholt haben, je Mannschaft (Verein)
 * zusammengezaehlt. Quelle sind die Auswertungen je Tipper
 * (tipps/auswert/vereine/<liga>_<nick>.ver: ein Abschnitt [Mannschaft] mit SG<Spieltag> getippten
 * Spielen und TP<Spieltag> Punkten). Bis 4.5 lmo-tippcalcwertverein.php.
 */
final class Vereinswertung
{
    /**
     * @param list<int> $reihenfolge die Mannschaften vom ersten zum letzten Platz
     * @param array<int, array{spiele: int|float, punkte: int|float}> $werte je Mannschaft
     */
    private function __construct(public readonly array $reihenfolge, public readonly array $werte)
    {
    }

    /**
     * Liest die Auswertungen und zaehlt bis zum Spieltag $bis (unter 1: alle).
     *
     * @param list<string> $pfade vorhandene Auswertungen, je Tipper eine
     * @param bool $ergebnismodus Tippmodus 1 (Ergebnisse); sonst Tendenz, dort zaehlt die Quote in Prozent
     */
    public static function lies(array $pfade, int $bis, int $spieltage, bool $ergebnismodus): self
    {
        if ($bis < 1) {
            $bis = $spieltage;
        }
        $werte = [];
        $anzahl = 0;
        foreach ($pfade as $pfad) {
            // wie bis 4.5 zaehlen die Mannschaften der zuletzt gelesenen Datei
            $anzahl = 0;
            $verein = '';
            foreach (file($pfad) ?: [] as $zeile) {
                $zeile = trim($zeile);
                if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                    $verein = trim(substr($zeile, 1, -1));
                    $anzahl++;
                } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';')) {
                    $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
                    $wert = trim(substr($zeile, (int) strpos($zeile, '=') + 1));
                    $art = substr($schluessel, 0, 2);
                    if ((int) substr($schluessel, 2) - 1 >= $bis || !in_array($art, ['SG', 'TP'], true)) {
                        continue;
                    }
                    $nr = (int) $verein;
                    $werte[$nr] ??= ['spiele' => 0, 'punkte' => 0];
                    $werte[$nr][$art == 'SG' ? 'spiele' : 'punkte'] += is_numeric($wert) ? +$wert : 0;
                }
            }
        }

        $tab0 = [];
        $alle = [];
        for ($a = 1; $a <= $anzahl; $a++) {
            $alle[$a] = $werte[$a] ?? ['spiele' => 0, 'punkte' => 0];
            $quote = self::quote($alle[$a]['punkte'], $alle[$a]['spiele'], $ergebnismodus);
            $tab0[] = (50000000 + ($quote === '0' ? 0 : (float) $quote * 100)) . (50000000 + $alle[$a]['punkte']) . (50000000 - $a) . (50000000 + $a);
        }
        rsort($tab0, SORT_STRING);
        $reihenfolge = array_map(static fn (string $zeile): int => intval(substr($zeile, 25)), $tab0);

        return new self($reihenfolge, $alle);
    }

    /** Der Platz einer Mannschaft (ab 1), 0 wenn sie fehlt. */
    public function platz(int $mannschaft): int
    {
        $platz = array_search($mannschaft, $this->reihenfolge, true);

        return $platz === false ? 0 : $platz + 1;
    }

    /**
     * Punkte je getipptem Spiel (im Tendenz-Modus in Prozent) mit zwei Stellen, '0' ohne Spiele.
     */
    public static function quote(int|float $punkte, int|float $spiele, bool $ergebnismodus): string
    {
        if ($spiele == 0) {
            return '0';
        }

        return number_format($punkte / $spiele * ($ergebnismodus ? 1 : 100), 2, '.', ',');
    }
}
