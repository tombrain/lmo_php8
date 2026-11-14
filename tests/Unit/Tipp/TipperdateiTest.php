<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tipper;
use Lmo\Addon\Tipp\Tipperdatei;
use PHPUnit\Framework\TestCase;

/**
 * Die Tipperdatei und ihre Tipper.
 */
class TipperdateiTest extends TestCase
{
    private string $pfad;

    protected function setUp(): void
    {
        $this->pfad = sys_get_temp_dir() . '/lmo-tippauth-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($this->pfad, "<?php exit; ?>\r\nanna|" . password_hash('alt', PASSWORD_BCRYPT) . "|5|Anna A|anna@example.org|Eins||||1|1|EOL\r\n\r\nbert|x|5|Bert B|bert@example.org|||||1|-1|EOL\r\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->pfad);
    }

    public function testTipperFinden(): void
    {
        $datei = Tipperdatei::lies($this->pfad);
        $anna = $datei->tipper('anna');

        self::assertNotNull($anna);
        self::assertSame(['anna', 'Anna A', 'anna@example.org', 'Eins'], [$anna->nick(), $anna->name(), $anna->email(), $anna->verein()]);
        self::assertTrue($anna->pruefeKennwort('alt'));
        self::assertFalse($anna->pruefeKennwort('falsch'));
        self::assertSame('bert', $datei->tipper('bert')?->nick());
        self::assertNull($datei->tipper('carla'));
        self::assertNull($datei->tipper('<?php exit; ?>'), 'die Schutzzeile ist kein Tipper');
    }

    public function testKennwortAendernUndSpeichern(): void
    {
        $datei = Tipperdatei::lies($this->pfad);
        $anna = $datei->tipper('anna');
        self::assertNotNull($anna);

        self::assertTrue($datei->mit($anna->mitKennwort('neu'))->speichere());

        $zeilen = file($this->pfad, FILE_IGNORE_NEW_LINES) ?: [];
        self::assertCount(3, $zeilen, 'ohne Leerzeilen, mit Zeilenende \n');
        self::assertSame('<?php exit; ?>', $zeilen[0], 'die Schutzzeile bleibt');
        self::assertSame('bert|x|5|Bert B|bert@example.org|||||1|-1|EOL', $zeilen[2], 'die anderen bleiben, wie sie sind');
        $neu = Tipperdatei::lies($this->pfad)->tipper('anna');
        self::assertTrue($neu?->pruefeKennwort('neu'));
        self::assertStringEndsWith('|5|Anna A|anna@example.org|Eins||||1|1|EOL', $neu->zeile());
    }

    public function testOhneDatei(): void
    {
        $datei = Tipperdatei::lies($this->pfad . '.fehlt');

        self::assertNull($datei->tipper('anna'));
        self::assertTrue($datei->speichere());
        self::assertSame("<?php exit; ?>\n", file_get_contents($this->pfad . '.fehlt'), 'eine neue Datei beginnt mit der Schutzzeile');
        unlink($this->pfad . '.fehlt');
        self::assertSame('a|b', Tipper::ausZeile('a|b')->zeile());
    }
}
