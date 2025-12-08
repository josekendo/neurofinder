<?php declare(strict_types=1);

/**
 * Script de recopilación que llama a la URL de Azure configurada.
 * Muestra mensajes de progreso cada 20 segundos durante la ejecución.
 */

// Detectar si se ejecuta en CLI o vía web
$isCli = (php_sapi_name() === 'cli');

// Si se ejecuta vía web, ejecutar en background y devolver respuesta inmediata
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Proceso de recopilación iniciado en background.\n";
    echo "Fecha de inicio: " . date('Y-m-d H:i:s') . "\n";
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
    
    ignore_user_abort(true);
    set_time_limit(600);
}

// Cargar configuración
$config = require __DIR__ . '/../config.php';

if (empty($config['AZURE_COLLECT_URL'])) {
    $error = "Error: AZURE_COLLECT_URL no está configurada\n";
    if ($isCli) {
        fwrite(STDERR, $error);
    } else {
        error_log($error);
    }
    exit(1);
}

$url = $config['AZURE_COLLECT_URL'];
$maxExecutionTime = 600; // 10 minutos
$progressInterval = 20; // 20 segundos

// Función para escribir salida
function writeOutput(string $message, bool $isCli): void {
    if ($isCli) {
        fwrite(STDOUT, $message);
        flush();
    } else {
        $logFile = __DIR__ . '/recopilate_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }
}

// Iniciar proceso
writeOutput("[" . date('Y-m-d H:i:s') . "] Iniciando recopilación desde: {$url}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Este proceso puede tardar hasta 10 minutos...\n", $isCli);

$startTime = time();

// Crear proceso hijo para mostrar progreso (solo en CLI con pcntl)
$progressPid = null;
if ($isCli && function_exists('pcntl_fork')) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        // Proceso hijo: mostrar progreso cada 20 segundos
        $elapsed = 0;
        while ($elapsed < $maxExecutionTime) {
            sleep($progressInterval);
            $elapsed += $progressInterval;
            $minutes = intval($elapsed / 60);
            $seconds = $elapsed % 60;
            writeOutput("[" . date('Y-m-d H:i:s') . "] Procesando... (Tiempo transcurrido: {$minutes}:{$seconds})\n", $isCli);
        }
        exit(0);
    } elseif ($pid > 0) {
        $progressPid = $pid;
    }
}

// Realizar la petición HTTP
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $maxExecutionTime,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        $error = "Error cURL: {$curlError}\n";
        writeOutput($error, $isCli);
        if ($progressPid) {
            posix_kill($progressPid, SIGTERM);
            pcntl_waitpid($progressPid, $status);
        }
        exit(1);
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $maxExecutionTime,
            'ignore_errors' => true,
            'follow_location' => true,
            'max_redirects' => 5,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = "Error al realizar la petición HTTP\n";
        writeOutput($error, $isCli);
        if ($progressPid) {
            posix_kill($progressPid, SIGTERM);
            pcntl_waitpid($progressPid, $status);
        }
        exit(1);
    }
}

// Terminar proceso hijo si existe
if ($progressPid) {
    posix_kill($progressPid, SIGTERM);
    pcntl_waitpid($progressPid, $status);
}

$endTime = time();
$elapsed = $endTime - $startTime;

// Mostrar resultado final
writeOutput("\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Proceso completado en " . intval($elapsed / 60) . ":" . sprintf("%02d", $elapsed % 60) . "\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ========== RESPUESTA ==========\n", $isCli);
writeOutput($response . "\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ===============================\n", $isCli);

exit(0);
