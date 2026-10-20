<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Kontoseiten eines angemeldeten Tippers: Kennwort aendern (todo=pwchange). Bis 4.5
 * lmo-tipppwchange.php; die uebrigen Seiten zeigt noch der alte Code.
 */
final class KontoController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegt die Tipperdatei */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == 'pwchange'
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
            $meldungen .= $datei->mit($tipper->mitKennwort($neu))->speichere()
                ? Tipprahmen::meldung($kontext, $kontext->text[138] ?? '')
                : Tipprahmen::meldung($kontext, $kontext->text[283] ?? '', true);
        }

        $verein = $kontext->sitzung->text('lmotipperverein');

        return new Seite($this->rahmen->html($kontext, $meldungen . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'kennwort.html.twig', [
            'text' => $kontext->text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'tipper' => $kontext->sitzung->text('lmotippername') . ($verein != '' ? ' - ' . $verein : ''),
            'gespeichert' => $gespeichert,
            'alt' => $alt,
            'neu' => $neu,
            'wiederholt' => $wiederholt,
        ])));
    }

    private function datei(Kontext $kontext): Tipperdatei
    {
        return Tipperdatei::lies($this->ordner . '/' . ($kontext->konfiguration->addon('tipp')['tippauthtxt'] ?? 'lmo-tippauth.txt'));
    }
}
