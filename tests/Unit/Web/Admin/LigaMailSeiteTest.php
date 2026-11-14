<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\LigaMailSeite;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * Liga per E-Mail: Formular, Versand einer oder aller Ligen als Zip, Rechte.
 */
class LigaMailSeiteTest extends TestCase
{
    private string $ordner;

    /** @var list<array{to: string, subject: string, body: string, anhaenge: list<string>, dateien: list<string>}> */
    private array $gesendet = [];

    private bool $scheitert = false;

    protected function setUp(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP-Erweiterung zip fehlt');
        }
        $this->ordner = sys_get_temp_dir() . '/lmo-ligamail-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner . 'ligen', 0777, true);
        mkdir($this->ordner . 'output');
        foreach (['b.l98', 'a.l98', 'c.l98', 'notiz.txt'] as $datei) {
            file_put_contents($this->ordner . 'ligen/' . $datei, '[Options]');
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): string
    {
        $test = $this;
        $mailer = static function () use ($test): PHPMailer {
            return new class ($test) extends PHPMailer {
                public function __construct(private LigaMailSeiteTest $test)
                {
                    parent::__construct(false);
                }

                public function send()
                {
                    return $this->test->sende($this);
                }
            };
        };
        $text = [330 => 'Alle Ligen', 341 => 'Ligaversand', 342 => 'Eine Liga', 347 => 'Schliessen', 348 => 'Verschickt', 550 => 'Fehler', 798 => 'de'];

        return (new LigaMailSeite($this->ordner . 'ligen/', $this->ordner . 'output/', $text, static fn (string $datei): bool => $datei !== 'c.l98' || $rolle === 2, $mailer, 'LMO', 'IMG', dirname(__DIR__, 4) . '/lmo/vorlagen'))
            ->html($anfrage + ['action' => 'admin', 'todo' => 'email'], $rolle, '/lmo/lmo-adminmimesend.php');
    }

    /** @internal fuer die Mail im Test: haelt fest, was verschickt wuerde */
    public function sende(PHPMailer $mail): bool
    {
        if ($this->scheitert) {
            $mail->ErrorInfo = 'kaputt';

            return false;
        }
        $anhaenge = [];
        $dateien = [];
        foreach ($mail->getAttachments() as $anhang) {
            $anhaenge[] = (string) $anhang[2];
            $zip = new \ZipArchive();
            $zip->open((string) $anhang[0]);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $dateien[] = (string) $zip->getNameIndex($i);
            }
            $zip->close();
        }
        $this->gesendet[] = ['to' => (string) $mail->getToAddresses()[0][0], 'subject' => $mail->Subject, 'body' => $mail->Body, 'anhaenge' => $anhaenge, 'dateien' => $dateien];

        return true;
    }

    public function testFormular(): void
    {
        $html = $this->seite(['down' => '2']);
        self::assertStringContainsString('<form action="/lmo/lmo-adminmimesend.php">', $html);
        self::assertStringContainsString('name="down" value="2"', $html);
        self::assertStringContainsString('Schliessen', $html);
        self::assertSame([], $this->gesendet);
    }

    public function testFormularMaskiert(): void
    {
        $html = $this->seite(['down' => '1"><script>x</script>']);
        self::assertStringNotContainsString('<script>x', $html);
        self::assertStringContainsString('value="1&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
    }

    public function testEineLiga(): void
    {
        $html = $this->seite(['down' => '2', 'madr' => 'x@example.org']);

        self::assertStringContainsString('<p class="message"><img src="IMG/right.svg" border="0" width="20" alt=""> Verschickt</p>', $html);
        self::assertSame([['to' => 'x@example.org', 'subject' => 'Ligaversand', 'body' => 'Eine Liga', 'anhaenge' => ['b.l98.zip'], 'dateien' => ['b.l98']]], $this->gesendet, 'die zweite in der sortierten Liste');
        self::assertFileDoesNotExist($this->ordner . 'output/ligen.zip', 'das Zip ist wieder weg');
    }

    public function testAlleLigen(): void
    {
        $this->seite(['down' => '-1', 'madr' => 'x@example.org'], 1);

        self::assertSame(['ligen.zip'], $this->gesendet[0]['anhaenge'] ?? null);
        self::assertSame('Alle Ligen', $this->gesendet[0]['body']);
        self::assertSame(['a.l98', 'b.l98'], $this->gesendet[0]['dateien'], 'der Hilfsadmin nur mit seinen Ligen');
    }

    public function testNichts(): void
    {
        self::assertStringNotContainsString('Schliessen', $this->seite(['down' => '3', 'madr' => 'x@example.org'], 1), 'fremde Liga: keine Meldung');
        self::assertStringNotContainsString('Verschickt', $this->seite(['down' => '9', 'madr' => 'x@example.org']), 'keine Liga mit der Nummer');
        self::assertStringNotContainsString('<form', $this->seite(['down' => '1'], 0), 'ohne Anmeldung');
        self::assertStringNotContainsString('<form', $this->seite(['down' => '1', 'todo' => 'x']));
        self::assertSame([], $this->gesendet);
    }

    public function testVersandScheitert(): void
    {
        $this->scheitert = true;
        self::assertStringContainsString('<p class="error"><img src="IMG/wrong.svg" border="0" width="20" alt=""> Fehler Details: kaputt</p>', $this->seite(['down' => '1', 'madr' => 'x@example.org']));
    }
}
