<?php

namespace Lmo\Web\Controller;

use Closure;
use Lmo\Liga\Spiel;
use Lmo\Web\Darstellung;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\ErgebnisListe;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Seiten\TabellenSeite;
use Lmo\Web\Statistikdatei;
use Lmo\Web\Vorlagen;

/**
 * Die Ergebnis- und die Tabellenseite einer Liga (action=results und action=table, nicht im
 * Pokal): liest Spieltag, Tabellenstand und Tabellenart aus der Anfrage und setzt die Seite aus
 * Spieltagsauswahl, Ergebnissen, Vor- und Zurueck-Pfeilen und Tabelle zusammen
 * (Vorlage ergebnisse_tabelle.html.twig).
 *
 * Parameter der Anfrage:
 *  - st      gewaehlter Spieltag (ohne: der aktuelle der Liga); gesetzt rechnet auch die Tabelle bis dahin
 *  - endtab  Spieltag, bis zu dem die Tabelle gerechnet wird (ohne: alle)
 *  - tabtype Tabellenart: 0 gesamt, 1 heim, 2 auswaerts, 3 Rueckrunde, 4 Hinrunde
 *  - tabonres, tabpkt Anzeige-Optionen (Konfiguration::ANZEIGEOPTIONEN)
 */
final class ErgebnisseController implements Controller
{
    /** @var (Closure(Spiel): string)|null */
    private ?Closure $zusatz;

    /**
     * @param (Closure(Spiel): string)|null $zusatz weiteres HTML am Ende jeder Ergebniszeile (Addons)
     */
    public function __construct(?Closure $zusatz = null)
    {
        $this->zusatz = $zusatz;
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && !$kontext->liga->liga->istPokal()
            && ($kontext->action == 'results' || $kontext->action == 'table');
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $datei = $geladen->datei;
        $e = $liga->einstellungen();
        $action = $kontext->action;
        // die Liga kann Ergebnisse oder Tabelle abschalten
        if (($action == 'results' && !$e->mitErgebnissen()) || ($action == 'table' && !$e->mitTabelle())) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        // Anzeige-Optionen wie tabonres darf der Aufruf ueberschreiben (Konfiguration::ANZEIGEOPTIONEN)
        $k = $kontext->konfiguration->mitAnzeigeoptionen($anfrage);
        $text = $kontext->text;
        $vorlagen = $kontext->pfade->vorlagen;
        $bilder = $kontext->pfade->bilder;
        $st = $anfrage->zahl('st', $e->aktuellerSpieltag());
        $tabtype = $anfrage->zahl('tabtype');
        // Tabelle bis Spieltag: ein gewaehlter Spieltag geht vor endtab; ohne beides der aktuelle Stand
        $endtab = $e->spieltage();
        $stand = '';
        if ($anfrage->hat('endtab')) {
            $endtab = $anfrage->zahl('endtab');
            $stand = $endtab . '. ' . $text[2];
        }
        if ($anfrage->hat('st')) {
            $endtab = $st;
            $stand = $st . '. ' . $text[2];
        }

        $adresse = $kontext->adresse;
        $basis = $adresse . '?action=' . $action . '&amp;';
        $spielplan = $adresse . '?action=program&amp;file=' . $datei . '&amp;selteam=';
        $spieltagLink = $basis . 'tabtype=' . $tabtype . '&amp;file=' . $datei . '&amp;st=';
        $tabellenLink = $basis . 'file=' . $datei . '&amp;endtab=' . $endtab . '&amp;st=' . $st . '&amp;tabtype=';

        // Statistikdatei der Liga (lmo-zustat.php) fuer die Torstatistik
        $statistik = Statistikdatei::lies($kontext->pfade->ausgabe($k, str_replace('.l98', '.l98.php', basename($datei))));
        $faktor = $e->torfaktor();

        $menue = (new SpieltagsMenue($text, $vorlagen))->zeige($liga, $st, $spieltagLink);

        $ergebnisse = null;
        if ($k->tabonres() >= 1 || $action == 'results') {
            $ergebnisse = (new ErgebnisListe($liga, $text, $kontext->darstellung, $vorlagen, $bilder))
                ->zeige($st, $datei, $spielplan, $k->datumsformat(), self::torstatistikSpieltag($kontext, $statistik, $st, $faktor), $k->spielfreiNennen(), $this->zusatz);
        }

        $tabelle = null;
        if ($k->tabonres() >= 1 || $action == 'table') {
            $tabelle = (new TabellenSeite($liga, $text, $kontext->darstellung, $vorlagen, $bilder))
                ->zeige($tabtype, $endtab, $stand, $tabellenLink, $datei, $k->tabonres(), $k->tabpkt(), self::torstatistikSaison($kontext, $statistik, $faktor));
        }

        return new Seite(Vorlagen::zeige($vorlagen, 'ergebnisse_tabelle.html.twig', [
            'text' => $text,
            'menue' => $menue,
            'ergebnisse' => $ergebnisse,
            'zurueck' => $st > 1 ? $spieltagLink . ($st - 1) : null,
            'vor' => $st < $e->spieltage() ? $spieltagLink . ($st + 1) : null,
            'tabelle' => $tabelle,
        ]), true);
    }

    /** Torstatistik des Spieltags unter den Ergebnissen; null ohne die Zeile, '' ohne Statistikdatei. */
    private static function torstatistikSpieltag(Kontext $kontext, ?Statistikdatei $statistik, int $st, int $faktor): ?string
    {
        if (!$kontext->konfiguration->torstatistikErgebnisse()) {
            return null;
        }
        if ($statistik === null) {
            return '';
        }
        $text = $kontext->text;

        return $text[38] . ': ' . Darstellung::mitFaktor($statistik->toreSpieltag($st), $faktor)
            . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $text[38] . $text[4001] . ': '
            . Darstellung::mitFaktor($statistik->toreJeSpielSpieltag($st), $faktor);
    }

    /** Torstatistik der Saison unter der Tabelle; '' ohne. */
    private static function torstatistikSaison(Kontext $kontext, ?Statistikdatei $statistik, int $faktor): string
    {
        if (!$kontext->konfiguration->torstatistikTabelle() || $statistik === null) {
            return '';
        }
        $text = $kontext->text;

        return $text[4000] . $text[38] . ': ' . Darstellung::mitFaktor($statistik->toreSaison(), $faktor)
            . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $text[38] . '&nbsp;' . $text[4001] . ': '
            . Darstellung::mitFaktor($statistik->toreJeSpielSaison(), $faktor);
    }
}
