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

        self::assertSame(
            file_get_contents($file),
            '### ' . $name . "\n" . $actual,
            "Ausgabe weicht vom Golden Master ab: $name"
        );
    }
}
