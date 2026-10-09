<?php

namespace Lmo\Tests\Unit\Classlib;

/** Hilfsfunktionen aus addon/classlib/functions.php */
final class FunctionsTest extends ClasslibTestCase
{
    public function testConstArraySplitsAtComma(): void
    {
        self::assertSame(['.gif', '.jpg', '.png'], const_array('.gif,.jpg,.png'));
        self::assertSame(['einzeln'], const_array('einzeln'));
    }

    public function testStrBeforCharReturnsTextBeforeLastOccurrence(): void
    {
        self::assertSame('fussballbl2004', strBeforChar('fussballbl2004.l98', '.'));
        self::assertSame('archiv.2023', strBeforChar('archiv.2023.l98', '.'));
        self::assertSame('', strBeforChar('ohnepunkt', '.'));
    }

    public function testStrAfterCharReturnsTextAfterLastOccurrence(): void
    {
        self::assertSame('l98', strAfterChar('fussballbl2004.l98', '.'));
        self::assertSame('l98', strAfterChar('archiv.2023.l98', '.'));
        self::assertSame('', strAfterChar('endet.', '.'));
    }

    public function testStrAfterCharWithoutCharReturnsEmptyString(): void
    {
        // Gegenstueck zu strBeforChar(): keine Endung, wenn das Zeichen fehlt
        self::assertSame('', strAfterChar('ohnepunkt', '.'));
    }

    public function testInString(): void
    {
        self::assertTrue(in_string('Bayern', 'FC Bayern München'));
        self::assertFalse(in_string('bayern', 'FC Bayern München'));
        self::assertTrue(in_string('bayern', 'FC Bayern München', true));
        self::assertFalse(in_string('Dortmund', 'FC Bayern München', true));
    }

    public function testReadLigaDirListsOnlyLeagueFiles(): void
    {
        $dir = $this->tempDir();
        $this->tempFile($dir, 'bundesliga.l98');
        $this->tempFile($dir, 'notizen.txt');
        $this->tempFile($dir, 'saison.2023.l98');
        $this->tempSubdir($dir, 'archiv');

        $data = [];
        self::assertTrue(readLigaDir($dir, $data));

        usort($data, static fn (array $a, array $b): int => strcmp($a['src'], $b['src']));
        self::assertSame([
            ['path' => $dir, 'src' => 'bundesliga.l98', 'fileName' => 'bundesliga'],
            ['path' => $dir, 'src' => 'saison.2023.l98', 'fileName' => 'saison.2023'],
        ], $data);
    }

    public function testReadLigaDirHandlesUppercaseExtension(): void
    {
        $dir = $this->tempDir();
        $this->tempFile($dir, 'POKAL.L98');

        $data = [];
        readLigaDir($dir, $data);

        self::assertCount(1, $data);
        self::assertSame('POKAL', $data[0]['fileName']);
    }

    public function testReadLigaDirAppendsToExistingArray(): void
    {
        $dir = $this->tempDir();
        $this->tempFile($dir, 'neu.l98');

        $data = [['path' => '/alt', 'src' => 'alt.l98', 'fileName' => 'alt']];
        readLigaDir($dir, $data);

        self::assertCount(2, $data);
        self::assertSame('alt', $data[0]['fileName']);
    }

    public function testReadLigaDirMissingDirectory(): void
    {
        $data = ['unveraendert'];

        self::assertFalse(readLigaDir(sys_get_temp_dir() . '/lmo-gibt-es-nicht-' . uniqid(), $data));
        self::assertSame(['unveraendert'], $data);
    }

    public function testFindTeamNameIgnoresSpacesDigitsAndPunctuation(): void
    {
        $names = ['1. FC Nürnberg', 'Hertha BSC', 'FC Bayern München'];

        self::assertSame(['1. FC Nürnberg'], findTeamName($names, 'FC Nürnberg'));
        self::assertSame(['Hertha BSC'], findTeamName($names, 'hertha-bsc'));
        self::assertSame([], findTeamName($names, 'Hertha'));
    }

    public function testFindTeamNameReturnsOnlyFirstMatch(): void
    {
        $names = ['SC Freiburg', 'SC Freiburg II'];

        self::assertSame(['SC Freiburg'], findTeamName($names, 'SC Freiburg 2'));
    }

    public function testFindTeamNameWithoutArray(): void
    {
        $none = null;

        self::assertSame([], findTeamName($none, 'egal'));
    }
}
