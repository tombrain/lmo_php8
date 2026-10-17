<?php

declare(strict_types=1);

namespace Lmo\Web\Controller;

use Lmo\Liga\Spiel;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Kreuztabelle einer Liga (action=cross, nicht im Pokal): je Heimmannschaft eine Zeile, je
 * Gastmannschaft eine Spalte, in den Zellen die Spiele der beiden (Ergebnis oder Anstosstag,
 * verlinkt auf den Spieltag). Vorlage kreuztabelle.html.twig.
 *
 * Parameter der Anfrage:
 *  - croteam  Mannschaft, deren Zeile und Spalte hervorgehoben werden (ohne: keine)
 */
final class KreuztabelleController implements Controller
{
    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && !$kontext->liga->liga->istPokal() && $kontext->action == 'cross';
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        // die Liga kann die Kreuztabelle abschalten
        if (!$e->mitKreuztabelle()) {
            return new Seite('');
        }
        $text = $kontext->text;
        $datei = $geladen->datei;
        $markiert = $kontext->anfrage->zahl('croteam');
        $kreuzLink = $kontext->adresse . '?action=cross&amp;file=' . $datei . '&amp;croteam=';
        $spieltagLink = $kontext->adresse . '?action=results&amp;file=' . $datei . '&amp;st=';

        // Namen, Kurznamen und Wappen je Mannschaft, zum Anzeigen schon escaped
        $mannschaften = [];
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            $name = self::html($mannschaft->anzeigename());
            $kurz = self::html($mannschaft->kurzname());
            $mannschaften[$nr] = [
                'nr' => $nr,
                'name' => $name,
                'kurz' => $kurz,
                'wappen' => $kontext->darstellung->wappen($datei, $mannschaft->anzeigename(), " alt='" . $kurz . "'"),
            ];
        }
        // alle Spiele, geordnet nach Heim- und Gastmannschaft
        $begegnungen = [];
        foreach ($liga->alleSpiele() as $spiel) {
            $begegnungen[$spiel->heim][$spiel->gast][] = $spiel;
        }

        $zeilen = [];
        foreach ($mannschaften as $i => $heim) {
            $zellen = [];
            foreach ($mannschaften as $j => $gast) {
                $zellen[] = [
                    'markiert' => $markiert != 0 && ($j == $markiert || $i == $markiert),
                    'leer' => $j == $i,
                    'spiele' => $j == $i ? [] : self::spiele($begegnungen[$i][$j] ?? [], $heim['name'], $gast['name'], $kontext, $spieltagLink),
                ];
            }
            $zeilen[] = ['mannschaft' => $heim, 'markiert' => $i == $markiert, 'zellen' => $zellen];
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'kreuztabelle.html.twig', [
            'text' => $text,
            'mannschaften' => array_values($mannschaften),
            'zeilen' => $zeilen,
            'link' => $kreuzLink,
            // Wappen der nicht vorhandenen Mannschaft hinter der letzten, wie bisher in der rechten Randspalte
            'leeres_wappen' => $kontext->darstellung->wappen($datei, '', " alt=''"),
        ]));
    }

    /**
     * Die Spiele einer Heim- gegen eine Gastmannschaft fuer eine Zelle.
     *
     * @param list<Spiel> $spiele
     * @return list<array{link: string, titel: string, text: string}> Titel und Text als fertiges HTML
     */
    private static function spiele(array $spiele, string $heim, string $gast, Kontext $kontext, string $spieltagLink): array
    {
        $text = $kontext->text;
        $e = $kontext->liga?->liga->einstellungen();
        assert($e !== null);
        $zellen = [];
        // Hinweise (Wertung am gruenen Tisch, Notiz) im Tooltip; ein Spiel ohne Hinweis leert ihn
        $hinweis = '';
        foreach ($spiele as $spiel) {
            if ($spiel->notiz != '' || $spiel->wertung > 0) {
                if ($spiel->wertung == 1) {
                    $hinweis .= '&#10;&#10;' . $text[219] . ':&#10;' . $heim . ' ' . $text[211];
                }
                if ($spiel->wertung == 2) {
                    $hinweis .= '&#10;&#10;' . $text[219] . ':&#10;' . $gast . ' ' . $text[211];
                }
                if ($spiel->wertung == 3) {
                    $hinweis .= '&#10;&#10;' . $text[219] . ':&#10;' . $text[212];
                }
                if ($spiel->notiz != '') {
                    $hinweis .= '&#10;&#10;' . $text[22] . ':&#10;' . self::html($spiel->notiz);
                }
            } else {
                $hinweis = '';
            }
            // Anstoss lang im Tooltip, kurz statt des Ergebnisses, solange nicht gespielt ist
            $lang = $kurz = '';
            if ($spiel->anstoss > 0) {
                $lang = ', ' . date('D. d.m.Y H:i', $spiel->anstoss);
                $kurz = date('d.m.', $spiel->anstoss);
            }
            if ($spiel->toreHeim === null && $kurz != '') {
                $anzeige = $kurz;
            } else {
                $ergebnis = $spiel->toreHeimText($e->torfaktor()) . ':' . $spiel->toreGastText($e->torfaktor());
                $anzeige = $spiel->toreHeim !== null ? '<strong>' . $ergebnis . '</strong>' : $ergebnis;
            }
            if ($e->mitVerlaengerung()) {
                $anzeige .= ' ' . $spiel->zusatzText($text[0], $text[1]);
            }
            if ($spiel->notiz != '' || $spiel->wertung > 0) {
                $anzeige .= ' !';
            }
            $zellen[] = [
                'link' => $spieltagLink . $spiel->spieltag,
                'titel' => $heim . ' - ' . $gast . ' &#10;(' . $spiel->spieltag . '. ' . $text[2] . $lang . $hinweis . ')',
                'text' => $anzeige,
            ];
        }

        return $zellen;
    }

    /** Text fuer HTML und Attribute (auch in einfachen Anfuehrungszeichen) escaped. */
    private static function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
    }
}
