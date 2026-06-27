<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

\Tester\Environment::setup();

// nette/caching ^2.5 uses implicit nullable params deprecated in PHP 8.4
error_reporting(E_ALL ^ E_DEPRECATED);
