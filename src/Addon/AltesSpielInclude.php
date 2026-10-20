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

use Lmo\Liga\Spiel;
use Lmo\Web\Kontext;

/**
 * Bruecke zu einem Addon nach alter Art, das am Ende jeder Ergebniszeile eine Datei einbindet
 * (bisher addon/stats/lmo-showprogram.inc.php in der Liga und addon/stats/lmo-showkoresults.inc.php
 * im Pokal, "bisherige Ergebnisse", nicht Teil des LMO). Die Datei sieht wie zuletzt $lmo_spiel,
 * $i (Partie ab 0), $j (Spieltag ab 0), $n (Spiel der Paarung ab 0), $st und $text; was sie
 * ausgibt, kommt in die Zeile.
 */
final class AltesSpielInclude implements MitZusatzJeSpiel
{
    /**
     * @param bool|null $pokal true: nur im Pokal, false: nur in der Liga, null: in beiden
     */
    public function __construct(
        private string $name,
        private string $datei,
        private ?bool $pokal = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function zusatzJeSpiel(Spiel $spiel, Kontext $kontext): string
    {
        if ($this->pokal !== null && $this->pokal !== ($kontext->liga?->liga->istPokal() ?? false)) {
            return '';
        }

        return (static function (string $lmo_addon_datei, Spiel $lmo_spiel, array $text): string {
            $i = $lmo_spiel->partie - 1;
            $j = $lmo_spiel->spieltag - 1;
            $n = $lmo_spiel->nr - 1;
            $st = $lmo_spiel->spieltag;
            ob_start();
            include $lmo_addon_datei;

            return (string) ob_get_clean();
        })($this->datei, $spiel, $kontext->text + $kontext->weitereTexte);
    }
}
