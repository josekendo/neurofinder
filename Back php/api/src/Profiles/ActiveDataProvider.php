<?php
declare(strict_types=1);

namespace NeuroFinder\Profiles;

use NeuroFinder\Contracts\DataProviderInterface;

final class ActiveDataProvider implements DataProviderInterface
{
    public function search(array $request): array
    {
        $query = trim((string)($request['query'] ?? ''));
        $filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];

        // Si no hay query, hacer búsqueda local en la base de datos
        if ($query === '') {
            return $this->searchLocal($filters);
        }

        // Si hay query, llamar a Azure
        return $this->searchAzure($query, $filters);
    }

    /**
     * Búsqueda local en la base de datos sin query
     */
    private function searchLocal(array $filters): array
    {
        // Obtener variables de entorno de la base de datos
        $host = $this->getEnvVar('DB_HOST') ?: 'localhost';
        $port = (int)($this->getEnvVar('DB_PORT') ?: 3306);
        $database = $this->getEnvVar('DB_DATABASE') ?: '';
        $username = $this->getEnvVar('DB_USERNAME') ?: '';
        $password = $this->getEnvVar('DB_PASSWORD') ?: '';

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Variables de entorno de base de datos no configuradas.');
        }

        // Crear conexión PDO
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        
        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Construir consulta SQL con filtros
            $sql = "SELECT 
                        url as id,
                        title,
                        excerpt,
                        summary,
                        published_at as publishedAt,
                        processed_at as processedAt,
                        score,
                        source,
                        language,
                        tags,
                        tipo as type
                    FROM items 
                    WHERE tipo IN ('article', 'news')";
            
            $params = [];
            
            // Filtro por tipos de documento (aplicado en SQL)
            // Mapear tipos del frontend a tipos de BD:
            // - article, paper, clinical-report -> article
            // - news -> news
            $documentTypes = (array)($filters['documentTypes'] ?? []);
            if ($documentTypes !== []) {
                $allowedTypes = [];
                foreach ($documentTypes as $docType) {
                    $docType = trim((string)$docType);
                    if ($docType === 'news') {
                        $allowedTypes[] = 'news';
                    } elseif (in_array($docType, ['article', 'paper', 'clinical-report'], true)) {
                        $allowedTypes[] = 'article';
                    }
                }
                
                // Eliminar duplicados
                $allowedTypes = array_unique($allowedTypes);
                
                // Aplicar filtro en SQL si hay tipos permitidos
                if (!empty($allowedTypes)) {
                    $docPlaceholders = [];
                    foreach ($allowedTypes as $i => $docType) {
                        $key = ':doc_type_' . $i;
                        $docPlaceholders[] = $key;
                        $params[$key] = $docType;
                    }
                    $sql .= " AND tipo IN (" . implode(', ', $docPlaceholders) . ")";
                } else {
                    // Si no hay tipos permitidos válidos, retornar vacío (no hay resultados)
                    return [];
                }
            }
            
            // Filtro por tipos de demencia
            $dementiaTypes = (array)($filters['dementiaTypes'] ?? []);
            if ($dementiaTypes !== []) {
                $conditions = [];
                foreach ($dementiaTypes as $i => $type) {
                    $key = ':dementia_json_' . $i;
                    // JSON_CONTAINS necesita un JSON válido, así que envuelvo el string en un array JSON
                    $conditions[] = "JSON_CONTAINS(tags, " . $key . ")";
                    $params[$key] = json_encode([$type]);
                }
                $sql .= " AND (" . implode(' OR ', $conditions) . ")";
            }
            
            // Filtro por idiomas
            $languages = (array)($filters['languages'] ?? []);
            if ($languages !== []) {
                $placeholders = [];
                foreach ($languages as $i => $lang) {
                    $key = ':lang_' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $lang;
                }
                $sql .= " AND language IN (" . implode(', ', $placeholders) . ")";
            }
            
            // Filtro por score mínimo
            $minScore = $filters['minScore'] ?? null;
            if (is_numeric($minScore)) {
                $sql .= " AND score >= :minScore";
                $params[':minScore'] = (float)$minScore;
            }
            
            // Filtro por fecha desde
            $dateFrom = $filters['dateFrom'] ?? null;
            if (is_string($dateFrom) && $dateFrom !== '') {
                $sql .= " AND published_at >= :dateFrom";
                $params[':dateFrom'] = $dateFrom;
            }
            
            // Filtro por fecha hasta
            $dateTo = $filters['dateTo'] ?? null;
            if (is_string($dateTo) && $dateTo !== '') {
                $sql .= " AND published_at <= :dateTo";
                $params[':dateTo'] = $dateTo;
            }
            
            // Ordenar
            $sortBy = $filters['sortBy'] ?? 'score';
            if ($sortBy === 'date') {
                $sql .= " ORDER BY published_at DESC";
            } else {
                $sql .= " ORDER BY score DESC";
            }
            
            // Límite (por defecto 50)
            $sql .= " LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $results = $stmt->fetchAll();

            // Convertir los resultados al formato esperado
            $articles = [];
            foreach ($results as $row) {
                $tags = json_decode($row['tags'] ?? '[]', true);
                if (!is_array($tags)) {
                    $tags = [];
                }

                $type = $row['type'] ?? 'article';
                
                // Construir resultado base
                $result = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'publishedAt' => $row['publishedAt'],
                    'processedAt' => $row['processedAt'] ?? '',
                    'score' => $row['score'] !== null ? (float)$row['score'] : 0.0,
                    'source' => $row['source'] ?? '',
                    'language' => $row['language'] ?? '',
                    'tags' => $tags,
                    'type' => $type
                ];
                
                // Agregar campos específicos según el tipo
                if ($type === 'news') {
                    $result['summary'] = $row['summary'] ?? $row['excerpt'] ?? '';
                    $result['url'] = $row['id']; // Para noticias, url es el id
                } else {
                    $result['excerpt'] = $row['excerpt'] ?? '';
                }

                $articles[] = $result;
            }

            return $articles;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Búsqueda usando Azure cuando hay query
     */
    private function searchAzure(string $query, array $filters): array
    {
        $azureSearchUrl = $this->getEnvVar('AZURE_SEARCH_URL');
        if ($azureSearchUrl === false || $azureSearchUrl === '') {
            throw new \RuntimeException('URL de búsqueda de Azure no configurada.');
        }

        // Construir URL base sin trailing slash
        $baseUrl = rtrim($azureSearchUrl, '/');
        
        // Construir parámetros de query
        $queryParams = [];
        $queryParams['q'] = $query;
        
        // Agregar parámetro k (límite) si es necesario
        $k = 40; // Por defecto 40
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $k = (int)$filters['limit'];
        }
        $queryParams['k'] = $k;
        
        // Construir URL completa con parámetros de query
        // Verificar si la URL base ya tiene parámetros de query (ej: ?code=xxx)
        // Si ya tiene '?', usar '&' para agregar más parámetros; si no, usar '?'
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
        $url = $baseUrl . $separator . http_build_query($queryParams);
        
        // Ejemplo: si baseUrl es "https://example.com/api?code=xxx"
        // y queryParams es ['q' => 'busqueda', 'k' => 40]
        // Resultado: "https://example.com/api?code=xxx&q=busqueda&k=40"

        // Realizar llamada HTTP GET a Azure
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Content-Type: application/json\r\nAccept: application/json",
                'timeout' => 30,
                'ignore_errors' => true // Para poder leer el código de estado HTTP
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        // Obtener código de estado HTTP y headers
        $httpCode = 0;
        $contentType = '';
        if (isset($http_response_header) && is_array($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
                $httpCode = (int)$matches[1];
            }
            
            // Buscar Content-Type en los headers
            foreach ($http_response_header as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $contentType = $header;
                    break;
                }
            }
        }
        
        // Verificar si la respuesta falló
        if ($response === false) {
            $error = error_get_last();
            $errorMessage = 'Error al llamar al servicio de búsqueda de Azure.';
            
            if ($httpCode > 0) {
                $errorMessage .= ' Código HTTP: ' . $httpCode;
            }
            
            if ($contentType !== '') {
                $errorMessage .= ' Content-Type: ' . $contentType;
            }
            
            if ($error !== null && $error['message'] !== '') {
                $errorMessage .= ' Detalle: ' . $error['message'];
            }
            
            throw new \RuntimeException($errorMessage);
        }
        
        // Verificar código de estado HTTP (incluso si file_get_contents no falló)
        if ($httpCode >= 400) {
            // Mostrar primeros caracteres de la respuesta para debugging
            $responsePreview = mb_substr($response, 0, 500);
            throw new \RuntimeException(
                'Error HTTP ' . $httpCode . ' del servicio de búsqueda de Azure. ' .
                'Respuesta: ' . $responsePreview . ($response !== $responsePreview ? '...' : '')
            );
        }

        // Verificar que la respuesta no esté vacía
        if (trim($response) === '') {
            throw new \RuntimeException('Respuesta vacía del servicio de búsqueda de Azure. Código HTTP: ' . $httpCode);
        }

        // Intentar decodificar JSON
        $data = json_decode($response, true);
        if (!is_array($data)) {
            $jsonError = json_last_error_msg();
            $jsonErrorCode = json_last_error();
            
            // Mostrar primeros caracteres de la respuesta para debugging
            $responsePreview = mb_substr($response, 0, 500);
            $errorMessage = 'Respuesta inválida del servicio de búsqueda de Azure. Error JSON: ' . $jsonError;
            
            if ($httpCode > 0) {
                $errorMessage .= ' (HTTP ' . $httpCode . ')';
            }
            
            if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
                $errorMessage .= ' Content-Type: ' . $contentType . ' (esperado: application/json)';
            }
            
            $errorMessage .= ' Respuesta recibida: ' . $responsePreview . ($response !== $responsePreview ? '...' : '');
            
            throw new \RuntimeException($errorMessage);
        }

        // Obtener resultados de Azure
        $azureResults = $data['resultados'] ?? [];
        if (empty($azureResults)) {
            return [];
        }

        // Filtrar resultados por score mínimo (>= 0.78)
        $minScore = 0.78;
        $azureResults = array_filter($azureResults, static function(array $result) use ($minScore): bool {
            $score = $result['score'] ?? 0.0;
            return is_numeric($score) && (float)$score >= $minScore;
        });

        if (empty($azureResults)) {
            return [];
        }

        // Extraer URLs de los resultados filtrados
        $urls = array_map(static fn(array $result): string => $result['url'] ?? '', $azureResults);
        $urls = array_filter($urls, static fn(string $url): bool => $url !== '');

        if (empty($urls)) {
            return [];
        }

        // Buscar los artículos en la base de datos usando las URLs
        return $this->getArticlesByUrls($urls, $filters);
    }

    /**
     * Obtiene artículos de la base de datos por sus URLs
     */
    private function getArticlesByUrls(array $urls, array $filters): array
    {
        // Obtener variables de entorno de la base de datos
        $host = $this->getEnvVar('DB_HOST') ?: 'localhost';
        $port = (int)($this->getEnvVar('DB_PORT') ?: 3306);
        $database = $this->getEnvVar('DB_DATABASE') ?: '';
        $username = $this->getEnvVar('DB_USERNAME') ?: '';
        $password = $this->getEnvVar('DB_PASSWORD') ?: '';

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Variables de entorno de base de datos no configuradas.');
        }

        // Crear conexión PDO
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        
        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Obtener filtros de idioma
            $languages = (array)($filters['languages'] ?? []);
            
            // Construir URLs para buscar en la BD
            // Las URLs en la BD tienen el sufijo #en o #es al final
            // Si hay filtro de idioma, buscar solo con esos sufijos
            // Si no hay filtro, buscar con cualquier sufijo de idioma
            $params = [];
            $sql = '';
            $urlsToSearch = []; // Para usar en ORDER BY después
            
            if (!empty($languages)) {
                // Si hay filtro de idioma, buscar solo con esos sufijos
                foreach ($urls as $url) {
                    // Remover cualquier sufijo existente
                    $urlBase = preg_replace('/#[\w-]+$/', '', $url);
                    // Agregar sufijo para cada idioma del filtro
                    foreach ($languages as $lang) {
                        $urlsToSearch[] = $urlBase . '#' . $lang;
                    }
                }
                
                // Construir consulta con IN
                $placeholders = [];
                foreach ($urlsToSearch as $i => $url) {
                    $key = ':url_' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $url;
                }
                
                if (empty($placeholders)) {
                    return [];
                }
                
                $sql = "SELECT 
                            url as id,
                            title,
                            excerpt,
                            summary,
                            published_at as publishedAt,
                            processed_at as processedAt,
                            score,
                            source,
                            language,
                            tags,
                            tipo as type
                        FROM items 
                        WHERE tipo IN ('article', 'news')
                        AND url IN (" . implode(', ', $placeholders) . ")";
            } else {
                // Si no hay filtro de idioma, buscar con cualquier sufijo de idioma
                // Usar LIKE para buscar la URL base con cualquier sufijo
                $likeConditions = [];
                foreach ($urls as $i => $url) {
                    // Remover cualquier sufijo existente
                    $urlBase = preg_replace('/#[\w-]+$/', '', $url);
                    $key = ':url_base_' . $i;
                    $likeConditions[] = "url LIKE " . $key;
                    $params[$key] = $urlBase . '#%';
                }
                
                if (empty($likeConditions)) {
                    return [];
                }
                
                $sql = "SELECT 
                            url as id,
                            title,
                            excerpt,
                            summary,
                            published_at as publishedAt,
                            processed_at as processedAt,
                            score,
                            source,
                            language,
                            tags,
                            tipo as type
                        FROM items 
                        WHERE tipo IN ('article', 'news')
                        AND (" . implode(' OR ', $likeConditions) . ")";
            }
            
            // Aplicar filtros adicionales
            $dementiaTypes = (array)($filters['dementiaTypes'] ?? []);
            if ($dementiaTypes !== []) {
                $conditions = [];
                foreach ($dementiaTypes as $i => $type) {
                    $key = ':dementia_json_' . $i;
                    // JSON_CONTAINS necesita un JSON válido, así que envuelvo el string en un array JSON
                    $conditions[] = "JSON_CONTAINS(tags, " . $key . ")";
                    $params[$key] = json_encode([$type]);
                }
                $sql .= " AND (" . implode(' OR ', $conditions) . ")";
            }
            
            // Filtro por tipo de documento (aplicado en SQL)
            // Mapear tipos del frontend a tipos de BD:
            // - article, paper, clinical-report -> article
            // - news -> news
            $documentTypes = (array)($filters['documentTypes'] ?? []);
            if ($documentTypes !== []) {
                $allowedTypes = [];
                foreach ($documentTypes as $docType) {
                    $docType = trim((string)$docType);
                    if ($docType === 'news') {
                        $allowedTypes[] = 'news';
                    } elseif (in_array($docType, ['article', 'paper', 'clinical-report'], true)) {
                        $allowedTypes[] = 'article';
                    }
                }
                
                // Eliminar duplicados
                $allowedTypes = array_unique($allowedTypes);
                
                // Aplicar filtro en SQL si hay tipos permitidos
                if (!empty($allowedTypes)) {
                    $docPlaceholders = [];
                    foreach ($allowedTypes as $i => $docType) {
                        $key = ':doc_type_' . $i;
                        $docPlaceholders[] = $key;
                        $params[$key] = $docType;
                    }
                    $sql .= " AND tipo IN (" . implode(', ', $docPlaceholders) . ")";
                } else {
                    // Si no hay tipos permitidos válidos, retornar vacío (no hay resultados)
                    return [];
                }
            }
            
            // Nota: No filtramos por language aquí porque ya lo hicimos en la búsqueda de URLs
            // Si hay filtro de idioma, ya buscamos solo URLs con ese sufijo
            // Si no hay filtro, buscamos todas las variantes de idioma
            
            $minScore = $filters['minScore'] ?? null;
            if (is_numeric($minScore)) {
                $sql .= " AND score >= :minScore";
                $params[':minScore'] = (float)$minScore;
            }
            
            $dateFrom = $filters['dateFrom'] ?? null;
            if (is_string($dateFrom) && $dateFrom !== '') {
                $sql .= " AND published_at >= :dateFrom";
                $params[':dateFrom'] = $dateFrom;
            }
            
            $dateTo = $filters['dateTo'] ?? null;
            if (is_string($dateTo) && $dateTo !== '') {
                $sql .= " AND published_at <= :dateTo";
                $params[':dateTo'] = $dateTo;
            }
            
            // Mantener el orden de las URLs de Azure (usando FIELD)
            // Si hay filtro de idioma, usar las URLs con sufijo; si no, usar las URLs base
            if (!empty($languages)) {
                // Ya tenemos $urlsToSearch con los sufijos
                $orderPlaceholders = [];
                foreach ($urlsToSearch as $i => $url) {
                    $key = ':order_url_' . $i;
                    $orderPlaceholders[] = $key;
                    $params[$key] = $url;
                }
                if (!empty($orderPlaceholders)) {
                    $sql .= " ORDER BY FIELD(url, " . implode(', ', $orderPlaceholders) . ")";
                }
            } else {
                // Sin filtro de idioma, ordenar por score y fecha
                $sortBy = $filters['sortBy'] ?? 'score';
                if ($sortBy === 'date') {
                    $sql .= " ORDER BY published_at DESC";
                } else {
                    $sql .= " ORDER BY score DESC";
                }
            }
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $results = $stmt->fetchAll();

            // Convertir los resultados al formato esperado
            $articles = [];
            foreach ($results as $row) {
                $tags = json_decode($row['tags'] ?? '[]', true);
                if (!is_array($tags)) {
                    $tags = [];
                }

                $type = $row['type'] ?? 'article';
                
                // Construir resultado base
                $result = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'publishedAt' => $row['publishedAt'],
                    'processedAt' => $row['processedAt'] ?? '',
                    'score' => $row['score'] !== null ? (float)$row['score'] : 0.0,
                    'source' => $row['source'] ?? '',
                    'language' => $row['language'] ?? '',
                    'tags' => $tags,
                    'type' => $type
                ];
                
                // Agregar campos específicos según el tipo
                if ($type === 'news') {
                    $result['summary'] = $row['summary'] ?? $row['excerpt'] ?? '';
                    $result['url'] = $row['id']; // Para noticias, url es el id
                } else {
                    $result['excerpt'] = $row['excerpt'] ?? '';
                }

                $articles[] = $result;
            }

            return $articles;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getArticle(string $url): ?array
    {
        // Obtener variables de entorno de la base de datos
        $host = $this->getEnvVar('DB_HOST') ?: 'localhost';
        $port = (int)($this->getEnvVar('DB_PORT') ?: 3306);
        $database = $this->getEnvVar('DB_DATABASE') ?: '';
        $username = $this->getEnvVar('DB_USERNAME') ?: '';
        $password = $this->getEnvVar('DB_PASSWORD') ?: '';

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Variables de entorno de base de datos no configuradas.');
        }

        // Crear conexión PDO
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        
        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Buscar el artículo por URL
            $sql = "SELECT 
                        url as id,
                        title,
                        excerpt,
                        summary,
                        published_at as publishedAt,
                        processed_at as processedAt,
                        score,
                        source,
                        language,
                        tags,
                        key_points as keyPoints
                    FROM items 
                    WHERE tipo = 'article'
                    AND url = :url
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':url', $url, \PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            // Parsear tags JSON
            $tags = json_decode($row['tags'] ?? '[]', true);
            if (!is_array($tags)) {
                $tags = [];
            }

            // Parsear key_points JSON
            $keyPoints = json_decode($row['keyPoints'] ?? '[]', true);
            if (!is_array($keyPoints)) {
                $keyPoints = [];
            }

            // Obtener artículos relacionados (mismos tags, excluyendo el actual)
            $related = $this->getRelatedArticles($pdo, $url, $tags);

            return [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => $row['excerpt'] ?? '',
                'summary' => $row['summary'] ?? '',
                'publishedAt' => $row['publishedAt'],
                'processedAt' => $row['processedAt'] ?? '',
                'score' => $row['score'] !== null ? (float)$row['score'] : 0.0,
                'source' => $row['source'] ?? '',
                'language' => $row['language'] ?? '',
                'tags' => $tags,
                'keyPoints' => $keyPoints,
                'related' => $related,
                'originalUrl' => $row['id']
            ];
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene artículos relacionados basados en tags compartidos
     */
    private function getRelatedArticles(\PDO $pdo, string $excludeUrl, array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        try {
            // Construir condiciones para buscar artículos con tags compartidos
            $conditions = [];
            $params = [':excludeUrl' => $excludeUrl];
            foreach ($tags as $i => $tag) {
                $key = ':tag_' . $i;
                $conditions[] = "JSON_CONTAINS(tags, " . $key . ")";
                $params[$key] = json_encode([$tag]);
            }

            $sql = "SELECT 
                        url as id,
                        title,
                        excerpt,
                        published_at as publishedAt,
                        processed_at as processedAt,
                        score,
                        source,
                        language,
                        tags
                    FROM items 
                    WHERE tipo = 'article'
                    AND url != :excludeUrl
                    AND (" . implode(' OR ', $conditions) . ")
                    ORDER BY score DESC, published_at DESC
                    LIMIT 4";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $results = $stmt->fetchAll();

            $related = [];
            foreach ($results as $row) {
                $articleTags = json_decode($row['tags'] ?? '[]', true);
                if (!is_array($articleTags)) {
                    $articleTags = [];
                }

                $related[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'] ?? '',
                    'publishedAt' => $row['publishedAt'],
                    'processedAt' => $row['processedAt'] ?? '',
                    'score' => $row['score'] !== null ? (float)$row['score'] : 0.0,
                    'source' => $row['source'] ?? '',
                    'language' => $row['language'] ?? '',
                    'tags' => $articleTags
                ];
            }

            return $related;
        } catch (\PDOException $e) {
            // Si hay error, retornar array vacío en lugar de fallar
            return [];
        }
    }

    public function getNews(?string $language = null, int $limit = 50): array
    {
        // Obtener variables de entorno de la base de datos
        $host = $this->getEnvVar('DB_HOST') ?: 'localhost';
        $port = (int)($this->getEnvVar('DB_PORT') ?: 3306);
        $database = $this->getEnvVar('DB_DATABASE') ?: '';
        $username = $this->getEnvVar('DB_USERNAME') ?: '';
        $password = $this->getEnvVar('DB_PASSWORD') ?: '';

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Variables de entorno de base de datos no configuradas.');
        }

        // Si no se especifica idioma, usar 'en' por defecto
        $lang = $language ?? 'en';

        // Crear conexión PDO
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        
        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Obtener las últimas noticias ordenadas por fecha de publicación (más recientes primero)
            // Las noticias están en la tabla items con tipo = 'news'
            // El campo language puede ser NULL para noticias, así que incluimos:
            // - Noticias con el idioma solicitado
            // - Noticias sin idioma específico (language IS NULL) solo si se solicita inglés (por defecto)
            if ($lang === 'en') {
                // Si es inglés (por defecto), incluir también noticias sin idioma específico
                $sql = "SELECT 
                            url as id,
                            title,
                            summary,
                            published_at as publishedAt,
                            url,
                            image_url as imageUrl,
                            tags
                    FROM items 
                    WHERE tipo = 'news'
                    AND (language = :language OR language IS NULL)
                    ORDER BY published_at DESC
                    LIMIT :limit";
            } else {
                // Para otros idiomas, solo mostrar noticias con ese idioma específico
                $sql = "SELECT 
                            url as id,
                            title,
                            summary,
                            published_at as publishedAt,
                            url,
                            image_url as imageUrl,
                            tags
                        FROM items 
                        WHERE tipo = 'news'
                        AND language = :language
                        ORDER BY published_at DESC
                        LIMIT :limit";
            }
            
            // Asegurar que el límite sea válido (entre 1 y 100)
            $limit = max(1, min(100, $limit));
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':language', $lang, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll();

            // Convertir los resultados al formato esperado
            $news = [];
            foreach ($results as $row) {
                // Parsear tags JSON
                $tags = json_decode($row['tags'] ?? '[]', true);
                if (!is_array($tags)) {
                    $tags = [];
                }

                $news[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'summary' => $row['summary'] ?? '',
                    'publishedAt' => $row['publishedAt'],
                    'url' => $row['url'],
                    'imageUrl' => $row['imageUrl'] ?? null,
                    'tags' => $tags
                ];
            }

            return $news;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getMetrics(): array
    {
        // Obtener variables de entorno de la base de datos
        $host = $this->getEnvVar('DB_HOST') ?: 'localhost';
        $port = (int)($this->getEnvVar('DB_PORT') ?: 3306);
        $database = $this->getEnvVar('DB_DATABASE') ?: '';
        $username = $this->getEnvVar('DB_USERNAME') ?: '';
        $password = $this->getEnvVar('DB_PASSWORD') ?: '';

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Variables de entorno de base de datos no configuradas.');
        }

        // Crear conexión PDO
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        
        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Consultar la tabla estadisticas (solo hay un registro con id=1)
            $stmt = $pdo->prepare('SELECT sources, articles, updated_at FROM estadisticas WHERE id = 1 LIMIT 1');
            $stmt->execute();
            $row = $stmt->fetch();

            if ($row === false) {
                // Si no hay datos, devolver valores por defecto
                return [
                    'sources' => 0,
                    'articles' => 0,
                    'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
                ];
            }

            // Convertir el timestamp de la BD a formato ISO 8601
            $updatedAt = new \DateTimeImmutable($row['updated_at']);
            
            return [
                'sources' => (int)$row['sources'],
                'articles' => (int)$row['articles'],
                'updatedAt' => $updatedAt->format(\DateTimeInterface::ATOM)
            ];
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene una variable de entorno, intentando múltiples métodos en orden de prioridad.
     * Compatible con SetEnv de Apache .htaccess y archivo de configuración PHP como fallback
     */
    private function getEnvVar(string $name): string|false
    {
        // 1. Intentar apache_getenv() (solo disponible cuando PHP es módulo de Apache)
        if (function_exists('apache_getenv')) {
            $value = apache_getenv($name);
            if ($value !== false) {
                return $value;
            }
        }

        // 2. Intentar $_SERVER (donde Apache SetEnv normalmente coloca las variables)
        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return $_SERVER[$name];
        }

        // 3. Intentar getenv() como fallback
        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }

        // 4. Intentar leer del archivo de configuración PHP (fallback cuando SetEnv no funciona)
        static $config = null;
        if ($config === null) {
            $configFile = __DIR__ . '/../../config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            } else {
                $config = [];
            }
        }

        if (isset($config[$name])) {
            return (string)$config[$name];
        }

        return false;
    }

    public function getLatestArticles(int $limit = 4, ?string $language = null): array
    {
        // Obtener variables de entorno de la base de datos
        $host = $this->getEnvVar('DB_HOST') ?: 'localhost';
        $port = (int)($this->getEnvVar('DB_PORT') ?: 3306);
        $database = $this->getEnvVar('DB_DATABASE') ?: '';
        $username = $this->getEnvVar('DB_USERNAME') ?: '';
        $password = $this->getEnvVar('DB_PASSWORD') ?: '';

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Variables de entorno de base de datos no configuradas.');
        }

        // Si no se especifica idioma, usar 'en' por defecto
        $lang = $language ?? 'en';

        // Asegurar que el límite sea válido (entre 1 y 100)
        $limit = max(1, min(100, $limit));

        // Crear conexión PDO
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        
        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Obtener los últimos artículos ordenados por fecha de publicación (más recientes primero)
            // Los artículos están en la tabla items con tipo = 'article'
            // Filtrar por idioma si se especifica
            $sql = "SELECT 
                        url as id,
                        title,
                        excerpt,
                        published_at as publishedAt,
                        processed_at as processedAt,
                        score,
                        source,
                        language,
                        tags
                    FROM items 
                    WHERE tipo = 'article'
                    AND language = :language
                    ORDER BY published_at DESC
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':language', $lang, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll();

            // Convertir los resultados al formato esperado
            $articles = [];
            foreach ($results as $row) {
                // Parsear tags JSON
                $tags = json_decode($row['tags'] ?? '[]', true);
                if (!is_array($tags)) {
                    $tags = [];
                }

                $articles[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'] ?? '',
                    'publishedAt' => $row['publishedAt'],
                    'processedAt' => $row['processedAt'] ?? '',
                    'score' => $row['score'] !== null ? (float)$row['score'] : 0.0,
                    'source' => $row['source'] ?? '',
                    'language' => $row['language'] ?? '',
                    'tags' => $tags
                ];
            }

            return $articles;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
        }
    }
}


