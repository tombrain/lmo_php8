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

namespace Lmo\Addon\Tipp;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Fieberkurve des Tippspiels (todo=fieber): fuer einen oder zwei Tipper die Punkte oder der
 * Platz je Spieltag als Bild (lmo-tipppaintgraph.php zeichnet es), gewaehlt im Formular darueber
 * (xstat1, xstat2, xkurvenmodus mit save). Auch fuer nicht angemeldete Besucher. Bis 4.5
 * lmo-tippfieber.php mit lmo-tippcalcgraph.php.
 */
final class FieberkurveController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Auswertungen */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && TippAddon::an($kontext->konfiguration)
            && $kontext->anfrage->text('todo') == 'fieber'
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->liga !== null;
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite($this->rahmen->auchFuerGaeste(
            $kontext,
            fn (bool $angemeldet): string => ($kontext->konfiguration->addon('tipp')['tippfieber'] ?? null) == 1 ? $this->fieberkurve($kontext) : ''
        ));
    }

    private function fieberkurve(Kontext $kontext): string
    {
        $geladen = $kontext->liga;
        if ($geladen === null) {
            return '';
        }
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $anfrage = $kontext->anfrage;
        // die Auswahl kommt mit dem Formular (save), sonst wie bis 4.5 aus der Adresse
        $gespeichert = $anfrage->gesetzt('save');
        $erster = $gespeichert ? trim($anfrage->text('xstat1')) : ($anfrage->gesetzt('fieber_stat1') ? $anfrage->text('fieber_stat1') : '-1');
        $zweiter = $gespeichert ? trim($anfrage->text('xstat2')) : ($anfrage->gesetzt('fieber_stat2') ? $anfrage->text('fieber_stat2') : '-1');
        $modus = $gespeichert ? trim($anfrage->text('xkurvenmodus')) : ($anfrage->gesetzt('kurvenmodus') ? $anfrage->text('kurvenmodus') : '1');
        if ($erster == $zweiter) {
            $zweiter = '-1';
        }
        // die Kurvenmodi 3 und 4 bot die Seite nie an (bis 4.5 brachen sie meist ab)
        $platzierung = $modus == 2;
        $spieltage = $geladen->liga->einstellungen()->spieltage();

        $kurve = Fieberkurve::lies(
            $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/') . 'auswert/' . substr($geladen->datei, 0, -4) . '.aus',
            $spieltage,
            $platzierung,
            [$cfg['krit1'] ?? null, $cfg['krit2'] ?? null, $cfg['krit3'] ?? null],
            ($cfg['showzus'] ?? null) == 1,
            ($cfg['showstsiege'] ?? null) == 1
        );
        $nicks = $kurve !== null ? $kurve->nicks : [];
        // die Auswahl nach Nick sortiert
        $reihenfolge = [];
        foreach ($nicks as $i => $nick) {
            $reihenfolge[] = strtolower($nick) . (50000000 + $i);
        }
        sort($reihenfolge, SORT_STRING);
        $auswahl = array_map(static fn (string $schluessel): array => ['nr' => intval(substr($schluessel, -7)), 'nick' => $nicks[intval(substr($schluessel, -7))] ?? ''], $reihenfolge);

        $bild = null;
        if ($kurve !== null && $nicks !== []) {
            // nur einer gewaehlt, und zwar als zweiter: dann ist er der erste (die Auswahl bleibt)
            [$a, $b] = $erster < 0 && $zweiter >= 0 ? [$zweiter, '-1'] : [$erster, $zweiter];
            $nummer = static fn (string $wert): ?int => is_numeric($wert) && (int) $wert >= 0 && isset($nicks[(int) $wert]) ? (int) $wert : null;
            $eins = $nummer($a);
            $zwei = $nummer($b);
            if ($eins !== null) {
                $werte = $platzierung ? $kurve->plaetze : $kurve->punkte;
                // das Groesste wie bis 4.5 ueber die Werte als Text ('' an Spieltagen ohne Wert)
                $hoechster = static fn (int $nr): int|string => max(['', ...($werte[$nr] ?? [])]);
                $max = $zwei !== null ? max($hoechster($eins), $hoechster($zwei)) : $hoechster($eins);
                $reihe = static fn (int $nr): string => ($spieltage > 0 ? implode('', array_map(static fn (int $j): string => ($werte[$nr][$j] ?? '') . ',', range(0, $spieltage - 1))) : '') . '0';
                $bild = $kontext->pfade->adresse . '/addon/tipp/lmo-tipppaintgraph.php?pganz=' . ($zwei !== null ? '2' : '1')
                    . '&amp;pgteam1=' . htmlentities($nicks[$eins])
                    . ($zwei !== null ? '&amp;pgteam2=' . htmlentities($nicks[$zwei]) : '')
                    . '&amp;max=' . $max
                    . '&amp;pgst=' . $spieltage
                    . '&amp;pgplatz1=' . $reihe($eins)
                    . ($zwei !== null ? '&amp;pgplatz2=' . $reihe($zwei) : '')
                    . '&amp;kmodus=' . ($platzierung ? '2' : '1')
                    . '&amp;pgtext1=' . ($text[135] ?? '')
                    . '&amp;pgtext2=' . ($platzierung ? ($text[136] ?? '') : strtoupper($t[38] ?? ''));
            }
        }

        return ($kurve === null ? Tipprahmen::meldung($kontext, $t[17] ?? '', true) : '')
            . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'fieberkurve.html.twig', [
                't' => $t,
                'adresse' => $kontext->adresse,
                'datei' => $geladen->datei,
                'auswahl' => $auswahl,
                'erster' => $erster,
                'zweiter' => $zweiter,
                'modus' => $modus,
                'mitKurve' => $nicks !== [],
                'bild' => $bild,
            ]);
    }
}
