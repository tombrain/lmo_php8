<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Welche Seiten und Berechnungen werden abgesichert.
 *
 * "voll": alle Ansichten, Tabellentypen und Spieltage; "reduziert": nur die
 * Hauptansichten (fuer die mitgelieferten, noch leeren Schluessel-Ligen).
 */
final class Matrix
{
    /** Liga mit echtem Spielplan: bekommt die volle Matrix. */
    private const FULL_REAL = ['1l_2024-25.l98'];

    /**
     * @return array<string,array{0:string}> Label => [Query]
     */
    public static function pages(string $sourceRoot): array
    {
        $cases = [];
        $add = static function (string $query) use (&$cases): void {
            $cases[$query === '' ? '(Ligenuebersicht)' : $query] = [$query];
        };

        $add('');
        $add('lmouserlang=English');

        foreach (self::leagues($sourceRoot) as $file => $meta) {
            $rounds = $meta['rounds'];
            $mid = max(1, intdiv($rounds, 2));
            $f = 'file=' . $file;

            if ($meta['tableOnly']) {
                // Szenarien fuer die Tabellenberechnung: nur Tabellenansichten
                $add($f . '&action=table');
                foreach ([1, 2, 3, 4] as $tabType) {
                    $add($f . '&action=table&tabtype=' . $tabType);
                }
                $add($f . '&action=table&endtab=' . $mid);
                $add($f . '&action=table&tabonres=2');
                $add($f . '&action=table&tabonres=2&tabtype=2');
                $add($f . '&action=table&tabpkt=0');
                continue;
            }

            $add($f);
            $add($f . '&action=table');
            $add($f . '&action=results&st=1');
            $add($f . '&action=program');
            $add($f . '&action=cross');
            $add($f . '&action=stats');

            if (!$meta['full']) {
                continue;
            }

            foreach ([1, 2, 3, 4] as $tabType) {
                $add($f . '&action=table&tabtype=' . $tabType);
            }
            $add($f . '&action=table&endtab=' . $mid);
            $add($f . '&action=table&st=' . $mid);
            // Diese Werte sind Konfigurationsoptionen. Sie lassen sich per GET ueberschreiben,
            // weil init.php $_GET nach dem Laden der Konfiguration in den globalen Scope extrahiert.
            $add($f . '&action=table&tabonres=2');
            $add($f . '&action=table&tabpkt=0');
            $add($f . '&action=table&tabonres=2&tabpkt=0&tabtype=3');
            // Statistik: ohne stat1 zeigt die Seite nur die Teamliste; Vergleiche kommen erst mit stat1/stat2
            $add($f . '&action=stats&stat1=0&stat2=2'); // stat2 ohne stat1 rutscht nach stat1
            $add($f . '&action=stats&stat1=1');
            $add($f . '&action=stats&stat1=1&stat2=2');
            $add($f . '&action=stats&stat1=2&stat2=1&tabtype=1');
            $add($f . '&action=results&st=' . $mid);
            $add($f . '&action=results&st=' . $rounds);
            $add($f . '&action=program&selteam=1');
            $add($f . '&action=graph');
            $add($f . '&action=cal');
            $add($f . '&action=info');
            $add($f . '&action=table&lmouserlang=English');
        }
        return $cases;
    }

    /**
     * Alle Ligadateien mit Berechnung: Name (relativ zu ligen/) => [rounds, full, tableOnly].
     *
     * @return array<string,array{rounds:int,full:bool,tableOnly:bool}>
     */
    public static function leagues(string $sourceRoot): array
    {
        $leagues = [];

        $dir = $sourceRoot . '/lmo/ligen';
        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $info) {
                if (!$info->isFile() || substr($info->getFilename(), -4) !== '.l98') {
                    continue;
                }
                $relative = ltrim(str_replace('\\', '/', substr($info->getPathname(), strlen($dir))), '/');
                $head = (string)file_get_contents($info->getPathname(), false, null, 0, 2000);
                $type = preg_match('/^Type=(\d+)/m', $head, $m) ? (int)$m[1] : 0;
                if ($type !== 0) {
                    continue; // KO-Ligen haben eine andere Datenstruktur und sind noch nicht abgedeckt
                }
                $rounds = preg_match('/^Rounds=(\d+)/m', $head, $m) ? (int)$m[1] : 1;
                $leagues[$relative] = [
                    'rounds' => $rounds,
                    'full' => in_array($relative, self::FULL_REAL, true),
                    'tableOnly' => false,
                ];
            }
        }

        foreach (LeagueFactory::files() as $file => $rounds) {
            $name = basename($file, '.l98');
            $tableOnly = LeagueFactory::pageMode($name) === 'table';
            $leagues[$file] = ['rounds' => $rounds, 'full' => !$tableOnly, 'tableOnly' => $tableOnly];
        }

        ksort($leagues);
        return $leagues;
    }
}
