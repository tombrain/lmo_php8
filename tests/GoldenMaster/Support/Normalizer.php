<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Macht Ausgaben vergleichbar: entfernt Laufzeit-Zufaelliges (Session-IDs,
 * Temp-Pfade, Zufallsfarben) und vereinheitlicht Whitespace.
 *
 * Die Whitespace-Normalisierung ist Absicht: Beim Umstellen auf Twig aendern
 * sich Einrueckung und Zeilenumbrueche, nicht aber die Struktur. Verglichen wird
 * deshalb eine kanonische Form mit einem Tag pro Zeile.
 */
final class Normalizer
{
    public static function html(string $html, string $instancePath, string $query = ''): string
    {
        $html = str_replace($instancePath, '{LMO_PATH}', $html);
        $html = str_replace("\r\n", "\n", $html);

        // Stack-Traces von PHP-Fehlern nennen die Hilfsskripte der Tests samt Zeilennummer
        // (z. B. ".../bin/http.php(57)"). Aendert sich ein Hilfsskript, verschiebt sich die Zeile.
        $html = preg_replace('#\S*/tests/GoldenMaster/bin/(\w+\.php)\(\d+\)#', '{HARNESS}/$1(N)', $html);

        // Session-ID in Links und Formularen (session.use_trans_sid)
        $html = preg_replace('/PHPSESSID=[A-Za-z0-9,-]+/', 'PHPSESSID={SID}', $html);
        $html = preg_replace('/(name="PHPSESSID"\s+value=")[^"]*(")/', '$1{SID}$2', $html);

        // Fieberkurve: Teamfarben sind zufaellig (mt_rand ohne Seed)
        if (strpos($query, 'action=graph') !== false) {
            $html = preg_replace('/\b\d{1,3},\d{1,3},\d{1,3},1\b/', '{RGBA}', $html);
        }

        return self::canonical($html);
    }

    /**
     * Admin-Seiten: wie html(), zusaetzlich wird die Laufzeitanzeige in der Fusszeile
     * ("<Text>: 0.0123 sek.") durch einen Platzhalter ersetzt.
     */
    public static function admin(string $html, string $instancePath, string $query = ''): string
    {
        $html = preg_replace('/(<td class="lmoFooter" align="right">[^<]*?: )\d+\.\d{4}( )/', '$1{ZEIT}$2', $html);
        // "Letztes Update der Liga: <Datum Uhrzeit>" zeigt die Aenderungszeit der Ligadatei. Nach einem
        // Speichern ist das die echte Uhrzeit (die Dateizeit unterliegt nicht der festen Testuhr).
        $html = preg_replace(
            '/(<td class="lmoFooter" valign="bottom" align="right">[^<]*?&nbsp;)\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}(<br>)/',
            '$1{STAND}$2',
            $html
        );
        return self::html($html, $instancePath, $query);
    }

    /** Whitespace zusammenfassen, ein Tag pro Zeile. */
    public static function canonical(string $html): string
    {
        $html = preg_replace('/[ \t\r\n]+/', ' ', $html);
        $html = preg_replace('/>\s*</', ">\n<", $html);
        $html = preg_replace('/\s+>/', '>', $html);
        return trim($html) . "\n";
    }
}
