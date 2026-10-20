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

namespace Lmo\Addon\Ticker;

use Lmo\Addon\MitRahmenteilen;
use Lmo\Web\Kontext;

/**
 * Addon "ticker": der Newsticker ueber einer Liga, wenn er in der Liga angeschaltet ist
 * (Option ticker, im Ergebniseditor). Einstellungen aus config/ticker/cfg.txt.
 */
final class TickerAddon implements MitRahmenteilen
{
    public function name(): string
    {
        return 'ticker';
    }

    public function rahmenteile(Kontext $kontext): array
    {
        $liga = $kontext->liga;
        if ($liga === null || !$liga->liga->einstellungen()->mitNewsticker()) {
            return [];
        }
        $cfg = $kontext->konfiguration->addon('ticker');
        // die Einstellungen lassen sich (wie beim eingebundenen ticker.php) mit Parametern der Adresse aendern
        $wert = static fn (string $parameter, string $schluessel): string => $kontext->anfrage->gesetzt($parameter)
            ? $kontext->anfrage->text($parameter)
            : ($cfg[$schluessel] ?? '');
        $ticker = new Ticker((int) $wert('tickerart', 'tickerart'), $wert('tickernotizen', 'notizanzeigen') == 1, $kontext->text, $kontext->texteVon('ticker'));

        return ['Newsticker' => $ticker->html(
            $kontext->pfade->adresse,
            $ticker->text([$liga->liga]),
            $wert('tickertitel', 'tickertitel') == 1,
            $wert('tickerbreite', 'breite'),
            $wert('tickergeschwindigkeit', 'geschwindigkeit')
        )];
    }
}
