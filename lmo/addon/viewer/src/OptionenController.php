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

namespace Lmo\Addon\Viewer;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Eine Ansicht des Viewers anlegen (todo=vieweroptions, nur Hauptadmin), als Assistent in vier
 * Schritten: Einstellungen (formular1), Ligen (formular2 mit B2), Mannschaften (formular3) und
 * Speichern als config/viewer/<Name>.view mit dem PHP-Code zum Einbinden. Bis 4.5
 * lmo-adminvieweroptions.php.
 */
final class OptionenController implements Controller
{
    /**
     * Die Einstellungen der Ansicht in der Reihenfolge der Datei: Feld => [Art, Vorgabe]. Art
     * zahl: intval, schalter: 1 wenn gesetzt, text: wie eingegeben; Vorgabe ohne Feld.
     */
    private const EINSTELLUNGEN = [
        'anzahl_tage_plus' => ['zahl', ''],
        'anzahl_tage_minus' => ['zahl', ''],
        'anzahl_spieltage_vor' => ['zahl', ''],
        'anzahl_spieltage_zurueck' => ['zahl', ''],
        'datumsformat' => ['text', 'd.m.Y'],
        'heute_highlight' => ['schalter', ''],
        'tabelle_verlinken' => ['schalter', ''],
        'spielberichte_neues_fenster' => ['schalter', ''],
        'mannschaftshomepages_verlinken' => ['schalter', ''],
        'mannschaftsnamen' => ['text', ''],
        'titelzeile' => ['text', ''],
        'anstosstermin' => ['text', ''],
        'template' => ['text', 'standard'],
        'uhrzeitformat' => ['text', 'H:i'],
        'tordummy' => ['text', '_'],
        'tabellensymbol' => ['text', 'tabelle.gif'],
        'spielberichtesymbol' => ['text', 'bericht.gif'],
        'notizsymbol' => ['text', 'notiz.gif'],
        'spieltagtext' => ['text', ''],
        'cache_refresh' => ['text', ''],
        'favteam_highlight' => ['schalter', ''],
    ];

    /**
     * @param string $addon Ordner des Addons (fuer den PHP-Code zum Einbinden von viewer.php)
     * @param string $vorlagen Ordner der Vorlagen des Viewers (template/viewer)
     * @param string $ansichten Ordner der gespeicherten Ansichten (config/viewer)
     * @param string $skripte Adresse der Skripte (URL_TO_JSDIR)
     */
    public function __construct(private string $addon, private string $vorlagen, private string $ansichten, private string $skripte)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'vieweroptions' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $a = $kontext->anfrage;
        $text = $kontext->text;
        $t = $kontext->texteVon('viewer');
        $ligenordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $ligen = $this->ligen($ligenordner, $text);
        $schritt1 = $a->gesetzt('formular1');
        $schritt2 = $a->gesetzt('formular2');
        $schritt3 = $a->gesetzt('formular3');
        $dateiname = '';
        $einstellungen = '';
        if ($schritt1) {
            $dateiname = $a->gesetzt('dateiname') ? $a->text('dateiname') : 'unbenannt';
            $einstellungen = $this->einstellungen($kontext);
        }
        // Schritt 2: die angekreuzten Ligen (c1, c2 ... in der Reihenfolge des Ligenordners)
        $gewaehlt = [];
        if ($a->gesetzt('B2')) {
            $dateiname = $a->text('dateiname');
            $einstellungen = implode(';', explode(';', $a->text('config_array')));
            for ($i = 1; $i < $a->zahl('zaehler'); $i++) {
                if ($a->gesetzt('c' . $i)) {
                    $gewaehlt[] = $i;
                }
            }
        }
        $ausgabe = '';
        if ($schritt3) {
            $dateiname = $a->text('dateiname');
            if (!self::gueltigerName($dateiname)) {
                // der Name muss im Ordner der Ansichten bleiben (bis 4.5 ungeprueft): zurueck zu Schritt 1
                $ausgabe = Meldung::html($kontext->pfade->bilder, ($text[567] ?? '') . ': ' . htmlspecialchars($dateiname), true);
                $schritt1 = $schritt2 = $schritt3 = false;
            } else {
                [$ausgabe] = $this->speichere($kontext, $ligenordner, $dateiname, $t);
            }
        }

        $mannschaften = $schritt2 && !$schritt3 ? $this->mannschaften($ligenordner, $ligen, $gewaehlt, $t) : [];

        return new Seite(Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_optionen.html.twig', [
            'text' => $text,
            't' => $t,
            'skripte' => $this->skripte,
            'bilder' => $kontext->pfade->bilder,
            'adresse' => $kontext->adresse . '?action=admin&todo=vieweroptions',
            'schritt' => $schritt3 ? 4 : ($schritt2 ? 3 : ($schritt1 ? 2 : 1)),
            'ausgabe' => $ausgabe,
            'gespeichert' => Meldung::html($kontext->pfade->bilder, $text[138] ?? ''),
            'code' => trim("\n<?php \n\$multi='" . $dateiname . "';\ninclude('" . $this->addon . "/viewer.php');\n?>"),
            'vorlagen' => $this->vorlagenNamen(),
            'ligen' => $ligen,
            'hinweis' => Meldung::html($kontext->pfade->bilder, $t[51] ?? '', true),
            'dateiname' => $dateiname,
            'einstellungen' => $einstellungen,
            'mannschaften' => $mannschaften,
            'anzahlMannschaften' => array_sum(array_map(static fn (array $liga): int => (int) array_sum(array_map('count', $liga['zeilen'])), $mannschaften)),
            'anzahlLigen' => count($gewaehlt),
        ]));
    }

    /**
     * Ein Name fuer eine Ansicht bleibt im Ordner der Ansichten: nicht leer, ohne / und \, nicht
     * mit einem Punkt am Anfang. Sonst ist alles erlaubt, wie bisher (auch Leerzeichen, Umlaute).
     */
    public static function gueltigerName(string $name): bool
    {
        return $name !== '' && strpbrk($name, '/\\') === false && $name[0] !== '.';
    }

    /**
     * Die Ligen im Ligenordner (ohne Unterordner), sortiert nach Dateinamen; ohne Namen "Liga ohne Namen N".
     *
     * @param array<int, string> $text
     * @return list<array{datei: string, name: string}>
     */
    private function ligen(string $ordner, array $text): array
    {
        $ligen = [];
        $ohneNamen = 0;
        foreach (scandir(rtrim($ordner, '/')) ?: [] as $datei) {
            if (strtolower(substr($datei, -4)) != '.l98' || !is_readable($ordner . $datei)) {
                continue;
            }
            $name = LigaDatei::liesAbschnitt($ordner . $datei, 'Options')['Name'] ?? '';
            if ($name == '') {
                $name = ($text[507] ?? '') . ' ' . ++$ohneNamen;
            }
            $ligen[] = ['datei' => $datei, 'name' => $name];
        }

        return $ligen;
    }

    /**
     * Die Vorlagen des Viewers (template/viewer/<Name>.tpl.php), sortiert.
     *
     * @return list<string>
     */
    private function vorlagenNamen(): array
    {
        $namen = [];
        foreach (scandir($this->vorlagen) ?: [] as $datei) {
            if (strtolower(substr($datei, -8)) == '.tpl.php') {
                $namen[] = substr($datei, 0, -8);
            }
        }

        return $namen;
    }

    /** Die Einstellungen aus Schritt 1 als Zeilen der Datei, getrennt mit ";". */
    private function einstellungen(Kontext $kontext): string
    {
        $a = $kontext->anfrage;
        $zeilen = ['modus=' . ($a->text('modus') == 1 ? '1' : '2')];
        foreach (self::EINSTELLUNGEN as $feld => [$art, $vorgabe]) {
            $wert = $vorgabe;
            if ($a->gesetzt($feld)) {
                $wert = match ($art) {
                    'zahl' => (string) intval($a->text($feld)),
                    'schalter' => '1',
                    default => $a->text($feld),
                };
            }
            $zeilen[] = $feld . '=' . $wert;
        }

        return implode(';', $zeilen);
    }

    /**
     * Schritt 3: die Mannschaften der gewaehlten Ligen zum Ankreuzen (t0, t1 ... mit Wert
     * <Datei>[<Nummer>]), je Zeile drei.
     *
     * @param list<array{datei: string, name: string}> $ligen
     * @param list<int> $gewaehlt Nummern der angekreuzten Ligen ab 1
     * @param array<int, string> $t
     * @return list<array{name: string, fehler: string, zeilen: list<list<array{feld: string, wert: string, name: string}>>}>
     */
    private function mannschaften(string $ordner, array $ligen, array $gewaehlt, array $t): array
    {
        $ergebnis = [];
        $nummer = 0;
        // wie bisher die ersten so vielen Ligen, wie angekreuzt sind, nicht die angekreuzten
        for ($nr = 1; $nr <= count($gewaehlt); $nr++) {
            $liga = $ligen[$nr - 1] ?? null;
            $datei = $liga['datei'] ?? '';
            if ($liga === null || !is_file($ordner . $datei)) {
                $ergebnis[] = ['name' => '', 'fehler' => '[' . $ordner . $datei . '] ' . ($t[50] ?? ''), 'zeilen' => []];
                continue;
            }
            $zeilen = [];
            $i = 0;
            foreach (Liga::lies($ordner . $datei)->mannschaften as $mannschaft) {
                $zeilen[intdiv($i, 3)][] = ['feld' => 't' . $nummer++, 'wert' => $datei . '[' . ($i + 1) . ']', 'name' => (string) $mannschaft->name];
                $i++;
            }
            $ergebnis[] = ['name' => $liga['name'], 'fehler' => '', 'zeilen' => array_values($zeilen)];
        }

        return $ergebnis;
    }

    /**
     * Schritt 4: speichert die Ansicht. Je angekreuzter Mannschaft eine Zeile <Datei>_<n>=<Nummer>,
     * vor der ersten einer Liga liga<n>=<Datei>.
     *
     * @param array<int, string> $t
     * @return array{string} Ausgaben dabei (Fehler)
     */
    private function speichere(Kontext $kontext, string $ordner, string $dateiname, array $t): array
    {
        $a = $kontext->anfrage;
        $ausgabe = '';
        $zeilen = ['[Viewer Ligen]'];
        $vorige = '';
        $ligen = 0;
        $nummer = 0;
        for ($i = 0; $i <= $a->zahl('zaehler'); $i++) {
            if (!$a->gesetzt('t' . $i)) {
                continue;
            }
            $wert = $a->text('t' . $i);
            $klammer = (int) strrpos($wert, '[');
            $datei = substr($wert, 0, $klammer);
            $mannschaft = substr($wert, $klammer + 1);
            $mannschaft = substr($mannschaft, 0, (int) strrpos($mannschaft, ']'));
            if (is_file($ordner . $datei)) {
                if ($datei != $vorige) {
                    $vorige = $datei;
                    $zeilen[] = 'liga' . ++$ligen . '=' . $datei;
                    $nummer = 0;
                }
            } else {
                $ausgabe .= '3. Formular SAVE_FILE: ' . $ordner . $datei . ' ' . ($t[42] ?? '') . '<br>';
            }
            $zeilen[] = $datei . '_' . ++$nummer . '=' . $mannschaft;
        }
        $inhalt = "[config]\n";
        foreach (explode(';', $a->text('config_array')) as $zeile) {
            $inhalt .= $zeile . "\n";
        }
        foreach ($zeilen as $zeile) {
            $inhalt .= $zeile . "\n";
        }
        if (@file_put_contents($this->ansichten . '/' . $dateiname . '.view', $inhalt) === false) {
            $ausgabe .= $kontext->text[567] ?? '';
        }

        return [$ausgabe];
    }
}
