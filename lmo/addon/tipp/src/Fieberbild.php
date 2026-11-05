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

/**
 * Das Bild der Tipp-Fieberkurve (PNG): je Spieltag die Punkte bzw. der Platz eines oder zweier
 * Tipper als Linie in einem Raster. Die Werte kommen als Parameter der Adresse, wie
 * FieberkurveController sie setzt: pganz (1 oder 2 Tipper), pgteam1/2 (Namen), max (hoechster
 * Wert), pgst (Spieltage), pgplatz1/2 (Werte, mit Komma getrennt, der letzte zaehlt nicht),
 * pgplatz1a/2a (zweite Linien, Modus 3 und 4), kmodus (1 Punkte, 2 Plaetze mit farbigen Rangzeilen,
 * 3 und 4 mit zweiten Linien), pgtext1/2 (Achsen). Bis 4.5 lmo-tipppaintgraph.php.
 */
final class Fieberbild
{
    /**
     * @param array<string, string> $werte Parameter der Adresse
     * @param array<string, string|null> $farben Farben des Designs (cfg.txt), null wenn nicht gesetzt:
     *        lmo_inner_background1, lmo_inner_color1, lmo_tabelle_background1 bis 3
     */
    public static function png(array $werte, array $farben): string
    {
        $wert = static fn (string $name): string => $werte[$name] ?? '';
        $kmodus = $wert('kmodus');
        $zwei = $wert('pganz') == 2;
        // wie bis 4.5 lose verglichen: ohne kmodus nur die erste Linie
        $mitErster = $kmodus < 4;
        $mitZweiter = $kmodus > 2;
        $max = isset($werte['max']) ? self::zahl($werte['max']) : 0;
        if ($max < 1) {
            $max = 10;
            $min = 1;
        } else {
            $min = $max;
        }

        // die Linien als Text (fuer "leer") und ihr kleinster Wert
        $linien = [];
        foreach (['linie' => [$mitErster, 'pgplatz1'], 'lini2' => [$mitErster && $zwei, 'pgplatz2'], 'liniea' => [$mitZweiter, 'pgplatz1a'], 'lini2a' => [$mitZweiter && $zwei, 'pgplatz2a']] as $name => [$an, $parameter]) {
            if (!$an) {
                continue;
            }
            $linien[$name] = explode(',', $wert($parameter));
            for ($i = 0; $i < count($linien[$name]) - 1; $i++) {
                if (strlen($linien[$name][$i]) > 0 && self::zahl($linien[$name][$i]) < $min) {
                    $min = self::zahl($linien[$name][$i]);
                }
            }
        }
        // die Linien als Abstand vom oberen Rand (in Zeilen); der letzte Wert bleibt wie bis 4.5 unverrechnet
        $hoehen = [];
        foreach ($linien as $name => $linie) {
            $hoehen[$name] = [];
            foreach ($linie as $i => $text) {
                if (strlen($text) == 0) {
                    continue;
                }
                if ($i == count($linie) - 1) {
                    $hoehen[$name][$i] = self::zahl($text);
                } else {
                    $hoehen[$name][$i] = $kmodus == 1 && ($name == 'linie' || $name == 'lini2') ? $max - self::zahl($text) : self::zahl($text) - $min;
                }
            }
        }

        $khoch = $max - $min > 0 ? round(240 / ($max - $min)) : 12;
        if ($khoch > 12) {
            $khoch = 12;
        }
        if ($khoch < 1) {
            $khoch = 1;
        } elseif ($max - $min < 21) {
            $khoch = 12;
        }
        $pgst = self::zahl($wert('pgst'));
        $hoch = (int) ((($max - $min + 1) * $khoch + 12) + 47);
        $breit = (int) ((($pgst + 1) * 12) + 35);
        $bild = imagecreate(max(1, $breit), max(1, $hoch));
        if ($bild === false) {
            return '';
        }

        // die zuerst belegte Farbe ist der Hintergrund
        $farbe = self::farbe($farben['lmo_inner_background1'] ?? null, [255, 255, 255]);
        self::belege($bild, $farbe);
        $luminanz = 0.3 * $farbe[0] + 0.59 * $farbe[1] + 0.11 * $farbe[2];
        $hell = $luminanz > 127 ? 190 : 127;
        $gitter = self::belege($bild, [(int) round($farbe[0] + $hell - $luminanz), (int) round($farbe[1] + $hell - $luminanz), (int) round($farbe[2] + $hell - $luminanz)]);
        $schrift = self::belege($bild, self::farbe($farben['lmo_inner_color1'] ?? null, [0, 0, 0]));
        $blau = self::belege($bild, [0, 0, 255]);
        $dunkelblau = self::belege($bild, [0, 0, 128]);
        $rot = self::belege($bild, [255, 0, 0]);
        $dunkelrot = self::belege($bild, [128, 0, 0]);
        $raenge = [];
        if ($kmodus > 1) {
            $raenge[1] = self::belege($bild, self::farbe($farben['lmo_tabelle_background1'] ?? null, [237, 244, 156]));
            $raenge[2] = self::belege($bild, self::farbe($farben['lmo_tabelle_background2'] ?? null, [204, 205, 254]));
            $raenge[3] = self::belege($bild, self::farbe($farben['lmo_tabelle_background3'] ?? null, [166, 238, 237]));
        }

        imagestring($bild, 2, 28, (int) (28 + (($max - $min + 1) * $khoch + 12)), $wert('pgtext1'), $schrift);
        imagestringup($bild, 2, 4, $hoch - 28, $wert('pgtext2'), $schrift);
        for ($i = $min; $i <= $max; $i++) {
            $j = $kmodus == 1 ? $max - $i + $min : $i;
            $j = $j < 10 ? '0' . $j : (string) $j;
            if ((int) ($i - $min) % (int) (13 - $khoch) == 0) {
                imagestring($bild, 1, 18, (int) (24 + ($khoch / 2) + (($i - $min) * $khoch)), $j, $schrift);
                imagestring($bild, 1, (int) (20 + (($pgst + 1) * 12)), (int) (24 + ($khoch / 2) + (($i - $min) * $khoch)), $j, $schrift);
            }
        }
        for ($i = 1; $i <= $pgst; $i++) {
            $j = $i < 10 ? '0' . $i : (string) $i;
            imagestring($bild, 1, 19 + ($i * 12), 18, $j, $schrift);
            imagestring($bild, 1, 19 + ($i * 12), (int) (18 + (($max - $min + 1) * $khoch + 12)), $j, $schrift);
        }
        for ($i = $min; $i <= $max; $i++) {
            imagerectangle($bild, 29, (int) (28 + (($i - $min) * $khoch)), (int) (17 + (($pgst + 1) * 12)), (int) (28 + $khoch + (($i - $min) * $khoch)), $gitter);
        }
        for ($i = 0; $i < $pgst; $i++) {
            imagerectangle($bild, 29 + ($i * 12), 28, 41 + ($i * 12), (int) (16 + (($max - $min + 1) * $khoch + 12)), $gitter);
        }
        // die ersten drei Plaetze farbig
        if ($kmodus > 1 && $khoch > 1) {
            for ($i = $min; $i <= $max; $i++) {
                if ($i == 1 || $i == 2 || $i == 3) {
                    for ($k = 1; $k <= $pgst; $k++) {
                        imagefill($bild, 20 + ($k * 12), (int) (29 + (($i - $min) * $khoch)), $raenge[(int) $i]);
                    }
                }
            }
        }
        imagestring($bild, 3, 19, 1, stripslashes($wert('pgteam1')), $blau);
        if ($zwei) {
            imagestring($bild, 3, $breit - imagefontwidth(3) * strlen(stripslashes($wert('pgteam2'))) - 2, 1, stripslashes($wert('pgteam2')), $rot);
        }
        $farbeDer = ['linie' => $blau, 'liniea' => $dunkelblau, 'lini2' => $rot, 'lini2a' => $dunkelrot];
        for ($i = 1; $i < $pgst; $i++) {
            foreach (['linie', 'liniea', 'lini2', 'lini2a'] as $name) {
                if (!isset($hoehen[$name][$i], $hoehen[$name][$i - 1])) {
                    continue;
                }
                $von = $hoehen[$name][$i - 1] * $khoch;
                $bis = $hoehen[$name][$i] * $khoch;
                foreach ([[24, 28], [23, 28], [24, 29], [23, 29]] as [$x, $y]) {
                    imageline($bild, $x + ($i * 12), (int) ($y + $khoch / 2 + $von), $x + (($i + 1) * 12), (int) ($y + $khoch / 2 + $bis), $farbeDer[$name]);
                }
            }
        }

        ob_start();
        imagepng($bild);

        return (string) ob_get_clean();
    }

    /** Ein Wert der Adresse als Zahl, wie bis 4.5 beim Rechnen mit dem Text (keine Zahl: 0). */
    private static function zahl(string $wert): int|float
    {
        return is_numeric($wert) ? +$wert : 0;
    }

    /**
     * Eine Farbe aus dem Design (#rgb oder #rrggbb); nicht gesetzt die Vorgabe, in anderer Form
     * schwarz (wie bis 4.5).
     *
     * @param list<int> $vorgabe
     * @return list<int>
     */
    private static function farbe(?string $farbe, array $vorgabe): array
    {
        if ($farbe === null) {
            return $vorgabe;
        }
        if (strlen($farbe) == 4) {
            return [(int) hexdec($farbe[1] . $farbe[1]), (int) hexdec($farbe[2] . $farbe[2]), (int) hexdec($farbe[3] . $farbe[3])];
        }
        if (strlen($farbe) == 7) {
            return [(int) hexdec(substr($farbe, 1, 2)), (int) hexdec(substr($farbe, 3, 2)), (int) hexdec(substr($farbe, 5, 2))];
        }

        return [0, 0, 0];
    }

    /** @param list<int> $farbe */
    private static function belege(\GdImage $bild, array $farbe): int
    {
        return (int) imagecolorallocate($bild, max(0, min(255, $farbe[0])), max(0, min(255, $farbe[1])), max(0, min(255, $farbe[2])));
    }
}
