<?php
header('Content-Type: text/css');
require(__DIR__ . '/init.php');

// das Stylesheet aus der Konfiguration (Lmo\Web\Stylesheet, Vorlage style-nc.css.twig)
echo \Lmo\Web\Stylesheet::css(PATH_TO_LMO . '/vorlagen', 'style-nc.css.twig', $cfgarray, URL_TO_IMGDIR, PATH_TO_TEMPLATEDIR . '/style.css');
