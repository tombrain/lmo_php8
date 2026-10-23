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

use Closure;
use Lmo\Liga\Rohdaten\Partie;
use Lmo\Liga\Spiel;

/**
 * Eine Spalte, die ein Addon zu den Ergebnissen eines Spieltags in der Administration beitraegt
 * (MitErgebnisSpalten): je Spiel eine Auswahl, etwa "ohne Tippwertung". Das Feld heisst
 * <feld><Nummer der Partie ab 0>, im Pokal mit der Nummer des Spiels dahinter.
 */
final class ErgebnisSpalte
{
    /**
     * @param string $feld Name des Formularfelds ohne Nummer (z. B. xmtipp)
     * @param string $titel Ueberschrift und Tooltip (HTML)
     * @param array<int|string, string> $optionen Wert => Text (HTML)
     * @param Closure(Spiel): string $wert der gewaehlte Wert eines Spiels
     * @param Closure(Partie, string, int): void $uebernimm traegt den gesendeten Wert in die Partie ein (Partie, Wert, Spiel ab 1)
     */
    public function __construct(
        public readonly string $feld,
        public readonly string $titel,
        public readonly array $optionen,
        private Closure $wert,
        private Closure $uebernimm,
    ) {
    }

    /** Der gewaehlte Wert eines Spiels. */
    public function wert(Spiel $spiel): string
    {
        return ($this->wert)($spiel);
    }

    /** Traegt den gesendeten Wert in die Partie ein (Spiel ab 1). */
    public function uebernimm(Partie $partie, string $wert, int $spiel): void
    {
        ($this->uebernimm)($partie, $wert, $spiel);
    }
}
