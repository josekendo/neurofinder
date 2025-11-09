<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use NeuroFinder\Application;

$app = new Application();
$app->run();


