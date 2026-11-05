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

namespace Lmo\Addon\Mini;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Mannschaft;

/**
 * Die Mannschaften und Spiele einer Liga fuer den Mini-Spielplan: das naechste Spiel einer
 * Mannschaft, die Spiele zweier Mannschaften gegeneinander, die Suche nach Namen. Bis 4.5 die
 * Klasse liga der Classlib (loadFile(), gamesSortedForTeam(), allPartieForTeams(), teamForName())
 * und findTeamName(); es zaehlt, was dort zaehlte.
 */
final class Begegnungen
{
    /**
     * @param list<int> $nummern die Mannschaften, die zaehlen (mit eigenem Abschnitt [Team<n>]), in der Reihenfolge der Datei
     * @param list<Spiel> $spiele alle Spiele, je Spieltag und Partie in der Reihenfolge der Datei
     */
    private function __construct(private Liga $liga, private array $nummern, public readonly array $spiele)
    {
    }

    public static function aus(Liga $liga): self
    {
        $nummern = [];
        foreach ($liga->mannschaften as $nr => $mannschaft) {
            if ($mannschaft->name !== null && isset($liga->mannschaften[count($nummern) + 1]) && $liga->mannschaften[count($nummern) + 1]->werte) {
                $nummern[] = $nr;
            }
        }
        $pokal = $liga->istPokal();
        $spiele = [];
        $spieltage = $liga->spieltage;
        ksort($spieltage);
        $runde = 1;
        while (isset($spieltage[$runde])) {
            $spieltag = $spieltage[$runde];
            // so viele Spiele je Partie, wie der Modus der Runde sagt, mindestens eins
            $modus = $spieltag->werte['MO'] ?? '';
            for ($p = 1; isset($spieltag->partien[$p]) && $spieltag->partien[$p]->heim !== null; $p++) {
                $partie = $spieltag->partien[$p];
                $n = 0;
                do {
                    $n++;
                    $werte = $partie->spiele[$n] ?? [];
                    $nummer = isset($werte['GA']) ? $p . ($pokal ? (string) $n : '') : $p . (string) $n;
                    $heim = (int) $partie->heim;
                    $gast = (int) $partie->gast;
                    // das Rueckspiel einer Paarung mit getauschtem Heimrecht
                    $spiele[] = new Spiel($runde, $nummer, (string) ($werte['AT'] ?? ''), (string) ($werte['NT'] ?? ''),
                        $n % 2 ? $heim : $gast, $n % 2 ? $gast : $heim, (string) ($werte['GA'] ?? ''), (string) ($werte['GB'] ?? ''));
                } while ($n < $modus);
            }
            $runde++;
        }

        return new self($liga, $nummern, $spiele);
    }

    /** Die Mannschaft zu einer Nummer (ab 1); null, wenn es sie nicht gibt. */
    public function mannschaft(int|string|null $nr): ?int
    {
        if ($nr === null || !($nr > 0) || $nr > count($this->nummern)) {
            return null;
        }

        return $this->nummern[(int) $nr - 1];
    }

    /** Die Mannschaft mit genau diesem Namen; null, wenn es sie nicht gibt. */
    public function mannschaftNamens(string $name): ?int
    {
        foreach ($this->nummern as $nr) {
            if (strcmp((string) $this->liga->mannschaften[$nr]->name, $name) == 0) {
                return $nr;
            }
        }

        return null;
    }

    /** @return list<string> die Namen der Mannschaften */
    public function namen(): array
    {
        return array_map(fn (int $nr): string => (string) $this->liga->mannschaften[$nr]->name, $this->nummern);
    }

    /** Name, Mittel- und Kurzname einer Mannschaft, wie sie in der Datei stehen */
    public function daten(int $nr): Mannschaft
    {
        return $this->liga->mannschaften[$nr] ?? new Mannschaft();
    }

    /**
     * Die Spiele einer Mannschaft nach dem Anstoss, bei gleichem Anstoss nach Spieltag und Nummer.
     *
     * @return list<Spiel>
     */
    public function spieleNachZeit(int $mannschaft): array
    {
        $spiele = array_values(array_filter($this->spiele, static fn (Spiel $s): bool => $s->heim == $mannschaft || $s->gast == $mannschaft));
        usort($spiele, static fn (Spiel $a, Spiel $b): int => [$a->zeit, $a->spieltag, $a->nummer] <=> [$b->zeit, $b->spieltag, $b->nummer]);

        return $spiele;
    }

    /**
     * Die Spiele von $heim gegen $gast, mit $beide auch die mit getauschtem Heimrecht.
     *
     * @return list<Spiel>
     */
    public function zwischen(int $heim, int $gast, bool $beide): array
    {
        return array_values(array_filter($this->spiele, static fn (Spiel $s): bool => ($s->heim == $heim && $s->gast == $gast) || ($beide && $s->heim == $gast && $s->gast == $heim)));
    }

    /**
     * Unscharfe Suche nach einem Namen: alle Namen, die ohne Leerzeichen, Ziffern und Sonderzeichen
     * gleich sind (mehrere Treffer heissen: zu ungenau).
     *
     * @param list<string> $namen
     * @return list<string>
     */
    public static function aehnlicheNamen(array $namen, string $suche): array
    {
        $ausdruck = '/\s+|\d+|\W/i';
        $gesucht = strtolower((string) preg_replace($ausdruck, '', $suche));

        return array_values(array_filter($namen, static fn (string $name): bool => strtolower((string) preg_replace($ausdruck, '', $name)) == $gesucht));
    }
}
