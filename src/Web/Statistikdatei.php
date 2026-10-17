<?php

namespace Lmo\Web;

/**
 * Die Statistikdatei einer Liga (output/<liga>.l98.php, geschrieben von lmo-zustat.php): PHP-Code,
 * der Variablen setzt. Hier wird sie in einem eigenen Bereich eingebunden, statt die Variablen
 * global zu verteilen.
 */
final class Statistikdatei
{
    /** @var array<string, mixed> */
    private array $werte;

    /** @param array<string, mixed> $werte Name der Variablen => Wert */
    public function __construct(array $werte)
    {
        $this->werte = $werte;
    }

    /** Die Datei lesen; null, wenn es sie nicht gibt. */
    public static function lies(string $pfad): ?self
    {
        if (!is_file($pfad)) {
            return null;
        }

        return new self((static function (string $lmo_statistikdatei): array {
            require $lmo_statistikdatei;
            unset($lmo_statistikdatei);

            return get_defined_vars();
        })($pfad));
    }

    /** Eine Variable der Datei; null, wenn sie sie nicht setzt. */
    public function wert(string $name): mixed
    {
        return $this->werte[$name] ?? null;
    }

    /** Tore der ganzen Saison ($gzutore). */
    public function toreSaison(): int|float|string
    {
        return $this->werte['gzutore'] ?? '';
    }

    /** Tore je Spiel der ganzen Saison ($gdstore). */
    public function toreJeSpielSaison(): int|float|string
    {
        return $this->werte['gdstore'] ?? '';
    }

    /** Tore eines Spieltags ($zutore). */
    public function toreSpieltag(int $spieltag): int|float|string
    {
        return $this->werte['zutore'][$spieltag] ?? '';
    }

    /** Tore je Spiel eines Spieltags ($dstore). */
    public function toreJeSpielSpieltag(int $spieltag): int|float|string
    {
        return $this->werte['dstore'][$spieltag] ?? '';
    }
}
