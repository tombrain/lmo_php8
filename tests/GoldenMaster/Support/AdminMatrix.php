<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Rein lesende Admin-Ansichten (GET) als Hauptadmin.
 *
 * Primaere Ligen bekommen alle Bearbeitungsansichten, die uebrigen nur die wichtigsten, damit
 * die Zahl der Snapshots ueberschaubar bleibt.
 */
final class AdminMatrix
{
    private const REAL = '1l_2024-25.l98';
    private const FULL = ['golden/basic.l98'];
    private const BRIEF = [
        'golden/special.l98', 'golden/handicap.l98', 'golden/wertung.l98',
        'golden/kegel.l98', 'golden/penalties.l98', 'golden/rankzones.l98', 'golden/minus2_direct.l98',
    ];

    /**
     * @return array<string,array{0:string,1:string}> Label => [Skript, Query]
     */
    public static function views(): array
    {
        $cases = [];
        $add = static function (string $query) use (&$cases): void {
            $cases['lmoadmin.php?' . $query] = ['lmoadmin.php', $query];
        };
        $a = 'action=admin';

        foreach (['', 'new', 'open', 'delete', 'upload', 'download', 'options', 'addons', 'design', 'user', 'update',
            'tipp', 'tippemail', 'tippuser', 'tippuseredit', 'tippoptions', 'vieweroptions', 'pdfoptions'] as $todo) {
            $add($a . ($todo === '' ? '' : '&todo=' . $todo));
        }
        $add($a . '&todo=open&subdir=golden/');
        $add($a . '&todo=open&subdir=dfb/');

        foreach (array_merge([self::REAL], self::FULL) as $file) {
            $f = '&file=' . $file;
            $add($a . '&todo=edit' . $f);
            foreach ([1, 7, -1, -2, -3, -10, -4] as $st) {
                $add($a . '&todo=edit' . $f . '&st=' . $st);
            }
            $add($a . '&todo=tabs' . $f);
            $add($a . '&todo=download' . $f);
        }
        foreach (self::BRIEF as $file) {
            $f = '&file=' . $file;
            $add($a . '&todo=edit' . $f . '&st=1');
            $add($a . '&todo=edit' . $f . '&st=-1');
            $add($a . '&todo=tabs' . $f);
        }
        return $cases;
    }
}
