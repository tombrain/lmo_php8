<?php

namespace Lmo\Web\Seiten;

use Closure;
use DateTime;
use Lmo\Liga\Einstellungen;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Web\Darstellung;
use Lmo\Web\Vorlagen;

/**
 * Die Ergebnisse eines Spieltags auf der Ergebnis- und Tabellenseite, mit der Vorlage
 * ergebnisse.html.twig: je Spiel Anstoss, Mannschaften, Tore, Zusatz, Wertung gegen beide,
 * Spielbericht und Hinweis, darunter Torstatistik des Spieltags und spielfreie Mannschaften.
 */
final class ErgebnisListe
{
    private Liga $liga;
    private Einstellungen $einstellungen;
    /** @var array<int|string, mixed> */
    private array $text;
    private Darstellung $darstellung;
    private string $vorlagen;
    private string $bilder;

    /**
     * @param array<int|string, mixed> $text Sprachtexte
     * @param string $vorlagen Ordner der Twig-Vorlagen
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public function __construct(Liga $liga, array $text, Darstellung $darstellung, string $vorlagen, string $bilder)
    {
        $this->liga = $liga;
        $this->einstellungen = $liga->einstellungen();
        $this->text = $text;
        $this->darstellung = $darstellung;
        $this->vorlagen = $vorlagen;
        $this->bilder = $bilder;
    }

    /** Anzahl der Spalten der Liste am Spieltag (fuer colspan). */
    public function breite(int $spieltag): int
    {
        $breite = 10;
        if ($this->einstellungen->mitVerlaengerung()) {
            $breite += 2;
        }
        // ein Spiel, das gegen beide gewertet ist, braucht vier Spalten mehr
        foreach ($this->liga->spiele($spieltag) as $spiel) {
            if ($spiel->wertung == 3) {
                $breite += 4;
                break;
            }
        }
        if ($this->einstellungen->mitAnstosszeit()) {
            $breite += 1;
        }

        return $breite;
    }

    /**
     * Die Ergebnisse als HTML.
     *
     * @param string $datei Ligadatei relativ zum Ligenordner (fuer die Wappen)
     * @param string $spielplan Link zum Spielplan einer Mannschaft, es fehlt nur die Nummer (fertiges HTML)
     * @param string $datumsformat Datumsformat der Konfiguration, wenn die Liga keines hat
     * @param string|null $torstatistik Torstatistik des Spieltags als fertiges HTML; null: keine Zeile dafuer
     * @param bool $spielfrei die Mannschaften ohne Spiel an diesem Spieltag nennen
     * @param (Closure(Spiel): string)|null $zusatz weiteres HTML am Ende jeder Zeile (Addons)
     */
    public function zeige(int $spieltag, string $datei, string $spielplan, string $datumsformat, ?string $torstatistik, bool $spielfrei, ?Closure $zusatz = null): string
    {
        $e = $this->einstellungen;
        $tag = $this->liga->spieltage[$spieltag] ?? null;
        $titel = $spieltag . '. ' . $this->text[2];
        if ($e->mitSpieltagsdatum() && $tag !== null) {
            if ($tag->ersterTagText() != '') {
                $titel .= ' ' . $this->text[3] . ' ' . $tag->ersterTagText();
            }
            if ($tag->letzterTagText() != '') {
                $titel .= ' ' . $this->text[4] . ' ' . $tag->letzterTagText();
            }
        }

        $spiele = iterator_to_array($this->liga->spiele($spieltag), false);
        if ($e->sortiertNachAnstoss()) {
            // Spiele ohne Anstoss stehen vorn; gleiche Zeiten bleiben in der Reihenfolge der Partien
            usort($spiele, static fn (Spiel $a, Spiel $b): int => ($a->anstoss ?? 0) <=> ($b->anstoss ?? 0));
        }
        $zeilen = [];
        $gespielt = [];
        foreach ($spiele as $spiel) {
            if ($spiel->heim <= 0 || $spiel->gast <= 0) {
                continue;
            }
            /* Spielfrei-Hack-Beginn1*/
            $gespielt[] = $spiel->heim;
            $gespielt[] = $spiel->gast;
            /* Spielfrei-Hack-Ende1*/
            $zeilen[] = $this->zeile($spiel, $datei, $datumsformat, $zusatz);
        }

        /* Spielfrei-Hack-Beginn2: Mannschaften ohne Spiel an diesem Spieltag */
        $frei = [];
        if ($spielfrei) {
            foreach ($this->liga->alleMannschaften() as $nr => $mannschaft) {
                if (!in_array($nr, $gespielt)) {
                    $frei[] = $this->mannschaft($nr, $mannschaft->anzeigename(), $datei);
                }
            }
        }
        /* Spielfrei-Hack-Ende2*/

        return Vorlagen::zeige($this->vorlagen, 'ergebnisse.html.twig', [
            'text' => $this->text,
            'titel' => $titel,
            'breite' => $this->breite($spieltag),
            'anstoss' => $e->mitAnstosszeit(),
            'spielplan' => $e->mitSpielplan() ? $spielplan : null,
            'verlaengerung' => $e->mitVerlaengerung(),
            'zeilen' => $zeilen,
            'torstatistik' => $torstatistik,
            'spielfrei' => $spielfrei,
            'frei' => $frei,
        ]);
    }

    /** @return array<string, mixed> */
    private function zeile(Spiel $spiel, string $datei, string $datumsformat, ?Closure $zusatz): array
    {
        $e = $this->einstellungen;
        $text = $this->text;
        $heim = $spiel->heimMannschaft()->anzeigename();
        $gast = $spiel->gastMannschaft()->anzeigename();
        $toreHeim = $spiel->toreHeimText($e->torfaktor());
        $toreGast = $spiel->toreGastText($e->torfaktor());
        $zusatzText = $spiel->zusatzText($text[0], $text[1]);

        $anstoss = '';
        if ($spiel->anstoss !== null) {
            $dt = new DateTime();
            $dt->setTimestamp($spiel->anstoss);
            $anstoss = $dt->format($e->datumsformat($datumsformat));
        }

        /** Mannschaftsicons finden **/
        // (fuer Spielbericht und Notiz)
        $wappenHeim = $wappenGast = '';
        if ($e->mitSpielberichten() || $spiel->notiz != '' || $spiel->wertung > 0) {
            $wappenHeim = $this->darstellung->wappen($datei, $heim, ' alt=""');
            $wappenGast = $this->darstellung->wappen($datei, $gast, ' alt=""');
        }
        $paarung = '<strong>' . $heim . $wappenHeim . ' - ' . $wappenGast . $gast;

        /** Spielbericht verlinken **/
        // null ohne die Spalte, '' wenn das Spiel keinen hat
        $bericht = null;
        if ($e->mitSpielberichten()) {
            $bericht = '';
            if ($spiel->bericht != '') {
                //Spielbericht Inhalt: $paarung . '</strong>' . Sprachtext 270
                // Link mit Mouseover-Funktion und data-info Attribut
                $bericht = '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars($paarung . '</strong>' . nl2br($text[270])) . '" class="tooltip-trigger"><a href="' . $spiel->bericht . '" target="_blank"><img src="' . $this->bilder . '/lmo-st1.svg" height="15" class="svg-no-style" border="0" alt=""></a></span>';
            }
        }

        /** Notizen anzeigen **/
        $hinweis = '';
        if ($spiel->notiz != '' || $spiel->wertung > 0) {
            //Grüner Tisch: Beidseitiges Ergebnis vorbereiten (" / Gast:Heim")
            //Alles in EIN Tag packen, damit das CSS es als EINE zentrierte Zeile erkennt
            $info = $paarung . '&nbsp;&nbsp;' . $toreHeim . ':' . $toreGast . ($spiel->wertung == 3 ? ' / ' . $toreGast . ':' . $toreHeim : '') . '</strong>';
            if ($e->mitVerlaengerung()) {
                $info .= '&nbsp;' . $zusatzText . '<br>';
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
                $info .= '<strong>' . $text[22] . ':</strong>' . $spiel->notiz;
            }
            //Ausgabe als neutrales Element (kein Link)
            $hinweis = '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($info)) . '" class="tooltip-trigger"><img src="' . $this->bilder . '/lmo-st2.svg" height="15" class="svg-no-style" alt=""></span>';
        }

        return [
            'anstoss' => $anstoss,
            'heim' => $this->mannschaft($spiel->heim, $heim, $datei),
            'gast' => $this->mannschaft($spiel->gast, $gast, $datei),
            'tore_heim' => $toreHeim,
            'tore_gast' => $toreGast,
            'zusatz' => $zusatzText,
            'gegen_beide' => $spiel->wertung == 3,
            'bericht' => $bericht,
            'hinweis' => $hinweis,
            /** bisherige Ergebnisse (AddOn Team Vergleich) */
            'addon' => $zusatz !== null ? $zusatz($spiel) : null,
        ];
    }

    /** @return array{nr: int, name: string, fett: bool, wappen: string} */
    private function mannschaft(int $nr, string $name, string $datei): array
    {
        $lieblings = $this->einstellungen->lieblingsmannschaft();

        return [
            'nr' => $nr,
            'name' => $name,
            'fett' => $lieblings > 0 && $lieblings == $nr,
            'wappen' => $this->darstellung->wappen($datei, $name, ' alt=""'),
        ];
    }
}
