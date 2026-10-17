<?php

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
