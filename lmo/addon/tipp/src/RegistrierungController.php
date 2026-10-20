<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Registrierung eines neuen Tippers (todo=newtipper): Nick, Name, Adresse, E-Mail-Adresse,
 * Kennwort, Verein und die Ligen, die er tippen will. Danach meldet er sich an. Bis 4.5
 * lmo-tippernew.php (mit lmo-tippcheckteam.php, lmo-tippnewteams.php und lmo-tippnewdir.php).
 */
final class RegistrierungController implements Controller
{
    /**
     * @param string $ordner Ordner des Addons (addon/tipp), dort liegen Tipperdatei und Tipps
     * @param Closure(string, string, string): string $freischaltmail schickt E-Mail-Adresse, Nick und Kennwort an den Tipper, gibt die Meldung zurueck
     * @param Closure(string): string $adminmail meldet dem Admin den neuen Nick, gibt einen Fehler zurueck ('' ohne)
     */
    public function __construct(private string $ordner, private Closure $freischaltmail, private Closure $adminmail)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == 'newtipper'
            && !$kontext->anfrage->gesetzt('viewermode');
    }

    public function zeige(Kontext $kontext): Seite
    {
        $t = $kontext->texteVon('tipp');
        $cfg = $kontext->konfiguration->addon('tipp');
        $anfrage = $kontext->anfrage;
        $sitzung = $kontext->sitzung;
        $wert = static fn (string $name): string => trim($anfrage->text($name));
        $nick = $wert('xtippernick');
        $vorname = $wert('xtippervorname');
        $nachname = $wert('xtippernachname');
        $email = $wert('xtipperemail');
        $strasse = $wert('xtipperstrasse');
        $plz = $wert('xtipperplz');
        $ort = $wert('xtipperort');
        $vereinswahl = $anfrage->gesetzt('xtippervereinradio') ? $wert('xtippervereinradio') : '0';
        $vereinAlt = $wert('xtippervereinalt');
        $vereinNeu = $wert('xtippervereinneu');
        $kennwort = $wert('xtipperpass');
        $kennwortWiederholt = $wert('xtipperpassw');
        $mitCode = ($cfg['freischaltcode'] ?? '') == 1;
        $email2 = $mitCode ? $wert('xtipperemail2') : $email;
        $realname = (int) ($cfg['realname'] ?? 0);
        $adresse = ($cfg['adresse'] ?? '') == 1;
        $imTeam = (int) ($cfg['tipperimteam'] ?? -1);
        $gewaehlt = $anfrage->gesetzt('xtipperligen') ? $anfrage->werte('xtipperligen') : null;
        $ligen = Tippligen::tippbar($kontext);
        $datei = Tipperdatei::lies($this->ordner . '/' . ($cfg['tippauthtxt'] ?? 'lmo-tippauth.txt'));
        $meldungen = '';
        $fehler = static function (string $text) use ($kontext, &$meldungen): void {
            $meldungen .= Tipprahmen::meldung($kontext, $text, true);
        };
        $gespeichert = $wert('newpage') == 1;

        if ($gespeichert) {
            foreach ($datei->alle() as $vorhanden) {
                if (strtolower($vorhanden->nick()) == strtolower($nick)) {
                    // Nick schon vorhanden
                    $gespeichert = false;
                    $fehler($t[24] ?? '');
                }
                if (strtolower($vorhanden->email()) == strtolower($email)) {
                    // Email schon vorhanden
                    $gespeichert = false;
                    $fehler($t[201] ?? '');
                }
            }
            if ($nick == '') {
                $gespeichert = false;
                $fehler($t[112] ?? '');
            }
            if (preg_match('/[\W|_]/', $nick) != 0) {
                $gespeichert = false;
                $fehler($t[109] ?? '');
            }
            if ($realname != 0) {
                if ($vorname == '') {
                    $gespeichert = false;
                    $fehler($t[66] ?? '');
                }
                if ($nachname == '') {
                    $gespeichert = false;
                    $fehler($t[67] ?? '');
                }
                if (str_contains($nachname, '&nbsp;') || str_contains($vorname, '&nbsp;')) {
                    $gespeichert = false;
                    $fehler($t[109] ?? '');
                }
            }
            if ($adresse) {
                foreach ([[$strasse, 129], [$plz, 130], [$ort, 131]] as [$feld, $nr]) {
                    if ($feld == '') {
                        $gespeichert = false;
                        $fehler($t[$nr] ?? '');
                    }
                }
            }
            if ($email == '' || str_contains($email, '&nbsp;') || !str_contains($email, '@') || $email != $email2) {
                $gespeichert = false;
                $fehler($t[68] ?? '');
            }
            if (!$mitCode) {
                if ($kennwort == '') {
                    $gespeichert = false;
                    $fehler($t[69] ?? '');
                } elseif (strlen($kennwort) < 3) {
                    $gespeichert = false;
                    $fehler($t[73] ?? '');
                }
                if ($kennwortWiederholt != $kennwort) {
                    $gespeichert = false;
                    $fehler($t[70] ?? '');
                }
            } else {
                // das Kennwort kommt mit der Freischaltmail
                $kennwort = bin2hex(random_bytes(8));
            }
            // der Verein: einer der Vereine der Tipper (hoechstens tipperimteam Tipper) oder ein neuer
            $vereine = $datei->vereine();
            if ($vereinswahl == 1) {
                if ($vereinAlt == '') {
                    $gespeichert = false;
                    $fehler($t[71] ?? '');
                } elseif (isset($vereine[$vereinAlt]) && $vereine[$vereinAlt] >= $imTeam && $imTeam != 0) {
                    // max. Tipperanzahl schon erreicht
                    $gespeichert = false;
                    $fehler($t[142] ?? '');
                }
            }
            if ($vereinswahl == 2) {
                if ($vereinNeu == '') {
                    $gespeichert = false;
                    $fehler($t[72] ?? '');
                } elseif (isset($vereine[$vereinNeu])) {
                    // Team existiert bereits
                    $gespeichert = false;
                    $fehler($t[143] ?? '');
                }
            }
        }

        if ($gespeichert) {
            $verein = $vereinswahl == 1 ? $vereinAlt : ($vereinswahl == 2 ? $vereinNeu : '');
            $sitzung->setze('lmotipperverein', $verein);
            $name = $vorname . '&nbsp;' . $nachname;
            $neu = Tipper::ausZeile(implode('|', [
                $nick,
                '',
                // ohne Freischaltung durch den Admin gleich freigeschaltet
                ($cfg['freischaltung'] ?? '') == 0 ? '5' : '',
                $realname != -1 ? $name : '',
                $email,
                $verein,
                $adresse ? $strasse : '',
                $adresse ? $plz : '',
                $adresse ? $ort : '',
                '1',
                '1',
                'EOL',
            ]))->mitKennwort($kennwort);
            $tipps = $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/');
            $auswertung = "\n[" . $nick . "]\nTeam=" . $verein . "\nName=" . $name . "\n";
            foreach ($ligen as $liga) {
                // nur Ligen aus der Liste: bis 4.5 ging jeder Name aus dem Formular in den Dateinamen
                if (in_array($liga['datei'], $gewaehlt ?? [], true)) {
                    // Tipp-Datei erstellen
                    file_put_contents($tipps . $liga['datei'] . '_' . $nick . '.tip', "\n", LOCK_EX);
                    file_put_contents($tipps . 'auswert/' . $liga['datei'] . '.aus', $auswertung, FILE_APPEND | LOCK_EX);
                }
            }
            $meldungen .= $datei->plus($neu)->speichere()
                ? Tipprahmen::meldung($kontext, $kontext->text[138] ?? '')
                : Tipprahmen::meldung($kontext, $kontext->text[283] ?? '', true);
            @file_put_contents($tipps . 'auswert/gesamt.aus', $auswertung, FILE_APPEND | LOCK_EX);
            /**Freischaltcode versenden*/
            if ($mitCode) {
                $meldungen .= ($this->freischaltmail)($email, $nick, $kennwort);
            }
            if (($cfg['mailbeianmeldung'] ?? '') == 1) {
                $meldungen .= ($this->adminmail)($nick);
            }
            $sitzung->setze('lmotippername', $nick);
        }
        // nach der Registrierung meldet sich der Tipper selbst an
        $sitzung->setze('lmotipperok', 0);

        // die Vereine fuer die Auswahl, nach Namen sortiert (wie bis 4.5 ueber Name und Nummer als Text)
        $auswahl = [];
        $nr = 0;
        foreach ($datei->vereine() as $vereinsname => $tipperzahl) {
            $auswahl[strtolower((string) $vereinsname) . (50000000 + $nr++)] = ['name' => (string) $vereinsname, 'tipper' => $tipperzahl];
        }
        ksort($auswahl, SORT_STRING);

        return new Seite($meldungen . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'registrierung.html.twig', [
            'text' => $kontext->text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'gespeichert' => $gespeichert,
            'realname' => $realname != 0,
            'mitAdresse' => $adresse,
            'mitCode' => $mitCode,
            'team' => $imTeam >= 0,
            'werte' => ['nick' => $nick, 'vorname' => $vorname, 'nachname' => $nachname, 'strasse' => $strasse, 'plz' => $plz, 'ort' => $ort,
                'email' => $email, 'email2' => $email2, 'kennwort' => $kennwort, 'wiederholt' => $kennwortWiederholt, 'vereinNeu' => $vereinNeu],
            'vereinswahl' => $vereinswahl,
            'vereinAlt' => $vereinAlt,
            'vereine' => array_values($auswahl),
            // ohne Auswahl im Formular sind alle Ligen angekreuzt
            'ligen' => array_map(static fn (array $liga): array => $liga + ['angekreuzt' => $gewaehlt === null || in_array($liga['datei'], $gewaehlt, true)], $ligen),
        ]));
    }
}
