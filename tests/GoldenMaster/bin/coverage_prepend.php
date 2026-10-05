<?php
/**
 * Wird von request.php und calc.php eingebunden, wenn GOLDEN_COVERAGE_DIR gesetzt ist.
 * Startet die Xdebug-Codeabdeckung und schreibt beim Beenden (auch nach exit()) die Abdeckung
 * der Dateien der Testinstanz als JSON: { "relativer/pfad.php": { "zeile": 1|-1 } }
 * (1 = ausgefuehrt, -1 = ausfuehrbar, aber nicht ausgefuehrt; toter Code wird weggelassen).
 *
 * Alles in einer Closure, damit keine Variablen im globalen Scope des Altcodes landen.
 */
(static function (): void {
    if (!function_exists('xdebug_start_code_coverage')) {
        fwrite(STDERR, "Xdebug nicht geladen: keine Coverage-Daten\n");
        return;
    }
    $dir = (string)getenv('GOLDEN_COVERAGE_DIR');
    $instance = rtrim((string)getenv('GOLDEN_INSTANCE'), '/') . '/';

    // Nur Dateien der Testinstanz erfassen (spart Zeit und Speicher).
    if (function_exists('xdebug_set_filter')
        && defined('XDEBUG_FILTER_CODE_COVERAGE') && defined('XDEBUG_PATH_INCLUDE')) {
        xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$instance]);
    }
    if (!@xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE)) {
        fwrite(STDERR, "Xdebug-Coverage konnte nicht gestartet werden (xdebug.mode=coverage?)\n");
        return;
    }

    register_shutdown_function(static function () use ($dir, $instance): void {
        $data = xdebug_get_code_coverage();
        xdebug_stop_code_coverage(false);
        $out = [];
        foreach ($data as $file => $lines) {
            if (strpos($file, $instance) !== 0) {
                continue;
            }
            $clean = [];
            foreach ($lines as $line => $status) {
                if ($status === -2) {
                    continue; // toter Code, nicht ausfuehrbar
                }
                $clean[$line] = $status > 0 ? 1 : -1;
            }
            $out[substr($file, strlen($instance))] = $clean;
        }
        file_put_contents(
            $dir . '/' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.json',
            json_encode($out)
        );
    });
})();
