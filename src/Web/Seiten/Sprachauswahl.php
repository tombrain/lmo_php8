<?php

declare(strict_types=1);

namespace Lmo\Web\Seiten;

/**
 * Die Auswahl der Sprache in der Fusszeile: je Sprachdatei lang/lang-<Sprache>.txt eine Flagge,
 * die gewaehlte ohne Link; fuer den Hauptadmin dahinter ein Link zum Bearbeiten der Texte.
 */
final class Sprachauswahl
{
    /**
     * Die Auswahl als HTML.
     *
     * @param string $sprachordner Ordner der Sprachdateien (PATH_TO_LANGDIR)
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR), Flaggen <Sprache>.svg
     * @param string $adresse Adresse der Seite ($_SERVER['PHP_SELF'])
     * @param string $abfrage bisherige Parameter der Seite ($_SERVER['QUERY_STRING']); lmouserlang wird ersetzt
     * @param string $gewaehlt die gewaehlte Sprache
     * @param array{0: string, 1: string}|null $bearbeiten Link und Titel zum Bearbeiten der Texte (Hauptadmin), null ohne
     */
    public static function html(string $sprachordner, string $bilder, string $adresse, string $abfrage, string $gewaehlt, ?array $bearbeiten = null): string
    {
        $html = '';
        $parameter = htmlentities((string) preg_replace('/&?lmouserlang=.+?\b/', '', $abfrage));
        // in fester (alphabetischer) Reihenfolge; bisher in der des Dateisystems
        foreach (scandir($sprachordner) ?: [] as $datei) {
            if (preg_match('/^lang-?(.*)?\.txt$/', $datei, $treffer) !== 1) {
                continue;
            }
            $sprache = $treffer[1] ?? '';
            if ($sprache === '') {
                // eine Datei lang.txt ohne Sprache: wie bisher keine Auswahl
                return '';
            }
            if ($sprache != $gewaehlt) {
                $html .= '
           <a href="' . $adresse . '?' . $parameter . '&amp;lmouserlang=' . $sprache . '" title="' . $sprache . '"><img src="' . $bilder . '/' . $sprache . '.svg" height="16" style="margin-right:1mm" title="' . $sprache . '" alt="' . $sprache . '"></a>';
            } else {
                $html .= '
          <img src="' . $bilder . '/' . $sprache . '.selected.svg" height="16" border="0" title="' . $sprache . '" alt="' . $sprache . '">';
            }
        }
        if ($bearbeiten !== null) {
            $html .= ' >> <a href="' . $bearbeiten[0] . '" class="lmo-lang-edit" title="' . htmlspecialchars($bearbeiten[1]) . '">'
                . '<img src="' . $bilder . '/pencil.svg" height="20" alt="Edit">'
                . '</a>';
        }

        return $html;
    }
}
