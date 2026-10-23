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

use Closure;

/**
 * Was die Seiten-Klassen an Bildern brauchen, austauschbar fuer Tests: das kleine und das grosse
 * Vereinswappen einer Mannschaft, das kleine Symbol einer Liga und das kleine Bild eines Spielers.
 * In der Anwendung kommen sie aus Lmo\Web\Bilder (bis 4.5 HTML_smallTeamIcon() und Verwandte der
 * Classlib).
 */
final class Darstellung
{
    /** @var Closure(string, string, string): string */
    private Closure $wappen;
    /** @var Closure(string, string): string */
    private Closure $ligaSymbol;
    /** @var Closure(string, string): string */
    private Closure $spielerbild;
    /** @var Closure(string, string, string): string */
    private Closure $wappenGross;

    /**
     * @param Closure(string, string, string): string $wappen Ligadatei, Name der Mannschaft, weitere Attribute => <img ...> oder ''
     * @param (Closure(string, string): string)|null $ligaSymbol Ligadatei, weitere Attribute => <img ...> oder ''; null: keins
     * @param (Closure(string, string): string)|null $spielerbild Name des Spielers, weitere Attribute => <img ...> oder ''; null: keins
     * @param (Closure(string, string, string): string)|null $wappenGross wie $wappen fuer das grosse Wappen; null: keins
     */
    public function __construct(Closure $wappen, ?Closure $ligaSymbol = null, ?Closure $spielerbild = null, ?Closure $wappenGross = null)
    {
        $this->wappen = $wappen;
        $this->ligaSymbol = $ligaSymbol ?? static fn (string $datei, string $attribute): string => '';
        $this->spielerbild = $spielerbild ?? static fn (string $name, string $attribute): string => '';
        $this->wappenGross = $wappenGross ?? static fn (string $datei, string $mannschaft, string $attribute): string => '';
    }

    /** Das grosse Wappen einer Mannschaft als HTML, '' ohne Bild. */
    public function wappenGross(string $datei, string $mannschaft, string $attribute = ''): string
    {
        return ($this->wappenGross)($datei, $mannschaft, $attribute);
    }

    /** Das kleine Bild eines Spielers als HTML, '' ohne Bild. */
    public function spielerbild(string $name, string $attribute = ''): string
    {
        return ($this->spielerbild)($name, $attribute);
    }

    /** Das kleine Symbol einer Liga als HTML, '' ohne Bild. */
    public function ligaSymbol(string $datei, string $attribute = ''): string
    {
        return ($this->ligaSymbol)($datei, $attribute);
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
