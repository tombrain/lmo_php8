<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Kontoseiten eines angemeldeten Tippers: Kennwort aendern (todo=pwchange) und Konto loeschen
 * (todo=delaccount). Bis 4.5 lmo-tipppwchange.php und lmo-tippdelaccount.php; die uebrigen Seiten
 * zeigt noch der alte Code.
 */
final class KontoController implements Controller
{
    private const SEITEN = ['pwchange', 'delaccount'];

    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen Tipperdatei und Tipps */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
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
        $inhalt = $kontext->anfrage->text('todo') == 'delaccount'
            ? $this->kontoLoeschen($kontext, $datei, $tipper)
            : $this->kennwortAendern($kontext, $datei, $tipper);

        return new Seite($this->rahmen->html($kontext, $inhalt));
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
