<?php

declare(strict_types=1);

namespace Lmo\Addon\Ticker;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Vorlagen;

/**
 * Der Newsticker: ein Lauftext mit den Ergebnissen des aktuellen Spieltags oder den News einer
 * oder mehrerer Ligen. Auf der Ligaseite zeigt ihn das Addon (TickerAddon), auf anderen Seiten
 * eingebunden addon/ticker/ticker.php.
 */
final class Ticker
{
    private const TRENNER = ' +++ ';

    /**
     * @param int $art 1 = alle Ergebnisse, 2 = News der Liga, 3 = nur die Spiele der Lieblingsmannschaft (tickerart)
     * @param bool $notizen Notizen der Spiele zeigen (notizanzeigen)
     * @param array<int, string> $text Sprachtexte des LMO
     * @param array<int, string> $tickerText Sprachtexte des Addons (lang/ticker)
     */
    public function __construct(
        private int $art,
        private bool $notizen,
        private array $text,
        private array $tickerText,
    ) {
    }

    /**
     * Der Lauftext fuer die Ligen, nacheinander.
     *
     * @param list<Liga|null> $ligen die Ligen; null fuer eine, die es nicht gibt oder die der Besucher nicht sehen darf
     */
    public function text(array $ligen): string
    {
        $trenner = self::TRENNER;
        $text = $this->text;
        $tickerText = $this->tickerText;
        $ticker_text = '';
        foreach ($ligen as $liga) {
            if ($liga === null) {
                // anhaengen statt ersetzen: eine fehlende Liga loescht nicht den Text der anderen
                $ticker_text .= $text[224] . $trenner;
                continue;
            }
            // je Liga neu beginnen: sonst stehen die Spiele der vorigen Ligen noch einmal bei der naechsten
            $hilf = $hilf1 = '';
            $e = $liga->einstellungen();
            // der aktuelle Spieltag der Liga
            $st = $e->aktuellerSpieltag();
            if ($this->art == 2) {
                // Zeilen des Newstickers aus [News]; NC ist nur deren Anzahl
                foreach ($liga->news as $schluessel => $zeile) {
                    if ($schluessel != 'NC' && strlen(stripslashes($zeile)) > 0) {
                        $hilf .= $trenner . stripslashes($zeile);
                    }
                }
                $ticker_text .= $hilf;
            } else {
                // die Paarungen des Spieltags und ihre Spiele (Lmo\Liga\Paarung, Lmo\Liga\Spiel); in der Liga je eines
                foreach ($liga->paarungen($st) as $paarung) {
                    if (!(($paarung->heim > 0) && ($paarung->gast > 0))) {
                        continue;
                    }
                    $heim = $paarung->heimMannschaft()->anzeigename();
                    $gast = $paarung->gastMannschaft()->anzeigename();
                    $favteam = $e->lieblingsmannschaft();
                    foreach ($paarung->spiele as $spiel) {
                        $zusatz = $spiel->zusatzText($text[0], $text[1]);
                        $mspezhilf = $zusatz == '&nbsp;' ? '' : ' ' . $zusatz;
                        if (!($favteam == $paarung->heim || $favteam == $paarung->gast || $this->art == 1)) {
                            continue;
                        }
                        $ergebnis = $heim . ' - ' . $gast . ' ' . $spiel->toreHeimText($e->torfaktor()) . ':' . $spiel->toreGastText($e->torfaktor()) . $mspezhilf;
                        if (!$e->istPokal()) {
                            $dummy1 = $spiel->wertung == 1 ? $tickerText[2] . ':' . addslashes($heim . ' ' . $text[211]) : '';
                            $dummy2 = $spiel->wertung == 2 ? $tickerText[2] . ':' . addslashes($gast . ' ' . $text[211]) : '';
                            $dummy3 = $spiel->wertung == 3 ? $tickerText[2] . ':' . addslashes($tickerText[3]) : '';
                            $dummy4 = $spiel->notiz != '' && $this->notizen ? ' Notiz' . ': ' . $spiel->notiz : '';
                            $hilf = $hilf . $ergebnis . $dummy1 . $dummy2 . $dummy3 . $dummy4 . $trenner;
                        } else {
                            $dummy4 = $spiel->notiz != '' && $this->notizen ? ' ' . $text[22] . ': ' . $spiel->notiz : '';
                            $hilf1 = $hilf1 . $ergebnis . $dummy4 . $trenner;
                        }
                    }
                }
            }
            $ticker_text .= " $trenner {$e->name()} ({$st}{$tickerText[1]}): $hilf $hilf1";
        }

        return $ticker_text;
    }

    /**
     * Der Ticker als HTML: Lauftext mit jQuery Marquee.
     *
     * @param string $vorlagen Ordner der Twig-Vorlagen
     * @param string $adresse Adresse des LMO (URL_TO_LMO), fuer die Skripte unter js/
     * @param string $lauftext text()
     * @param bool $titel Ueberschrift zeigen (tickertitel)
     * @param string $breite Breite in Buchstaben (breite)
     * @param string $geschwindigkeit Dauer eines Durchlaufs in Millisekunden (geschwindigkeit)
     */
    public function html(string $vorlagen, string $adresse, string $lauftext, bool $titel, string $breite, string $geschwindigkeit): string
    {
        return Vorlagen::zeige($vorlagen, 'ticker.html.twig', [
            'adresse' => $adresse,
            'titel' => $titel ? $this->tickerText[0] : '',
            'lauftext' => $lauftext,
            'breite' => $breite,
            'geschwindigkeit' => $geschwindigkeit,
        ]);
    }
}
