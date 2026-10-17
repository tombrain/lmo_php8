<?php

declare(strict_types=1);

namespace Lmo\Web;

/**
 * Der Rahmen der oeffentlichen Seiten (Titel, Menue, Hauptteil, Fusszeile) mit den Vorlagen
 * rahmen.html.twig (der LMO in eine andere Seite eingebunden) und rahmen_komplett.html.twig
 * (lmo.php direkt aufgerufen: ganze HTML-Seite). Sie ersetzen die mitgelieferten Vorlagen
 * template/lmo-standard.tpl.php und template/lmo-standard-komplett.tpl.php; eigene Vorlagen
 * nach alter Art zeigt vorerst weiter lmo-showmain2.php.
 */
final class Rahmen
{
    /** die mitgelieferten Vorlagen nach alter Art, die dieser Rahmen ersetzt: Name => ganze Seite? */
    public const MITGELIEFERT = ['lmo-standard.tpl.php' => false, 'lmo-standard-komplett.tpl.php' => true];

    /** Die Teile des Rahmens, wie die Platzhalter der alten Vorlagen heissen. */
    public const TEILE = [
        'Titel', 'HTML_LANG', 'Stylesheet', 'Javascript', 'Newsticker',
        'Kalender', 'Ergebnisse', 'Tabelle', 'Spielplan', 'Kreuztabelle', 'Fieberkurve', 'Ligastatistik', 'Spielerstatistik', 'Tippspiel', 'Info',
        'Hauptteil', 'Savehtml', 'Sprachauswahl', 'Ligenuebersicht', 'LetzteAuswertung', 'Berechnungszeit', 'Infolink',
    ];

    /**
     * Die Vorlage nach alter Art, die eine Seite benutzt: eine ligaeigene [liga].tpl.php, wenn es
     * sie gibt, sonst LMO_TEMPLATE.
     *
     * @param string $ordner Ordner der Vorlagen nach alter Art (PATH_TO_TEMPLATEDIR)
     * @param string $datei Ligadatei ('' ohne)
     * @param string $vorgabe LMO_TEMPLATE
     */
    public static function alteVorlage(string $ordner, string $datei, string $vorgabe): string
    {
        $eigene = basename($datei) . '.tpl.php';

        return $datei !== '' && is_file($ordner . '/' . $eigene) ? $eigene : $vorgabe;
    }

    /** Ersetzt der Twig-Rahmen diese Vorlage (eine der mitgelieferten)? */
    public static function ersetzt(string $alteVorlage): bool
    {
        return isset(self::MITGELIEFERT[$alteVorlage]);
    }

    /**
     * Die Seite als HTML.
     *
     * @param string $vorlagen Ordner der Twig-Vorlagen
     * @param string $alteVorlage die mitgelieferte Vorlage, die ersetzt wird (bestimmt, ob ganze Seite)
     * @param array<string, string> $teile Teil => fertiges HTML (TEILE); fehlende sind leer
     */
    public static function zeige(string $vorlagen, string $alteVorlage, array $teile): string
    {
        $werte = [];
        foreach (self::TEILE as $teil) {
            $werte[$teil] = $teile[$teil] ?? '';
        }

        return Vorlagen::zeige($vorlagen, self::MITGELIEFERT[$alteVorlage] ?? false ? 'rahmen_komplett.html.twig' : 'rahmen.html.twig', ['teile' => $werte]);
    }
}
