<?php

declare(strict_types=1);

namespace Lmo\Web\Seiten;

use Lmo\Liga\Spiel;

/**
 * Die Tooltips zu einem Spiel in Ergebnissen und Spielplan: Spielbericht (Symbol lmo-st1) und
 * Hinweis (Symbol lmo-st2) mit Ergebnis, Zusatz, Wertung am gruenen Tisch und Notiz. Das
 * HTML im Tooltip steht escaped im Attribut data-info.
 */
final class SpielHinweis
{
    /**
     * Die Paarung fett, mit Wappen: "<strong>Heim[Wappen] - [Wappen]Gast" (das </strong> schliesst
     * der Aufrufer, damit Ergebnisse noch fett dazukommen koennen).
     */
    public static function paarung(string $heim, string $wappenHeim, string $wappenGast, string $gast): string
    {
        return '<strong>' . $heim . $wappenHeim . ' - ' . $wappenGast . $gast;
    }

    /**
     * Das Symbol fuer den Spielbericht.
     *
     * @param string $paarung aus paarung()
     * @param string $hinweisText Sprachtext 270 ("Spielbericht ansehen")
     * @param string $bilder Adresse des Bilderordners
     * @param string|null $link Adresse des Berichts; null: das Symbol ohne Link (Spielplan)
     */
    public static function bericht(string $paarung, string $hinweisText, string $bilder, ?string $link): string
    {
        $info = htmlspecialchars($paarung . '</strong>' . nl2br($hinweisText));
        $symbol = $link !== null
            ? '<a href="' . $link . '" target="_blank"><img src="' . $bilder . '/lmo-st1.svg" height="15" class="svg-no-style" border="0" alt=""></a>'
            : '<img src="' . $bilder . '/lmo-st1.svg" height="15" class="svg-no-style" alt="">';

        return '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . $info . '" class="tooltip-trigger">' . $symbol . '</span>';
    }

    /**
     * Das Symbol fuer den Hinweis zu einem Spiel mit Notiz oder Wertung am gruenen Tisch; '' ohne.
     *
     * @param array<int, string> $text Sprachtexte
     * @param string $paarung aus paarung()
     * @param string $zusatz Zusatz zum Ergebnis (n.V., i.E.); null ohne die Angabe
     * @param bool $umbruchNachNotiz ein <br> nach der Notiz (Spielplan)
     */
    public static function hinweis(
        Spiel $spiel,
        array $text,
        string $paarung,
        string $heim,
        string $gast,
        string $toreHeim,
        string $toreGast,
        ?string $zusatz,
        string $bilder,
        bool $umbruchNachNotiz = false,
    ): string {
        if ($spiel->notiz == '' && $spiel->wertung == 0) {
            return '';
        }
        //Grüner Tisch: Beidseitiges Ergebnis vorbereiten (" / Gast:Heim")
        //Alles in EIN Tag packen, damit das CSS es als EINE zentrierte Zeile erkennt
        $info = $paarung . '&nbsp;&nbsp;' . $toreHeim . ':' . $toreGast . ($spiel->wertung == 3 ? ' / ' . $toreGast . ':' . $toreHeim : '') . '</strong>';
        if ($zusatz !== null) {
            $info .= '&nbsp;' . $zusatz . '<br>';
        }
        //Grüner Tisch: Heimteam siegt
        if ($spiel->wertung == 1) {
            $info .= '<strong>' . $text[219] . ':</strong>' . $heim . '&nbsp;' . $text[211] . '<br>';
        }
        //Grüner Tisch: Gastteam siegt
        if ($spiel->wertung == 2) {
            $info .= '<strong>' . $text[219] . ':</strong>' . $gast . '&nbsp;' . $text[211] . '<br>';
        }
        //Grüner Tisch: Beidseitiges Ergebnis
        if ($spiel->wertung == 3) {
            $info .= '<strong>' . $text[219] . ':</strong>' . $text[212] . '<br>';
        }
        //Allgemeine Notiz
        if ($spiel->notiz != '') {
            $info .= '<strong>' . $text[22] . ':</strong>' . $spiel->notiz . ($umbruchNachNotiz ? '<br>' : '');
        }

        //Ausgabe als neutrales Element (kein Link)
        return '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($info)) . '" class="tooltip-trigger"><img src="' . $bilder . '/lmo-st2.svg" height="15" class="svg-no-style" alt=""></span>';
    }

    /**
     * Das Symbol fuer die Notiz zu einem Pokalspiel; '' ohne Notiz. Im Pokal steht unter der
     * Paarung mit Ergebnis nur die Notiz (keine Wertung am gruenen Tisch).
     *
     * @param array<int, string> $text Sprachtexte
     * @param string $paarung aus paarung()
     */
    public static function pokalNotiz(Spiel $spiel, array $text, string $paarung, string $toreHeim, string $toreGast, string $bilder): string
    {
        if ($spiel->notiz == '') {
            return '';
        }
        //Zentrierte Kopfzeile für Notizen
        $info = $paarung . '&nbsp;&nbsp;' . $toreHeim . ':' . $toreGast . '</strong>';
        //Allgemeine Notiz
        $info .= '<br><strong>' . $text[22] . ':</strong> ' . $spiel->notiz;

        //Ausgabe als neutrales Element (kein Link)
        return '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($info)) . '" class="tooltip-trigger"><img src="' . $bilder . '/lmo-st2.svg" height="15" class="svg-no-style" border="0" alt=""></span>';
    }
}
