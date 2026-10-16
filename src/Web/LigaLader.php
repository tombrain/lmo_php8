<?php

namespace Lmo\Web;

use Closure;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;

/**
 * Laedt die Liga, die eine Seite zeigen soll, wie lmo-openfile.php: aus dem Ligenordner, nur
 * .l98-Dateien und nur, wenn der Besucher sie sehen darf (Hilfsadmins nur ihre eigenen Ligen).
 */
final class LigaLader
{
    private string $ligenordner;
    private string $datumsformat;
    /** @var Closure(string): bool */
    private Closure $darfLesen;

    /**
     * @param string $ligenordner Pfad des Ligenordners, mit "/" am Ende (PATH_TO_LMO . '/' . dirliga)
     * @param string $datumsformat Datumsformat fuer den Stand der Datei
     * @param (Closure(string): bool)|null $darfLesen Ligadatei => darf der Besucher sie sehen? (check_hilfsadmin)
     */
    public function __construct(string $ligenordner, string $datumsformat, ?Closure $darfLesen = null)
    {
        $this->ligenordner = $ligenordner;
        $this->datumsformat = $datumsformat;
        $this->darfLesen = $darfLesen ?? static fn (string $datei): bool => true;
    }

    /**
     * Die Liga zu einer Datei relativ zum Ligenordner (auch in Unterordnern, "archiv/x.l98"); null,
     * wenn es sie nicht gibt, sie keine .l98-Datei ist, ausserhalb des Ligenordners liegt, nicht
     * gelesen werden darf oder leer ist.
     */
    public function lade(string $datei): ?GeladeneLiga
    {
        if ($datei === '' || substr($datei, -4) != '.l98' || in_array('..', preg_split('#[/\\\\]#', $datei) ?: [], true)) {
            return null;
        }
        $pfad = $this->ligenordner . $datei;
        if (!is_file($pfad) || !($this->darfLesen)($datei)) {
            return null;
        }
        $zeilen = file($pfad);
        if (!$zeilen) {
            return null;
        }

        return new GeladeneLiga($datei, Liga::ausEintraegen(LigaDatei::zerlege($zeilen)), date($this->datumsformat, (int) filemtime($pfad)));
    }
}
