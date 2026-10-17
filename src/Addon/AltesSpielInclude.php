<?php

namespace Lmo\Addon;

use Lmo\Liga\Spiel;
use Lmo\Web\Kontext;

/**
 * Bruecke zu einem Addon nach alter Art, das am Ende jeder Ergebniszeile eine Datei einbindet
 * (bisher addon/stats/lmo-showprogram.inc.php, "bisherige Ergebnisse", nicht Teil des LMO). Die
 * Datei sieht wie zuletzt $lmo_spiel, $i (Partie ab 0), $j (Spieltag ab 0), $st und $text; was
 * sie ausgibt, kommt in die Zeile.
 */
final class AltesSpielInclude implements MitZusatzJeSpiel
{
    public function __construct(
        private string $name,
        private string $datei,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function zusatzJeSpiel(Spiel $spiel, Kontext $kontext): string
    {
        return (static function (string $lmo_addon_datei, Spiel $lmo_spiel, array $text): string {
            $i = $lmo_spiel->partie - 1;
            $j = $lmo_spiel->spieltag - 1;
            $st = $lmo_spiel->spieltag;
            ob_start();
            include $lmo_addon_datei;

            return (string) ob_get_clean();
        })($this->datei, $spiel, $kontext->text);
    }
}
