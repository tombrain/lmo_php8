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

use Lmo\Web\Vorlagen;

/**
 * Die Tippregeln: Anleitung, Punkte nach den Einstellungen des Tippspiels und Haftung, in der
 * Sprache des Besuchers (ohne eigene Texte englisch). Als eigene Seite (addon/tipp/tippregeln.php)
 * und als Dialog im Menue des Tippspiels (tipp/cfg.txt regeln). Bis 4.5 tippregeln.php, im Menue
 * eingebunden ueber die Datei aus tipp/cfg.txt regelnlink.
 */
final class Tippregeln
{
    /** Die Einstellungen, die die Regeln zeigen, mit ihrem Wert, wenn sie fehlen */
    private const EINSTELLUNGEN = ['jokertipp' => '', 'jokertippmulti' => '', 'tippBis' => '', 'rergebnis' => '', 'rtendenzdiff' => '', 'rtendenz' => '', 'rtor' => '', 'rremis' => ''];

    /**
     * @param array<string, string> $cfg Einstellungen des Tippspiels (tipp/cfg.txt)
     * @param string $sprache Sprache des Besuchers (lmouserlang)
     */
    public static function html(array $cfg, string $sprache): string
    {
        /** @var array<string, array<string, string>> $texte */
        $texte = require dirname(__DIR__) . '/lang/tippregeln.php';

        return Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'tippregeln.html.twig', [
            'r' => $texte[$sprache] ?? $texte['English'],
            'k' => $cfg + self::EINSTELLUNGEN,
        ]);
    }
}
