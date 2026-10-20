<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Kontoseiten eines angemeldeten Tippers: persoenliche Daten (todo=daten), Kennwort aendern
 * (todo=pwchange) und Konto loeschen (todo=delaccount). Bis 4.5 lmo-tippdaten.php (mit
 * lmo-tippcheckteam.php und lmo-tippnewteams.php), lmo-tipppwchange.php und lmo-tippdelaccount.php;
 * die uebrigen Seiten zeigt noch der alte Code.
 */
final class KontoController implements Controller
{
    private const SEITEN = ['daten', 'pwchange', 'delaccount'];

    /**
     * @param string $ordner Ordner des Addons (addon/tipp), dort liegen Tipperdatei und Tipps
     * @param Closure(string, string, string): string $freischaltmail schickt Nick und neues Kennwort an die E-Mail-Adresse und gibt die Meldung dazu zurueck
     */
    public function __construct(private string $ordner, private Tipprahmen $rahmen, private Closure $freischaltmail)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && in_array($kontext->anfrage->text('todo'), self::SEITEN, true)
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->sitzung->zahl('lmotipperok') == 5;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $datei = $this->datei($kontext);
        $tipper = $datei->tipper($kontext->sitzung->text('lmotippername'));
        if ($tipper === null) {
            // angemeldet, aber nicht (mehr) in der Datei: bis 4.5 brach die Seite hier ab (exit)
            return new Seite($this->rahmen->html($kontext, ''));
        }
        $inhalt = match ($kontext->anfrage->text('todo')) {
            'daten' => $this->persoenlicheDaten($kontext, $datei, $tipper),
            'delaccount' => $this->kontoLoeschen($kontext, $datei, $tipper),
            default => $this->kennwortAendern($kontext, $datei, $tipper),
        };

        return new Seite($this->rahmen->html($kontext, $inhalt));
    }

    private function persoenlicheDaten(Kontext $kontext, Tipperdatei $datei, Tipper $tipper): string
    {
        $t = $kontext->texteVon('tipp');
        $cfg = $kontext->konfiguration->addon('tipp');
        $anfrage = $kontext->anfrage;
        $wert = static fn (string $name): string => trim($anfrage->text($name));
        $vorname = $wert('xtippervorname');
        $nachname = $wert('xtippernachname');
        $email = $wert('xtipperemail');
        $strasse = $wert('xtipperstrasse');
        $plz = $wert('xtipperplz');
        $ort = $wert('xtipperort');
        $vereinswahl = $anfrage->gesetzt('xtippervereinradio') ? $wert('xtippervereinradio') : '0';
        $vereinAlt = $wert('xtippervereinalt');
        $vereinNeu = $wert('xtippervereinneu');
        $email2 = ($cfg['freischaltcode'] ?? '') == 1 ? $wert('xtipperemail2') : $email;
        $realname = (int) ($cfg['realname'] ?? 0);
        $adresse = ($cfg['adresse'] ?? '') == 1;
        $imTeam = (int) ($cfg['tipperimteam'] ?? -1);
        $meldungen = '';
        $fehler = static function (string $text) use ($kontext, &$meldungen): void {
            $meldungen .= Tipprahmen::meldung($kontext, $text, true);
        };
        $gespeichert = $wert('newpage') == 1;

        if (!$gespeichert) {
            $vereinswahl = $tipper->verein() == '' ? '0' : '1';
            $vereinAlt = $tipper->verein();
        } else {
            if ($realname != 0) {
                if ($vorname == '') {
                    $gespeichert = false;
                    $fehler($t[66] ?? '');
                }
                if ($nachname == '') {
                    $gespeichert = false;
                    $fehler($t[67] ?? '');
                }
                if (str_contains($nachname, ' ') || str_contains($vorname, ' ')) {
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
            if ($email == '' || str_contains($email, ' ') || !str_contains($email, '@') || $email != $email2) {
                $gespeichert = false;
                $fehler($t[68] ?? '');
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
            $kontext->sitzung->setze('lmotipperverein', $verein);
            // neue E-Mail-Adresse: neues Kennwort, das die Freischaltmail nennt; gespeichert wird nur sein Hash
            $neuesKennwort = ($cfg['freischaltcode'] ?? '') != 1 && $email != $tipper->email() ? bin2hex(random_bytes(6)) : null;
            $neu = Tipper::ausZeile(implode('|', [
                $tipper->nick(),
                $tipper->kennwort(),
                $tipper->feld(2),
                // ohne Namensfelder im Formular (realname=0) bleibt der gespeicherte Name, -1: kein Name
                $realname == 0 ? $tipper->name() : ($realname != -1 ? $vorname . ' ' . $nachname : ''),
                $email,
                $verein,
                $adresse ? $strasse : $tipper->feld(6),
                $adresse ? $plz : $tipper->feld(7),
                $adresse ? $ort : $tipper->feld(8),
                $anfrage->gesetzt('xnews') ? '1' : '-1',
                $anfrage->gesetzt('xremind') ? '1' : '-1',
                'EOL',
            ]));
            if ($neuesKennwort !== null) {
                $neu = $neu->mitKennwort($neuesKennwort);
            }
            $meldungen .= $this->speichere($kontext, $datei->mit($neu));
            if ($neuesKennwort !== null) {
                /**Freischaltcode versenden*/
                $meldungen .= ($this->freischaltmail)($email, $tipper->nick(), $neuesKennwort);
            }
        }

        // die Vereine fuer die Auswahl, nach Namen sortiert (wie bis 4.5 ueber Name und Nummer als Text)
        $auswahl = [];
        $nr = 0;
        foreach ($datei->vereine() as $name => $tipperzahl) {
            $auswahl[strtolower((string) $name) . (50000000 + $nr++)] = ['name' => (string) $name, 'tipper' => $tipperzahl];
        }
        ksort($auswahl, SORT_STRING);
        $name = $tipper->name();
        $leerzeichen = (int) strpos($name, ' ');

        return $meldungen . $this->vorlage($kontext, 'daten.html.twig', [
            'gespeichert' => $gespeichert,
            'realname' => $realname != 0,
            'mitAdresse' => $adresse,
            'email2' => ($cfg['freischaltcode'] ?? '') == 1,
            'team' => $imTeam >= 0,
            'vorname' => substr($name, 0, $leerzeichen),
            'nachname' => substr($name, $leerzeichen + 1),
            'gespeicherter' => $tipper,
            'newsletter' => $tipper->feld(9) != -1,
            'erinnerung' => $tipper->feld(10) != -1,
            'vereinswahl' => $vereinswahl,
            'vereinAlt' => $vereinAlt,
            'vereinNeu' => $vereinNeu,
            'vereine' => array_values($auswahl),
        ]);
    }

    private function kennwortAendern(Kontext $kontext, Tipperdatei $datei, Tipper $tipper): string
    {
        $t = $kontext->texteVon('tipp');
        $anfrage = $kontext->anfrage;
        $alt = $anfrage->text('xtipperpass');
        $neu = $anfrage->text('xtipperpassneu');
        $wiederholt = $anfrage->text('xtipperpassneuw');
        $meldungen = '';
        $gespeichert = $anfrage->gesetzt('newpage');

        if ($gespeichert) {
            $alt = trim($alt);
            if (!$tipper->pruefeKennwort($alt)) {
                $gespeichert = false;
                $meldungen .= Tipprahmen::meldung($kontext, $t[42] ?? '', true);
            }
        }
        if ($gespeichert) {
            $neu = trim($neu);
            if ($neu == '') {
                $gespeichert = false;
                $meldungen .= Tipprahmen::meldung($kontext, $t[69] ?? '', true);
            } elseif (strlen($neu) < 3) {
                $gespeichert = false;
                $meldungen .= Tipprahmen::meldung($kontext, $t[73] ?? '', true);
            }
            $wiederholt = trim($wiederholt);
            if ($wiederholt != $neu) {
                $gespeichert = false;
                $meldungen .= Tipprahmen::meldung($kontext, $t[70] ?? '', true);
            }
        }
        if ($gespeichert) {
            $meldungen .= $this->speichere($kontext, $datei->mit($tipper->mitKennwort($neu)));
        }

        return $meldungen . $this->vorlage($kontext, 'kennwort.html.twig', [
            'gespeichert' => $gespeichert,
            'alt' => $alt,
            'neu' => $neu,
            'wiederholt' => $wiederholt,
        ]);
    }

    private function kontoLoeschen(Kontext $kontext, Tipperdatei $datei, Tipper $tipper): string
    {
        $anfrage = $kontext->anfrage;
        $kennwort = $anfrage->text('xtipperpass');
        $meldungen = '';
        $geloescht = $anfrage->text('newpage') == 1;

        if ($geloescht) {
            $kennwort = trim($kennwort);
            if (!$tipper->pruefeKennwort($kennwort)) {
                $geloescht = false;
                $meldungen .= Tipprahmen::meldung($kontext, $kontext->texteVon('tipp')[42] ?? '', true);
            }
        }
        if ($geloescht) {
            // die Tipps des Tippers in allen Ligen
            $tipps = $this->ordner . '/' . ($kontext->konfiguration->addon('tipp')['dirtipp'] ?? 'tipps/');
            foreach (glob($tipps . '*_' . $tipper->nick() . '.tip') ?: [] as $tippdatei) {
                @unlink($tippdatei);
            }
            $meldungen .= $this->speichere($kontext, $datei->ohne($tipper->nick()));
            // abgemeldet
            $kontext->sitzung->setze('lmotipperok', 0);
            $kontext->sitzung->setze('lmotipperverein', '');
        }

        return $meldungen . $this->vorlage($kontext, 'konto_loeschen.html.twig', [
            'geloescht' => $geloescht,
            'kennwort' => $kennwort,
        ]);
    }

    /** Speichert die Tipperdatei und gibt die Meldung dazu zurueck (bis 4.5 lmo-tippsaveauth.php). */
    private function speichere(Kontext $kontext, Tipperdatei $datei): string
    {
        return $datei->speichere()
            ? Tipprahmen::meldung($kontext, $kontext->text[138] ?? '')
            : Tipprahmen::meldung($kontext, $kontext->text[283] ?? '', true);
    }

    /** @param array<string, mixed> $daten */
    private function vorlage(Kontext $kontext, string $vorlage, array $daten): string
    {
        $verein = $kontext->sitzung->text('lmotipperverein');

        return Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', $vorlage, $daten + [
            'text' => $kontext->text,
            't' => $kontext->texteVon('tipp'),
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'tipper' => $kontext->sitzung->text('lmotippername') . ($verein != '' ? ' - ' . $verein : ''),
        ]);
    }

    private function datei(Kontext $kontext): Tipperdatei
    {
        return Tipperdatei::lies($this->ordner . '/' . ($kontext->konfiguration->addon('tipp')['tippauthtxt'] ?? 'lmo-tippauth.txt'));
    }
}
