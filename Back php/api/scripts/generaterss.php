<?php
declare(strict_types=1);

/**
 * Script para generar archivos RSS estáticos de noticias y artículos.
 *
 * Genera 4 archivos por idioma y tipo:
 *   - rss/news-en.xml, rss/news-es.xml   (noticias)
 *   - rss/articles-en.xml, rss/articles-es.xml (artículos)
 *
 * Identificación por idioma: el nombre del archivo incluye el código de idioma (en, es).
 * El cron del servidor puede ejecutar este script diariamente.
 *
 * Uso: php scripts/generaterss.php
 *
 * Configuración (config.php o variables de entorno):
 *   - SITE_URL: URL base del sitio (para <link> del canal y de artículos)
 *   - RSS_PATH o RSS_DIR: directorio de salida (por defecto: dos niveles arriba de getcwd()/rss)
 *   - DB_*: conexión a la base de datos
 *
 * Estructura de datos (tabla items):
 *   Noticias (tipo=news): url, title, summary, published_at, image_url, language.
 *   Artículos (tipo=article): url, title, excerpt, published_at, language.
 * En RSS, cada noticia usa su url como <link>; cada artículo usa SITE_URL/articles?url=...
 */

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Error: No se encuentra el archivo de configuración: $configPath\n");
    exit(1);
}

$config = require $configPath;
if (!is_array($config)) {
    fwrite(STDERR, "Error: El archivo de configuración no devuelve un array válido.\n");
    exit(1);
}

$baseUrl = getenv('SITE_URL') !== false ? getenv('SITE_URL') : ($config['SITE_URL'] ?? 'https://www.neurofinder.org');
$baseUrl = rtrim(trim((string)$baseUrl), '/');
if ($baseUrl === '') {
    fwrite(STDERR, "Error: No se pudo determinar SITE_URL.\n");
    exit(1);
}

$dbHost = getenv('DB_HOST') !== false ? getenv('DB_HOST') : ($config['DB_HOST'] ?? 'localhost');
$dbPort = (int)(getenv('DB_PORT') !== false ? getenv('DB_PORT') : ($config['DB_PORT'] ?? 3306));
$dbName = getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : ($config['DB_DATABASE'] ?? '');
$dbUser = getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : ($config['DB_USERNAME'] ?? '');
$dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['DB_PASSWORD'] ?? '');

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, "Error: Variables de base de datos (DB_DATABASE, DB_USERNAME) no configuradas.\n");
    exit(1);
}

$defaultRssDir = (function () {
    $cwd = getcwd();
    $up = dirname($cwd);
    $up2 = dirname($up);
    return $up2 . DIRECTORY_SEPARATOR . 'rss';
})();
$rssDir = getenv('RSS_PATH') !== false ? getenv('RSS_PATH') : (getenv('RSS_DIR') !== false ? getenv('RSS_DIR') : ($config['RSS_PATH'] ?? $config['RSS_DIR'] ?? $defaultRssDir));
$rssDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rssDir), DIRECTORY_SEPARATOR);
if (!is_dir($rssDir)) {
    if (!mkdir($rssDir, 0755, true)) {
        fwrite(STDERR, "Error: No se puede crear el directorio RSS: $rssDir\n");
        exit(1);
    }
    echo "Directorio creado: $rssDir\n";
}

$languages = ['en', 'es'];
$limit = 100;

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Error de base de datos: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Escapa texto para uso en XML (RSS).
 */
function escapeRss(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Formatea fecha a RFC 2822 para RSS pubDate.
 */
function rssDate(?string $date): string
{
    if ($date === null || $date === '') {
        return gmdate(DATE_RFC2822);
    }
    $dt = new DateTimeImmutable($date);
    return $dt->setTimezone(new DateTimeZone('UTC'))->format(DATE_RFC2822);
}

/**
 * Genera el XML RSS 2.0 y lo escribe en $filePath.
 *
 * @param string $filePath Ruta del archivo de salida
 * @param string $channelTitle Título del canal
 * @param string $channelDescription Descripción del canal
 * @param string $lang Código de idioma (en, es)
 * @param array<int, array<string, mixed>> $items Lista de ítems (con title, link, description, pubDate, guid, opcional imageUrl)
 */
function writeRss(string $filePath, string $channelTitle, string $channelDescription, string $lang, array $items, string $baseUrl): void
{
    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->setIndentString('  ');
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('rss');
    $xml->writeAttribute('version', '2.0');
    $xml->writeAttribute('xml:lang', $lang === 'es' ? 'es' : 'en');
    $xml->startElement('channel');
    $xml->writeElement('title', escapeRss($channelTitle));
    $xml->writeElement('link', $baseUrl);
    $xml->writeElement('description', escapeRss($channelDescription));
    $xml->writeElement('language', $lang === 'es' ? 'es' : 'en');
    $xml->writeElement('lastBuildDate', gmdate(DATE_RFC2822));
    $xml->writeElement('generator', 'NeuroFinder generaterss.php');

    foreach ($items as $item) {
        $xml->startElement('item');
        $xml->writeElement('title', escapeRss((string)($item['title'] ?? '')));
        $xml->writeElement('link', (string)($item['link'] ?? ''));
        $xml->writeElement('description', escapeRss((string)($item['description'] ?? '')));
        $xml->writeElement('pubDate', (string)($item['pubDate'] ?? ''));
        $guid = (string)($item['guid'] ?? $item['link'] ?? '');
        $xml->startElement('guid');
        $xml->writeAttribute('isPermaLink', 'true');
        $xml->text($guid);
        $xml->endElement();
        if (!empty($item['imageUrl'])) {
            $xml->startElement('enclosure');
            $xml->writeAttribute('url', $item['imageUrl']);
            $xml->writeAttribute('type', 'image/jpeg');
            $xml->endElement();
        }
        $xml->endElement();
    }

    $xml->endElement(); // channel
    $xml->endElement(); // rss
    $xml->endDocument();
    $content = $xml->outputMemory();
    if (file_put_contents($filePath, $content) === false) {
        throw new RuntimeException("No se pudo escribir: $filePath");
    }
}

$titles = [
    'news' => [
        'en' => 'NeuroFinder – Latest News',
        'es' => 'NeuroFinder – Últimas noticias',
    ],
    'articles' => [
        'en' => 'NeuroFinder – Latest Articles',
        'es' => 'NeuroFinder – Últimos artículos',
    ],
];
$descriptions = [
    'news' => [
        'en' => 'Latest neuroscience and neurotechnology news from NeuroFinder.',
        'es' => 'Últimas noticias de neurociencia y neurotecnología en NeuroFinder.',
    ],
    'articles' => [
        'en' => 'Latest scientific articles and papers indexed by NeuroFinder.',
        'es' => 'Últimos artículos y publicaciones científicas indexados por NeuroFinder.',
    ],
];

$generated = 0;
foreach ($languages as $lang) {
    // ---- Noticias ----
    if ($lang === 'en') {
        $sqlNews = "SELECT url, title, summary, published_at, image_url
                    FROM items
                    WHERE tipo = 'news' AND (language = :lang OR language IS NULL)
                    ORDER BY published_at DESC
                    LIMIT :limit";
    } else {
        $sqlNews = "SELECT url, title, summary, published_at, image_url
                    FROM items
                    WHERE tipo = 'news' AND language = :lang
                    ORDER BY published_at DESC
                    LIMIT :limit";
    }
    $stmtNews = $pdo->prepare($sqlNews);
    $stmtNews->bindValue(':lang', $lang, PDO::PARAM_STR);
    $stmtNews->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtNews->execute();
    $rowsNews = $stmtNews->fetchAll();

    $newsItems = [];
    foreach ($rowsNews as $r) {
        $newsItems[] = [
            'title' => $r['title'],
            'link' => $r['url'],
            'description' => $r['summary'] ?? '',
            'pubDate' => rssDate($r['published_at'] ?? null),
            'guid' => $r['url'],
            'imageUrl' => !empty($r['image_url']) ? $r['image_url'] : null,
        ];
    }
    $newsPath = $rssDir . DIRECTORY_SEPARATOR . 'news-' . $lang . '.xml';
    writeRss(
        $newsPath,
        $titles['news'][$lang],
        $descriptions['news'][$lang],
        $lang,
        $newsItems,
        $baseUrl
    );
    echo "✓ $newsPath (" . count($newsItems) . " noticias)\n";
    $generated++;

    // ---- Artículos ----
    $sqlArticles = "SELECT url, title, excerpt, published_at
                    FROM items
                    WHERE tipo = 'article' AND language = :lang
                    ORDER BY published_at DESC
                    LIMIT :limit";
    $stmtArticles = $pdo->prepare($sqlArticles);
    $stmtArticles->bindValue(':lang', $lang, PDO::PARAM_STR);
    $stmtArticles->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtArticles->execute();
    $rowsArticles = $stmtArticles->fetchAll();

    $articleItems = [];
    foreach ($rowsArticles as $r) {
        $articleLink = $baseUrl . '/articles?url=' . rawurlencode($r['url']);
        $articleItems[] = [
            'title' => $r['title'],
            'link' => $articleLink,
            'description' => $r['excerpt'] ?? '',
            'pubDate' => rssDate($r['published_at'] ?? null),
            'guid' => $r['url'],
            'imageUrl' => null,
        ];
    }
    $articlesPath = $rssDir . DIRECTORY_SEPARATOR . 'articles-' . $lang . '.xml';
    writeRss(
        $articlesPath,
        $titles['articles'][$lang],
        $descriptions['articles'][$lang],
        $lang,
        $articleItems,
        $baseUrl
    );
    echo "✓ $articlesPath (" . count($articleItems) . " artículos)\n";
    $generated++;
}

echo "\nRSS generados: $generated archivos en $rssDir\n";
echo "  - Noticias: news-en.xml, news-es.xml\n";
echo "  - Artículos: articles-en.xml, articles-es.xml\n";
