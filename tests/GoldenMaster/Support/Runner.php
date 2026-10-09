<?php

namespace Lmo\Tests\GoldenMaster\Support;

use RuntimeException;

/**
 * Fuehrt eine Anfrage an die Testinstanz in einem eigenen PHP-Prozess aus.
 *
 * Ein Prozess je Anfrage, weil das Original mit globalem Zustand, exit() und
 * require-Ketten arbeitet. Das Zeitverhalten wird mit faketime fixiert
 * (Kalender, "heute"-Markierungen); ohne faketime laufen die Tests trotzdem,
 * die Kalenderseiten sind dann aber vom Tagesdatum abhaengig.
 */
final class Runner
{
    /** Fester Startzeitpunkt der simulierten Uhr */
    public const FAKE_TIME = '2025-03-15 12:00:00';

    private Instance $instance;
    private ?bool $faketime = null;

    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    /** Simuliert GET <query> gegen lmo.php. Rueckgabe: Ausgabe (stdout) plus ggf. PHP-Meldungen (stderr). */
    public function request(string $query): string
    {
        return $this->run('request.php', [$query]);
    }

    /** Berechnet die Tabellen einer Liga in allen Ansichten, Rueckgabe: JSON. */
    /** @param bool $allRounds jeden Spieltag rechnen statt der festen Auswahl fuer den Golden Master */
    public function calc(string $leagueFile, bool $allRounds = false): string
    {
        return $this->run('calc.php', $allRounds ? [$leagueFile, 'alle'] : [$leagueFile]);
    }

    /**
     * Allgemeine Anfrage (GET/POST, beliebiger Einstiegspunkt, optional mit Sitzung).
     *
     * @param array{script:string,query?:string,method?:string,post?:array,sid?:string} $spec
     * @return array{body:string,sid:string} Ausgabe und Sitzungs-ID fuer die naechste Anfrage
     */
    public function http(array $spec): array
    {
        $sidFile = tempnam(sys_get_temp_dir(), 'lmo-sid');
        $specFile = tempnam(sys_get_temp_dir(), 'lmo-spec');
        $spec['sidOut'] = $sidFile;
        file_put_contents($specFile, json_encode($spec));

        $body = $this->run('http.php', [$specFile]);
        $sid = trim((string)file_get_contents($sidFile));
        @unlink($sidFile);
        @unlink($specFile);
        return ['body' => $body, 'sid' => $sid];
    }

    public function hasFaketime(): bool
    {
        if ($this->faketime === null) {
            // Bei der Coverage-Messung ohne faketime (Vorsichtsmassnahme: LD_PRELOAD plus
            // Coverage-Treiber). Zeitabhaengige Seiten (Kalender) koennen dann vom Snapshot
            // abweichen; fuer die Messung ist das egal.
            $this->faketime = getenv('GOLDEN_NO_FAKETIME') !== '1'
                && getenv('GOLDEN_COVERAGE') !== '1'
                && trim((string)shell_exec('command -v faketime 2>/dev/null')) !== '';
        }
        return $this->faketime;
    }

    /** Zielordner der Coverage-Rohdaten, null wenn GOLDEN_COVERAGE nicht 1 ist. */
    public static function coverageDir(): ?string
    {
        if (getenv('GOLDEN_COVERAGE') !== '1') {
            return null;
        }
        $dir = Fixture::projectRoot() . '/coverage/raw';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function run(string $script, array $args): string
    {
        $command = [];
        if ($this->hasFaketime()) {
            array_push($command, 'faketime', self::FAKE_TIME);
        }
        array_push(
            $command,
            PHP_BINARY,
            '-d', 'display_errors=stderr',
            '-d', 'log_errors=0',
            '-d', 'error_reporting=' . E_ALL,
            '-d', 'html_errors=0',
            '-d', 'memory_limit=512M',
            // lmo-functions.php ruft bei JEDEM Seitenaufruf eine Update-URL im Internet ab.
            // Fuer reproduzierbare Tests darf es keinen Netzwerkzugriff geben; die Abfrage faellt
            // dann auf den lokalen Fallback zurueck (Ergebnis wird nur im Admin-Bereich angezeigt).
            '-d', 'allow_url_fopen=0',
            '-d', 'date.timezone=Europe/Berlin',
            '-d', 'session.save_path=' . $this->instance->sessionDir()
        );
        $coverageDir = self::coverageDir();
        if ($coverageDir !== null) {
            array_push($command, '-d', 'xdebug.mode=coverage');
        }
        array_push(
            $command,
            dirname(__DIR__) . '/bin/' . $script,
            $this->instance->path()
        );
        foreach ($args as $arg) {
            $command[] = $arg;
        }

        // Ausgabe ueber Dateien statt Pipes: kein Deadlock bei grossen Ausgaben.
        $outFile = tempnam(sys_get_temp_dir(), 'lmo-out');
        $errFile = tempnam(sys_get_temp_dir(), 'lmo-err');
        $env = null;
        if ($this->hasFaketime()) {
            // Neuere libfaketime-Versionen (z. B. Ubuntu auf dem GitHub-Runner) verschieben auch die
            // Dateizeiten aus stat() um den Zeitversatz; die fixierte Ligadatei-Zeit (Instance::FIXED_MTIME)
            // wuerde dann je nach echtem Datum anders angezeigt. Nur die Uhr faelschen.
            $env = array_merge(getenv(), ['NO_FAKE_STAT' => '1']);
        }
        if ($coverageDir !== null) {
            $env = array_merge($env ?? getenv(), [
                'GOLDEN_COVERAGE_DIR' => $coverageDir,
                'GOLDEN_INSTANCE' => $this->instance->path(),
            ]);
        }
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['file', $outFile, 'w'], 2 => ['file', $errFile, 'w']],
            $pipes,
            $this->instance->path(),
            $env
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Prozess konnte nicht gestartet werden: ' . implode(' ', $command));
        }
        fclose($pipes[0]);
        $exit = proc_close($process);

        $stdout = (string)file_get_contents($outFile);
        $stderr = (string)file_get_contents($errFile);
        @unlink($outFile);
        @unlink($errFile);

        $stderr = trim($stderr);
        if ($stderr !== '') {
            $stdout .= "\n<!-- STDERR\n" . $stderr . "\n-->";
        }
        if ($exit !== 0) {
            $stdout .= "\n<!-- EXIT " . $exit . ' -->';
        }
        return $stdout;
    }
}
