<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Beobachtet, welche Dateien eine Aktion in der Testinstanz anlegt, aendert oder loescht.
 *
 * Verglichen wird der Inhalt vor und nach einem Schritt; geaenderte Textdateien erscheinen als
 * Unified Diff, neue Textdateien mit Inhalt, Binaerdateien nur mit Groesse und Pruefsumme.
 * Kennwort-Hashes (Zufalls-Salt) und Temp-Pfade werden vor dem Vergleich vereinheitlicht.
 */
final class StateTracker
{
    /** Teile der Instanz, die sich durch die Anwendung nie aendern oder nicht interessieren. */
    private const IGNORE = '#^(\.sessions|vendor|src|templates)/#';

    /** Obergrenze fuer die Ausgabe vollstaendiger Dateiinhalte im Bericht. */
    private const MAX_DUMP = 60000;

    /**
     * @return array<string,string> relativer Pfad => Inhalt
     */
    public static function read(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $info) {
            if (!$info->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($info->getPathname(), strlen($root))), '/');
            if (preg_match(self::IGNORE, $relative)) {
                continue;
            }
            $files[$relative] = (string)file_get_contents($info->getPathname());
        }
        ksort($files);
        return $files;
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     */
    public static function diff(array $before, array $after, string $instancePath): string
    {
        $report = '';
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $path) {
            $old = $before[$path] ?? null;
            $new = $after[$path] ?? null;
            if ($old === $new) {
                continue;
            }
            if ($new === null) {
                $report .= "GELOESCHT $path\n";
                continue;
            }
            if (strpos($new, "\0") !== false || ($old !== null && strpos($old, "\0") !== false)) {
                $report .= ($old === null ? 'NEU' : 'GEAENDERT') . " $path (binaer, " . strlen($new) . ' Bytes, md5 ' . md5($new) . ")\n";
                continue;
            }
            $new = self::normalize($new, $instancePath);
            if ($old === null) {
                $report .= "NEU $path (" . strlen($new) . " Bytes)\n";
                $report .= strlen($new) > self::MAX_DUMP ? "[Inhalt zu lang: md5 " . md5($new) . "]\n" : $new . (substr($new, -1) === "\n" ? '' : "\n");
                continue;
            }
            $old = self::normalize($old, $instancePath);
            // Nur Zeilenenden geaendert (CRLF-Vorlage aus einem Windows-Checkout, LF geschrieben):
            // auf Linux-Checkouts (GitHub) gibt es diese Aenderung gar nicht.
            if ($old === $new) {
                continue;
            }
            $report .= "GEAENDERT $path\n" . self::unifiedDiff($old, $new);
        }
        return $report;
    }

    private static function normalize(string $content, string $instancePath): string
    {
        $content = str_replace($instancePath, '{LMO_PATH}', $content);
        $content = str_replace("\r\n", "\n", $content);
        // Kennwort-Hashes mit zufaelligem Salt (bcrypt, argon2)
        return (string)preg_replace('/\$(?:2y|argon2id|argon2i)\$[^|\s]+/', '{HASH}', $content);
    }

    private static function unifiedDiff(string $old, string $new): string
    {
        $a = tempnam(sys_get_temp_dir(), 'lmo-a');
        $b = tempnam(sys_get_temp_dir(), 'lmo-b');
        file_put_contents($a, $old);
        file_put_contents($b, $new);
        $output = shell_exec('diff -U 2 --label vorher --label nachher ' . escapeshellarg($a) . ' ' . escapeshellarg($b) . ' 2>&1');
        @unlink($a);
        @unlink($b);
        if (!is_string($output) || $output === '') {
            return "[diff nicht verfuegbar; md5 vorher " . md5($old) . ', nachher ' . md5($new) . "]\n";
        }
        return strlen($output) > self::MAX_DUMP ? substr($output, 0, self::MAX_DUMP) . "\n[Diff gekuerzt]\n" : $output;
    }
}
