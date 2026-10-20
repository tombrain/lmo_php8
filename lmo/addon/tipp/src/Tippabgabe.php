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

use Lmo\Web\Anfrage;

/**
 * Die Tipps eines Spieltags aus dem Formular der Tippabgabe: Nur Spiele, die noch getippt werden
 * koennen, uebernehmen die Eingabe; die anderen behalten ihren bisherigen Tipp. Ein Joker auf
 * einem Spiel, das nicht mehr getippt werden kann, bleibt, wo er war. Bis 4.5 der Speicherteil
 * von lmo-tippedit.php und lmo-tippjokeranticheat.php.
 */
final class Tippabgabe
{
    /**
     * @param array<int, mixed> $tipps die neuen Tipps wie Tippdatei::runde()
     * @param string|null $joker das Spiel mit Joker; null ohne Jokertipp
     * @param bool $jokerGesperrt der Joker lag schon auf einem Spiel, das nicht mehr getippt werden kann
     */
    private function __construct(
        public readonly array $tipps,
        public readonly ?string $joker,
        public readonly bool $jokerGesperrt,
    ) {
    }

    /**
     * @param array<int, mixed> $bisher die Tipps aus der Tippdatei (Tippdatei::runde())
     * @param string $bisherJoker der Joker aus der Tippdatei
     * @param int $tippmodus 1 = Ergebnisse, 0 = Tendenz (1, 0, 2)
     * @param bool $jokertipp mit Joker (jokertipp)
     * @param \Closure(int): bool $aenderbar Anstoss des Spiels => kann es noch getippt werden?
     */
    public static function aus(Anfrage $anfrage, Tipprunde $runde, array $bisher, string $bisherJoker, int $tippmodus, bool $jokertipp, \Closure $aenderbar): self
    {
        $tipps = $bisher;
        $joker = $jokertipp ? trim($anfrage->text('xjokerspiel')) : null;
        foreach ($runde->paarungen as $i => $paarung) {
            foreach ($runde->pokal ? ($runde->spieleJePaarung > 0 ? range(0, $runde->spieleJePaarung - 1) : []) : [0] as $n) {
                if (!$aenderbar((int) ($paarung->spiele[$n]->anstoss ?? 0))) {
                    continue;
                }
                $feld = $runde->pokal ? $i . $n : (string) $i;
                $tipp = $tippmodus == 1 ? self::ergebnis($anfrage, $feld) : self::tendenz($anfrage, $feld);
                if ($runde->pokal) {
                    $spiele = is_array($tipps[$i] ?? null) ? $tipps[$i] : [];
                    $spiele[$n] = $tipp;
                    $tipps[$i] = $spiele;
                } else {
                    $tipps[$i] = $tipp;
                }
            }
        }

        // Joker-Hack: lag der Joker schon auf einem Spiel, das nicht mehr getippt werden kann, bleibt er dort
        $gesperrt = false;
        if ($joker !== null && $joker != $bisherJoker && $bisherJoker > 0) {
            if (!$runde->pokal) {
                $anstoss = $runde->paarungen[(int) $bisherJoker - 1]->spiele[0]->anstoss ?? 0;
            } else {
                $anstoss = $runde->paarungen[(int) substr($bisherJoker, 0, -1) - 1]->spiele[(int) substr($bisherJoker, -1) - 1]->anstoss ?? 0;
            }
            if (!$aenderbar((int) $anstoss)) {
                $joker = $bisherJoker;
                $gesperrt = true;
            }
        }

        return new self($tipps, $joker, $gesperrt);
    }

    /**
     * Ein getipptes Ergebnis: Tore beider Mannschaften als Zahlen; leer, "_" oder kleiner 0 heisst
     * kein Tipp. Wie bis 4.5 der Reihe nach: eine leere Heimseite setzt beide auf -1, eine gueltige
     * Gastseite danach aber wieder ihre Tore.
     *
     * @return array{0: int, 1: int}
     */
    private static function ergebnis(Anfrage $anfrage, string $feld): array
    {
        $a = trim($anfrage->text('xtippa' . $feld));
        $b = trim($anfrage->text('xtippb' . $feld));
        $kein = static fn (string $wert): bool => $wert == '' || $wert < 0 || $wert == '_';
        $tipp = [-1, -1];
        if (!$kein($a)) {
            $tipp[0] = intval($a);
        }
        if ($kein($b)) {
            $tipp = [-1, -1];
        } else {
            $tipp[1] = intval($b);
        }

        return $tipp;
    }

    /**
     * Eine getippte Tendenz: 1 Heimsieg, 2 Auswaertssieg, 3 Unentschieden, sonst kein Tipp.
     *
     * @return array{0: string, 1: string}
     */
    private static function tendenz(Anfrage $anfrage, string $feld): array
    {
        $wert = $anfrage->text('xtipp' . $feld, '0');

        return match (true) {
            $wert == 1 => ['1', '0'],
            $wert == 2 => ['0', '1'],
            $wert == 3 => ['0', '0'],
            default => ['-1', '-1'],
        };
    }
}
