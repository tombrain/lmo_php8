<?php

declare(strict_types=1);

namespace Lmo\Web;

use Closure;

/**
 * Was die Seiten-Klassen aus dem alten Code des LMO brauchen, solange er noch als Funktionen
 * vorliegt: das kleine Vereinswappen einer Mannschaft (HTML_smallTeamIcon() in lmo-functions.php).
 */
final class Darstellung
{
    /** @var Closure(string, string, string): string */
    private Closure $wappen;

    /**
     * @param Closure(string, string, string): string $wappen Ligadatei, Name der Mannschaft, weitere Attribute => <img ...> oder ''
     */
    public function __construct(Closure $wappen)
    {
        $this->wappen = $wappen;
    }

    /** Das kleine Wappen einer Mannschaft als HTML, '' ohne Bild. */
    public function wappen(string $datei, string $mannschaft, string $attribute = ''): string
    {
        return ($this->wappen)($datei, $mannschaft, $attribute);
    }

    /**
     * Ein Wert geteilt durch den Faktor der Liga (Tore oder Punkte, die mit Faktor gespeichert
     * sind), wie applyFactor() in lmo-functions.php: unveraendert, wenn Wert oder Faktor 0 sind.
     *
     * @param int|float|string $wert
     * @return int|float|string
     */
    public static function mitFaktor($wert, int $faktor)
    {
        if (is_numeric($wert) && $wert != 0 && $faktor != 0) {
            return $wert / $faktor;
        }

        return $wert;
    }
}
