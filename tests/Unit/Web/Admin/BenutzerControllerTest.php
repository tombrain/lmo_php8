<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\AdminKonten;
use Lmo\Web\Admin\BenutzerController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Benutzer der Administration: Konten lesen und schreiben, anzeigen, anlegen, bearbeiten, loeschen.
 */
class BenutzerControllerTest extends TestCase
{
    private string $ordner;

    private string $konten;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminbenutzer-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen', 0777, true);
        file_put_contents($this->ordner . '/ligen/a.l98', "[Options]\nName=Erste\n");
        file_put_contents($this->ordner . '/ligen/b.l98', "[Options]\n");
        $this->konten = $this->ordner . '/lmo-auth.php';
        file_put_contents($this->konten, "<?php exit(); ?>\nchef|HASH0|2|||\nzora|HASH1|1|a|1\nanna|HASH2|1||0\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/ligen/*') ?: []);
        rmdir($this->ordner . '/ligen');
        unlink($this->konten);
        rmdir($this->ordner);
    }

    /** @param array<string, mixed> $anfrage */
    private function seite(array $anfrage): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'user'] + $anfrage),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => 2])
        );
        $controller = new BenutzerController($this->konten);
        self::assertTrue($controller->zustaendig($kontext));
        self::assertFalse($controller->zustaendig(new Kontext($kontext->anfrage, '', null, $kontext->konfiguration, [], '', $kontext->pfade, $kontext->darstellung, sitzung: new Sitzung(['lmouserok' => 1]))), 'nur fuer den Hauptadmin');

        return $controller->zeige($kontext)->html;
    }

    public function testAdminKonten(): void
    {
        self::assertSame([['chef', 'HASH0', '2', '', '', ''], ['zora', 'HASH1', '1', 'a', '1'], ['anna', 'HASH2', '1', '', '0']], AdminKonten::lies($this->konten));
        self::assertNull(AdminKonten::lies($this->ordner . '/gibtsnicht.php'));

        self::assertTrue(AdminKonten::schreibe($this->konten, [['chef', 'H', '2', '', '']]));
        self::assertStringEqualsFile($this->konten, "<?php exit(); ?>\nchef|H|2||\n");
        self::assertFalse(AdminKonten::schreibe($this->ordner . '/gibtsnicht/lmo-auth.php', []));
    }

    public function testListe(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('show=0"><em>chef</em></a>', $html, 'Admins kursiv');
        self::assertStringContainsString('show=1">zora</a>', $html);
        self::assertStringContainsString('<br>T318<br>', $html, 'ohne Auswahl der Hinweis');
        self::assertStringContainsString('<input type="hidden" name="show" value="3">', $html, 'das neue Konto');
        self::assertStringNotContainsString('xadmin_name0', $html);
    }

    public function testHilfsadmin(): void
    {
        $html = $this->seite(['show' => '1']);

        self::assertStringContainsString('<td align="right"> zora', $html);
        self::assertStringContainsString('name="xadmin_name1" size="16" maxlength="32" value="zora"', $html);
        self::assertStringContainsString('name="xadmin_rang1" value="1"  checked>', $html);
        self::assertStringContainsString('name="xadmin_erweitert1"  checked >', $html);
        self::assertStringContainsString('value="a" checked>Erste</td>', $html);
        self::assertStringContainsString('value="b">No Name</td>', $html);
        self::assertStringContainsString('del=1" onclick="return confirm(\'T499\');"', $html);

        $admin = $this->seite(['show' => '0']);
        self::assertStringContainsString('name="xadmin_erweitert0"   disabled>', $admin);
        self::assertStringNotContainsString('xhelfer_ligen', $admin, 'keine Ligen fuer Admins');
        self::assertStringNotContainsString('del=0', $admin, 'der Hauptadmin bleibt');
    }

    public function testBearbeiten(): void
    {
        $html = $this->seite(['save' => '1', 'xadmin_name1' => ' bert ', 'xadmin_pass1' => 'geheim', 'xadmin_rang1' => '1', 'xhelfer_ligen1' => ['ligen/a.l98', 'b', 'c'], 'xadmin_erweitert1' => 'on']);

        self::assertStringStartsWith(Meldung::html('IMG', 'T138'), $html);
        $konten = (array) AdminKonten::lies($this->konten);
        self::assertSame(['chef', 'anna', 'bert'], array_column($konten, 0), 'nach dem Hauptadmin sortiert');
        self::assertTrue(password_verify('geheim', $konten[2][1]));
        self::assertSame(['1', 'a,b', '1'], array_slice($konten[2], 2), 'nur vorhandene Ligen, ohne Ordner und .l98');

        $this->seite(['save' => '1', 'xadmin_name1' => 'anna', 'xadmin_pass1' => '', 'xadmin_rang1' => '2', 'xadmin_erweitert1' => 'on']);
        self::assertSame(['anna', 'HASH2', '2', '', '0'], ((array) AdminKonten::lies($this->konten))[1], 'altes Passwort, Admin ohne Ligen');
    }

    public function testAnlegenUndLoeschen(): void
    {
        $html = $this->seite(['save' => '-1', 'xadmin_name' => 'neu', 'xadmin_pass' => 'pw']);
        self::assertStringStartsWith(Meldung::html('IMG', 'T138'), $html);
        self::assertStringContainsString('value="neu"', $html, 'gleich gewaehlt');
        $konten = (array) AdminKonten::lies($this->konten);
        self::assertSame(['neu', '1', '', ''], [$konten[3][0], $konten[3][2], $konten[3][3], $konten[3][4]]);

        self::assertStringStartsWith(Meldung::html('IMG', 'T567', true), $this->seite(['save' => '-1', 'xadmin_name' => 'zora', 'xadmin_pass' => 'pw']), 'doppelt');
        self::assertStringStartsWith(Meldung::html('IMG', 'T567', true), $this->seite(['save' => '-1', 'xadmin_name' => 'x', 'xadmin_pass' => '']), 'ohne Passwort');

        $this->seite(['del' => '1']);
        self::assertSame(['chef', 'anna', 'neu'], array_column((array) AdminKonten::lies($this->konten), 0));

        $this->seite(['del' => '0']);
        self::assertSame(['chef', 'anna', 'neu'], array_column((array) AdminKonten::lies($this->konten), 0), 'der Hauptadmin bleibt');
    }

    public function testDateiFehlt(): void
    {
        unlink($this->konten);
        self::assertSame(Meldung::html('IMG', 'T283', true), $this->seite([]));
        touch($this->konten);
    }
}
