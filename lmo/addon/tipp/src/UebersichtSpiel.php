<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Ein Spiel in der Tippuebersicht ueber alle Ligen eines Tippers ("Spiele der naechsten Tage"):
 * das Spiel zum Anzeigen, wo es hingehoert (Liga, Spieltag, Nummer wie im Schluessel der Tippdatei)
 * und der Tipp des Tippers. Tipps und Joker bleiben Text wie in der Datei, weil sie so auch wieder
 * hineingeschrieben werden. Bis 4.5 die parallelen Arrays $liga, $spieltag, $spiel, $tippa, $tippb,
 * $jksp und $tipp_jokertippaktiv in lmo-tippviewer.php.
 */
final class UebersichtSpiel
{
    /**
     * @param string $liga Ligadatei ohne .l98
     * @param string $spieltag Nummer des Spieltags
     * @param string $nr Nummer des Spiels wie im Schluessel: Partie, im Pokal gefolgt vom Spiel der Paarung
     * @param int|string $tippa Tore Heim; "_" ohne Tipp, '' nach dem Leeren fuer die Anzeige
     * @param int|string $tippb Tore Gast, wie $tippa
     * @param int|string $joker Spiel mit Joker in diesem Spieltag, 0 ohne, '' ohne Tipp
     * @param int|string $jokerBis bis wann der Joker gesetzt werden kann (Zeitstempel), "0" ohne Joker
     */
    public function __construct(
        public readonly AnstehendesSpiel $spiel,
        public readonly string $liga,
        public readonly string $spieltag,
        public readonly string $nr,
        public int|string $tippa = '',
        public int|string $tippb = '',
        public int|string $joker = '',
        public int|string $jokerBis = '0',
    ) {
    }

    /** Pokal oder Liga (Type der Liga). */
    public function pokal(): bool
    {
        return $this->spiel->typ != 0;
    }
}
