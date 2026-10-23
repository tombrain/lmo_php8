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
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Controller\KalenderController;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Grundeinstellungen einer Liga (todo=edit&st=-1, Haupt- und Hilfsadmins) auf Reitern (show):
 * 0 Allgemein (Name, Bezeichnungen, Faktoren, Lieblings- und gewaehlte Mannschaft, Fieberkurve),
 * 2 Anzeige, 3 Punkte und Sortierung (nur Liga), 4 Tabelle (nur Liga). Mit save=1 werden die
 * Werte des Reiters gespeichert; Hilfsadmins ohne erweiterte Rechte nur die Mannschaften auf
 * Reiter 0. Bis 4.5 lmo-adminbasic.php.
 */
final class GrundeinstellungenController implements Controller
{
    /** Datumsformate zur Auswahl; jedes andere steht im Textfeld */
    private const DATUMSFORMATE = ['d.m. H:i', 'd.m.Y H:i', 'D., d.m. H:i', 'l, d.m. H:i', 'D., d.m.Y H:i', 'l, d.m.Y H:i'];

    private int $jetzt;

    /**
     * @param Closure(Liga, string, string): array{string, Liga} $speichere speichert eine Liga wie lmo-saveliga.php (Rohdaten, Datei, gewaehlter Spieltag)
     * @param string $punkte die allgemeine Bezeichnung der Punkte (Text 37 vor der eigenen der Liga, $orgpkt)
     * @param string $tore dasselbe fuer die Tore (Text 38, $orgtor)
     * @param int|null $jetzt Zeitpunkt fuer die Beispiele der Datumsformate; null: time()
     */
    public function __construct(private Closure $speichere, private string $punkte, private string $tore, ?int $jetzt = null)
    {
        $this->jetzt = $jetzt ?? time();
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -1 && $kontext->sitzung->zahl('lmouserok') >= 1;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        // ohne Liga (fehlt oder fuer diesen Hilfsadmin nicht freigegeben) bleibt die Seite leer
        if ($geladen === null) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $sitzung = $kontext->sitzung;
        $voll = $sitzung->zahl('lmouserok') == 2 || $sitzung->zahl('lmouserokerweitert') == 1;
        // Spielerstatistik geladen und eingeschaltet (Addon spieler)
        $einspieler = $kontext->addons->hat('spieler') && $kontext->konfiguration->addonSchalter('spieler', 'einspieler', true);
        $tabonres = $kontext->konfiguration->tabonres();
        $liga = $geladen->liga;

        // die Einstellungen der Liga mit ihren Vorgabewerten, Schluessel wie in [Options]; beim
        // Speichern kommt, was sich geaendert hat, in die Rohdaten
        $o = $liga->einstellungen()->alle();
        $o['DatF'] = $o['DatF'] ?? $kontext->konfiguration->datumsformat();
        $geladenOptionen = $o;
        $show = $anfrage->text('show', '0');
        $meldung = '';
        if ($anfrage->text('save', '0') == 1) {
            $o = $this->uebernimm($o, $show, $anfrage, $voll, $einspieler, $tabonres, $kontext->konfiguration->datumsformat());
            foreach ($o as $schluessel => $wert) {
                // streng wie bisher: ein Schalter (Zahl) gilt gegenueber dem Wert der Datei (Text) als geaendert
                if ($wert !== ($geladenOptionen[$schluessel] ?? null)) {
                    $liga->setzeEinstellung((string) $schluessel, is_scalar($wert) ? (string) $wert : '');
                }
            }
            [$meldung, $liga] = ($this->speichere)($liga, $geladen->datei, $anfrage->text('st'));
        }

        $namen = ['___'];
        foreach ($geladen->liga->alleMannschaften() as $nr => $mannschaft) {
            $namen[$nr] = $mannschaft->anzeigename();
        }
        $tage = $kontext->texteVon('date');
        $datumsbeispiele = [];
        foreach (self::DATUMSFORMATE as $format) {
            $datumsbeispiele[] = KalenderController::uebersetzt(date($format, $this->jetzt), $tage);
        }
        $beispiele = [];
        foreach (['D', 'l', 'd', 'j', 'm', 'M', 'F', 'y', 'Y', 'H', 'i', 'A'] as $zeichen) {
            $beispiele[$zeichen] = KalenderController::uebersetzt(date($zeichen, $this->jetzt), $tage);
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_grundeinstellungen.html.twig', [
            'text' => $kontext->text,
            'spieler' => $kontext->texteVon('spieler'),
            'adresse' => $kontext->adresse,
            'navigation' => LigaNavigation::fuer($kontext, $liga, 'edit', $anfrage->text('st')),
            'addr' => LigaNavigation::adresse($kontext, 'edit', $geladen->datei),
            'datei' => $geladen->datei,
            'st' => $anfrage->text('st'),
            'show' => $show,
            'voll' => $voll,
            'o' => $o,
            'mannschaften' => $geladen->liga->einstellungen()->mannschaften(),
            'namen' => $namen,
            'einspieler' => $einspieler,
            'tabonres' => $tabonres,
            'datumsformate' => self::DATUMSFORMATE,
            'datumsbeispiele' => $datumsbeispiele,
            'beispiele' => $beispiele,
        ]));
    }

    /**
     * Die Werte des Formulars auf dem Reiter show; die Typen wie bis 4.5 (Schalter als Zahl,
     * Eingaben als Text), weil das Speichern streng vergleicht.
     *
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    private function uebernimm(array $o, string $show, Anfrage $a, bool $voll, bool $einspieler, int $tabonres, string $datumsformat): array
    {
        $an = static fn (string $name): int => $a->gesetzt($name) ? 1 : 0;
        $oder = static fn (string $name, mixed $sonst): mixed => $a->gesetzt($name) ? trim($a->text($name)) : $sonst;
        $zahlOder = static fn (string $name, mixed $sonst): mixed => $a->gesetzt($name) && is_numeric($a->text($name)) ? $a->text($name) : $sonst;
        switch ($show) {
            case 0:
                if ($voll) {
                    $o['Name'] = $oder('xtitel', $o['Name']);
                    if ($o['Name'] == '') {
                        $o['Name'] = 'No Name';
                    }
                    $o['goalfaktor'] = $zahlOder('xgoalfaktor', $o['goalfaktor']);
                    $o['pointsfaktor'] = $zahlOder('xpointsfaktor', $o['pointsfaktor']);
                }
                $o['favTeam'] = $oder('xfavteam', $o['favTeam']);
                $o['selTeam'] = $oder('xselteam', $o['selTeam']);
                if ($o['Type'] == 1) {
                    $o['playoffmode'] = $zahlOder('xplayoffmode', $o['playoffmode']);
                }
                if ($o['Type'] == 0) {
                    $o['kurve1'] = $oder('xstat1', $o['kurve1']);
                    $o['kurve2'] = $oder('xstat2', $o['kurve2']);
                    $o['namePkt'] = $oder('xnamepkt', $o['namePkt']);
                    if ($o['namePkt'] == $this->punkte) {
                        $o['namePkt'] = '';
                    }
                    $o['nameTor'] = $oder('xnametor', $o['nameTor']);
                    if ($o['nameTor'] == $this->tore) {
                        $o['nameTor'] = '';
                    }
                }
                break;
            case 2:
                if ($voll) {
                    $o['enableGameSort'] = $an('xenablegamesort');
                    $o['DatM'] = $an('xdatm');
                    if ($a->hat('xdatfselect')) {
                        $o['DatF'] = $a->gesetzt('xdatf') ? $a->text('xdatf') : $datumsformat;
                    } else {
                        $o['DatF'] = $a->gesetzt('xdatf2') ? $a->text('xdatf2') : $o['DatF'];
                    }
                    $o['DatS'] = $an('xdats');
                    $o['Plan'] = $an('xplan');
                    $o['Ergebnis'] = $an('xergebnis');
                    if ($einspieler) {
                        $o['mittore'] = $an('xmittore');
                    }
                    $o['DatC'] = $an('xdatc');
                    if ($o['DatS'] == 0 && $o['DatM'] == 0) {
                        $o['DatC'] = 0;
                    }
                    if ($o['Type'] == 0) {
                        $o['OnRun'] = $a->gesetzt('xonrun') ? $a->text('xonrun') : $o['OnRun'];
                        $o['Graph'] = $an('xkurve');
                        $o['Kreuz'] = $an('xkreuz');
                        if ($tabonres == 0) {
                            $o['Tabelle'] = $an('xtabelle');
                        }
                        $o['Ligastats'] = $an('xligastats');
                    } else {
                        $o['KlFin'] = $an('xklfin');
                        $o['playdown'] = $an('xplaydown');
                        $o['playoffmode'] = $a->gesetzt('xplayoffmode') ? $a->text('xplayoffmode') : $o['playoffmode'];
                    }
                    $o['urlT'] = $an('xurlt');
                    $o['urlB'] = $an('xurlb');
                }
                break;
            case 3:
                if ($voll && $o['Type'] == 0) {
                    $o['MinusPoints'] = $a->gesetzt('xminus') ? 2 : 1;
                    $o['Spez'] = $an('xspez');
                    $o['HideDraw'] = $an('xhidr');
                    // direkter Vergleich: 1 = vor der Tordifferenz, 2 = erst nach Tordifferenz und Toren
                    $o['Direct'] = $a->gesetzt('xdirekt') ? ($a->gesetzt('xdirektnach') ? 2 : 1) : 0;
                    // Auswaertstore gibt es nur im direkten Vergleich
                    $o['DirectAway'] = ($o['Direct'] != 0 && $a->gesetzt('xdirektauswaerts')) ? 1 : 0;
                    $o['SortGames'] = $an('xsortgames');
                    $o['Kegel'] = $an('xkegel');
                    $o['HandS'] = $an('xhands');
                    foreach (['PointsForWin' => 'xpns', 'PointsForDraw' => 'xpnu', 'PointsForLost' => 'xpnn', 'XtraS' => 'xpxs', 'XtraU' => 'xpxu', 'XtraV' => 'xpxn', 'SpezS' => 'xpps', 'SpezU' => 'xppu', 'SpezV' => 'xppn'] as $schluessel => $feld) {
                        $o[$schluessel] = $oder($feld, $o[$schluessel]);
                    }
                }
                break;
            case 4:
                if ($voll && $o['Type'] == 0) {
                    $o['tableHinRueck'] = $an('xeinhinrueck');
                    $o['tableHeimAusw'] = $an('xeinheimausw');
                    $o['Champ'] = $an('xchamp');
                    foreach (['CL' => 'xanzcl', 'CK' => 'xanzck', 'UC' => 'xanzuc', 'AR' => 'xanzar', 'AB' => 'xanzab'] as $schluessel => $feld) {
                        $o[$schluessel] = $oder($feld, $o[$schluessel]);
                    }
                }
                break;
        }

        return $o;
    }
}
