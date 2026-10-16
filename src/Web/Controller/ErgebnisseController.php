<?php

namespace Lmo\Web\Controller;

use Closure;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Seiten\ErgebnisListe;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Seiten\TabellenSeite;
use Lmo\Web\Statistikdatei;
use Lmo\Web\Vorlagen;

/**
 * Die Ergebnis- und die Tabellenseite einer Liga (action=results und action=table): liest
 * Spieltag, Tabellenstand und Tabellenart aus der Anfrage und setzt die Seite aus
 * Spieltagsauswahl, Ergebnissen, Vor- und Zurueck-Pfeilen und Tabelle zusammen
 * (Vorlage ergebnisse_tabelle.html.twig).
 *
 * Parameter der Anfrage:
 *  - st      gewaehlter Spieltag (ohne: der aktuelle der Liga); gesetzt rechnet auch die Tabelle bis dahin
 *  - endtab  Spieltag, bis zu dem die Tabelle gerechnet wird (ohne: alle)
 *  - tabtype Tabellenart: 0 gesamt, 1 heim, 2 auswaerts, 3 Rueckrunde, 4 Hinrunde
 */
final class ErgebnisseController
{
    private Liga $liga;
    /** @var array<int|string, mixed> */
    private array $text;
    private Darstellung $darstellung;
    private string $vorlagen;
    private string $bilder;
    private Konfiguration $konfiguration;
    private ?Statistikdatei $statistik;
    /** @var (Closure(Spiel): string)|null */
    private ?Closure $zusatz;

    /**
     * @param array<int|string, mixed> $text Sprachtexte
     * @param string $vorlagen Ordner der Twig-Vorlagen
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     * @param Konfiguration $konfiguration Konfiguration der Installation (tabonres, tabpkt, Torstatistik, spielfrei, Datumsformat)
     * @param Statistikdatei|null $statistik Statistikdatei der Liga (fuer die Torstatistik), null ohne
     * @param (Closure(Spiel): string)|null $zusatz weiteres HTML am Ende jeder Ergebniszeile (Addons)
     */
    public function __construct(Liga $liga, array $text, Darstellung $darstellung, string $vorlagen, string $bilder, Konfiguration $konfiguration, ?Statistikdatei $statistik, ?Closure $zusatz = null)
    {
        $this->liga = $liga;
        $this->text = $text;
        $this->darstellung = $darstellung;
        $this->vorlagen = $vorlagen;
        $this->bilder = $bilder;
        $this->konfiguration = $konfiguration;
        $this->statistik = $statistik;
        $this->zusatz = $zusatz;
    }

    /**
     * Die Seite als HTML.
     *
     * @param string $action "results" oder "table"
     * @param string $adresse Adresse des LMO ($_SERVER['PHP_SELF'])
     * @param string $datei Ligadatei relativ zum Ligenordner
     */
    public function zeige(Anfrage $anfrage, string $action, string $adresse, string $datei): string
    {
        $e = $this->liga->einstellungen();
        // Anzeige-Optionen wie tabonres darf der Aufruf ueberschreiben (Konfiguration::ANZEIGEOPTIONEN)
        $k = $this->konfiguration->mitAnzeigeoptionen($anfrage);
        $text = $this->text;
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

        $basis = $adresse . '?action=' . $action . '&amp;';
        $spielplan = $adresse . '?action=program&amp;file=' . $datei . '&amp;selteam=';
        $spieltagLink = $basis . 'tabtype=' . $tabtype . '&amp;file=' . $datei . '&amp;st=';
        $tabellenLink = $basis . 'file=' . $datei . '&amp;endtab=' . $endtab . '&amp;st=' . $st . '&amp;tabtype=';

        $menue = (new SpieltagsMenue($text, $this->vorlagen))->zeige($this->liga, $st, $spieltagLink);

        $ergebnisse = null;
        if ($k->tabonres() >= 1 || $action == 'results') {
            $ergebnisse = (new ErgebnisListe($this->liga, $text, $this->darstellung, $this->vorlagen, $this->bilder))
                ->zeige($st, $datei, $spielplan, $k->datumsformat(), $this->torstatistikSpieltag($st), $k->spielfreiNennen(), $this->zusatz);
        }

        $tabelle = null;
        if ($k->tabonres() >= 1 || $action == 'table') {
            $tabelle = (new TabellenSeite($this->liga, $text, $this->darstellung, $this->vorlagen, $this->bilder))
                ->zeige($tabtype, $endtab, $stand, $tabellenLink, $datei, $k->tabonres(), $k->tabpkt(), $this->torstatistikSaison());
        }

        return Vorlagen::zeige($this->vorlagen, 'ergebnisse_tabelle.html.twig', [
            'text' => $text,
            'menue' => $menue,
            'ergebnisse' => $ergebnisse,
            'zurueck' => $st > 1 ? $spieltagLink . ($st - 1) : null,
            'vor' => $st < $e->spieltage() ? $spieltagLink . ($st + 1) : null,
            'tabelle' => $tabelle,
        ]);
    }

    /** Torstatistik des Spieltags unter den Ergebnissen; null ohne die Zeile, '' ohne Statistikdatei. */
    private function torstatistikSpieltag(int $st): ?string
    {
        if (!$this->konfiguration->torstatistikErgebnisse()) {
            return null;
        }
        if ($this->statistik === null) {
            return '';
        }
        $faktor = $this->liga->einstellungen()->torfaktor();

        return $this->text[38] . ': ' . Darstellung::mitFaktor($this->statistik->toreSpieltag($st), $faktor)
            . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $this->text[38] . $this->text[4001] . ': '
            . Darstellung::mitFaktor($this->statistik->toreJeSpielSpieltag($st), $faktor);
    }

    /** Torstatistik der Saison unter der Tabelle; '' ohne. */
    private function torstatistikSaison(): string
    {
        if (!$this->konfiguration->torstatistikTabelle() || $this->statistik === null) {
            return '';
        }
        $faktor = $this->liga->einstellungen()->torfaktor();

        return $this->text[4000] . $this->text[38] . ': ' . Darstellung::mitFaktor($this->statistik->toreSaison(), $faktor)
            . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $this->text[38] . '&nbsp;' . $this->text[4001] . ': '
            . Darstellung::mitFaktor($this->statistik->toreJeSpielSaison(), $faktor);
    }
}
