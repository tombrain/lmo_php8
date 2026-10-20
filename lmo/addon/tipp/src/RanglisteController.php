<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Vorlagen;

/**
 * Die Rangliste des Tippspiels (todo=wert): Einzel-, Team- oder interne Wertung (wertung), bis zu
 * einem Spieltag oder nur dieser (stwertmodus, endtab), sortiert nach Punkten, Quote oder Spielen
 * (gewicht), in Seiten (start); mit all=1 die Gesamtwertung aller Ligen. Auch fuer nicht
 * angemeldete Besucher. Bis 4.5 lmo-tippwert.php mit lmo-tippcalcwert.php und
 * lmo-tippcalcwertteam.php.
 */
final class RanglisteController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Auswertungen */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == 'wert'
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->liga !== null;
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite($this->rahmen->auchFuerGaeste($kontext, fn (bool $angemeldet): string => $this->rangliste($kontext, $angemeldet)));
    }

    private function rangliste(Kontext $kontext, bool $angemeldet): string
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $anfrage = $kontext->anfrage;
        $sitzung = $kontext->sitzung;
        $liga = $kontext->liga?->liga;
        $datei = $kontext->liga !== null ? $kontext->liga->datei : '';
        $e = $liga?->einstellungen();
        $spieltage = $e !== null ? $e->spieltage() : 0;
        $pokal = $e !== null && $e->istPokal();

        $bis = $anfrage->zahl('endtab');
        $stand = '';
        if ($bis == 0) {
            if ($e !== null) {
                $bis = $spieltage;
            }
        } else {
            $stand = $bis . '. ' . ($text[2] ?? '');
        }
        // all wie aufgerufen in den Links, "1" ist die Gesamtwertung
        $all = $anfrage->gesetzt('all') ? $anfrage->text('all') : 0;
        $alle = $all == 1;
        $st = $anfrage->gesetzt('st') && $anfrage->text('st') !== '' ? (int) $anfrage->text('st') : ($e !== null ? $e->aktuellerSpieltag() : 0);
        if ($alle) {
            $bis = 0;
            $stand = '';
            $spieltage = 0;
        } else {
            $st = $bis;
        }
        $wertung = $anfrage->gesetzt('wertung') ? $anfrage->text('wertung') : 'einzel';
        $gewicht = $anfrage->gesetzt('gewicht') ? $anfrage->text('gewicht') : 'absolut';
        $modus = $anfrage->gesetzt('stwertmodus') ? $anfrage->text('stwertmodus') : 'bis';
        $start = $anfrage->gesetzt('start') ? $anfrage->zahl('start') : 1;
        $teamintern = $anfrage->text('teamintern');
        $einzeln = $wertung == 'einzel' || $wertung == 'intern';
        $siege = ($cfg['showstsiege'] ?? null) == 1 && !(($stand != '' && $modus == 'nur') || $alle);
        $zusatz = ($cfg['showzus'] ?? null) == 1;
        $jeSeite = $cfg['anzseite1'] ?? null;
        $jeSeite = $jeSeite < 1 ? 40 : (int) $jeSeite;

        $pfad = $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/') . 'auswert/' . ($alle ? 'gesamt' : substr($datei, 0, -4)) . '.aus';
        $lies = fn (int $bis): ?Rangliste => Rangliste::lies(
            $pfad,
            !$einzeln,
            $modus,
            $stand != '',
            $bis,
            $spieltage,
            $cfg['tippmodus'] ?? null,
            $gewicht,
            [$cfg['krit1'] ?? null, $cfg['krit2'] ?? null, $cfg['krit3'] ?? null],
            $zusatz,
            $siege
        );
        $fehlt = Tipprahmen::meldung($kontext, $t[17] ?? '', true);
        $meldungen = '';
        $vorher = [];
        if ($bis > 1 && $stand != '' && $modus != 'nur') {
            $vorige = $lies($bis - 1);
            $meldungen .= $vorige === null ? $fehlt : '';
            $vorher = $vorige?->plaetze() ?? [];
        }
        $rangliste = $lies($bis);
        $meldungen .= $rangliste === null ? $fehlt : '';
        $bis = $rangliste !== null ? $rangliste->bis : $bis;
        $anzahl = $rangliste !== null ? count($rangliste->eintraege) : 0;
        $jetzt = $rangliste?->plaetze() ?? [];

        // die Links; die Werte aus der Anfrage maskiert
        $h = static fn (string|int $wert): string => htmlspecialchars((string) $wert);
        $intern = $h(str_replace(' ', '%20', $teamintern));
        $basis = $kontext->adresse . '?action=tipp&amp;todo=wert&amp;';
        $artLink = $stand == ''
            ? $basis . 'all=' . $h($all) . '&amp;file=' . $datei . '&amp;gewicht=' . $h($gewicht) . '&amp;wertung='
            : $basis . 'stwertmodus=' . $h($modus) . '&amp;file=' . $datei . '&amp;gewicht=' . $h($gewicht) . '&amp;endtab=' . $bis . '&amp;wertung=';
        $mitte = 'stwertmodus=' . $h($modus) . '&amp;gewicht=' . $h($gewicht) . '&amp;all=' . $h($all) . '&amp;file=' . $datei . '&amp;wertung=' . $h($wertung) . '&amp;teamintern=' . $intern;
        $spieltagLink = $basis . $mitte . '&amp;endtab=';
        $seitenLink = $basis . $mitte . ($stand == '' ? '' : '&amp;endtab=' . $bis) . '&amp;start=';
        $modusLink = $basis . 'gewicht=' . $h($gewicht) . '&amp;file=' . $datei . '&amp;endtab=' . $bis . '&amp;wertung=' . $h($wertung) . '&amp;teamintern=' . $intern . '&amp;stwertmodus=';
        $gewichtLink = $stand == ''
            ? $basis . 'all=' . $h($all) . '&amp;file=' . $datei . '&amp;wertung=' . $h($wertung) . '&amp;teamintern=' . $intern . '&amp;gewicht='
            : $basis . 'stwertmodus=' . $h($modus) . '&amp;file=' . $datei . '&amp;endtab=' . $bis . '&amp;wertung=' . $h($wertung) . '&amp;teamintern=' . $intern . '&amp;gewicht=';

        // mit Auswertung zeigt die Teamwertung die Spalten der Teams immer (bis 4.5 ueberschrieb sie
        // die Einstellung tipperimteam mit der Zahl der Tipper je Team)
        $mitTeams = (!$einzeln && $rangliste !== null) || ($cfg['tipperimteam'] ?? null) >= 0;
        if ($wertung == 'intern') {
            $start = 1;
            $jeSeite = $anzahl;
        }
        $seiten = $jeSeite > 0 ? $anzahl / $jeSeite : 0;
        $auswahl = [];
        for ($i = 0; $i < $seiten; $i++) {
            $von = $i * $jeSeite + 1;
            $auswahl[] = ['von' => $von, 'bis' => min(($i + 1) * $jeSeite, $anzahl), 'link' => $von != $start ? $seitenLink . $von : null];
        }

        $zeilen = $rangliste !== null ? $this->zeilen($kontext, $rangliste, $vorher, $jetzt, [
            'einzeln' => $einzeln, 'wertung' => $wertung, 'teamintern' => $teamintern, 'start' => $start, 'ende' => min($start + $jeSeite - 1, $anzahl),
            'pfeile' => $bis > 1 && $stand != '' && $modus != 'nur', 'mitPfeilspalte' => $stand != '' && $modus != 'nur', 'mitTeams' => $mitTeams,
            'zusatz' => $zusatz, 'siege' => $siege, 'gewicht' => $gewicht, 'artLink' => $artLink,
        ]) : [];

        $verein = $sitzung->text('lmotipperverein');

        return $meldungen . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'rangliste.html.twig', [
            'text' => $text,
            't' => $t,
            'bilder' => $kontext->pfade->bilder,
            'cfg' => [
                'rergebnis' => $cfg['rergebnis'] ?? '', 'rtendenzdiff' => $cfg['rtendenzdiff'] ?? '', 'rtendenz' => $cfg['rtendenz'] ?? '',
                'rtor' => $cfg['rtor'] ?? '', 'rremis' => $cfg['rremis'] ?? '',
            ],
            'spalten' => self::spalten($cfg, $zusatz),
            'titel' => $angemeldet ? $sitzung->text('lmotippername') . ($verein != '' ? ' - ' . $verein : '') : ($t[158] ?? ''),
            'mitTeams' => $mitTeams,
            'einzeln' => $einzeln,
            'wertung' => $wertung,
            'artLink' => $artLink,
            'internLink' => $verein != '' || $wertung == 'intern' ? ($wertung == 'intern' ? '' : $artLink . 'intern&amp;teamintern=' . rawurlencode($verein)) : null,
            'spieltagsmenue' => (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga, $st, $spieltagLink),
            'auswahl' => $seiten > 1 ? $auswahl : [],
            'mitModus' => $all == 0,
            'modus' => $modus,
            'modusLink' => $modusLink,
            'stand' => $pokal && $stand != '' ? self::runde($text, $st, $spieltage) : $stand,
            'gewicht' => $gewicht,
            'gewichtLink' => $gewichtLink,
            'ergebnismodus' => ($cfg['tippmodus'] ?? null) == 1,
            'siege' => $siege,
            'zeilen' => $zeilen,
            'zurueck' => $stand != '' && $bis > 1 ? $spieltagLink . ($bis - 1) : null,
            'weiter' => $stand != '' && $bis < $spieltage ? $spieltagLink . ($bis + 1) : null,
            'navigation' => $stand != '',
        ]);
    }

    /**
     * Die Zeilen der sichtbaren Plaetze.
     *
     * @param array<int, int> $vorher Platz je Eintrag am Spieltag davor
     * @param array<int, int> $jetzt Platz je Eintrag
     * @param array{einzeln: bool, wertung: string, teamintern: string, start: int, ende: int, pfeile: bool, mitPfeilspalte: bool, mitTeams: bool, zusatz: bool, siege: bool, gewicht: string, artLink: string} $o
     * @return list<array<string, mixed>>
     */
    private function zeilen(Kontext $kontext, Rangliste $rangliste, array $vorher, array $jetzt, array $o): array
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $t = $kontext->texteVon('tipp');
        $nick = $kontext->sitzung->text('lmotippername');
        $verein = $kontext->sitzung->text('lmotipperverein');
        $h = static fn (string $wert): string => htmlspecialchars($wert);
        $eintraege = $rangliste->eintraege;
        $anzahl = count($eintraege);
        $ersterHatPunkte = $anzahl > 0 && $eintraege[$rangliste->eintragAuf(0)]['punkte'] > 0;
        $zeilen = [];
        // lx: erster Platz einer Gruppe gleicher Tipper, lax: zuletzt angezeigter Platz (wie bis 4.5)
        $lx = 1;
        $lax = 0;
        for ($x = 1; $x <= $anzahl; $x++) {
            if ($x < $o['start'] || $x > $o['ende']) {
                continue;
            }
            $i = $rangliste->eintragAuf($x - 1);
            $e = $eintraege[$i];
            if ($x == 1 || !$rangliste->gleichAuf($x - 1)) {
                $lx = $x;
            }
            if ($o['wertung'] == 'intern' && $o['teamintern'] != $e['team']) {
                continue;
            }
            if ($lx == $x) {
                $lax = $x;
            }
            $fett = ($o['einzeln'] && $nick == $e['nick']) || ($o['wertung'] == 'team' && $verein == $e['team']);
            $rang = $o['wertung'] != 'intern' ? $lax : $lx;
            $klasse = 'nobr';
            if ($e['punkte'] > 0) {
                $klasse = match ($rang) {
                    1 => 'lmoTabelleMeister nobr',
                    2 => 'lmoTabelleCleague nobr',
                    3 => 'lmoTabelleCleaguequali nobr',
                    default => 'nobr',
                };
            }
            if ($o['wertung'] != 'team' && $e['nick'] == '') {
                continue;
            }
            $platz = null;
            if ($lax == $x) {
                $platz = $x;
            } elseif ($o['wertung'] == 'intern' && $lax != $lx) {
                $platz = $lx;
                $lax = $lx;
            }
            $pfeil = 0;
            if ($o['pfeile'] && $ersterHatPunkte) {
                $pfeil = ($jetzt[$i] ?? 0) < ($vorher[$i] ?? 0) ? 1 : (($jetzt[$i] ?? 0) > ($vorher[$i] ?? 0) ? 2 : 0);
            }
            $p = $e['p'];
            if ($o['zusatz'] && ($cfg['tippmodus'] ?? null) == 1 && !(($cfg['rtendenzdiff'] ?? null) > ($cfg['rtendenz'] ?? null))) {
                // ohne eigene Spalte zaehlen die Punkte fuer die Tordifferenz zur Tendenz
                $p[3] += $p[2];
            }
            $teamLink = static fn (string $team): string => $o['artLink'] . 'intern&amp;teamintern=' . htmlspecialchars(str_replace(' ', '%20', $team));
            $zeilen[] = [
                'klasse' => $klasse,
                'fett' => $fett,
                'platz' => $platz,
                'pfeil' => $o['mitPfeilspalte'] ? $pfeil : null,
                'name' => $o['einzeln'] ? $this->name($cfg, $e, $h) : null,
                'team' => $h($e['team']),
                'teamLink' => $o['wertung'] != 'intern' && ($o['einzeln'] ? $e['team'] != '' : $e['team'] != ' ') ? $teamLink($e['team']) : null,
                'tipper' => $e['tipper'],
                'schnitt' => !$o['einzeln'] && $e['tipper'] > 0 ? number_format($e['punkte'] / $e['tipper'], 2, '.', ',') : '',
                'spiele' => $e['spiele'],
                'p' => $p,
                'siege' => $e['siege'],
                'quote' => number_format($e['quote'] / 100, 2, '.', ','),
                'punkte' => $e['punkte'],
            ];
        }

        return $zeilen;
    }

    /**
     * Name, Nick und Email eines Tippers nach showname, shownick und showemail.
     *
     * @param array<string, mixed> $cfg
     * @param array{nick: string, name: string, email: string} $e
     * @param \Closure(string): string $h
     */
    private function name(array $cfg, array $e, \Closure $h): string
    {
        $name = $cfg['showname'] ?? null;
        $nick = $cfg['shownick'] ?? null;
        $email = $cfg['showemail'] ?? null;
        $mail = "<a href='mailto:" . $h($e['email']) . "'>";
        $html = '';
        if ($name == 1) {
            $mitMail = $email == 1 && $e['email'] != '';
            $html .= ($mitMail ? $mail : '') . $h($e['name']) . ($mitMail ? '</a>' : '');
        }
        if ($nick == 1 || ($email == 0 && $name == 0)) {
            $html .= ($name == 1 ? ' (' : '') . ($name == 0 && $email == 1 ? $mail : '') . $h($e['nick'])
                . ($name == 0 && $email == 1 ? '</a>' : '') . ($name == 1 ? ')' : '');
        } elseif ($email == 1 && $name == 0) {
            $html .= $mail . $h($e['email']) . '</a>';
        }

        return $html;
    }

    /**
     * Welche Zusatzspalten es gibt (showzus): richtiges Ergebnis, Tordifferenz, Tendenz und Tore
     * nur im Ergebnis-Tippmodus, Unentschieden-Bonus und Joker immer.
     *
     * @param array<string, mixed> $cfg
     * @return array<int, bool>
     */
    private static function spalten(array $cfg, bool $zusatz): array
    {
        $ergebnis = ($cfg['tippmodus'] ?? null) == 1;

        return [
            1 => $zusatz && $ergebnis && ($cfg['rergebnis'] ?? null) > 0,
            2 => $zusatz && $ergebnis && ($cfg['rtendenzdiff'] ?? null) > ($cfg['rtendenz'] ?? null),
            3 => $zusatz && $ergebnis && ($cfg['rtendenz'] ?? null) > 0,
            4 => $zusatz && $ergebnis && ($cfg['rtor'] ?? null) > 0,
            5 => $zusatz && ($cfg['rremis'] ?? null) > 0,
            6 => $zusatz && ($cfg['jokertipp'] ?? null) == 1,
        ];
    }

    /**
     * Name einer Pokalrunde: Finale, Halbfinale, Viertelfinale, Achtelfinale, sonst "N. Runde".
     *
     * @param array<int|string, mixed> $text
     */
    private static function runde(array $text, int $st, int $runden): string
    {
        return match ($st) {
            $runden => (string) ($text[374] ?? ''),
            $runden - 1 => (string) ($text[373] ?? ''),
            $runden - 2 => (string) ($text[372] ?? ''),
            $runden - 3 => (string) ($text[371] ?? ''),
            default => $st . '. ' . ($text[370] ?? ''),
        };
    }
}
