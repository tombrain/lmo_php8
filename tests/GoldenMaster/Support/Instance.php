<?php

namespace Lmo\Tests\GoldenMaster\Support;

use RuntimeException;

/**
 * Isolierte, lauffaehige LMO-Installation in einem Temp-Verzeichnis.
 *
 * Kopiert <Quelle>/lmo, legt die Konfiguration an, die sonst der Installer
 * schreibt, und fixiert alles, was die Ausgabe zeitabhaengig machen wuerde.
 * Dadurch laufen die Tests ohne Webserver und ohne Installationsschritt, und
 * die Quelle (Originalstand oder Refactoring-Stand) bleibt unveraendert.
 */
final class Instance
{
    /** 2025-01-01 12:00:00 UTC: fester Zeitstempel fuer alle Ligadateien ("Stand"-Anzeige) */
    public const FIXED_MTIME = 1735732800;

    /** feste URL, damit erzeugte Links in allen Laeufen gleich sind */
    public const URL = 'http://lmo.test/lmo';

    /** Verzeichnisse der Quelle, die nicht in die Testinstanz kopiert werden */
    private const SKIP = ['tests', '.git', 'output'];

    private string $path;

    private function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function create(string $sourceRoot): self
    {
        $source = rtrim($sourceRoot, '/') . '/lmo';
        if (!is_file($source . '/init.php')) {
            throw new RuntimeException(
                "Keine LMO-Quelle gefunden unter $source (erwartet <Quelle>/lmo/init.php). " .
                'LMO_SOURCE pruefen.'
            );
        }

        $path = sys_get_temp_dir() . '/lmo-golden-' . getmypid() . '-' . bin2hex(random_bytes(3));
        self::copyTree($source, $path, true);

        // Was der Installer sonst anlegt: Standardkonfiguration + init-parameters.php
        if (is_dir($path . '/install/config')) {
            self::mergeTree($path . '/install/config', $path . '/config');
        }
        $cfg = $path . '/config/cfg.txt';
        if (!is_file($cfg)) {
            throw new RuntimeException('config/cfg.txt fehlt auch nach dem Zusammenfuehren mit install/config.');
        }
        $text = file_get_contents($cfg);
        // Berechnungszeit ("Seite in 0.0123 Sek.") wuerde jede Ausgabe veraendern.
        $text = preg_replace('/^calctime=.*$/m', 'calctime=0', $text);
        file_put_contents($cfg, $text);

        file_put_contents(
            $path . '/config/init-parameters.php',
            "<?php\n\$lmo_dateipfad='" . $path . "';\n\$lmo_url='" . self::URL . "';\n?>"
        );

        // Was der Installer sonst anlegt bzw. als schreibbar prueft (install/install.php, Liste 777/666):
        // ohne addon/tipp/lmo-tippauth.txt bricht z. B. die Tippspiel-Benutzerverwaltung mit einem Fatal Error ab.
        foreach (['addon/tipp/tipps', 'addon/tipp/tipps/auswert', 'addon/tipp/tipps/einsicht',
            'addon/tipp/tipps/auswert/vereine', 'addon/spieler/stats', 'config/viewer'] as $dir) {
            if (!is_dir($path . '/' . $dir)) {
                mkdir($path . '/' . $dir, 0777, true);
            }
        }
        if (!is_file($path . '/addon/tipp/lmo-tippauth.txt')) {
            file_put_contents($path . '/addon/tipp/lmo-tippauth.txt', '');
        }

        foreach (['output', 'ligen/archiv', '.sessions'] as $dir) {
            if (!is_dir($path . '/' . $dir)) {
                mkdir($path . '/' . $dir, 0777, true);
            }
        }

        $instance = new self($path);
        $instance->freezeLeagueTimes();
        return $instance;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function ligenDir(): string
    {
        return $this->path . '/ligen';
    }

    public function sessionDir(): string
    {
        return $this->path . '/.sessions';
    }

    /** Nach dem Anlegen weiterer Ligen aufrufen (Datum "Stand" und Sortierung nach Dateidatum). */
    public function freezeLeagueTimes(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->ligenDir(), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                touch($file->getPathname(), self::FIXED_MTIME);
            }
        }
    }

    public function destroy(): void
    {
        if (is_dir($this->path)) {
            self::removeTree($this->path);
        }
    }

    private static function copyTree(string $from, string $to, bool $topLevel = false): void
    {
        mkdir($to, 0777, true);
        foreach (scandir($from) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if ($topLevel && in_array($name, self::SKIP, true)) {
                continue;
            }
            $src = $from . '/' . $name;
            $dst = $to . '/' . $name;
            // Composer-Abhaengigkeiten (inkl. PHPUnit) nur verlinken: wird nie beschrieben,
            // und das Kopieren je Testinstanz waere teuer.
            if ($topLevel && $name === 'vendor' && @symlink($src, $dst)) {
                continue;
            }
            if (is_dir($src)) {
                self::copyTree($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }

    /** Kopiert Dateien aus $from nach $to, ohne vorhandene zu ueberschreiben. */
    private static function mergeTree(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0777, true);
        }
        foreach (scandir($from) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $src = $from . '/' . $name;
            $dst = $to . '/' . $name;
            if (is_dir($src)) {
                self::mergeTree($src, $dst);
            } elseif (!file_exists($dst)) {
                copy($src, $dst);
            }
        }
    }

    private static function removeTree(string $dir): void
    {
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $p = $dir . '/' . $name;
            is_dir($p) && !is_link($p) ? self::removeTree($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
