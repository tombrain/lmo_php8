<?php
/**
 * Fasst die Coverage-Rohdaten (coverage/raw/*.json) zusammen.
 * Aufruf: php coverage_report.php <Projektwurzel>
 *
 * Ausgabe auf der Konsole: Zusammenfassung je Bereich und Tabelle der Kerndateien.
 * Dateien in coverage/: report.txt (alle Dateien), report.csv, uncovered.txt (nicht
 * ausgefuehrte Zeilenbereiche je Datei).
 *
 * Hinweis: Fuer Dateien, die in keinem Testlauf geladen wurden, ist die Zeilenzahl
 * nur geschaetzt (nicht leere Zeilen ohne reine Kommentare/Klammern), im Report mit "~".
 */
$root = rtrim($argv[1] ?? '/app', '/');
$sourceRoot = rtrim((string)(getenv('LMO_SOURCE') ?: $root), '/') . '/lmo';
$rawDir = $root . '/coverage/raw';
$outDir = $root . '/coverage';

$rawFiles = glob($rawDir . '/*.json') ?: [];
if (!$rawFiles) {
    fwrite(STDERR, "Keine Coverage-Rohdaten in $rawDir (GOLDEN_COVERAGE=1 gesetzt, Xdebug geladen?)\n");
    exit(1);
}

// --- Rohdaten zusammenfuehren: eine Zeile gilt als ausgefuehrt, wenn irgendein Prozess sie ausgefuehrt hat
$merged = [];
foreach ($rawFiles as $file) {
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) {
        continue;
    }
    foreach ($data as $relative => $lines) {
        foreach ($lines as $line => $status) {
            $current = $merged[$relative][$line] ?? -1;
            $merged[$relative][$line] = ($status == 1 || $current == 1) ? 1 : -1;
        }
    }
}

// --- alle PHP-Dateien der Quelle
$universe = [];
if (is_dir($sourceRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $info) {
        if (!$info->isFile() || substr($info->getFilename(), -4) !== '.php') {
            continue;
        }
        $relative = ltrim(str_replace('\\', '/', substr($info->getPathname(), strlen($sourceRoot))), '/');
        if (preg_match('#^(tests|vendor|\.git)/#', $relative)) {
            continue;
        }
        $universe[$relative] = $info->getPathname();
    }
}

function area(string $relative): string
{
    if (strpos($relative, '/') === false) {
        // Admin-Dateien (lmo-admin*.php, lmoadmin.php) getrennt vom oeffentlichen Kern ausweisen,
        // damit sichtbar ist, wie weit der Admin-Bereich des Golden Masters abgedeckt ist.
        if (preg_match('/^lmo-?admin/', $relative)) {
            return 'Kern: Admin';
        }
        return 'Kern: oeffentlich';
    }
    $parts = explode('/', $relative);
    if ($parts[0] === 'addon' && count($parts) > 2) {
        return 'addon/' . $parts[1];
    }
    return $parts[0];
}

function approximateLines(string $path): int
{
    $count = 0;
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $t = trim($line);
        if ($t === '' || $t === '<?php' || $t === '?>' || $t === '{' || $t === '}'
            || strpos($t, '//') === 0 || strpos($t, '#') === 0 || strpos($t, '*') === 0 || strpos($t, '/*') === 0) {
            continue;
        }
        $count++;
    }
    return $count;
}

function ranges(array $lines): string
{
    sort($lines);
    $out = [];
    $start = $prev = null;
    foreach ($lines as $line) {
        if ($start === null) {
            $start = $prev = $line;
        } elseif ($line === $prev + 1) {
            $prev = $line;
        } else {
            $out[] = $start === $prev ? (string)$start : "$start-$prev";
            $start = $prev = $line;
        }
    }
    if ($start !== null) {
        $out[] = $start === $prev ? (string)$start : "$start-$prev";
    }
    return implode(', ', $out);
}

// --- Zeilen je Datei
$all = array_unique(array_merge(array_keys($universe), array_keys($merged)));
sort($all);
$rows = [];
foreach ($all as $relative) {
    if ($relative === 'config/init-parameters.php' || strpos($relative, 'output/') === 0) {
        continue; // vom Test-Setup bzw. vom Programm zur Laufzeit erzeugt, kein Quellcode
    }
    $approx = false;
    $uncovered = [];
    if (isset($merged[$relative])) {
        $total = count($merged[$relative]);
        $executed = count(array_filter($merged[$relative], static fn ($s) => $s == 1));
        $uncovered = array_keys(array_filter($merged[$relative], static fn ($s) => $s != 1));
        $status = $executed === $total ? 'vollstaendig' : ($executed === 0 ? 'geladen, nichts ausgefuehrt' : 'teilweise');
    } else {
        $total = isset($universe[$relative]) ? approximateLines($universe[$relative]) : 0;
        $executed = 0;
        $approx = true;
        $status = 'nie geladen';
    }
    $rows[] = [
        'file' => $relative, 'area' => area($relative), 'total' => $total, 'executed' => $executed,
        'percent' => $total > 0 ? 100 * $executed / $total : 100.0,
        'status' => $status, 'approx' => $approx, 'uncovered' => $uncovered,
    ];
}

// --- Zusammenfassung je Bereich
$areas = [];
foreach ($rows as $row) {
    $a = $row['area'];
    $areas[$a] ??= ['files' => 0, 'loaded' => 0, 'total' => 0, 'executed' => 0];
    $areas[$a]['files']++;
    $areas[$a]['loaded'] += $row['status'] === 'nie geladen' ? 0 : 1;
    $areas[$a]['total'] += $row['total'];
    $areas[$a]['executed'] += $row['executed'];
}
ksort($areas);

$summary = sprintf("%-26s %7s %9s %10s %11s %7s\n", 'Bereich', 'Dateien', 'geladen', 'Zeilen', 'ausgefuehrt', '%');
$sumTotal = $sumExecuted = $sumFiles = $sumLoaded = 0;
foreach ($areas as $name => $a) {
    $summary .= sprintf(
        "%-26s %7d %9d %10d %11d %6.1f%%\n",
        $name, $a['files'], $a['loaded'], $a['total'], $a['executed'],
        $a['total'] > 0 ? 100 * $a['executed'] / $a['total'] : 100
    );
    $sumTotal += $a['total'];
    $sumExecuted += $a['executed'];
    $sumFiles += $a['files'];
    $sumLoaded += $a['loaded'];
}
$ownTotal = $ownExecuted = $ownFiles = $ownLoaded = 0;
foreach ($areas as $name => $a) {
    if (in_array($name, ['includes', 'lang', 'help', 'js'], true)) {
        continue; // Fremdbibliotheken, Sprachdaten, Hilfetexte, Skripte
    }
    $ownFiles += $a['files'];
    $ownLoaded += $a['loaded'];
    $ownTotal += $a['total'];
    $ownExecuted += $a['executed'];
}
$summary .= str_repeat('-', 78) . "\n" . sprintf(
    "%-26s %7d %9d %10d %11d %6.1f%%\n",
    'GESAMT', $sumFiles, $sumLoaded, $sumTotal, $sumExecuted, $sumTotal > 0 ? 100 * $sumExecuted / $sumTotal : 100
) . sprintf(
    "%-26s %7d %9d %10d %11d %6.1f%%\n",
    'davon eigener Code', $ownFiles, $ownLoaded, $ownTotal, $ownExecuted, $ownTotal > 0 ? 100 * $ownExecuted / $ownTotal : 100
) . "(eigener Code = ohne includes, lang, help, js; Zeilen nie geladener Dateien sind geschaetzt)\n";

function fileTable(array $rows): string
{
    $out = sprintf("%-44s %8s %11s %7s  %s\n", 'Datei', 'Zeilen', 'ausgefuehrt', '%', 'Status');
    foreach ($rows as $row) {
        $out .= sprintf(
            "%-44s %8s %11d %6.1f%%  %s\n",
            substr($row['file'], 0, 44), ($row['approx'] ? '~' : '') . $row['total'], $row['executed'], $row['percent'], $row['status']
        );
    }
    return $out;
}

$core = array_values(array_filter($rows, static fn ($r) => str_starts_with($r['area'], 'Kern:')));
usort($core, static fn ($a, $b) => $a['percent'] <=> $b['percent'] ?: strcmp($a['file'], $b['file']));

// --- Dateien schreiben
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
file_put_contents($outDir . '/report.txt', $summary . "\n" . fileTable($rows));

$csv = fopen($outDir . '/report.csv', 'wb');
fputcsv($csv, ['datei', 'bereich', 'zeilen', 'ausgefuehrt', 'prozent', 'status', 'zeilen_geschaetzt'], ';');
foreach ($rows as $row) {
    fputcsv($csv, [$row['file'], $row['area'], $row['total'], $row['executed'], round($row['percent'], 1), $row['status'], $row['approx'] ? 'ja' : 'nein'], ';');
}
fclose($csv);

$uncovered = '';
foreach ($rows as $row) {
    if ($row['uncovered']) {
        $uncovered .= $row['file'] . ': ' . ranges($row['uncovered']) . "\n";
    }
}
file_put_contents($outDir . '/uncovered.txt', $uncovered);

// --- Konsole
echo "\nCoverage des Golden Masters (nur Dateien unter lmo/, ohne tests/ und vendor/)\n\n";
echo $summary;
echo "\nKerndateien, schlechteste zuerst (Details: coverage/report.txt, report.csv, uncovered.txt):\n\n";
echo fileTable($core);
echo "\n'~' = Zeilenzahl geschaetzt (Datei nie geladen).\n";
