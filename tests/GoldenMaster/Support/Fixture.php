<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Gemeinsame Testinstanz je PHP-Prozess (wird beim ersten Zugriff angelegt und
 * am Ende aufgeraeumt). Quelle: Umgebungsvariable LMO_SOURCE (Standard: Repo-Wurzel).
 */
final class Fixture
{
    private static ?self $instance = null;

    private Instance $app;
    private Runner $runner;

    private function __construct(Instance $app)
    {
        $this->app = $app;
        $this->runner = new Runner($app);
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            $app = Instance::create(self::sourceRoot());
            LeagueFactory::writeAll(self::sourceRoot() . '/lmo', $app->ligenDir() . '/golden');
            $app->freezeLeagueTimes();
            register_shutdown_function(static function () use ($app): void {
                $app->destroy();
            });
            self::$instance = new self($app);
        }
        return self::$instance;
    }

    /**
     * Neue, unabhaengige Testinstanz (fuer Tests, die Dateien veraendern).
     *
     * @return array{0:Instance,1:Runner}
     */
    public static function freshInstance(): array
    {
        $app = Instance::create(self::sourceRoot());
        LeagueFactory::writeAll(self::sourceRoot() . '/lmo', $app->ligenDir() . '/golden');
        $app->freezeLeagueTimes();
        register_shutdown_function(static function () use ($app): void {
            $app->destroy();
        });
        return [$app, new Runner($app)];
    }

    /**
     * Neue Instanz im Zustand "frisch hochgeladen" (ohne Installer-Schritte).
     *
     * @return array{0:Instance,1:Runner}
     */
    public static function uninstalledInstance(): array
    {
        $app = Instance::createUninstalled(self::sourceRoot());
        register_shutdown_function(static function () use ($app): void {
            $app->destroy();
        });
        return [$app, new Runner($app)];
    }

    /** Frische Instanz mit angemeldetem Hauptadmin; das Kennwort-Upgrade beim Login zaehlt nicht als Aenderung. */
    public static function loggedInClient(): AdminClient
    {
        [$app, $runner] = self::freshInstance();
        $client = new AdminClient($app, $runner);
        $client->login('admin', 'lmo');
        $client->changes();
        return $client;
    }

    /** Frische Instanz ohne Anmeldung. */
    public static function anonymousClient(): AdminClient
    {
        [$app, $runner] = self::freshInstance();
        return new AdminClient($app, $runner);
    }

    private static ?AdminClient $admin = null;

    /** Gemeinsamer, bereits als Hauptadmin angemeldeter Client fuer rein lesende Ansichten. */
    public static function adminClient(): AdminClient
    {
        if (self::$admin === null) {
            [$app, $runner] = self::freshInstance();
            $client = new AdminClient($app, $runner);
            $client->login('admin', 'lmo');
            $client->changes(); // Kennwort-Upgrade beim ersten Login nicht den Ansichten zurechnen
            self::$admin = $client;
        }
        return self::$admin;
    }

    /** Projektwurzel (enthaelt lmo/, tests/, docker/); hier landet auch coverage/. */
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function sourceRoot(): string
    {
        $env = getenv('LMO_SOURCE');
        return rtrim($env !== false && $env !== '' ? $env : self::projectRoot(), '/');
    }

    public function app(): Instance
    {
        return $this->app;
    }

    public function runner(): Runner
    {
        return $this->runner;
    }
}
