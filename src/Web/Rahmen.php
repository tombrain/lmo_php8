<?php

declare(strict_types=1);

namespace Lmo\Web;

use Lmo\Web\Seiten\Druckansicht;
use Lmo\Web\Seiten\Fusszeile;
use Lmo\Web\Seiten\Hauptmenue;

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
     * Die Teile des Kerns als fertiges HTML: Kopf der Seite, Menue der Liga, Hauptteil, Links zur
     * Druckansicht und Fusszeile. Die der Addons nach alter Art (Spielerstatistik, Newsticker,
     * Tippspiel) und den Copyright-Hinweis (Infolink) ergaenzt lmo-showmain2.php.
     *
     * @param string $hauptteil die Seite (Lmo\Web\Seite::$html oder vom alten Code)
     * @param bool $druckbar die Seite hat eine Druckansicht (Lmo\Web\Seite::$druckbar)
     * @param string $sprachauswahl die Auswahl der Sprache ('' ohne; Konfiguration einsprachwahl)
     * @param float|null $sekunden Berechnungszeit der Seite in Sekunden, null: nicht zeigen (calctime)
     * @return array<string, string> Teil => HTML
     */
    public static function teile(Kontext $kontext, string $hauptteil, bool $druckbar, string $sprachauswahl, ?float $sekunden): array
    {
        $text = $kontext->text;
        if ($kontext->liga !== null) {
            $titel = $kontext->liga->liga->einstellungen()->name();
        } else {
            $titel = $kontext->action == 'tipp' ? ($kontext->texteVon('tipp')[0] ?? '') : $text[53];
        }
        $teile = [
            'Titel' => $titel,
            // <html lang=""> mit ISO-Sprachcode
            'HTML_LANG' => $text[798],
            'Stylesheet' => '  <link type="text/css" rel="stylesheet" href="' . $kontext->pfade->adresse . '/lmo-style-nc.php">
  <style type="text/css">@import url("' . $kontext->pfade->adresse . '/lmo-style.php");</style>',
            'Javascript' => Vorlagen::zeige($kontext->pfade->vorlagen, 'tooltip.html.twig', []),
            'Sprachauswahl' => $sprachauswahl,
            'Hauptteil' => $hauptteil,
            'Savehtml' => Druckansicht::html($kontext, $druckbar),
        ];
        // das Menue nur mit Liga; ohne sind seine Teile leer (eigene Vorlagen nach alter Art haben die Platzhalter)
        $teile += $kontext->liga !== null ? Hauptmenue::teile($kontext) : array_fill_keys(['Kalender', 'Ergebnisse', 'Tabelle', 'Spielplan', 'Kreuztabelle', 'Fieberkurve', 'Ligastatistik', 'Info'], '');

        return $teile + Fusszeile::teile($kontext, $kontext->konfiguration->schalter('backlink'), $sekunden);
    }

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
