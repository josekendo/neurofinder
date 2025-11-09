<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'NeuroFinder\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath . '.php';

    if (is_file($file)) {
        require $file;
    }
});


