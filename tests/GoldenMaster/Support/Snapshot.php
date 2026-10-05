<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Ablage der Referenzausgaben (Golden Master) unter __snapshots__/.
 * Modus per Umgebungsvariable GOLDEN_MODE: "record" schreibt, sonst wird verglichen.
 */
final class Snapshot
{
    /** "record" (alle neu schreiben), "record-missing" (nur fehlende anlegen) oder "compare". */
    public static function mode(): string
    {
        $mode = (string)getenv('GOLDEN_MODE');
        return in_array($mode, ['record', 'record-missing'], true) ? $mode : 'compare';
    }

    public static function recording(): bool
    {
        return self::mode() === 'record';
    }

    public static function recordingMissing(): bool
    {
        return self::mode() === 'record-missing';
    }

    public static function path(string $group, string $name): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._=-]+/', '_', $name);
        $slug = substr($slug, 0, 110) . '-' . substr(sha1($name), 0, 8);
        return dirname(__DIR__) . '/__snapshots__/' . $group . '/' . $slug . '.txt';
    }
}
