<?php declare(strict_types=1);

/**
 * Script de procesamiento que llama a la función procesador de Azure,
 * recibe los items compactados y los guarda en la base de datos.
 */

// Detectar si se ejecuta en CLI o vía web
$isCli = (php_sapi_name() === 'cli');

// Si se ejecuta vía web, ejecutar en background y devolver respuesta inmediata
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'message' => 'Proceso de procesamiento iniciado en background.',
        'fecha_inicio' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
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

if (empty($config['AZURE_PROCESS_URL'])) {
    $error = "Error: AZURE_PROCESS_URL no está configurada\n";
    if ($isCli) {
        fwrite(STDERR, $error);
    } else {
        error_log($error);
    }
    exit(1);
}

// Función para escribir salida
function writeOutput(string $message, bool $isCli): void {
    if ($isCli) {
        fwrite(STDOUT, $message);
        flush();
    } else {
        $logFile = __DIR__ . '/process_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }
}

// Función para conectar a la base de datos
function getDbConnection(array $config): ?PDO {
    try {
        $host = $config['DB_HOST'] ?? '';
        $port = $config['DB_PORT'] ?? 3306;
        $database = $config['DB_DATABASE'] ?? '';
        $username = $config['DB_USERNAME'] ?? '';
        $password = $config['DB_PASSWORD'] ?? '';
        
        if (empty($host) || empty($database) || empty($username)) {
            throw new Exception("Configuración de base de datos incompleta");
        }
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        throw new Exception("Error al conectar a la base de datos: " . $e->getMessage());
    }
}

// Función para insertar/actualizar un item en la BD
function guardarItem(PDO $pdo, array $item, bool $isCli): bool {
    try {
        // Preparar datos
        $url = substr($item['url'] ?? '', 0, 767); // Límite de BD
        $tipo = in_array($item['tipo'] ?? '', ['article', 'news'], true) ? $item['tipo'] : 'article';
        $title = substr($item['title'] ?? '', 0, 500);
        $published_at = $item['published_at'] ?? date('Y-m-d');
        $tags = is_array($item['tags'] ?? null) ? json_encode($item['tags'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        $summary = $item['summary'] ?? '';
        
        // Validar campos requeridos
        if (empty($url) || empty($title) || empty($published_at)) {
            writeOutput("  ⚠ Item omitido: faltan campos requeridos (URL, título o fecha)\n", $isCli);
            return false;
        }
        
        // Campos específicos de artículos
        $excerpt = null;
        $processed_at = null;
        $score = null;
        $source = null;
        $language = null;
        $key_points = null;
        
        if ($tipo === 'article') {
            $excerpt = !empty($item['excerpt']) ? substr($item['excerpt'], 0, 500) : null;
            $processed_at = $item['processed_at'] ?? null;
            $score = isset($item['score']) && $item['score'] !== null ? (float)$item['score'] : null;
            $source = !empty($item['source']) ? substr($item['source'], 0, 255) : null;
            $language = !empty($item['language']) ? substr($item['language'], 0, 10) : null;
            $key_points = is_array($item['key_points'] ?? null) ? json_encode($item['key_points'], JSON_UNESCAPED_UNICODE) : null;
        }
        
        // Campos específicos de noticias
        $image_url = null;
        if ($tipo === 'news') {
            $image_url = !empty($item['image_url']) ? substr($item['image_url'], 0, 1000) : null;
        }
        
        // Usar INSERT ... ON DUPLICATE KEY UPDATE para manejar duplicados
        $sql = "INSERT INTO items (
            url, tipo, title, published_at, tags, summary,
            excerpt, processed_at, score, source, language, key_points,
            image_url
        ) VALUES (
            :url, :tipo, :title, :published_at, :tags, :summary,
            :excerpt, :processed_at, :score, :source, :language, :key_points,
            :image_url
        ) ON DUPLICATE KEY UPDATE
            tipo = VALUES(tipo),
            title = VALUES(title),
            published_at = VALUES(published_at),
            tags = VALUES(tags),
            summary = VALUES(summary),
            excerpt = VALUES(excerpt),
            processed_at = VALUES(processed_at),
            score = VALUES(score),
            source = VALUES(source),
            language = VALUES(language),
            key_points = VALUES(key_points),
            image_url = VALUES(image_url),
            updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':url' => $url,
            ':tipo' => $tipo,
            ':title' => $title,
            ':published_at' => $published_at,
            ':tags' => $tags,
            ':summary' => $summary,
            ':excerpt' => $excerpt,
            ':processed_at' => $processed_at,
            ':score' => $score,
            ':source' => $source,
            ':language' => $language,
            ':key_points' => $key_points,
            ':image_url' => $image_url
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        writeOutput("  ❌ Error al guardar item: " . $e->getMessage() . "\n", $isCli);
        return false;
    } catch (Exception $e) {
        writeOutput("  ❌ Error: " . $e->getMessage() . "\n", $isCli);
        return false;
    }
}

// Iniciar proceso
writeOutput("[" . date('Y-m-d H:i:s') . "] Iniciando procesamiento desde: {$config['AZURE_PROCESS_URL']}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Este proceso puede tardar varios minutos...\n", $isCli);

$startTime = time();

// Realizar la petición HTTP al procesador
$url = $config['AZURE_PROCESS_URL'];
$maxExecutionTime = 600; // 10 minutos

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
        exit(1);
    }
    
    if ($httpCode !== 200) {
        $error = "Error HTTP {$httpCode}: {$response}\n";
        writeOutput($error, $isCli);
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
        exit(1);
    }
}

// Parsear respuesta JSON
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $error = "Error al parsear JSON: " . json_last_error_msg() . "\n";
    writeOutput($error, $isCli);
    writeOutput("Respuesta recibida: " . substr($response, 0, 500) . "...\n", $isCli);
    exit(1);
}

// Verificar que hay items
if (!isset($data['items']) || !is_array($data['items'])) {
    $error = "Error: La respuesta no contiene un array 'items'\n";
    writeOutput($error, $isCli);
    exit(1);
}

$items = $data['items'];
$totalItems = count($items);

writeOutput("\n[" . date('Y-m-d H:i:s') . "] Items recibidos: {$totalItems}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Archivos procesados: " . ($data['archivos_procesados'] ?? 0) . "\n", $isCli);

if ($totalItems === 0) {
    writeOutput("[" . date('Y-m-d H:i:s') . "] No hay items para procesar.\n", $isCli);
    exit(0);
}

// Conectar a la base de datos
try {
    $pdo = getDbConnection($config);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Conectado a la base de datos.\n", $isCli);
} catch (Exception $e) {
    $error = "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    writeOutput($error, $isCli);
    exit(1);
}

// Guardar items en la base de datos
$exitosos = 0;
$fallidos = 0;

writeOutput("[" . date('Y-m-d H:i:s') . "] Guardando items en la base de datos...\n", $isCli);

// Iniciar transacción para mejor rendimiento
$pdo->beginTransaction();

try {
    foreach ($items as $index => $item) {
        if (($index + 1) % 50 === 0) {
            writeOutput("[" . date('Y-m-d H:i:s') . "] Procesando item " . ($index + 1) . "/{$totalItems}...\n", $isCli);
        }
        
        if (guardarItem($pdo, $item, $isCli)) {
            $exitosos++;
        } else {
            $fallidos++;
        }
    }
    
    // Confirmar transacción
    $pdo->commit();
    writeOutput("[" . date('Y-m-d H:i:s') . "] Transacción confirmada.\n", $isCli);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $pdo->rollBack();
    $error = "Error en la transacción: " . $e->getMessage() . "\n";
    writeOutput($error, $isCli);
    exit(1);
}

$endTime = time();
$elapsed = $endTime - $startTime;

// Mostrar resultado final
writeOutput("\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ========== RESULTADO ==========\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items procesados: {$totalItems}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items guardados exitosamente: {$exitosos}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items fallidos: {$fallidos}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Tiempo transcurrido: " . intval($elapsed / 60) . ":" . sprintf("%02d", $elapsed % 60) . "\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ===============================\n", $isCli);

exit(0);

