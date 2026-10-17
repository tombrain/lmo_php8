<?php

declare(strict_types=1);

namespace Lmo\Addon;

use Lmo\Web\Kontext;

/**
 * Bruecke zu einem Addon nach alter Art, das unter einer Seite eine Datei einbindet (bisher
 * addon/pdf/lmo-showprogram.inc.php unter dem Spielplan, "Teamplan", nicht Teil des LMO). Die
 * Datei sieht $file, $text, $lmo_liga und die Parameter der Anfrage als Variablen (wie bisher
 * durch extract($_GET) in init.php, z. B. $selteam); was sie ausgibt, kommt unter die Seite.
 */
final class AltesSeitenInclude implements MitZusatzUnterSeite
{
    /**
     * @param string $action die Seite, unter der die Datei eingebunden wird
     */
    public function __construct(
        private string $name,
        private string $action,
        private string $datei,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function zusatzUnterSeite(Kontext $kontext): string
    {
        if ($kontext->action !== $this->action || $kontext->liga === null) {
            return '';
        }

        return (static function (string $lmo_addon_datei, array $lmo_addon_parameter, string $file, array $text, \Lmo\Liga\Rohdaten\Liga $lmo_liga): string {
            extract($lmo_addon_parameter, EXTR_SKIP);
            ob_start();
            include $lmo_addon_datei;

            return (string) ob_get_clean();
        })($this->datei, $kontext->anfrage->alle(), $kontext->liga->datei, $kontext->text + $kontext->weitereTexte, $kontext->liga->liga);
    }
}
