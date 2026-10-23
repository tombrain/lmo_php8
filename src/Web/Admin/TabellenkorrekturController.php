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
use Lmo\Liga\Rohdaten\Mannschaft;
use Lmo\Liga\Tabelle;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Darstellung;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Vorlagen;

/**
 * Die Tabelle eines Spieltags korrigieren (todo=tabs, Haupt- und Hilfsadmins): die Tabelle bis
 * zum Spieltag in der errechneten Reihenfolge, je Mannschaft der Platz, auf dem sie stehen soll.
 * Mit save=1 wird die Korrektur (HS im Spieltag) gespeichert. Bis 4.5 lmo-admintab.php.
 */
final class TabellenkorrekturController implements Controller
{
    /**
     * @param Closure(Liga, string, string): array{string, Liga} $speichere speichert eine Liga wie lmo-saveliga.php (Rohdaten, Datei, gewaehlter Spieltag)
     */
    public function __construct(private Closure $speichere)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'tabs' && $kontext->sitzung->zahl('lmouserok') >= 1;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        // ohne Liga (fehlt oder fuer diesen Hilfsadmin nicht freigegeben) bleibt die Seite leer
        if ($geladen === null) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        $mannschaften = $e->mannschaften();
        // ohne Spieltag in der Anfrage der aktuelle (lmo-openfile.php)
        $st = $anfrage->gesetzt('st') ? $anfrage->text('st') : ($liga->optionen['Actual'] ?? '1');
        // die Tabelle bis zum gewaehlten Spieltag, in der errechneten Reihenfolge (ohne Korrektur)
        $tabelle = Tabelle::fuerAnsicht($liga, 0, (int) $st);
        $meldung = '';
        if ($anfrage->text('save') == 1) {
            $korrektur = '';
            $aufIhremPlatz = 0;
            for ($i = 1; $i <= $mannschaften; $i++) {
                $korrektur .= sprintf('%02s', trim($anfrage->text('xplatz' . $i)));
                if ($i == trim($anfrage->text('xplatz' . $i))) {
                    $aufIhremPlatz++;
                }
            }
            // je Platz zwei Ziffern; steht jede Mannschaft auf ihrem Platz, gibt es keine Korrektur
            $liga->spieltag((int) $st)->setzeTabellenkorrektur($aufIhremPlatz == $mannschaften ? '0' : $korrektur);
            [$meldung, $liga] = ($this->speichere)($liga, $geladen->datei, $st);
            $e = $liga->einstellungen();
        }

        // der gespeicherte Platz je Zeile, ohne Korrektur der Reihe nach
        $gespeichert = $liga->spieltage[(int) $st]->werte['HS'] ?? '0';
        $plaetze = [];
        for ($i = 0; $i < $mannschaften; $i++) {
            $plaetze[$i + 1] = $gespeichert != 0 ? intval(substr($gespeichert, $i * 2, 2)) : $i + 1;
        }
        $punktfaktor = $e->punktfaktor();
        $torfaktor = $e->torfaktor();
        $zeilen = [];
        for ($x = 1; $x <= $mannschaften; $x++) {
            $i = $tabelle->reihenfolge[$x - 1];
            $zeile = $tabelle->zeilen[$i];
            $mannschaft = $liga->mannschaften[$i] ?? new Mannschaft();
            $notiz = $this->notiz($kontext->darstellung->wappen($geladen->datei, $mannschaft->anzeigename(), ' alt=""'), $mannschaft, $text, $e->mitMinuspunkten(), $punktfaktor, $torfaktor);
            $zeilen[] = [
                'x' => $x,
                'platz' => $plaetze[$x],
                'name' => $mannschaft->anzeigename(),
                'liebling' => $i == $e->lieblingsmannschaft(),
                'notiz' => $notiz !== null ? nl2br($notiz) : null,
                // dieselbe Notiz als Text fuer alert() (bis 4.5 immer leer)
                'alarm' => $notiz !== null ? (string) json_encode(trim(html_entity_decode(strip_tags($notiz))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                'spiele' => $zeile->spiele,
                'siege' => $zeile->siege,
                'unentschieden' => $zeile->unentschieden,
                'niederlagen' => $zeile->niederlagen,
                'punkte' => Darstellung::mitFaktor($zeile->punkte, $punktfaktor),
                'minuspunkte' => Darstellung::mitFaktor($zeile->minuspunkte, $punktfaktor),
                'tore' => Darstellung::mitFaktor($zeile->tore, $torfaktor),
                'gegentore' => Darstellung::mitFaktor($zeile->gegentore, $torfaktor),
                'differenz' => Darstellung::mitFaktor($zeile->tordifferenz(), $torfaktor),
            ];
        }
        $addb = LigaNavigation::adresse($kontext, 'tabs', $geladen->datei);

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_tabellenkorrektur.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'navigation' => LigaNavigation::fuer($kontext, $liga, 'tabs', $st),
            // auch der gewaehlte Spieltag ist verlinkt
            'spieltagsmenue' => (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga, (int) $st, $addb, true),
            'name' => $e->name(),
            'datei' => $geladen->datei,
            'st' => $st,
            'mannschaften' => $mannschaften,
            'unentschieden' => !$e->ohneUnentschieden(),
            'minuspunkte' => $e->mitMinuspunkten(),
            'punkteVorn' => $kontext->konfiguration->wert('tabpkt') == 0,
            'punkteHinten' => $kontext->konfiguration->wert('tabpkt') == 1,
            'breite' => 16 + ($e->mitVerlaengerung() ? 2 : 0),
            'zeilen' => $zeilen,
        ]));
    }

    /**
     * Die Notiz einer Mannschaft zum Aufklappen (Strafen, Boni, Notiz) als HTML mit Zeilenumbruechen,
     * null ohne.
     *
     * @param array<int, string> $text
     */
    private function notiz(string $wappen, Mannschaft $mannschaft, array $text, bool $mitMinuspunkten, int $punktfaktor, int $torfaktor): ?string
    {
        $name = $mannschaft->anzeigename();
        $strafp = $mannschaft->werte['SP'] ?? '0';
        $strafm = $mannschaft->werte['SM'] ?? '0';
        $tor1 = $mannschaft->werte['TOR1'] ?? '0';
        $tor2 = $mannschaft->werte['TOR2'] ?? '0';
        $strafdat = $mannschaft->werte['STDA'] ?? '0';
        $notiz = $mannschaft->werte['NOT'] ?? '';
        if (!($notiz != '' || $strafp != 0 || $strafm != 0 || $tor1 != 0 || $tor2 != 0)) {
            return null;
        }
        /*
         * Notizen anzeigen
         *
         * Achtung: Da beim Speichern Strafpunkte/-tore positiv sind und Bonuspunkte negativ (altes System des LMO)
         * muss mit -1 multipliziert werden, um die Punkte/Tore richtig anzuzeigen
         */
        $ab = $strafdat != 0 ? ' (' . ($text[524] ?? '') . ' ' . ($text[145] ?? '') . ' ' . $strafdat . ')' : '';
        // das kleine Wappen wie in der Tabelle (bis 4.5 getSmallImage(): nur .gif, mit kaputtem Attribut)
        $html = $wappen . ' <strong>' . $name . '</strong>';
        // Straf-/Bonuspunkte
        if ($strafp != 0 || $strafm != 0) {
            $html .= "\n\n<strong>" . ($text[128] ?? '') . ':</strong> ' . self::strafe($strafp, $punktfaktor);
            // Minuspunkte
            if ($mitMinuspunkten) {
                $html .= ':' . self::strafe($strafm, $punktfaktor);
            }
            $html .= $ab;
        }
        // Straf-/Bonustore
        if ($tor1 != 0 || $tor2 != 0) {
            $html .= "\n<strong>" . ($text[522] ?? '') . ':</strong> ' . self::strafe($tor1, $torfaktor) . ':' . self::strafe($tor2, $torfaktor) . $ab;
        }
        // Teamnotizen
        if ($notiz != '') {
            $html .= "\n\n<strong>" . ($text[22] ?? '') . ":</strong>\n" . $notiz;
        }

        return $html;
    }

    /** Strafe (positiv gespeichert) oder Bonus (negativ) zum Anzeigen: Bonus mit "+", Strafe negativ. */
    private static function strafe(string $wert, int $faktor): string
    {
        $zahl = Darstellung::mitFaktor($wert, $faktor);
        $gegenteil = -1 * (is_numeric($zahl) ? +$zahl : 0);

        return ($wert < 0 ? '+' : '') . $gegenteil;
    }
}
