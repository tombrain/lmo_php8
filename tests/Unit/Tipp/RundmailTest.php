<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Rundmail;
use Lmo\Addon\Tipp\Tippzeit;
use PHPUnit\Framework\TestCase;

/**
 * Mails an die Tipper: Newsletter, Einzelmail und Erinnerung an die noch nicht getippten Spiele.
 *
 * Die Liga (fixtures/tippliga.l98): Spieltag 1 mit Alpha - B | C um 1740826800 und Gamma - Alpha
 * zwei Stunden spaeter; "jetzt" ist eine Stunde vor dem ersten Spiel.
 */
class RundmailTest extends TestCase
{
    private const JETZT = 1740826800 - 3600;

    private string $ordner;

    /** @var list<array{string, string}> verschickte Mails: Adresse, Text */
    private array $mails = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-rundmail-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner . 'ligen', 0777, true);
        mkdir($this->ordner . 'tipps', 0777, true);
        // mit vier Mannschaften zaehlen beide Spiele des Spieltags als Paarungen (mit drei nur eines)
        file_put_contents($this->ordner . 'ligen/tippliga.l98', str_replace(['Teams=3', "3=Gamma\n"], ['Teams=4', "3=Gamma\n4=Delta\n"], (string) file_get_contents(__DIR__ . '/fixtures/tippliga.l98')));
        file_put_contents($this->ordner . 'tipper.txt', "<?php exit; ?>\n"
            . "anna|x|5|Anna A|anna@x||||1|1|EOL\n"
            . "bert|x|5|Bert B|bert@x||||-1|-1|EOL\n"
            . "cleo|x|5|Cleo C|||||1|1|EOL\n");
        // anna hat das erste Spiel getippt, das zweite nicht
        file_put_contents($this->ordner . 'tipps/tippliga_anna.tip', "[Round1]\nGA1=1\nGB1=0\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $cfg */
    private function rundmail(array $cfg = ['immeralle' => '1']): Rundmail
    {
        $text = [2 => 'Spieltag', 370 => 'Runde', 371 => 'Achtelfinale', 372 => 'Viertelfinale', 373 => 'Halbfinale', 374 => 'Finale'];
        $datum = array_fill(0, 38, '?');
        $datum[5] = 'Samstag';
        $datum[16] = 'März';

        return new Rundmail($this->ordner . 'tipper.txt', $this->ordner . 'tipps/', $this->ordner . 'ligen/', $cfg, new Tippzeit(0, false, '15:30', -1),
            $text, [87 => 'bis'], $datum, 'http://x/?nick=', function (string $an, string $text): ?string {
                $this->mails[] = [$an, $text];

                return $an == 'fehler@x' ? 'FEHLER' : null;
            }, self::JETZT);
    }

    public function testNewsletter(): void
    {
        self::assertSame([1, ''], $this->rundmail()->newsletter('Hallo [name] ([nick]) [pass]', 1, 9));
        self::assertSame([['anna@x', 'Hallo Anna A (anna) http://x/?nick=anna']], $this->mails, 'bert will keinen, cleo hat keine Adresse');

        $this->mails = [];
        self::assertSame([0, ''], $this->rundmail()->newsletter('X', 2, 3), 'nur von bis');
    }

    public function testNameAusDerRegistrierung(): void
    {
        file_put_contents($this->ordner . 'tipper.txt', "<?php exit; ?>\nnora|x|5|Nora&nbsp;Neu|nora@x||||1|1|EOL\nleer|x|5|&nbsp;|leer@x||||1|1|EOL\n");

        $this->rundmail()->newsletter('Hallo [name]!', 1, 2);
        self::assertSame([['nora@x', 'Hallo Nora Neu!'], ['leer@x', 'Hallo !']], $this->mails, 'bis 4.5 mit &nbsp; im Text');
    }

    public function testEinzelnMitFehler(): void
    {
        file_put_contents($this->ordner . 'tipper.txt', "<?php exit; ?>\nfehl|x|5|F|fehler@x||||1|1|EOL\n");

        self::assertSame([0, 'FEHLER'], $this->rundmail()->einzeln('X', 'fehl'));
    }

    public function testErinnerungEinerLiga(): void
    {
        [$anzahl] = $this->rundmail()->erinnerung("Hallo [nick]:\n[spiele]", '1', 1, 9, 'tippliga.l98', '1');

        self::assertSame(1, $anzahl, 'bert will keine Erinnerung, cleo hat keine Adresse');
        $zeit = date('d.F.Y H:i', 1740834000);
        self::assertSame([['anna@x', "Hallo anna:\nGamma - Alpha (bis Samstag, " . str_replace('March', 'März', $zeit) . ')']], $this->mails, 'nur das nicht getippte Spiel');
    }

    public function testErinnerungAllerLigen(): void
    {
        [$anzahl] = $this->rundmail()->erinnerung('[spiele]', '1', 1, 9, null, '0');

        self::assertSame(1, $anzahl);
        self::assertSame([['anna@x', "\nTippliga:\n1. Spieltag:\nGamma - Alpha (bis " . date('d.m.Y H:i', 1740834000) . ')']], $this->mails);
    }

    public function testOhneTippdateiKeineErinnerung(): void
    {
        unlink($this->ordner . 'tipps/tippliga_anna.tip');

        self::assertSame([0, ''], $this->rundmail()->erinnerung('[spiele]', '1', 1, 9, 'tippliga.l98', '0'));
        self::assertSame([0, ''], $this->rundmail()->erinnerung('[spiele]', '1', 1, 9, null, '0'));
    }
}
