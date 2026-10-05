<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Protokoll einer Schrittfolge (Anfrage, Antwort, Dateiaenderungen) als ein Snapshot-Text.
 */
final class Transcript
{
    private string $text = '';

    public function add(string $title, string $html, string $changes): void
    {
        $this->text .= "=== $title ===\n"
            . rtrim($html) . "\n"
            . "--- Dateiaenderungen ---\n"
            . ($changes === '' ? "(keine)\n" : rtrim($changes) . "\n")
            . "\n";
    }

    public function text(): string
    {
        return $this->text;
    }
}
