<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Anmelden, Abmelden (todo=logout) und Kennwort vergessen (todo=getpass) im Tippspiel, mit der
 * Anmeldeseite fuer nicht angemeldete Besucher. Wer sich anmeldet, sieht danach seine Startseite.
 * Bis 4.5 lmo-tippstart.php, lmo-tippauth.php und lmo-tippemailpass.php.
 *
 * Der Stand der Anmeldung steht in der Sitzung (lmotipperok): 5 angemeldet, 0 nicht angemeldet,
 * -1 Kennwort falsch, -2 Nick unbekannt, -3 Nick fuer "Kennwort vergessen" unbekannt, -4
 * Registrierung (lmo-tippernew.php), -5 Kennwort vergessen.
 */
final class AnmeldungController implements Controller
{
    private const SEITEN = ['', 'logout', 'getpass'];

    /**
     * @param string $ordner Ordner des Addons (addon/tipp), dort liegen Tipperdatei und Tipps
     * @param Closure(string, string, string): ?string $kennwortmail schickt E-Mail-Adresse, Nick und neues Kennwort an den Tipper; null oder der Fehler
     */
    public function __construct(
        private string $ordner,
        private Tipprahmen $rahmen,
        private StartseiteController $startseite,
        private Closure $kennwortmail,
    ) {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        $todo = $kontext->anfrage->text('todo');

        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && in_array($todo, self::SEITEN, true)
            && !$kontext->anfrage->gesetzt('viewermode')
            // mitten in der Registrierung bleibt es bei ihr
            && !($todo == '' && $kontext->sitzung->text('lmotipperok') == -4);
    }

    public function zeige(Kontext $kontext): Seite
    {
        $sitzung = $kontext->sitzung;
        $anfrage = $kontext->anfrage;
        $t = $kontext->texteVon('tipp');
        foreach (['lmotipperok' => 0, 'lmotippername' => '', 'lmotipperpass' => '', 'lmotipperverein' => ''] as $name => $vorgabe) {
            if (!$sitzung->gesetzt($name)) {
                $sitzung->setze($name, $vorgabe);
            }
        }
        $todo = $anfrage->text('todo');
        if ($todo == 'logout') {
            $sitzung->setze('lmotipperok', 0);
            $sitzung->setze('lmotipperverein', '');
        } elseif ($todo == 'getpass') {
            $sitzung->setze('lmotipperok', -5);
        }
        $datei = $this->datei($kontext);
        $meldungen = '';
        $kennwortVergessen = $anfrage->gesetzt('xtippername2') ? $anfrage->text('xtippername2') : null;
        $mailVerschickt = false;

        // anmelden
        $ok = $sitzung->text('lmotipperok');
        if ($ok < 1 && $ok > -4 && $anfrage->hat('xtippername') && $anfrage->hat('xtipperpass')) {
            $nick = $anfrage->text('xtippername');
            $kennwort = $anfrage->text('xtipperpass');
            $sitzung->setze('lmotippername', $nick);
            $sitzung->setze('lmotipperpass', $kennwort);
            $sitzung->setze('lmotipperok', -2);
            $tipper = $datei->tipper($nick);
            if ($tipper !== null) {
                $sitzung->setze('lmotipperok', -1);
                $gespeichert = $tipper->kennwort();
                $algorithmus = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                // ein Hash wird geprueft (und mit besserem Verfahren neu gebildet), ein Kennwort im Klartext umgestellt
                $istHash = password_get_info($gespeichert)['algo'] !== null;
                if ($istHash ? password_verify($kennwort, $gespeichert) : $kennwort === $gespeichert) {
                    if (!$istHash || password_needs_rehash($gespeichert, $algorithmus)) {
                        $datei->mit($tipper->mitKennwort($kennwort))->speichere();
                    }
                    $sitzung->setze('lmotipperok', $tipper->feld(2));
                }
            }
        }

        // Kennwort vergessen: neues Kennwort per Mail an den Tipper (Nick oder E-Mail-Adresse)
        if ($sitzung->text('lmotipperok') == -5) {
            if ($kennwortVergessen === null) {
                $sitzung->setze('lmotipperok', 0);
            } else {
                $tipper = $datei->tipper($kennwortVergessen) ?? $datei->tipperMitEmail($kennwortVergessen);
                if ($tipper === null) {
                    // Benutzer nicht gefunden
                    $sitzung->setze('lmotipperok', -3);
                } else {
                    $neu = bin2hex(random_bytes(4));
                    $datei->mit($tipper->mitKennwort($neu))->speichere();
                    $mailVerschickt = true;
                    $fehler = ($this->kennwortmail)(trim($tipper->email()), $tipper->nick(), $neu);
                    if ($fehler === null) {
                        $meldungen .= Tipprahmen::meldung($kontext, $t[78] ?? ''); // Erfolgsmeldung
                        $sitzung->setze('lmotipperok', 0);
                    } else {
                        $meldungen .= Tipprahmen::meldung($kontext, ($t[80] ?? '') . ' Details: ' . $fehler, true);
                    }
                }
            }
        }

        $ok = $sitzung->text('lmotipperok');
        if ($ok == 5) {
            return $this->startseite->zeige($kontext);
        }
        if ($ok > 0) {
            // angemeldet, aber nicht freigeschaltet: Menue und Ueberschrift ohne Inhalt
            return new Seite($this->rahmen->html($kontext, ''));
        }
        if (!($ok < 1 && $ok > -4)) {
            // die Mail mit dem neuen Kennwort ging nicht raus: nur die Meldung
            return new Seite($meldungen);
        }
        if ($ok == -1) {
            $kennwortVergessen = $sitzung->text('lmotippername');
        }

        $liga = $kontext->liga;
        $ligen = Tippligen::tippbar($kontext);

        return new Seite($meldungen . $this->rahmen->menue($kontext) . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'anmeldung.html.twig', [
            'text' => $kontext->text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'adda' => $kontext->adresse . '?action=tipp&amp;todo=',
            'bilder' => $kontext->pfade->bilder,
            'liganame' => $liga !== null ? $liga->liga->einstellungen()->name() : '',
            'datei' => $anfrage->text('file'),
            'stand' => $ok,
            'nichtFreigeschaltet' => $anfrage->gesetzt('xtippersub') && $ok === '' && !$mailVerschickt,
            'nick' => $sitzung->text('lmotippername'),
            'kennwort' => $sitzung->text('lmotipperpass'),
            'nickVergessen' => $kennwortVergessen ?? '',
            'ligen' => $ligen,
            'wertungen' => Tippligen::wertungen($kontext, $ligen, $this->ordner . '/' . ($kontext->konfiguration->addon('tipp')['dirtipp'] ?? 'tipps/')),
        ]));
    }

    private function datei(Kontext $kontext): Tipperdatei
    {
        return Tipperdatei::lies($this->ordner . '/' . ($kontext->konfiguration->addon('tipp')['tippauthtxt'] ?? 'lmo-tippauth.txt'));
    }
}
