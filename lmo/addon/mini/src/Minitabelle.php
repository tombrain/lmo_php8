<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Addon\Mini;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Mannschaft;
use Lmo\Liga\Tabelle;
use Lmo\Web\Bilder;

/**
 * Die Mini-Tabelle (lmo-minitab.php): ein Ausschnitt der Tabelle um einen Platz (sonst um die
 * Lieblingsmannschaft) mit Tendenz zum Vorspieltag und den Farben der Platzgruppen. Liefert die
 * Werte fuer die Vorlagen (Lmo\Addon\Mini\Vorlagen) unter den Namen der Platzhalter.
 */
final class Minitabelle
{
    /**
     * @param array<string, mixed> $cfg die Konfiguration ($cfgarray, mit den Werten des Mini unter 'mini')
     * @param array<int|string, mixed> $text die Sprachtexte
     * @param string $bilderAdresse Adresse der Bilder (URL_TO_IMGDIR)
     * @param string $adresse Adresse des LMO (URL_TO_LMO)
     * @param string $vorlagenAdresse Adresse der Vorlagen (URL_TO_TEMPLATEDIR)
     */
    public function __construct(
        private array $cfg,
        private array $text,
        private Bilder $bilder,
        private string $bilderAdresse,
        private string $adresse,
        private string $vorlagenAdresse
    ) {
    }

    /**
     * Die Werte.
     *
     * @param string $datei die Ligadatei relativ zum Ligenordner
     * @param int $stand Zeit der letzten Aenderung der Ligadatei
     * @param int $ueber Plaetze ueber dem gewaehlten
     * @param int $unter Plaetze unter dem gewaehlten
     * @param int|null $platz der Platz in der Mitte; null (oder ausserhalb): der der Lieblingsmannschaft
     * @return array{werte: array<string, string>, bloecke: array<string, list<array<string, string>>>}
     */
    public function werte(Liga $liga, string $datei, int $stand, int $ueber, int $unter, ?int $platz): array
    {
        $e = $liga->einstellungen();
        $mini = is_array($this->cfg['mini'] ?? null) ? $this->cfg['mini'] : [];
        $wert = static fn (string $schluessel): int => (int) $e->wert($schluessel);
        $tabelle = self::tabelle($liga, $e->spieltage());
        $anzahl = count($tabelle);
        // Tendenz = Platz am Vorspieltag minus Platz jetzt; vor dem zweiten Spieltag 0 fuer alle
        $vorher = $e->aktuellerSpieltag() > 1 ? self::tabelle($liga, $e->aktuellerSpieltag() - 1) : $tabelle;
        $favorit = $e->lieblingsmannschaft();

        // der Ausschnitt
        $mitte = 1;
        if ($platz === null || $platz <= 0 || $platz > $anzahl) {
            foreach ($tabelle as $zeile) {
                if ($favorit == $zeile['nr']) {
                    $mitte = $zeile['pos'];
                }
            }
        } else {
            $mitte = $platz;
        }
        $von = $mitte - $unter;
        $bis = $mitte + $ueber;
        if ($von <= 0) {
            $von = 1;
            $bis = $unter + 1 + $ueber < $anzahl ? $unter + 1 + $ueber : $anzahl;
        }
        if ($bis > $anzahl) {
            $bis = $anzahl;
            $von = $anzahl - $unter - 1 - $ueber > 0 ? $anzahl - $unter - 1 - $ueber : 1;
        }

        $zeilen = [];
        $letzteKlasse = '';
        foreach ($tabelle as $j => $z) {
            if ($z['pos'] < $von || $z['pos'] > $bis) {
                continue;
            }
            $tendenz = 0;
            foreach ($vorher as $v) {
                if ($z['nr'] == $v['nr']) {
                    $tendenz = $v['pos'] - $z['pos'];
                    break;
                }
            }
            $differenz = $z['pTor'] - $z['mTor'];
            $bild = $tendenz > 0 ? 'TendenzPfeilUpImg' : ($tendenz < 0 ? 'TendenzPfeilDownImg' : 'TendenzPunktImg');
            [$stil, $klasse] = $this->stil($z['pos'], $j, $anzahl, $wert, $mini);
            // Linie zwischen den Platzgruppen
            if ($j && $letzteKlasse != $klasse && !empty($mini['StyleStripLine'])) {
                $stil .= 'border-top: ' . self::text($mini['StyleStripLine']) . ';';
            }
            $letzteKlasse = $klasse;
            if ($favorit > 0 && $z['nr'] == $favorit) {
                $stil .= 'font-weight:bolder;';
            }
            $zeilen[] = [
                'Platz' => '<strong>' . $z['pos'] . '</strong>',
                'TeamBild' => $this->bilder->bild($z['name'], 'teams', 'small'),
                'TeamBildMiddle' => $this->bilder->bild($z['name'], 'teams', 'middle'),
                'TeamBildBig' => $this->bilder->bild($z['name'], 'teams', 'big'),
                'TeamLang' => $z['name'],
                'TeamMittel' => $z['mittel'],
                'Team' => $z['kurz'],
                'Teamnotiz' => $z['notiz'],
                'Punkte' => $wert('MinusPoints') == 1 ? (string) $z['pPkt'] : $z['pPkt'] . ':' . $z['mPkt'],
                'PlusTore' => (string) $z['pTor'],
                'MinusTore' => (string) $z['mTor'],
                'Tordifferenz' => $differenz > 0 ? '+' . $differenz : (string) $differenz,
                'Spiele' => (string) $z['spiele'],
                'Siege' => (string) $z['s'],
                'Unentschieden' => (string) $z['u'],
                'Niederlagen' => (string) $z['n'],
                'Tendenz' => $tendenz > 0 ? '+' . $tendenz : (string) $tendenz,
                'TendenzIMG' => '<img src="' . $this->bilderAdresse . '/' . self::text($mini[$bild] ?? '') . '" border="0" title="' . $tendenz . '">',
                'Style' => $stil,
                // die Klasse der Lieblingsmannschaft (classFavorit) setzt lmo-minitab.php wie bis 4.5 leer
                'Class' => $klasse,
            ];
        }

        $sprache = is_array($this->text['mini'] ?? null) ? $this->text['mini'] : [];

        return [
            'werte' => [
                'LigaBild' => $this->bilder->bild(basename($datei), 'liga', 'small'),
                'LigaBildMiddle' => $this->bilder->bild(basename($datei), 'liga', 'middle'),
                'LigaBildBig' => $this->bilder->bild(basename($datei), 'liga', 'big'),
                'ligaDatum' => self::text($sprache[14] ?? '') . ': ' . date('d.m.Y', $stand),
                'URL_TO_LMO' => $this->adresse,
                'URL_TO_TEMPLATEDIR' => $this->vorlagenAdresse . '/mini/',
                'Link' => $this->adresse . '/?action=table&amp;file=' . $datei,
                'Tabelle' => self::text($this->text[16] ?? ''),
            ],
            'bloecke' => ['Inhalt' => $zeilen],
        ];
    }

    /**
     * Stil und CSS-Klasse einer Zeile: die Streifen (stripedTable), darueber die Farben der
     * Platzgruppen aus dem Design (lmo_tabelle_background1 bis 6) und die Klassen des Mini. Mit
     * stripedVorPlatz=1 bleibt der Streifen, die Platzgruppe gibt nur Textfarbe und Klasse.
     *
     * @param \Closure(string): int $wert Einstellung der Liga als Zahl
     * @param array<mixed> $mini die Konfiguration des Mini
     * @return array{string, string}
     */
    private function stil(int $pos, int $j, int $anzahl, \Closure $wert, array $mini): array
    {
        $stil = '';
        if (!empty($mini['stripedTable'])) {
            $farben = explode(';', trim(self::text($mini['stripedTable'])));
            $stil .= 'background-color: ' . ($j % 2 == 0 ? $farben[0] : ($farben[1] ?? '')) . ';';
        }
        $gruppe = null;
        if ($pos <= $wert('Champ')) {
            $gruppe = ['tabelle_classMeister', 1];
        } elseif ($pos <= $wert('Champ') + $wert('CL')) {
            $gruppe = ['tabelle_classCLAufsteiger', 2];
        } elseif ($pos <= $wert('Champ') + $wert('CL') + $wert('CK')) {
            $gruppe = ['tabelle_classCLQuali', 3];
        } elseif ($pos <= $wert('Champ') + $wert('CL') + $wert('CK') + $wert('UC')) {
            $gruppe = ['tabelle_classUEFA', 4];
        } elseif ($pos > $anzahl - $wert('AB')) {
            $gruppe = ['tabelle_classAbsteiger', 6];
        } elseif ($pos > $anzahl - $wert('AB') - $wert('AR')) {
            $gruppe = ['tabelle_classAbstiegsRelegation', 5];
        }
        if ($gruppe === null) {
            return [$stil, ''];
        }
        [$klasse, $nr] = $gruppe;
        if (!empty($mini['stripedTable']) && !empty($mini['stripedVorPlatz'])) {
            // der Streifen bleibt (stripedVorPlatz=1); von der Platzgruppe nur Textfarbe und Klasse
            $stil .= isset($this->cfg['lmo_tabelle_color' . $nr]) ? 'color: ' . self::text($this->cfg['lmo_tabelle_color' . $nr]) . ';' : '';

            return [$stil, isset($mini[$klasse]) ? self::text($mini[$klasse]) : ''];
        }
        // die Farbe der Platzgruppe ersetzt den Streifen
        $stil = isset($this->cfg['lmo_tabelle_background' . $nr]) ? 'background: ' . self::text($this->cfg['lmo_tabelle_background' . $nr]) . ' repeat;' : '';
        $stil .= isset($this->cfg['lmo_tabelle_color' . $nr]) ? 'color: ' . self::text($this->cfg['lmo_tabelle_color' . $nr]) . ';' : '';

        return [$stil, isset($mini[$klasse]) ? self::text($mini[$klasse]) : ''];
    }

    /**
     * Die Tabelle bis zu einem Spieltag; mit Handicap in der Reihenfolge nach der Angabe des
     * Spieltags (HS) wie die Haupttabelle, bis zum letzten Spieltag gerechnet die des aktuellen.
     *
     * @return list<array{pos: int, nr: int, name: string, kurz: string, mittel: string, notiz: string, pPkt: int, mPkt: int, pTor: int, mTor: int, spiele: int, s: int, u: int, n: int}>
     */
    private static function tabelle(Liga $liga, int $bis): array
    {
        $tabelle = Tabelle::berechne($liga, Tabelle::GESAMT, 1, $bis);
        $e = $liga->einstellungen();
        $runde = $bis >= $e->spieltage() ? max(1, $e->aktuellerSpieltag()) : $bis;
        $reihenfolge = $e->mitHandicap()
            ? $tabelle->reihenfolgeNachHandicap(isset($liga->spieltage[$runde]) ? (string) ($liga->spieltage[$runde]->werte['HS'] ?? '0') : '0')
            : $tabelle->reihenfolge;
        $zeilen = [];
        foreach ($reihenfolge as $i => $nr) {
            $z = $tabelle->zeilen[$nr];
            $m = $liga->mannschaften[$nr] ?? new Mannschaft();
            $zeilen[] = ['pos' => $i + 1, 'nr' => $nr, 'name' => (string) $m->name, 'kurz' => (string) $m->kurz, 'mittel' => (string) $m->mittel,
                'notiz' => (string) ($m->werte['NOT'] ?? ''), 'pPkt' => $z->punkte, 'mPkt' => $z->minuspunkte, 'pTor' => $z->tore, 'mTor' => $z->gegentore,
                'spiele' => $z->spiele, 's' => $z->siege, 'u' => $z->unentschieden, 'n' => $z->niederlagen];
        }

        return $zeilen;
    }

    private static function text(mixed $wert): string
    {
        return is_scalar($wert) ? (string) $wert : '';
    }
}
