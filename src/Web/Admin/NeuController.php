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

namespace Lmo\Web\Admin;

use Closure;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Partie;
use Lmo\Liga\Zufallsspielplan;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Eine neue Liga anlegen (todo=new, nur der Hauptadmin), in Schritten (newpage):
 *  0  Dateiname, Titel, Liga oder Pokal
 *  1  Mannschaften, Spieltage, Spiele je Spieltag (Pokal: nur die Mannschaften)
 *  2  Spielplan: keiner, aus einer Liga gleicher Groesse oder ausgelost (Pokal: Spiele je Runde)
 *  3  die Liga wird angelegt
 * Bis 4.5 lmo-adminnew.php mit lmo-adminnewdir.php, lmo-adminopenprogram.php und lmo-adminrndprogram.php.
 */
final class NeuController implements Controller
{
    /** Spiele je Paarung, die ein Pokal je Runde haben kann (Texte 353 bis 357) */
    private const POKALMODI = [1 => 353, 2 => 354, 3 => 355, 5 => 356, 7 => 357];

    /**
     * @param Closure(Liga, string, string): array{string, Liga} $speichere schreibt eine Liga (Rohdaten, Dateiname im Ligenordner, gewaehlter Spieltag) wie lmo-saveliga.php; zurueck kommen dessen Meldungen und die gespeicherte Liga
     * @param Closure(int, int): int|null $zufall Zufallszahl fuer den ausgelosten Spielplan (mt_rand)
     */
    public function __construct(private Closure $speichere, private ?Closure $zufall = null)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'new' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $ordner = $kontext->konfiguration->ligenordner();
        $xfile = self::dateiname($anfrage->text('xfile'));
        $xtitel = $anfrage->text('xtitel');
        $xtype = $anfrage->text('xtype');
        $xprogram = $anfrage->text('xprogram', '0');
        $newpage = $anfrage->zahl('newpage');
        $xanzst = $anfrage->text('xanzst', '0');
        $xanzsp = $anfrage->text('xanzsp', '0');
        $xteams = $anfrage->text('xteams', '0');
        $meldung = '';
        $xmodus = [];
        $datei = '';

        if ($newpage == 0) {
            $xfile = $xfile == '' ? 'noname' : $xfile;
            $xtitel = $xtitel == '' ? 'No Name' : $xtitel;
            $xtype = $xtype == '' ? '0' : $xtype;
        }
        if ($newpage == 1 && file_exists($kontext->pfade->lmo . '/' . $ordner . $xfile . '.l98')) {
            $meldung = Meldung::html($kontext->pfade->bilder, $text[280] ?? '', true);
            $newpage = 0;
        }
        if ($newpage == 2) {
            if ($xtype == 0) {
                $xprogram = $xprogram == '' ? 'none' : $xprogram;
            } else {
                $xanzst = (string) strlen(decbin((int) $xteams - 1));
                $xmodus = array_fill(0, 7, '1');
            }
        }
        if ($newpage == 3) {
            $datei = $xfile . '.l98';
            // eine Vorlage nur, wenn das Formular sie anbietet (gleich grosse Liga im Ligenordner)
            $vorlage = null;
            foreach ($xtype == 0 ? $this->vorlagen($kontext, $xtype, $xteams, $xanzst, $xanzsp) : [] as $angeboten) {
                if ($angeboten['wert'] === $xprogram) {
                    $vorlage = $kontext->pfade->lmo . '/' . $ordner . $angeboten['wert'];
                }
            }
            // die neue Liga beginnt am ersten Spieltag
            [$meldung] = ($this->speichere)($this->neueLiga($anfrage, $text, $xtitel, $xtype, $xteams, $xanzst, $xanzsp, $xprogram, $vorlage, $kontext->konfiguration->datumsformat()), $datei, '1');
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_neu.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'ordner' => $ordner,
            'newpage' => $newpage,
            'xfile' => $xfile,
            'xtitel' => $xtitel,
            'xtype' => $xtype,
            'xteams' => $xteams,
            'xanzst' => $xanzst,
            'xanzsp' => $xanzsp,
            'xprogram' => $xprogram,
            'xmodus' => $xmodus,
            'pokalmodi' => self::POKALMODI,
            'pokalgroessen' => [2, 4, 8, 16, 24, 32, 64, 128],
            // wie bisher: leer gesendet gilt 16, ohne Wert ist nichts vorgewaehlt
            'pokalteams' => $xteams === '' ? '16' : $xteams,
            'vorlagen' => $newpage == 2 && $xtype == 0 ? $this->vorlagen($kontext, $xtype, $xteams, $xanzst, $xanzsp) : [],
            'zufall' => $newpage == 2 && $xtype == 0 && self::zufallMoeglich((int) $xteams, (int) $xanzst, (int) $xanzsp),
            'datei' => $datei,
        ]));
    }

    /**
     * Die Rohdaten der neuen Liga: die Einstellungen, mit denen eine Liga beginnt, Mannschaften mit
     * vorlaeufigen Namen und der Spielplan (leer, aus der Vorlage oder ausgelost). Alles Uebrige
     * ergaenzt das Speichern (lmo-saveliga.php).
     *
     * @param array<int|string, string> $text
     * @param string|null $vorlage Datei der Liga, deren Spielplan uebernommen wird
     */
    private function neueLiga(Anfrage $anfrage, array $text, string $xtitel, string $xtype, string $xteams, string $xanzst, string $xanzsp, string $xprogram, ?string $vorlage, string $datumsformat): Liga
    {
        $paarungen = [];
        if ($xtype == 0 && $xprogram != 'none') {
            if ($xprogram == 'random') {
                $paarungen = Zufallsspielplan::paarungen((int) $xteams, $this->zufall);
            } elseif ($vorlage !== null) {
                // Paarungen der Vorlage aus den Rohdaten der Ligadatei
                foreach (Liga::lies($vorlage)->spieltage as $nr => $spieltag) {
                    foreach ($spieltag->partien as $partieNr => $partie) {
                        $paarungen[$nr - 1][$partieNr - 1] = [$partie->heim, $partie->gast];
                    }
                }
            }
        }

        $liga = new Liga();
        $neu = ['Name' => $xtitel, 'Type' => $xtype, 'Teams' => $xteams];
        if ($xtype == 0) {
            $neu += [
                'Rounds' => $xanzst, 'Matches' => $xanzsp, 'Spez' => '0', 'HandS' => '0', 'Kegel' => '0',
                'PointsForWin' => '3', 'PointsForDraw' => '1', 'PointsForLost' => '0', 'HideDraw' => '0', 'OnRun' => '0',
                'MinusPoints' => '1', 'Direct' => '0', 'SortGames' => '0', 'DirectAway' => '0', 'Champ' => '1', 'CL' => '1', 'CK' => '1', 'UC' => '3', 'AR' => '0', 'AB' => '3',
                'tableHinRueck' => '1', 'tableHeimAusw' => '1', 'Graph' => '1', 'Kreuz' => '1', 'Tabelle' => '1', 'Ligastats' => '1',
                'kurve1' => '0', 'kurve2' => '0',
            ];
        } else {
            $neu += ['KlFin' => '0', 'playdown' => '0', 'playoffmode' => '0'];
        }
        $neu += [
            'goalfaktor' => '1', 'pointsfaktor' => '1', 'enableGameSort' => '1', 'DatC' => '1', 'DatS' => '1', 'DatM' => '1',
            'DatF' => $datumsformat, 'urlT' => '1', 'urlB' => '1', 'Plan' => '1', 'Ergebnis' => '1', 'mittore' => '1',
            'favTeam' => '0', 'selTeam' => '0', 'ticker' => '0',
        ];
        foreach ($neu as $schluessel => $wert) {
            $liga->setzeEinstellung($schluessel, $wert);
        }
        $einstellungen = $liga->einstellungen();
        for ($i = 1; $i <= $einstellungen->mannschaften(); $i++) {
            $mannschaft = $liga->mannschaft($i);
            $mannschaft->name = ($text[281] ?? '') . ' ' . $i;
            $mannschaft->kurz = ($text[282] ?? '') . $i;
        }
        for ($i = 1; $i <= $einstellungen->spieltage(); $i++) {
            $spieltag = $liga->spieltag($i);
            $spieltag->setzeErstenTag('');
            $spieltag->setzeLetztenTag('');
            $spiele = 1;
            if ($xtype != 0) {
                // Pokal: so viele Spiele je Paarung, wie fuer die Runde gewaehlt
                $spiele = $anfrage->gesetzt('xmod' . $i) ? $anfrage->zahl('xmod' . $i) : 1;
                $spieltag->setzeSpieleJePaarung($spiele);
            }
            for ($j = 1; $j <= $einstellungen->partienImSpieltag($i); $j++) {
                $partie = Partie::leer();
                for ($n = 2; $n <= $spiele; $n++) {
                    $partie->spiele[$n] = $partie->spiele[1];
                }
                if ($xtype == 0 && $xprogram != 'none') {
                    // der Spielplan aus der Vorlage oder der ausgeloste; fehlt eine Paarung, bleibt sie leer (0)
                    [$heim, $gast] = $paarungen[$i - 1][$j - 1] ?? [null, null];
                    $partie->setzeMannschaften((int) $heim, (int) $gast);
                }
                $spieltag->partien[$j] = $partie;
            }
        }

        return $liga;
    }

    /**
     * Die Ligen im Ligenordner gleicher Groesse, deren Spielplan die neue Liga uebernehmen kann
     * (Wert: Name der Datei im Ligenordner, Name der Liga; ohne Namen Text 507 mit Nummer).
     *
     * @return list<array{wert: string, name: string}>
     */
    private function vorlagen(Kontext $kontext, string $xtype, string $xteams, string $xanzst, string $xanzsp): array
    {
        $ordner = $kontext->konfiguration->ligenordner();
        $dateien = [];
        foreach (scandir($kontext->pfade->lmo . '/' . rtrim($ordner, '/')) ?: [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $vorlagen = [];
        $ohneNamen = 0;
        foreach ($dateien as $datei) {
            $o = LigaDatei::liesAbschnitt($kontext->pfade->lmo . '/' . $ordner . $datei, 'Options');
            if (($o['Teams'] ?? '') == $xteams && ($o['Rounds'] ?? '') == $xanzst && ($o['Matches'] ?? '') == $xanzsp && ($o['Type'] ?? '0') == $xtype) {
                $name = $o['Name'] ?? '';
                if ($name == '') {
                    $name = ($kontext->text[507] ?? '') . ++$ohneNamen;
                }
                $vorlagen[] = ['wert' => $datei, 'name' => $name];
            }
        }

        return $vorlagen;
    }

    /**
     * Der Dateiname der neuen Liga wie lmofilename() im Browser (js/admin.js.php): ohne Ordner und
     * ohne .l98. So bleibt die Datei im Ligenordner, auch wenn die Anfrage nicht aus dem Formular kommt.
     */
    private static function dateiname(string $eingabe): string
    {
        $name = basename(str_replace('\\', '/', $eingabe));

        return strtolower(substr($name, -4)) == '.l98' ? substr($name, 0, -4) : $name;
    }

    /**
     * Ein ausgeloster Spielplan passt nur fuer jeder gegen jeden: ab 4 Mannschaften, so viele Spiele je
     * Spieltag wie Paarungen, Hin- und Rueckrunde oder nur die Hinrunde. (Aenderungen s.janke@tu-bs.de)
     */
    private static function zufallMoeglich(int $teams, int $spieltage, int $spiele): bool
    {
        if ($teams < 4) {
            return false;
        }
        $sollSpiele = (int) floor($teams / 2);
        $sollSpieltage = floor($teams * ($teams - 1) / $sollSpiele);

        return $spiele == $sollSpiele && ($spieltage == $sollSpieltage || $spieltage == $sollSpieltage / 2);
    }
}
