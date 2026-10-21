<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Addon;

use Lmo\Liga\Spiel;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use UnexpectedValueException;

/**
 * Die Addons einer Installation und ihre Erweiterungspunkte. Der Kern fragt hier nach, statt die
 * Addons beim Namen zu kennen.
 */
final class Addons
{
    /** @var list<Addon> */
    private array $addons;

    /** @param list<Addon> $addons */
    public function __construct(array $addons = [])
    {
        $this->addons = $addons;
    }

    /**
     * Die Addons im Ordner: jedes Unterverzeichnis mit einer addon.php, die ein Addon zurueckgibt,
     * in alphabetischer Reihenfolge. Ordner ohne addon.php (Addons nach alter Art) zaehlen nicht.
     *
     * @throws UnexpectedValueException wenn eine addon.php kein Addon zurueckgibt
     */
    public static function ausOrdner(string $ordner): self
    {
        $addons = [];
        foreach (is_dir($ordner) ? (scandir($ordner) ?: []) : [] as $name) {
            $datei = $ordner . '/' . $name . '/addon.php';
            if ($name == '.' || $name == '..' || !is_file($datei)) {
                continue;
            }
            $addon = (static fn (string $lmo_addon_datei): mixed => require $lmo_addon_datei)($datei);
            if (!$addon instanceof Addon) {
                throw new UnexpectedValueException($datei . ' gibt kein ' . Addon::class . ' zurueck');
            }
            $addons[] = $addon;
        }

        return new self($addons);
    }

    /** Dieselben Addons und dazu weitere (z. B. Bruecken zu Addons nach alter Art). */
    public function mit(Addon ...$weitere): self
    {
        return new self(array_merge($this->addons, array_values($weitere)));
    }

    /** @return list<Addon> */
    public function alle(): array
    {
        return $this->addons;
    }

    /** Ist ein Addon dieses Namens da? */
    public function hat(string $name): bool
    {
        foreach ($this->addons as $addon) {
            if ($addon->name() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Die Controller aller Addons mit eigenen Seiten.
     *
     * @return list<Controller>
     */
    public function controller(): array
    {
        $controller = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitSeiten) {
                array_push($controller, ...$addon->controller());
            }
        }

        return $controller;
    }

    /** Das HTML aller Addons fuer das Ende einer Ergebniszeile, hintereinander. */
    public function zusatzJeSpiel(Spiel $spiel, Kontext $kontext): string
    {
        $html = '';
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitZusatzJeSpiel) {
                $html .= $addon->zusatzJeSpiel($spiel, $kontext);
            }
        }

        return $html;
    }

    /** Das HTML aller Addons unter der Seite, hintereinander. */
    public function zusatzUnterSeite(Kontext $kontext): string
    {
        $html = '';
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitZusatzUnterSeite) {
                $html .= $addon->zusatzUnterSeite($kontext);
            }
        }

        return $html;
    }

    /**
     * Die Teile, die Addons zum Rahmen beitragen; liefern mehrere denselben, stehen sie hintereinander.
     *
     * @return array<string, string> Teil des Rahmens => fertiges HTML
     */
    public function rahmenteile(Kontext $kontext): array
    {
        $teile = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitRahmenteilen) {
                foreach ($addon->rahmenteile($kontext) as $teil => $html) {
                    $teile[$teil] = ($teile[$teil] ?? '') . $html;
                }
            }
        }

        return $teile;
    }

    /**
     * Die Controller aller Addons mit eigenen Seiten in der Administration.
     *
     * @return list<Controller>
     */
    public function adminController(): array
    {
        $controller = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitAdminSeiten) {
                array_push($controller, ...$addon->adminController());
            }
        }

        return $controller;
    }

    /**
     * Die Punkte aller Addons fuer das Hauptmenue der Administration, in der Reihenfolge der Addons.
     *
     * @return list<Menuepunkt>
     */
    public function adminMenue(Kontext $kontext): array
    {
        $punkte = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitAdminMenue) {
                array_push($punkte, ...$addon->adminMenue($kontext));
            }
        }

        return $punkte;
    }

    /**
     * Die Punkte aller Addons fuer die Navigation beim Bearbeiten einer Liga.
     *
     * @return list<Menuepunkt>
     */
    public function ligaMenue(Kontext $kontext): array
    {
        $punkte = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitAdminMenue) {
                array_push($punkte, ...$addon->ligaMenue($kontext));
            }
        }

        return $punkte;
    }

    /**
     * Die Punkte aller Addons fuer die Startseite der Administration.
     *
     * @return list<Menuepunkt>
     */
    public function startseite(Kontext $kontext): array
    {
        $punkte = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitAdminMenue) {
                array_push($punkte, ...$addon->startseite($kontext));
            }
        }

        return $punkte;
    }

    /**
     * Die Schalter aller Addons fuer die Optionen, je Addon unter seinem Namen, in der Reihenfolge der Addons.
     *
     * @return array<string, list<Schalter>>
     */
    public function schalter(Kontext $kontext): array
    {
        $schalter = [];
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitSchaltern && ($eigene = $addon->schalter($kontext)) !== []) {
                $schalter[$addon->name()] = $eigene;
            }
        }

        return $schalter;
    }

    /** Hat ein Addon Zusatz je Spiel? Sonst bleibt die Zeile ohne. */
    public function mitZusatzJeSpiel(): bool
    {
        foreach ($this->addons as $addon) {
            if ($addon instanceof MitZusatzJeSpiel) {
                return true;
            }
        }

        return false;
    }
}
