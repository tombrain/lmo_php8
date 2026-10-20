<?php

declare(strict_types=1);

namespace Lmo\Addon;

use Closure;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;

/**
 * Bruecke zu einer Seite der Administration nach alter Art (z. B. addon/tipp/lmo-admintipp.php).
 * Die Administration bindet die Datei (datei()) wie bisher im globalen Bereich ein: Der alte Code
 * legt dort Variablen an, die seine Funktionen per global lesen (etwa die Zaehler der Tipp-
 * Auswertung). zeige() bindet sie ersatzweise mit allen globalen Variablen per Referenz ein; neue
 * Variablen bleiben dabei lokal.
 */
final class AlteAdminSeite implements Controller
{
    /**
     * @param Closure(Kontext): bool $zustaendig fuer welche Anfragen die Seite gilt
     * @param string $datei die Datei der Seite
     */
    public function __construct(private Closure $zustaendig, private string $datei)
    {
    }

    /** Die Datei der Seite, zum Einbinden im globalen Bereich. */
    public function datei(): string
    {
        return $this->datei;
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return ($this->zustaendig)($kontext);
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite((static function (string $lmo_admin_datei): string {
            foreach (array_keys($GLOBALS) as $lmo_admin_name) {
                if ($lmo_admin_name !== 'GLOBALS' && $lmo_admin_name[0] !== '_' && $lmo_admin_name !== 'lmo_admin_datei') {
                    ${$lmo_admin_name} = &$GLOBALS[$lmo_admin_name];
                }
            }
            unset($lmo_admin_name);
            ob_start();
            require $lmo_admin_datei;

            return (string) ob_get_clean();
        })($this->datei));
    }
}
