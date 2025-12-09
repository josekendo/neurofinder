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

// Función para actualizar estadísticas de la base de datos
// Todas las estadísticas se calculan contando directamente desde la BD
function actualizarEstadisticas(PDO $pdo, bool $isCli): array {
    try {
        // Contar items totales directamente desde la BD (artículos + noticias)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM items");
        $result = $stmt->fetch();
        $totalItemsBD = (int)($result['total'] ?? 0);
        
        // Contar fuentes únicas directamente desde la BD 
        // (solo de artículos, donde source no es NULL ni vacío)
        $stmt = $pdo->query("SELECT COUNT(DISTINCT source) as total FROM items WHERE source IS NOT NULL AND source != ''");
        $result = $stmt->fetch();
        $totalFuentes = (int)($result['total'] ?? 0);
        
        // Contar artículos procesados directamente desde la BD 
        // (items con tipo 'article' o 'news')
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM items WHERE tipo IN ('article', 'news')");
        $result = $stmt->fetch();
        $totalArticulos = (int)($result['total'] ?? 0);
        
        // Actualizar tabla estadisticas
        // Usamos VALUES() en ON DUPLICATE KEY UPDATE para evitar problemas con parámetros duplicados
        $sqlUpdate = "INSERT INTO estadisticas (id, sources, articles, updated_at) 
                      VALUES (1, :sources, :articles, NOW())
                      ON DUPLICATE KEY UPDATE 
                          sources = VALUES(sources),
                          articles = VALUES(articles),
                          updated_at = NOW()";
        
        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute([
            ':sources' => $totalFuentes,
            ':articles' => $totalArticulos
        ]);
        
        writeOutput("[" . date('Y-m-d H:i:s') . "] ✓ Estadísticas actualizadas en la base de datos.\n", $isCli);
        
        // Retornar valores para mostrar en el resultado final
        return [$totalItemsBD, $totalFuentes, $totalArticulos];
        
    } catch (Exception $e) {
        writeOutput("[" . date('Y-m-d H:i:s') . "] ⚠ Error al actualizar estadísticas: " . $e->getMessage() . "\n", $isCli);
        return [0, 0, 0];
    }
}

// Función para validar y formatear fecha
function validarFecha(string $fecha): ?string {
    // Intentar parsear la fecha en varios formatos comunes
    $formatos = ['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'd/m/Y', 'Y-m-d\TH:i:s\Z'];
    
    foreach ($formatos as $formato) {
        $fechaObj = DateTime::createFromFormat($formato, $fecha);
        if ($fechaObj !== false) {
            return $fechaObj->format('Y-m-d');
        }
    }
    
    // Si es un timestamp, convertir a fecha
    if (is_numeric($fecha)) {
        $fechaObj = new DateTime('@' . $fecha);
        return $fechaObj->format('Y-m-d');
    }
    
    return null;
}

// Función para añadir hash de idioma a la URL
function añadirHashIdiomaUrl(string $url, ?string $language): string {
    // Si no hay idioma, retornar URL original
    if (empty($language)) {
        return $url;
    }
    
    // Normalizar idioma (solo primeros 2 caracteres, minúsculas)
    $lang = strtolower(substr(trim($language), 0, 2));
    if (empty($lang)) {
        return $url;
    }
    
    // Si la URL ya tiene hash, removerlo primero
    $urlSinHash = preg_replace('/#[\w-]+$/', '', $url);
    
    // Añadir hash del idioma al final
    $urlConHash = $urlSinHash . '#' . $lang;
    
    // Asegurar que no exceda el límite de 767 caracteres
    if (strlen($urlConHash) > 767) {
        // Recortar la URL base para dejar espacio para el hash
        $longitudMaxima = 767 - strlen('#' . $lang);
        $urlConHash = substr($urlSinHash, 0, $longitudMaxima) . '#' . $lang;
    }
    
    return $urlConHash;
}

// Función para insertar/actualizar un item en la BD
function guardarItem(PDO $pdo, array $item, bool $isCli): bool {
    try {
        // Obtener URL base y tipo
        $urlBase = $item['url'] ?? '';
        $tipo = in_array($item['tipo'] ?? '', ['article', 'news'], true) ? $item['tipo'] : 'article';
        $title = substr($item['title'] ?? '', 0, 500);
        $published_at_raw = $item['published_at'] ?? date('Y-m-d');
        $tags = is_array($item['tags'] ?? null) ? json_encode($item['tags'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        $summary = $item['summary'] ?? '';
        
        // Validar y formatear fecha
        $published_at = validarFecha($published_at_raw);
        if ($published_at === null) {
            // Si no se puede validar, intentar usar la fecha actual como fallback
            $published_at = date('Y-m-d');
        }
        
        // Validar campos requeridos
        if (empty($urlBase) || empty($title)) {
            writeOutput("  ⚠ Item omitido: faltan campos requeridos (URL o título)\n", $isCli);
            return false;
        }
        
        // Extraer idioma (siempre debe tener valor, por defecto 'en')
        // IMPORTANTE: El idioma se guarda siempre en la BD, tanto para artículos como para noticias
        // Las noticias también reciben traducción y deben tener su idioma guardado
        $language = 'en'; // Por defecto inglés
        if (!empty($item['language'])) {
            $language = substr(trim($item['language']), 0, 10);
            // Normalizar a minúsculas y validar que sea un código válido
            $language = strtolower($language);
            // Si no es un código válido conocido, usar 'en' por defecto
            $idiomasValidos = ['es', 'en', 'fr', 'de', 'it', 'pt'];
            if (!in_array($language, $idiomasValidos, true)) {
                $language = 'en';
            }
        }
        // Garantizar que el idioma nunca sea null o vacío
        if (empty($language)) {
            $language = 'en';
        }
        
        // Campos específicos de artículos
        $excerpt = null;
        $processed_at = null;
        $score = null;
        $source = null;
        $key_points = null;
        
        if ($tipo === 'article') {
            // excerpt es TEXT en BD, no hay límite estricto, pero limitamos a un tamaño razonable
            $excerpt = !empty($item['excerpt']) ? $item['excerpt'] : null;
            if ($excerpt !== null && strlen($excerpt) > 65535) { // Límite aproximado de TEXT
                $excerpt = substr($excerpt, 0, 65535);
            }
            
            // Validar fecha de procesamiento - si no hay, usar fecha actual
            $processed_at_raw = $item['processed_at'] ?? null;
            if ($processed_at_raw !== null) {
                $processed_at = validarFecha($processed_at_raw);
                if ($processed_at === null) {
                    // Si no es válida, usar fecha actual
                    $processed_at = date('Y-m-d');
                }
            } else {
                // Si no hay fecha de procesado, usar la fecha actual
                $processed_at = date('Y-m-d');
            }
            
            // Validar score (debe estar entre 0 y 1, o ser null)
            if (isset($item['score']) && $item['score'] !== null) {
                $score = (float)$item['score'];
                if ($score < 0) $score = 0;
                if ($score > 1) $score = 1;
            }
            
            $source = !empty($item['source']) ? substr($item['source'], 0, 255) : null;
            $key_points = is_array($item['key_points'] ?? null) ? json_encode($item['key_points'], JSON_UNESCAPED_UNICODE) : null;
        }
        
        // Campos específicos de noticias
        $image_url = null;
        if ($tipo === 'news') {
            $image_url = !empty($item['image_url']) ? substr($item['image_url'], 0, 1000) : null;
            // Las noticias no tienen processed_at según el esquema de BD
            $processed_at = null;
        }
        
        // Añadir hash de idioma a la URL (para artículos y noticias)
        $url = añadirHashIdiomaUrl($urlBase, $language);
        
        // Asegurar que la URL no exceda el límite de BD
        if (strlen($url) > 767) {
            $url = substr($url, 0, 767);
            writeOutput("  ⚠ URL recortada por exceder límite: " . substr($urlBase, 0, 100) . "...\n", $isCli);
        }
        
        // Validar summary (es TEXT en BD, no hay límite estricto)
        if (strlen($summary) > 65535) { // Límite aproximado de TEXT
            $summary = substr($summary, 0, 65535);
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
        // Intentar obtener la URL final si está disponible, sino usar la base
        $urlFinal = $url ?? $urlBase ?? ($item['url'] ?? 'desconocida');
        $urlItem = substr($urlFinal, 0, 100);
        writeOutput("  ❌ Error al guardar item (URL: {$urlItem}): " . $e->getMessage() . "\n", $isCli);
        return false;
    } catch (Exception $e) {
        // Intentar obtener la URL final si está disponible, sino usar la base
        $urlFinal = $url ?? $urlBase ?? ($item['url'] ?? 'desconocida');
        $urlItem = substr($urlFinal, 0, 100);
        writeOutput("  ❌ Error al procesar item (URL: {$urlItem}): " . $e->getMessage() . "\n", $isCli);
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

writeOutput("[" . date('Y-m-d H:i:s') . "] Realizando petición GET a: {$url}\n", $isCli);

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $maxExecutionTime,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($response === false) {
        $error = "[" . date('Y-m-d H:i:s') . "] ❌ Error cURL: {$curlError}\n";
        writeOutput($error, $isCli);
        exit(1);
    }
    
    writeOutput("[" . date('Y-m-d H:i:s') . "] Respuesta recibida - HTTP {$httpCode}, Content-Type: " . ($contentType ?: 'desconocido') . "\n", $isCli);
    
    if ($httpCode !== 200) {
        $error = "[" . date('Y-m-d H:i:s') . "] ❌ Error HTTP {$httpCode}: " . substr($response, 0, 500) . "\n";
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
            'user_agent' => 'PHP Process Script/1.0',
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = "[" . date('Y-m-d H:i:s') . "] ❌ Error al realizar la petición HTTP con file_get_contents\n";
        writeOutput($error, $isCli);
        exit(1);
    }
    
    writeOutput("[" . date('Y-m-d H:i:s') . "] Respuesta recibida correctamente\n", $isCli);
}

// Parsear respuesta JSON
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $error = "Error al parsear JSON: " . json_last_error_msg() . "\n";
    writeOutput($error, $isCli);
    writeOutput("Respuesta recibida: " . substr($response, 0, 500) . "...\n", $isCli);
    exit(1);
}

// Verificar si hay un error en la respuesta de Azure
if (isset($data['error'])) {
    $error = "Error en la respuesta de Azure: " . $data['error'] . "\n";
    writeOutput($error, $isCli);
    // Continuar procesando si hay items, pero advertir del error
    if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        exit(1);
    }
    writeOutput("⚠ Advertencia: Se detectó un error pero se continuará procesando los items disponibles.\n", $isCli);
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

// Conectar a la base de datos (necesario incluso si no hay items para actualizar estadísticas)
try {
    $pdo = getDbConnection($config);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Conectado a la base de datos.\n", $isCli);
} catch (Exception $e) {
    $error = "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    writeOutput($error, $isCli);
    exit(1);
}

if ($totalItems === 0) {
    writeOutput("[" . date('Y-m-d H:i:s') . "] No hay items para procesar.\n", $isCli);
    
    // Actualizar estadísticas incluso si no hay items nuevos
    writeOutput("[" . date('Y-m-d H:i:s') . "] Actualizando estadísticas...\n", $isCli);
    [$totalItemsBD, $totalFuentes, $totalArticulos] = actualizarEstadisticas($pdo, $isCli);
    
    $endTime = time();
    $elapsed = $endTime - $startTime;
    
    writeOutput("\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] ========== RESULTADO ==========\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Items procesados en esta ejecución: 0\n", $isCli);
    writeOutput("\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] ========== ESTADÍSTICAS DE LA BASE DE DATOS ==========\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Items totales en BD: {$totalItemsBD}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Fuentes únicas: {$totalFuentes}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Artículos totales: {$totalArticulos}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Tiempo transcurrido: " . intval($elapsed / 60) . ":" . sprintf("%02d", $elapsed % 60) . "\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] ===============================\n", $isCli);
    
    exit(0);
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
    
    // Actualizar estadísticas incluso si hubo error en la transacción
    writeOutput("[" . date('Y-m-d H:i:s') . "] Actualizando estadísticas después del error...\n", $isCli);
    [$totalItemsBD, $totalFuentes, $totalArticulos] = actualizarEstadisticas($pdo, $isCli);
    
    $endTime = time();
    $elapsed = $endTime - $startTime;
    
    writeOutput("\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] ========== RESULTADO (CON ERRORES) ==========\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Items procesados en esta ejecución: {$totalItems}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Items guardados exitosamente: {$exitosos}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Items fallidos: {$fallidos}\n", $isCli);
    writeOutput("\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] ========== ESTADÍSTICAS DE LA BASE DE DATOS ==========\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Items totales en BD: {$totalItemsBD}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Fuentes únicas: {$totalFuentes}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Artículos totales: {$totalArticulos}\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] Tiempo transcurrido: " . intval($elapsed / 60) . ":" . sprintf("%02d", $elapsed % 60) . "\n", $isCli);
    writeOutput("[" . date('Y-m-d H:i:s') . "] ===============================\n", $isCli);
    
    exit(1);
}

// Actualizar estadísticas de la base de datos
writeOutput("[" . date('Y-m-d H:i:s') . "] Actualizando estadísticas...\n", $isCli);
[$totalItemsBD, $totalFuentes, $totalArticulos] = actualizarEstadisticas($pdo, $isCli);

$endTime = time();
$elapsed = $endTime - $startTime;

// Mostrar resultado final
writeOutput("\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ========== RESULTADO ==========\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items procesados en esta ejecución: {$totalItems}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items guardados exitosamente: {$exitosos}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items fallidos: {$fallidos}\n", $isCli);
writeOutput("\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ========== ESTADÍSTICAS DE LA BASE DE DATOS ==========\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Items totales en BD: {$totalItemsBD}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Fuentes únicas: {$totalFuentes}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Artículos totales: {$totalArticulos}\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] Tiempo transcurrido: " . intval($elapsed / 60) . ":" . sprintf("%02d", $elapsed % 60) . "\n", $isCli);
writeOutput("[" . date('Y-m-d H:i:s') . "] ===============================\n", $isCli);

exit(0);

