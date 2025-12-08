<?php
declare(strict_types=1);

/**
 * Archivo de configuración para variables de entorno.
 * Este archivo se usa cuando las variables SetEnv de .htaccess no están disponibles
 * (por ejemplo, cuando PHP se ejecuta como PHP-FPM).
 * 
 * IMPORTANTE: En producción, considera usar variables de entorno del sistema
 * o un archivo .env que no se suba al repositorio.
 */

return [
    'APP_PROFILE' => 'mock',
    'DB_HOST' => '',
    'DB_PORT' => 3306,
    'DB_DATABASE' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
    'AZURE_COLLECT_URL' => '',
    'AZURE_FETCH_URL' => '',
];

