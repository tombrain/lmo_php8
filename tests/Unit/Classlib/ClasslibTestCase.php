<?php

namespace Lmo\Tests\Unit\Classlib;

use PHPUnit\Framework\TestCase;

/**
 * Laedt die classlib ohne init.php: nur die Konstanten, die die Klassen selbst brauchen.
 * Ligen werden im Speicher aufgebaut (league()) oder als kleine .l98-Datei geschrieben (leagueFile()).
 */
abstract class ClasslibTestCase extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    public static function setUpBeforeClass(): void
    {
        $lmo = dirname(__DIR__, 3) . '/lmo';
        foreach ([
            'PATH_TO_ADDONDIR' => $lmo . '/addon',
            'PATH_TO_IMGDIR' => __DIR__ . '/fixtures/img',
            'URL_TO_IMGDIR' => 'http://lmo.test/img',
            'CLASSLIB_VERSION_NR' => '2.8',
            'CLASSLIB_VERSION' => '(classlib&nbsp;2.8)',
            'CLASSLIB_IMG_TYPES' => '.gif,.jpg,.png,.jpeg',
        ] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        require_once PATH_TO_ADDONDIR . '/classlib/classes.php';
        require_once PATH_TO_ADDONDIR . '/classlib/functions.php';
        require_once PATH_TO_ADDONDIR . '/classlib/html_output.php';
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tempFiles) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
        $this->tempFiles = [];
    }

    /**
     * Liga im Speicher. Teams bekommen die Nummern 1..n in der angegebenen Reihenfolge.
     *
     * @param string[] $teams
     * @param array<int,array<int,array>> $rounds je Spieltag Partien [heimNr, gastNr, hTore, gTore, spielEnde = 0, zeit = '']
     * @param array<string,mixed> $options Werte fuer die Options-Sektion
     */
    protected static function league(array $teams, array $rounds, array $options = [], string $class = 'liga'): \liga
    {
        /** @var \liga $liga */
        $liga = new $class('Testliga');
        foreach (array_values($teams) as $i => $name) {
            $team = new \team($name, substr($name, 0, 3), $i + 1);
            $liga->addTeam($team);
        }
        foreach ($rounds as $r => $games) {
            $spieltag = new \spieltag($r + 1, '', '');
            foreach ($games as $n => $g) {
                $heim = $liga->teamForNumber($g[0]);
                $gast = $liga->teamForNumber($g[1]);
                $partie = new \partie($n + 1, $g[5] ?? '', '', $heim, $gast, $g[2], $g[3]);
                $partie->setSpielEnde($g[4] ?? 0);
                $liga->addPartie($partie);
                $spieltag->addPartie($partie);
                // partie haelt Referenzen auf $heim/$gast: vor der naechsten Zuweisung loesen
                unset($heim, $gast);
            }
            $liga->addSpieltag($spieltag);
        }
        $liga->options = new \optionsSektion($liga, $options);
        return $liga;
    }

    /**
     * Reihenfolge der Teamnamen in einer berechneten Tabelle.
     *
     * @return string[]
     */
    protected static function order(array $table): array
    {
        return array_map(static fn (array $row): string => $row['team']->name, $table);
    }

    /** Tabellenzeile eines Teams. */
    protected static function row(array $table, string $teamName): array
    {
        foreach ($table as $row) {
            if ($row['team']->name === $teamName) {
                return $row;
            }
        }
        throw new \RuntimeException("Team $teamName nicht in der Tabelle");
    }

    /** Schreibt eine Ligadatei (Zeilen mit LF) und loescht sie nach dem Test. */
    protected function leagueFile(string $content): string
    {
        $path = $this->tempDir() . '/liga.l98';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/lmo-classlib-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $this->tempFiles[] = $dir;
        return $dir;
    }

    protected function tempSubdir(string $dir, string $name): void
    {
        mkdir($dir . '/' . $name);
        $this->tempFiles[] = $dir . '/' . $name;
    }

    protected function tempFile(string $dir, string $name, string $content = ''): void
    {
        file_put_contents($dir . '/' . $name, $content);
        $this->tempFiles[] = $dir . '/' . $name;
    }
}
