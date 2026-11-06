<?php

declare(strict_types=1);

// Composer-Autoloader und, wie zur Laufzeit in init.php, die Klassen der Addons aus addon/<addon>/src
require __DIR__ . '/../vendor/autoload.php';
\Lmo\Addon\Autoloader::registriere(dirname(__DIR__) . '/lmo/addon');
