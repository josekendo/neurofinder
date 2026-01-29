<?php
declare(strict_types=1);

/**
 * Script para generar el sitemap.xml dinámicamente.
 * 
 * Este script:
 * 1. Incluye páginas fijas (inicio, noticias, quienes-somos)
 * 2. Obtiene todos los artículos de la base de datos
 * 3. Genera una entrada en el sitemap por cada artículo
 * 
 * Uso: php scripts/generate_sitemap.php
 * 
 * El sitemap se genera en la raíz del sitio web (configurable mediante SITEMAP_PATH)
 */

// Cargar configuración
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Error: No se encuentra el archivo de configuración: $configPath\n");
    fwrite(STDERR, "Directorio actual del script: " . __DIR__ . "\n");
    exit(1);
}

echo "Cargando configuración desde: $configPath\n";
$config = require $configPath;

if (!is_array($config)) {
    fwrite(STDERR, "Error: El archivo de configuración no devuelve un array válido.\n");
    exit(1);
}

// URL base del sitio - intentar múltiples métodos
$baseUrl = null;
$envUrl = getenv('SITE_URL');
if ($envUrl !== false && $envUrl !== '') {
    $baseUrl = $envUrl;
    echo "✓ URL obtenida de variable de entorno SITE_URL: $baseUrl\n";
} elseif (isset($config['SITE_URL']) && !empty($config['SITE_URL'])) {
    $baseUrl = $config['SITE_URL'];
    echo "✓ URL obtenida de config.php: $baseUrl\n";
} else {
    $baseUrl = 'https://www.neurofinder.org';
    echo "⚠ Usando URL por defecto (no se encontró SITE_URL): $baseUrl\n";
    echo "  Para configurar la URL, puedes:\n";
    echo "  1. Establecer la variable de entorno SITE_URL\n";
    echo "  2. O agregar 'SITE_URL' => 'tu-url' en config.php\n";
}

if (empty($baseUrl)) {
    fwrite(STDERR, "Error: No se pudo determinar la URL base del sitio.\n");
    exit(1);
}

// Obtener variables de entorno o usar valores por defecto
$dbHost = getenv('DB_HOST') !== false ? getenv('DB_HOST') : ($config['DB_HOST'] ?? 'localhost');
$dbPort = (int)(getenv('DB_PORT') !== false ? getenv('DB_PORT') : ($config['DB_PORT'] ?? 3306));
$dbName = getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : ($config['DB_DATABASE'] ?? '');
$dbUser = getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : ($config['DB_USERNAME'] ?? '');
$dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['DB_PASSWORD'] ?? '');

if (empty($dbName) || empty($dbUser)) {
    fwrite(STDERR, "Error: Variables de entorno de base de datos no configuradas.\n");
    fwrite(STDERR, "Por favor, configura DB_DATABASE y DB_USERNAME.\n");
    exit(1);
}

// Ruta donde se generará el sitemap
// Por defecto: dos niveles arriba del directorio de trabajo actual (/home/neuroft/www/)
// Se puede configurar mediante variable de entorno SITEMAP_PATH o config.php
// Si se ejecuta desde Back php/api/, dos niveles arriba es la raíz del sitio
$defaultSitemapPath = getcwd() . '/../../sitemap.xml';
$sitemapPath = getenv('SITEMAP_PATH') !== false ? getenv('SITEMAP_PATH') : ($config['SITEMAP_PATH'] ?? $defaultSitemapPath);
$sitemapDir = dirname($sitemapPath);

// Intentar resolver la ruta absoluta del directorio
$resolvedDir = realpath($sitemapDir);
if ($resolvedDir !== false) {
    $sitemapPath = $resolvedDir . DIRECTORY_SEPARATOR . basename($sitemapPath);
    $sitemapDir = $resolvedDir;
} else {
    // El directorio no existe, intentar crearlo
    if (!is_dir($sitemapDir)) {
        if (!mkdir($sitemapDir, 0755, true)) {
            fwrite(STDERR, "Error: No se puede crear el directorio: $sitemapDir\n");
            fwrite(STDERR, "Ruta completa intentada: $sitemapPath\n");
            exit(1);
        }
        echo "Directorio creado: $sitemapDir\n";
        // Intentar resolver de nuevo después de crear
        $resolvedDir = realpath($sitemapDir);
        if ($resolvedDir !== false) {
            $sitemapPath = $resolvedDir . DIRECTORY_SEPARATOR . basename($sitemapPath);
            $sitemapDir = $resolvedDir;
        }
    }
}

echo "Ruta del sitemap: $sitemapPath\n";

try {
    // Conectar a la base de datos
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Conectado a la base de datos: $dbName\n";
    
    // Obtener todos los artículos de la base de datos
    // Usamos paginación para manejar grandes volúmenes de datos
    $pageSize = 1000;
    $page = 1;
    $allArticles = [];
    
    do {
        $offset = ($page - 1) * $pageSize;
        
        $sql = "SELECT url, published_at, updated_at 
                FROM items 
                WHERE tipo = 'article' 
                ORDER BY published_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $articles = $stmt->fetchAll();
        $allArticles = array_merge($allArticles, $articles);
        
        echo "Obtenidos " . count($articles) . " artículos (página $page)\n";
        
        $page++;
    } while (count($articles) === $pageSize);
    
    echo "Total de artículos encontrados: " . count($allArticles) . "\n";
    
    // Generar el contenido del sitemap
    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->setIndentString('  ');
    
    // Iniciar el documento XML
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('urlset');
    $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    
    // Páginas fijas
    // Página de inicio
    $xml->startElement('url');
    $xml->writeElement('loc', rtrim($baseUrl, '/') . '/');
    $xml->writeElement('changefreq', 'daily');
    $xml->writeElement('priority', '1.0');
    $xml->endElement();
    
    // Página de búsqueda
    $xml->startElement('url');
    $xml->writeElement('loc', rtrim($baseUrl, '/') . '/search');
    $xml->writeElement('changefreq', 'weekly');
    $xml->writeElement('priority', '0.8');
    $xml->endElement();
    
    // Página de noticias (actualización diaria)
    $xml->startElement('url');
    $xml->writeElement('loc', rtrim($baseUrl, '/') . '/news');
    $xml->writeElement('changefreq', 'daily');
    $xml->writeElement('priority', '0.7');
    $xml->endElement();
    
    // Página de quienes-somos
    $xml->startElement('url');
    $xml->writeElement('loc', rtrim($baseUrl, '/') . '/quienes-somos');
    $xml->writeElement('changefreq', 'monthly');
    $xml->writeElement('priority', '0.6');
    $xml->endElement();
    
    // Agregar cada artículo
    foreach ($allArticles as $article) {
        $articleUrl = $article['url'];
        // Usar rawurlencode para codificar correctamente la URL en el query string
        $encodedUrl = rawurlencode($articleUrl);
        $articleUrlPath = rtrim($baseUrl, '/') . '/articles?url=' . $encodedUrl;
        
        // Usar updated_at si está disponible, sino published_at
        $lastmod = $article['updated_at'] ?? $article['published_at'] ?? null;
        
        $xml->startElement('url');
        $xml->writeElement('loc', $articleUrlPath);
        
        if ($lastmod) {
            // Formatear fecha según el estándar de sitemap (YYYY-MM-DD)
            $date = new DateTime($lastmod);
            $xml->writeElement('lastmod', $date->format('Y-m-d'));
        }
        
        $xml->writeElement('changefreq', 'monthly');
        $xml->writeElement('priority', '0.5');
        $xml->endElement();
    }
    
    // Cerrar elementos
    $xml->endElement(); // urlset
    $xml->endDocument();
    
    // Obtener el contenido XML
    $xmlContent = $xml->outputMemory();
    
    // Escribir el archivo
    if (file_put_contents($sitemapPath, $xmlContent) === false) {
        throw new RuntimeException("Error al escribir el archivo: $sitemapPath");
    }
    
    echo "✓ Sitemap generado exitosamente: $sitemapPath\n";
    echo "Total de URLs en el sitemap: " . (4 + count($allArticles)) . "\n";
    echo "  - Páginas fijas: 4\n";
    echo "  - Artículos: " . count($allArticles) . "\n";
    
} catch (PDOException $e) {
    fwrite(STDERR, "Error de base de datos: " . $e->getMessage() . "\n");
    exit(1);
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
