<?php

namespace Lmo\Tests\GoldenMaster\Support;

trait AssertsSnapshots
{
    private function assertMatchesSnapshot(string $group, string $name, string $actual): void
    {
        $file = Snapshot::path($group, $name);

        if (Snapshot::recording()) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            // Erste Zeile: Name im Klartext (Dateinamen sind gekuerzt), danach die Ausgabe.
            file_put_contents($file, '### ' . $name . "\n" . $actual);
            $this->addToAssertionCount(1);
            return;
        }

        if (!is_file($file) && Snapshot::recordingMissing()) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($file, '### ' . $name . "\n" . $actual);
            $this->addToAssertionCount(1);
            return;
        }

        if (!is_file($file)) {
            self::fail(
                "Kein Snapshot fuer \"$name\" ($file). " .
                'Am unveraenderten Originalstand aufzeichnen: GOLDEN_MODE=record-missing (siehe TESTING.md).'
            );
        }

        $expected = (string)file_get_contents($file);
        $actual = '### ' . $name . "\n" . $actual;
        if (PHP_VERSION_ID < 80200) {
            // Snapshots stammen von PHP 8.3: Texte, die sich nur durch die PHP-Version unterscheiden, angleichen
            $expected = self::withoutPhpVersionDifferences($expected);
            $actual = self::withoutPhpVersionDifferences($actual);
        }

        self::assertSame($expected, $actual, "Ausgabe weicht vom Golden Master ab: $name");
    }

    /**
     * Entfernt, was PHP 8.1 anders ausgibt als die PHP-Version der Snapshots:
     * - der Hinweis "Ignoring session_start()" nennt erst ab PHP 8.2, wo die Sitzung begann
     * - Liste der Zeitzonen (haengt von der mitgelieferten Zeitzonen-Datenbank ab)
     */
    private static function withoutPhpVersionDifferences(string $text): string
    {
        $text = preg_replace('/ \(started from \S+ on line \w+\)/', '', $text);
        $text = preg_replace('~^<option value="(?:Africa|America|Antarctica|Arctic|Asia|Atlantic|Australia|Europe|Indian|Pacific)/[^"]+"[^>]*>[^<]*</option>\n~m', '', $text);

        return $text;
    }
}
