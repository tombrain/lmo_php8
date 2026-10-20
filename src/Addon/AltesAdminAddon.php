<?php

declare(strict_types=1);

namespace Lmo\Addon;

use Lmo\Web\Kontext;

/**
 * Bruecke zu einem Addon nach alter Art mit einer eigenen Seite in der Administration (bisher
 * addon/pdf/lmo-adminpdfoptions.inc.php, "PDF", nicht Teil des LMO): ein Punkt im Hauptmenue und
 * die Seite dazu, beides nur fuer den Hauptadmin.
 */
final class AltesAdminAddon implements MitAdminMenue, MitAdminSeiten
{
    /**
     * @param string $todo Wert fuer todo
     * @param string $datei die Datei der Seite
     * @param int $nameText Nummer des Texts fuer den Menuepunkt (Sprachtexte des Addons)
     * @param int $titelText Nummer des Texts fuer den Tooltip
     */
    public function __construct(
        private string $name,
        private string $todo,
        private string $datei,
        private int $nameText,
        private int $titelText,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function adminMenue(Kontext $kontext): array
    {
        $texte = $kontext->texteVon($this->name);

        return [new Menuepunkt($this->todo, $texte[$this->nameText] ?? '', $texte[$this->titelText] ?? '', $kontext->anfrage->text('todo') == $this->todo)];
    }

    public function ligaMenue(Kontext $kontext): array
    {
        return [];
    }

    public function adminController(): array
    {
        $todo = $this->todo;

        return [new AlteAdminSeite(
            static fn (Kontext $kontext): bool => $kontext->anfrage->text('todo') == $todo && $kontext->sitzung->zahl('lmouserok') == 2,
            $this->datei
        )];
    }
}
