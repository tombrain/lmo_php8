<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * "Liga per E-Mail" (lmo-adminmimesend.php, aus Administration > Herunterladen): die Seite in
 * allen Faellen und was verschickt wird (Empfaenger, Betreff, Anhaenge).
 */
final class LigaMailTest extends TestCase
{
    use AssertsSnapshots;

    /** Der Hinweis zu session_start() stammt vom Testrahmen, der die Sitzung schon gestartet hat. */
    private static function ohneTestrahmen(string $html): string
    {
        return (string) preg_replace('/Notice: session_start\(\): Ignoring session_start\(\)[^>]*? on line \d+ ?/', '', $html);
    }

    /** @return string Empfaenger, Betreff und Anhaenge der verschickten Mails */
    private static function mails(string $ordner): string
    {
        $text = '';
        foreach (glob($ordner . '/*.eml') ?: [] as $datei) {
            $mail = str_replace("\r\n", "\n", (string) file_get_contents($datei));
            preg_match_all('/^(?:To|Subject): .*$|filename=\S+/m', $mail, $m);
            $text .= implode("\n", $m[0]) . "\n";
            unlink($datei);
        }

        return $text === '' ? "(keine Mail)\n" : $text;
    }

    public function testSeiten(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP-Erweiterung zip fehlt');
        }
        $c = Fixture::loggedInClient();
        $mailordner = $c->instance()->mailDir();
        if (!is_dir($mailordner)) {
            mkdir($mailordner, 0777, true);
        }
        $t = new Transcript();
        foreach ([
            'Formular' => 'action=admin&todo=email&down=3',
            'Formular fuer alle' => 'action=admin&todo=email&down=-1',
            'eine Liga' => 'action=admin&todo=email&down=1&madr=empfaenger@example.org',
            'alle Ligen' => 'action=admin&todo=email&down=-1&madr=empfaenger@example.org',
            'ohne down' => 'action=admin&todo=email&madr=empfaenger@example.org',
            'anderes todo' => 'action=admin&todo=x&down=1&madr=empfaenger@example.org',
        ] as $fall => $parameter) {
            $t->add($fall, self::ohneTestrahmen($c->get('lmo-adminmimesend.php', $parameter)), self::mails($mailordner));
        }

        touch($mailordner . '/fail');
        $t->add('Versand scheitert', self::ohneTestrahmen($c->get('lmo-adminmimesend.php', 'action=admin&todo=email&down=1&madr=empfaenger@example.org')), self::mails($mailordner));
        unlink($mailordner . '/fail');

        $t->add('ohne Anmeldung', self::ohneTestrahmen(Fixture::anonymousClient()->get('lmo-adminmimesend.php', 'action=admin&todo=email&down=1&madr=empfaenger@example.org')), self::mails($mailordner));

        $this->assertMatchesSnapshot('liga-mail', 'seiten', $t->text());
    }
}
