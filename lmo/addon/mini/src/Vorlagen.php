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

namespace Lmo\Addon\Mini;

use LMO_Template;

/**
 * Die Vorlagen des Mini (template/mini/<name>.tpl.php). Ist eine Datei so, wie sie ausgeliefert
 * wurde, zeigt die Twig-Vorlage gleichen Namens in addon/mini/vorlagen die Werte; eine eigene oder
 * angepasste Vorlage gilt weiter nach alter Art (LMO_Template, Platzhalter <!--Name-->, veraltet).
 */
final class Vorlagen
{
    /** Die mitgelieferten Vorlagen: Name => md5 der ausgelieferten Datei (mit LF) */
    public const MITGELIEFERT = [
        'all' => '1f97bad0f6a09b102362d5ef82777921',
        'mininext' => '151e36abb2c7166955d48e46c49fe34b',
        'round' => '29905251495a8b738f4a92d26fa7fbd8',
        'round_all' => '5faf2d884e13e61c7c2deeff4d8d30b3',
        'standard' => '17f9b30c1654d0be54de3eff2eb9e034',
        'standard_old' => 'b3b718be6e4afcb9ccbd3d8f7bb590e6',
    ];

    /**
     * @param string $ordner Ordner der Vorlagen nach alter Art (template/mini)
     * @param string $twig Ordner der Twig-Vorlagen (addon/mini/vorlagen)
     */
    public function __construct(private string $ordner, private string $twig)
    {
    }

    /** Wird die Vorlage mit Twig gezeigt (die ausgelieferte Fassung)? */
    public function mitTwig(string $name): bool
    {
        $datei = $this->ordner . '/' . $name . '.tpl.php';
        if (!isset(self::MITGELIEFERT[$name]) || !is_file($datei)) {
            return false;
        }

        return md5(str_replace("\r\n", "\n", (string) file_get_contents($datei))) === self::MITGELIEFERT[$name];
    }

    /**
     * Die Vorlage mit den Werten.
     *
     * @param array<string, string> $werte Platzhalter => Wert
     * @param array<string, list<array<string, string>>> $bloecke Block => Zeilen mit ihren Platzhaltern
     */
    public function zeige(string $name, array $werte, array $bloecke): string
    {
        if ($this->mitTwig($name)) {
            return \Lmo\Web\Vorlagen::zeige($this->twig, $name . '.html.twig', $werte + $bloecke);
        }
        $vorlage = new LMO_Template($this->ordner);
        // nicht gesetzte Platzhalter verschwinden wie bei Twig (bis 4.5 blieben sie als HTML-Kommentar stehen)
        $vorlage->loadTemplatefile($name . '.tpl.php', true);
        foreach ($bloecke as $block => $zeilen) {
            foreach ($zeilen as $zeile) {
                $vorlage->setCurrentBlock($block);
                $vorlage->setVariable($zeile);
                $vorlage->parseCurrentBlock();
            }
        }
        $vorlage->setVariable($werte);

        return (string) $vorlage->get();
    }
}
