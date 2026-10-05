<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminMatrix;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Alle Admin-Ansichten (nur lesend) als angemeldeter Hauptadmin. Ein Snapshot enthaelt die
 * normalisierte Seite und, am Ende, alle Dateien, die der blosse Seitenaufruf veraendert hat
 * (normalerweise keine).
 */
final class AdminViewsTest extends TestCase
{
    use AssertsSnapshots;

    /**
     * @dataProvider views
     */
    public function testAdminViewIsUnchanged(string $script, string $query): void
    {
        $client = Fixture::adminClient();
        $html = $client->get($script, $query);
        $changes = $client->changes();

        $this->assertMatchesSnapshot(
            'admin-views',
            $script . '?' . $query,
            $html . "\n### DATEIAENDERUNGEN\n" . ($changes === '' ? "(keine)\n" : $changes)
        );
    }

    public static function views(): array
    {
        return AdminMatrix::views();
    }
}
