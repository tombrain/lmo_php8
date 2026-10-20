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
 * Was alle Tipper fuer einen Spieltag getippt haben, zusammengezaehlt je Spiel: die Tendenzen
 * (Heimsieg, Unentschieden, Auswaertssieg), die getippten Tore und wie viele getippt haben. Quelle
 * ist die Einsicht des Spieltags (tipps/einsicht/<liga>_<st>.ein, ein Abschnitt je Tipper). Bis 4.5
 * lmo-tippcalceinsicht.php.
 */
final class Einsichtstatistik
{
    /**
     * @param array<int|string, array{t1: int, t0: int, t2: int, a: int|float, b: int|float, anz: int}> $spiele Spiel (i bzw. im Pokal "i-n", ab 0) => Zahlen
     * @param list<array{nick: string, joker: string, tipps: array<int|string, array{0: string, 1: string}>}> $tipper die Tipper in der Reihenfolge der Datei
     */
    private function __construct(private array $spiele, private array $tipper)
    {
    }

    /** Liest die Einsicht; null, wenn es sie (noch) nicht gibt. */
    public static function lies(string $pfad, bool $pokal): ?self
    {
        if (!file_exists($pfad)) {
            return null;
        }
        $spiele = [];
        $tipper = null;
        $heim = [];
        $alle = [];
        // der Joker gilt im Abschnitt des Tippers ab seiner @-Zeile, sonst -1
        $joker = '-1';
        foreach (file($pfad) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if (str_starts_with($zeile, '@') && str_ends_with($zeile, '@')) {
                $joker = trim(substr($zeile, 1, -1));
                continue;
            }
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
                $joker = '-1';
                if ($abschnitt !== $tipper) {
                    // ein neuer Tipper: seine Heimtore sind noch nicht getippt
                    $tipper = $abschnitt;
                    $heim = [];
                    $alle[] = ['nick' => $abschnitt, 'joker' => '', 'tipps' => []];
                }
                continue;
            }
            if (!str_contains($zeile, '=') || str_starts_with($zeile, ';')) {
                continue;
            }
            $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
            $wert = trim(substr($zeile, (int) strpos($zeile, '=') + 1));
            $spiel = $pokal
                ? ((int) substr($schluessel, 2, -1) - 1) . '-' . ((int) substr($schluessel, -1) - 1)
                : (string) ((int) substr($schluessel, 2) - 1);
            $zahlen = $spiele[$spiel] ?? ['t1' => 0, 't0' => 0, 't2' => 0, 'a' => 0, 'b' => 0, 'anz' => 0];
            $letzter = array_key_last($alle);
            if ($letzter !== null && in_array(substr($schluessel, 0, 2), ['GA', 'GB'], true)) {
                $alle[$letzter]['joker'] = $joker;
                $alle[$letzter]['tipps'][$spiel] ??= [Tippdatei::KEIN_TIPP, Tippdatei::KEIN_TIPP];
                $alle[$letzter]['tipps'][$spiel][substr($schluessel, 0, 2) == 'GA' ? 0 : 1] = $wert;
            }
            if (substr($schluessel, 0, 2) == 'GA') {
                $heim[$spiel] = $wert;
                if (is_numeric($wert) && $wert > 0) {
                    $zahlen['a'] += +$wert;
                }
            } elseif (substr($schluessel, 0, 2) == 'GB') {
                if (is_numeric($wert) && $wert >= 0) {
                    $zahlen['b'] += +$wert;
                    $zahlen['anz']++;
                }
                // wie bis 4.5: ohne Heimtipp wird mit "_" verglichen
                $a = $heim[$spiel] ?? '_';
                if ($wert < $a) {
                    $zahlen['t1']++;
                } elseif ($wert > $a) {
                    $zahlen['t2']++;
                } elseif ($a >= 0 && $wert >= 0) {
                    $zahlen['t0']++;
                }
            } else {
                continue;
            }
            $spiele[$spiel] = $zahlen;
        }

        return new self($spiele, $alle);
    }

    /**
     * Die Tipper mit ihren Tipps (Spiel wie in spiel(): "i" bzw. "i-n" => [Heim, Gast] wie in der
     * Datei, KEIN_TIPP fehlt er) und ihrem Joker (-1 ohne), in der Reihenfolge der Datei.
     *
     * @return list<array{nick: string, joker: string, tipps: array<int|string, array{0: string, 1: string}>}>
     */
    public function tipper(): array
    {
        return $this->tipper;
    }

    /**
     * Die Zahlen eines Spiels (Paarung $i, im Pokal Spiel $n, ab 0); 0, wo keiner getippt hat.
     *
     * @return array{t1: int, t0: int, t2: int, a: int|float, b: int|float, anz: int}
     */
    public function spiel(int $i, ?int $n = null): array
    {
        return $this->spiele[$n === null ? (string) $i : $i . '-' . $n] ?? ['t1' => 0, 't0' => 0, 't2' => 0, 'a' => 0, 'b' => 0, 'anz' => 0];
    }
}
