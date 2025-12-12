<?php
declare(strict_types=1);

/**
 * Script para generar un JSON con todas las fuentes normalizadas de la base de datos.
 * 
 * Este script:
 * 1. Extrae todas las fuentes únicas de la tabla items
 * 2. Las normaliza (minúsculas, trim, etc.)
 * 3. Genera un archivo JSON con la estructura: { "fuente_normalizada": score }
 * 
 * Uso: php scripts/generate_sources_json.php
 */

require_once __DIR__ . '/../config.php';

// Cargar configuración
$config = require __DIR__ . '/../config.php';

// Obtener variables de entorno o usar valores por defecto
$dbHost = getenv('DB_HOST') ?: ($config['DB_HOST'] ?? 'localhost');
$dbPort = (int)(getenv('DB_PORT') ?: ($config['DB_PORT'] ?? 3306));
$dbName = getenv('DB_DATABASE') ?: ($config['DB_DATABASE'] ?? '');
$dbUser = getenv('DB_USERNAME') ?: ($config['DB_USERNAME'] ?? '');
$dbPass = getenv('DB_PASSWORD') ?: ($config['DB_PASSWORD'] ?? '');

if (empty($dbName) || empty($dbUser)) {
    fwrite(STDERR, "Error: Variables de entorno de base de datos no configuradas.\n");
    fwrite(STDERR, "Por favor, configura DB_DATABASE y DB_USERNAME.\n");
    exit(1);
}

/**
 * Normaliza una fuente: convierte a minúsculas, elimina espacios extra y caracteres especiales
 */
function normalizeSource(string $source): string {
    // Convertir a minúsculas
    $normalized = strtolower(trim($source));
    
    // Eliminar múltiples espacios en blanco
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    
    // Eliminar caracteres especiales al inicio/final (puedes ajustar según necesites)
    $normalized = trim($normalized, ".,;:!?()[]{}");
    
    return $normalized;
}

try {
    // Conectar a la base de datos
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Conectado a la base de datos: $dbName\n";
    
    // Obtener todas las fuentes únicas de la tabla items
    $stmt = $pdo->query("SELECT DISTINCT source FROM items WHERE source IS NOT NULL AND source != '' ORDER BY source");
    $sources = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Encontradas " . count($sources) . " fuentes únicas.\n";
    
    // Normalizar y crear el array asociativo
    $normalizedSources = [];
    foreach ($sources as $source) {
        $normalized = normalizeSource($source);
        if ($normalized !== '') {
            // Si ya existe una normalización igual, mantener la original más corta o la primera
            if (!isset($normalizedSources[$normalized])) {
                $normalizedSources[$normalized] = [
                    'original' => $source,
                    'score' => null // Score será definido manualmente después
                ];
            }
        }
    }
    
    echo "Fuentes normalizadas: " . count($normalizedSources) . "\n";
    
    // Cargar el JSON existente si existe para preservar los scores
    $jsonFile = __DIR__ . '/../data/sources_reliability.json';
    $existingScores = [];
    
    if (file_exists($jsonFile)) {
        $existingData = json_decode(file_get_contents($jsonFile), true);
        if (is_array($existingData)) {
            foreach ($existingData as $normalized => $data) {
                if (is_array($data) && isset($data['score'])) {
                    $existingScores[$normalized] = $data['score'];
                } elseif (is_numeric($data)) {
                    // Compatibilidad: si el formato antiguo era { "fuente": score }
                    $existingScores[$normalized] = (float)$data;
                }
            }
        }
        echo "Cargados " . count($existingScores) . " scores existentes.\n";
    }
    
    // Aplicar scores existentes a las fuentes normalizadas
    foreach ($normalizedSources as $normalized => &$data) {
        if (isset($existingScores[$normalized])) {
            $data['score'] = $existingScores[$normalized];
        }
    }
    unset($data); // Liberar referencia
    
    // Crear directorio si no existe
    $dataDir = dirname($jsonFile);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
        echo "Directorio creado: $dataDir\n";
    }
    
    // Ordenar por fuente normalizada para mejor legibilidad
    ksort($normalizedSources);
    
    // Generar JSON con formato legible
    $json = json_encode($normalizedSources, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        throw new RuntimeException('Error al generar JSON: ' . json_last_error_msg());
    }
    
    // Escribir archivo
    if (file_put_contents($jsonFile, $json) === false) {
        throw new RuntimeException("Error al escribir el archivo: $jsonFile");
    }
    
    echo "✓ Archivo generado exitosamente: $jsonFile\n";
    echo "Total de fuentes: " . count($normalizedSources) . "\n";
    
    // Contar cuántas tienen score
    $withScore = 0;
    foreach ($normalizedSources as $data) {
        if (isset($data['score']) && $data['score'] !== null) {
            $withScore++;
        }
    }
    echo "Fuentes con score definido: $withScore\n";
    echo "Fuentes sin score (usarán 0.1 por defecto): " . (count($normalizedSources) - $withScore) . "\n";
    
} catch (PDOException $e) {
    fwrite(STDERR, "Error de base de datos: " . $e->getMessage() . "\n");
    exit(1);
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
