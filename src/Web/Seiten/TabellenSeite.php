<?php

declare(strict_types=1);

namespace Lmo\Web\Seiten;

use Lmo\Liga\Einstellungen;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Mannschaft;
use Lmo\Liga\Tabelle;
use Lmo\Liga\Tabellenzeile;
use Lmo\Web\Darstellung;
use Lmo\Web\Vorlagen;

/**
 * Die Tabelle einer Liga auf der Tabellen- und Ergebnisseite: rechnet die Tabellen (gesamt bzw. die
 * gewaehlte Art, dazu Heim- und Auswaertsbilanz und den Stand vom Spieltag davor fuer die Pfeile),
 * bereitet je Mannschaft eine Zeile vor und fuellt damit die Vorlage tabelle.html.twig.
 */
final class TabellenSeite
{
    private Liga $liga;
    private Einstellungen $einstellungen;
    /** @var array<int, string> */
    private array $text;
    private Darstellung $darstellung;
    private string $vorlagen;
    private string $bilder;

    /**
     * @param array<int, string> $text Sprachtexte
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

    /**
     * Die Tabelle als HTML.
     *
     * @param int $art Tabellenart: 0 gesamt, 1 heim, 2 auswaerts, 3 Rueckrunde, 4 Hinrunde
     * @param int $bis Spieltag, bis zu dem gerechnet wird
     * @param string $stand Stand der Tabelle als Text ("12. Spieltag"); leer: der aktuelle Stand
     * @param string $link Link auf diese Seite, es fehlt nur die Tabellenart (fuer die Auswahl ueber der Tabelle)
     * @param string $datei Ligadatei relativ zum Ligenordner (fuer die Wappen)
     * @param int $tabonres 2: Heim- und Auswaertsbilanz neben der Tabelle (Konfiguration)
     * @param int $tabpkt Punktespalte: 0 in der Mitte, 1 am rechten Rand (Konfiguration)
     * @param string $torstatistik Torstatistik der Saison unter der Tabelle, fertiges HTML ('' ohne)
     */
    public function zeige(int $art, int $bis, string $stand, string $link, string $datei, int $tabonres, int $tabpkt, string $torstatistik): string
    {
        $e = $this->einstellungen;
        $tabelle = Tabelle::fuerAnsicht($this->liga, $art, $bis);
        $plaetze = $this->reihenfolge($tabelle, $art, $stand);
        // Platz je Mannschaft am Spieltag davor, fuer die Pfeile
        $vorher = [];
        if ($bis > 1) {
            foreach ($this->reihenfolge(Tabelle::fuerAnsicht($this->liga, $art, $bis - 1), $art, $stand) as $x => $nr) {
                $vorher[$nr] = $x + 1;
            }
        }
        // Heim- und Auswaertsbilanz; bei Hin- oder Rueckrundentabelle auf diese Haelfte beschraenkt
        $heim = $auswaerts = null;
        if ($tabonres == 2) {
            $heim = Tabelle::fuerAnsicht($this->liga, Tabelle::HEIM, $bis, $art);
            $auswaerts = Tabelle::fuerAnsicht($this->liga, Tabelle::AUSWAERTS, $bis, $art);
        }

        $zeilen = [];
        $ab = 1;
        foreach ($plaetze as $index => $nr) {
            $platz = $index + 1;
            if ($platz > $e->mannschaften()) {
                break;
            }
            $mannschaft = $this->liga->mannschaften[$nr] ?? new Mannschaft();
            $name = $mannschaft->anzeigename();
            $url = $mannschaft->werte['URL'] ?? '';
            [$klasse, $ab] = $this->farbe($platz, $art, $ab);
            $pfeil = 0;
            if ($bis > 1) {
                $pfeil = $platz < $vorher[$nr] ? 1 : ($platz > $vorher[$nr] ? 2 : 0);
            }
            $zeilen[] = [
                'platz' => $platz,
                'klasse' => $klasse,
                'fett' => $nr == $e->lieblingsmannschaft(),
                'pfeil' => $pfeil,
                'symbol' => $this->darstellung->wappen($datei, $name, ' alt=""'),
                'name' => $name,
                'url' => ($url != '' && $e->mitMannschaftslinks()) ? $url : null,
                'notiz' => $this->notiz($mannschaft, $datei),
                'gesamt' => $this->werte($tabelle->zeilen[$nr]),
                'heim' => $heim !== null ? $this->werte($heim->zeilen[$nr]) : null,
                'auswaerts' => $auswaerts !== null ? $this->werte($auswaerts->zeilen[$nr]) : null,
            ];
        }

        $breite = $e->mitMinuspunkten() ? 13 : 11;

        return Vorlagen::zeige($this->vorlagen, 'tabelle.html.twig', [
            'text' => $this->text,
            'bilder' => $this->bilder,
            'auswahl' => $this->auswahl($art, $link, $tabonres),
            'tabonres' => $tabonres,
            'tabpkt' => $tabpkt,
            'tabdat' => $stand,
            'breite' => $breite,
            'gesamtbreite' => $tabonres == 2 ? 3 * $breite + 11 : $breite + 11,
            'punkte_attr' => $e->mitMinuspunkten() ? ' colspan="3" align="center"' : ' align="right"',
            'minus' => $e->mitMinuspunkten(),
            'ohne_unentschieden' => $e->ohneUnentschieden(),
            'zeilen' => $zeilen,
            'torstatistik' => $torstatistik,
        ]);
    }

    /**
     * Die Mannschaften vom ersten zum letzten Platz. Fuehrt die Liga ein Handicap, gilt in der
     * Gesamttabelle dessen Reihenfolge: die des aktuellen Spieltags, bei einer Tabelle "bis
     * Spieltag X" die von X.
     *
     * @return list<int>
     */
    private function reihenfolge(Tabelle $tabelle, int $art, string $stand): array
    {
        $e = $this->einstellungen;
        if (!$e->mitHandicap() || $art != Tabelle::GESAMT) {
            return $tabelle->reihenfolge;
        }
        $runde = $stand === '' ? $e->aktuellerSpieltag() : $tabelle->bis;

        return $tabelle->reihenfolgeNachHandicap(isset($this->liga->spieltage[$runde]) ? ($this->liga->spieltage[$runde]->werte['HS'] ?? '0') : '0');
    }

    /**
     * Farbe einer Zeile in der Gesamttabelle: Meister, Europapokal, Relegation, Abstieg.
     *
     * @param int $ab erster Platz nach dem Meister (2, wenn es einen Meister gibt, sonst 1)
     * @return array{string, int} Klasse und der Platz, ab dem die Europapokalplaetze zaehlen
     */
    public function farbe(int $x, int $art, int $ab): array
    {
        $e = $this->einstellungen;
        $klasse = 'nobr';
        if ($art != Tabelle::GESAMT) {
            return [$klasse, $ab];
        }
        $cl = $e->plaetzeChampionsLeague();
        $ck = $e->plaetzeClQualifikation();
        $uc = $e->plaetzeUefaCup();
        $anzahl = $e->mannschaften();
        if ($x == 1 && $e->mitMeister()) {
            $klasse = 'lmoTabelleMeister nobr';
            $ab = 2;
        }
        if ($x >= $ab && $x < $ab + $cl && $cl > 0) {
            $klasse = 'lmoTabelleCleague nobr';
        }
        if ($x >= $ab + $cl && $x < $ab + $cl + $ck && $ck > 0) {
            $klasse = 'lmoTabelleCleaguequali nobr';
        }
        if ($x >= $ab + $cl + $ck && $x < $ab + $cl + $ck + $uc && $uc > 0) {
            $klasse = 'lmoTabelleUefa nobr';
        }
        if ($x <= $anzahl - $e->plaetzeAbstieg() && $x > $anzahl - $e->plaetzeAbstieg() - $e->plaetzeRelegation() && $e->plaetzeRelegation() > 0) {
            $klasse = 'lmoTabelleRelegation nobr';
        }
        if ($x <= $anzahl && $x > $anzahl - $e->plaetzeAbstieg() && $e->plaetzeAbstieg() > 0) {
            $klasse = 'lmoTabelleAbsteiger nobr';
        }

        return [$klasse, $ab];
    }

    /**
     * Auswahl der Tabellenart ueber der Tabelle: Gesamt, Heim, Auswaerts, Hinrunde, Rueckrunde.
     *
     * @return list<array{art: int, text: mixed, titel: mixed, link: string|null}>
     */
    private function auswahl(int $art, string $link, int $tabonres): array
    {
        $e = $this->einstellungen;
        if (!$e->mitHinRueckrundentabelle() && !$e->mitHeimAuswaertstabelle()) {
            return [];
        }
        $arten = [[0, 16, 27]];
        if ($tabonres != 2 && $e->mitHeimAuswaertstabelle()) {
            $arten[] = [1, 41, 28];
            $arten[] = [2, 42, 29];
        }
        if ($e->mitHinRueckrundentabelle()) {
            $arten[] = [4, 4003, 4003];
            $arten[] = [3, 4002, 4002];
        }
        $auswahl = [];
        foreach ($arten as [$nr, $text, $titel]) {
            $auswahl[] = [
                'art' => $nr,
                'text' => $this->text[$text],
                'titel' => $this->text[$titel],
                'link' => $art != $nr ? $link . $nr : null,
            ];
        }

        return $auswahl;
    }

    /**
     * Werte einer Zeile zum Anzeigen, Punkte und Tore mit den Faktoren der Liga.
     *
     * @return array<string, int|float|string>
     */
    private function werte(Tabellenzeile $z): array
    {
        $punkte = $this->einstellungen->punktfaktor();
        $tore = $this->einstellungen->torfaktor();

        return [
            'spiele' => $z->spiele,
            'siege' => $z->siege,
            'unentschieden' => $z->unentschieden,
            'niederlagen' => $z->niederlagen,
            'punkte' => Darstellung::mitFaktor($z->punkte, $punkte),
            'minuspunkte' => Darstellung::mitFaktor($z->minuspunkte, $punkte),
            'tore' => Darstellung::mitFaktor($z->tore, $tore),
            'gegentore' => Darstellung::mitFaktor($z->gegentore, $tore),
            'tordifferenz' => Darstellung::mitFaktor($z->tordifferenz(), $tore),
        ];
    }

    /**
     * Das Symbol mit dem Hinweis zu Strafen, Torkorrektur und Notiz einer Mannschaft als HTML,
     * "&nbsp;" ohne Hinweis. Strafen stehen mit umgekehrtem Vorzeichen in der Datei (Bonus negativ).
     */
    public function notiz(Mannschaft $mannschaft, string $datei): string
    {
        $e = $this->einstellungen;
        $text = $this->text;
        $strafp = $mannschaft->werte['SP'] ?? '0';
        $strafm = $mannschaft->werte['SM'] ?? '0';
        $tor1 = $mannschaft->werte['TOR1'] ?? '0';
        $tor2 = $mannschaft->werte['TOR2'] ?? '0';
        $ab = $mannschaft->werte['STDA'] ?? '0';
        $notiz = $mannschaft->werte['NOT'] ?? '';
        if ($notiz == '' && $strafp == 0 && $strafm == 0 && $tor1 == 0 && $tor2 == 0) {
            return '&nbsp;';
        }
        $name = $mannschaft->anzeigename();
        $umgekehrt = static function (string $wert, int $faktor): string {
            $zahl = Darstellung::mitFaktor($wert, $faktor);
            $angezeigt = is_numeric($zahl) ? -1 * ($zahl + 0) : $zahl;

            return ($wert < 0 ? '+' : '') . $angezeigt;
        };
        $abSpieltag = $ab != 0 ? '<br>' . $text[524] . '&nbsp;' . $text[2] . '&nbsp;' . $ab . '<br>' : '';

        $info = ' <strong>' . $this->darstellung->wappen($datei, $name, ' alt=""') . $name . '</strong>';
        if ($strafp != 0 || $strafm != 0) {
            $info .= '<strong>' . $text[128] . ':</strong>' . $umgekehrt($strafp, $e->punktfaktor());
            if ($e->mitMinuspunkten()) {
                $info .= ':' . $umgekehrt($strafm, $e->punktfaktor());
            }
            $info .= $abSpieltag;
        }
        if ($tor1 != 0 || $tor2 != 0) {
            $info .= '<br><strong>' . $text[522] . ':</strong>' . $umgekehrt($tor1, $e->torfaktor()) . ':' . $umgekehrt($tor2, $e->torfaktor());
            $info .= $abSpieltag;
        }
        if ($notiz != '') {
            $info .= '<br><strong>' . $text[22] . ':</strong>' . $notiz;
        }

        return '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($info))
            . '" class="tooltip-trigger"><img src="' . $this->bilder . '/lmo-st2.svg" height="15" class="svg-no-style" alt=""></span>';
    }
}
