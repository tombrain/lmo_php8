<?php

declare(strict_types=1);

namespace Lmo\Web;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Die Twig-Vorlagen des LMO (Ordner lmo/vorlagen).
 *
 * Ausgaben werden HTML-maskiert. Was schon fertiges HTML ist (Sprachtexte mit &nbsp;, Links mit
 * &amp;, Bilder), kennzeichnet die Vorlage mit |raw.
 */
final class Vorlagen
{
    /** @var array<string, Environment> je Ordner eine Umgebung */
    private static array $umgebungen = [];

    /**
     * Fuellt eine Vorlage mit Daten und gibt das HTML zurueck.
     *
     * @param string $ordner Ordner der Vorlagen
     * @param string $vorlage Name der Vorlage relativ zum Ordner, z. B. "tabelle.html.twig"
     * @param array<string, mixed> $daten
     */
    public static function zeige(string $ordner, string $vorlage, array $daten): string
    {
        return self::umgebung($ordner)->render($vorlage, $daten);
    }

    /** Die Twig-Umgebung fuer einen Ordner; sie wird einmal je Aufruf angelegt. */
    public static function umgebung(string $ordner): Environment
    {
        return self::$umgebungen[$ordner] ??= new Environment(new FilesystemLoader($ordner), [
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);
    }
}
