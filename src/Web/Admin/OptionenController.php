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

use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Controller\KalenderController;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Optionen der Installation (todo=options, nur der Hauptadmin) auf drei Seiten (show): 0
 * Allgemein (Sprache, Zeitzone, Ligenordner, Zeit- und Datumsformat, E-Mail, Sortierung der
 * Ligen), 1 Tabelle, 2 Schalter. Mit save=1 werden die Werte der gezeigten Seite gespeichert.
 * Bis 4.5 lmo-adminoptions.php.
 */
final class OptionenController implements Controller
{
    /** Datumsformate zur Auswahl; jedes andere steht im Textfeld */
    private const DATUMSFORMATE = ['d.m. H:i', 'd.m.Y H:i', 'D., d.m. H:i', 'l, d.m. H:i', 'D., d.m.Y H:i', 'l, d.m.Y H:i'];

    /** Die Schalter auf Seite 2 (Feld x<Name>) */
    private const SCHALTER = ['backlink', 'archivlink', 'calctime', 'einsavehtml', 'einsprachwahl', 'einspieler', 'eintippspiel', 'einspielfrei', 'einzutore', 'einzutoretab', 'einzustats'];

    private int $jetzt;

    /**
     * @param string $konfigurationsordner Ordner der cfg.txt (PATH_TO_CONFIGDIR)
     * @param int|null $jetzt Zeitpunkt fuer die Beispiele der Datumsformate; null: time()
     */
    public function __construct(private string $konfigurationsordner, ?int $jetzt = null)
    {
        $this->jetzt = $jetzt ?? time();
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'options' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $show = $anfrage->zahl('show');
        $konfiguration = $kontext->konfiguration;
        $meldung = '';
        if ($anfrage->zahl('save') == 1) {
            // gespeichert werden nur Werte, die die Konfiguration schon hat (wie lmo-savecfg.php)
            $konfiguration = $konfiguration->mit(array_intersect_key(self::geaendert($anfrage, $show, $kontext), $konfiguration->alle()));
            $meldung = $konfiguration->speichere($this->konfigurationsordner)
                ? Meldung::html($kontext->pfade->bilder, $text[138] ?? '')
                : Meldung::html($kontext->pfade->bilder, $text[283] ?? '', true);
        }

        $sprachen = [];
        foreach (glob($kontext->pfade->lmo . '/lang/lang-*.txt') ?: [] as $datei) {
            if (preg_match('/lang-(.+)\.txt$/', $datei, $treffer)) {
                $sprachen[] = $treffer[1];
            }
        }
        $werte = [];
        foreach (array_merge(['deflang', 'timezone', 'dirliga', 'deftime', 'defdateformat', 'aadr', 'liga_sort', 'liga_sort_direction', 'tabpkt', 'tabonres'], self::SCHALTER) as $schluessel) {
            $werte[$schluessel] = $konfiguration->wert($schluessel);
        }
        $namen = $kontext->texteVon('date');
        $datumsformate = [];
        foreach (self::DATUMSFORMATE as $format) {
            $datumsformate[$format] = KalenderController::uebersetzt(date($format, $this->jetzt), $namen);
        }
        $beispiele = [];
        foreach (['D', 'l', 'd', 'j', 'n', 'M', 'F', 'y', 'Y', 'H', 'i', 'A'] as $zeichen) {
            $beispiele[$zeichen] = KalenderController::uebersetzt(date($zeichen, $this->jetzt), $namen);
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_optionen.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'show' => $show,
            'datei' => $kontext->liga->datei ?? '',
            'k' => $werte,
            'sprachen' => $sprachen,
            'zeitzonen' => self::zeitzonen(),
            'datumsformate' => $datumsformate,
            'eigenesFormat' => !in_array($konfiguration->wert('defdateformat'), self::DATUMSFORMATE),
            'beispiele' => $beispiele,
        ]));
    }

    /**
     * Die Werte aus dem Formular der Seite show.
     *
     * @return array<string, string|int>
     */
    private static function geaendert(Anfrage $anfrage, int $show, Kontext $kontext): array
    {
        $konfiguration = $kontext->konfiguration;
        $alt = static fn (string $name, string $schluessel): string => $anfrage->gesetzt($name) ? $anfrage->text($name) : $konfiguration->wert($schluessel);
        switch ($show) {
            case 0:
                $dirliga = trim($anfrage->text('xdirliga'));
                if ($dirliga == '') {
                    $dirliga = './';
                }
                $dirliga = str_replace('\\', '/', $dirliga);       // (Falschen) Backslash -> Slash
                if (substr($dirliga, -1) != '/') {
                    $dirliga .= '/';  // Slash ergänzen falls nicht vorhanden
                }

                //Zeitformat kontrollieren
                $zeit = explode(':', $anfrage->text('xdeftime', '15:30'));

                return [
                    'dirliga' => $dirliga,
                    'deflang' => $anfrage->gesetzt('xdeflang') ? trim($anfrage->text('xdeflang')) : $konfiguration->wert('deflang'),
                    'deftime' => date('H:i', (int) mktime((int) $zeit[0], isset($zeit[1]) ? (int) $zeit[1] : null)),
                    'defdateformat' => $anfrage->hat('xdefdateselect') ? $alt('xdefdateformat', 'defdateformat') : $alt('xdefdateformat2', 'defdateformat'),
                    'timezone' => $anfrage->text('xtimezone'),
                    'aadr' => $anfrage->text('xadr'),
                    'liga_sort' => $anfrage->text('xliga_sort', 'liga_name'),
                    'liga_sort_direction' => $anfrage->text('xliga_sort_direction', 'asc'),
                ];
            case 1:
                return [
                    'tabpkt' => trim($alt('xtabpkt', 'tabpkt')),
                    'tabonres' => trim($alt('xtabonres', 'tabonres')),
                ];
            case 2:
                $schalter = [];
                foreach (self::SCHALTER as $name) {
                    $schalter[$name] = $anfrage->gesetzt('x' . $name) ? 1 : 0;
                }

                return $schalter;
        }

        return [];
    }

    /**
     * Die Zeitzonen zur Auswahl, nach Kontinenten: Zeitzone => Name. Bis 4.5 get_timezones() in
     * lmo-functions.php; wie dort zaehlen nur die ersten beiden Teile des Namens.
     *
     * @return array<string, array<string, string>>
     */
    public static function zeitzonen(): array
    {
        $kontinente = ['Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific'];
        $zonen = [];
        foreach (array_reverse(timezone_identifiers_list()) as $zone) {
            $zone = explode('/', $zone); // 0 => Continent, 1 => City
            // Only use "friendly" continent names
            if (in_array($zone[0], $kontinente, true) && isset($zone[1])) {
                $zonen[$zone[0]][$zone[0] . '/' . $zone[1]] = str_replace('_', ' ', $zone[1]); // Creates array(DateTimeZone => 'Friendly name')
            }
        }

        return array_reverse($zonen);
    }
}
