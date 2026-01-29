<?php
declare(strict_types=1);

namespace NeuroFinder\Profiles;

use NeuroFinder\Contracts\DataProviderInterface;

final class MockDataProvider implements DataProviderInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $articles = [
        [
            'id' => 'alzheimers-2025-001',
            'title' => 'Biomarcadores sanguíneos para diagnóstico temprano de Alzheimer',
            'excerpt' => 'Estudio multicéntrico que valida nuevos biomarcadores plasmáticos para detectar Alzheimer previo a la aparición de síntomas severos.',
            'publishedAt' => '2025-07-18',
            'processedAt' => '2025-07-21',
            'score' => 0.92,
            'source' => 'Journal of NeuroScience',
            'language' => 'es',
            'tags' => ['tnm.alzheimer', 'biomarcadores', 'diagnóstico-temprano']
        ],
        [
            'id' => 'vascular-2025-014',
            'title' => 'Intervenciones combinadas para demencia vascular leve',
            'excerpt' => 'Ensayo clínico que evalúa la eficacia de un protocolo de rehabilitación cognitiva y actividad física en pacientes con demencia vascular leve.',
            'publishedAt' => '2025-06-09',
            'processedAt' => '2025-06-10',
            'score' => 0.85,
            'source' => 'European Neurology Review',
            'language' => 'en',
            'tags' => ['tnm.vascular', 'rehabilitación', 'actividad-física']
        ]
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $news = [
        [
            'id' => 'news-001',
            'title' => 'La OMS actualiza recomendaciones sobre demencias mixtas',
            'summary' => 'El nuevo informe incluye pautas sobre diagnóstico diferencial y seguimiento integral de pacientes con demencia mixta.',
            'publishedAt' => '2025-07-23',
            'url' => 'https://www.who.int/news/dementia-mixed',
            'tags' => ['oms', 'guías-clínicas', 'demencia-mixta']
        ],
        [
            'id' => 'news-002',
            'title' => 'España lanza programa piloto de seguimiento domiciliario con IA',
            'summary' => 'La iniciativa combina sensores domésticos y análisis semántico de notas clínicas para anticipar crisis conductuales.',
            'publishedAt' => '2025-07-17',
            'url' => 'https://www.ceafa.es/noticias/seguimiento-domotico-ia',
            'tags' => ['españa', 'seguimiento', 'ia']
        ]
    ];

    /**
     * @var array<string, mixed>
     */
    private array $metrics = [
        'sources' => 128,
        'articles' => 6423,
        'updatedAt' => '2025-07-23T12:45:00Z'
    ];

    public function search(array $request): array
    {
        $query = strtolower(trim((string)($request['query'] ?? '')));
        $filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];

        $results = array_filter($this->articles, static function (array $article) use ($query, $filters): bool {
            if ($query !== '') {
                $haystack = strtolower($article['title'] . ' ' . $article['excerpt']);
                if (strpos($haystack, $query) === false) {
                    return false;
                }
            }

            $dementiaTypes = (array)($filters['dementiaTypes'] ?? []);
            if ($dementiaTypes !== []) {
                $matchesType = (bool)array_intersect($dementiaTypes, $article['tags']);
                if (!$matchesType) {
                    return false;
                }
            }

            $languages = (array)($filters['languages'] ?? []);
            if ($languages !== [] && !in_array($article['language'], $languages, true)) {
                return false;
            }

            $minScore = $filters['minScore'] ?? null;
            if (is_numeric($minScore) && $article['score'] < (float)$minScore) {
                return false;
            }

            $dateFrom = $filters['dateFrom'] ?? null;
            if (is_string($dateFrom) && $dateFrom !== '' && $article['publishedAt'] < $dateFrom) {
                return false;
            }

            $dateTo = $filters['dateTo'] ?? null;
            if (is_string($dateTo) && $dateTo !== '' && $article['publishedAt'] > $dateTo) {
                return false;
            }

            return true;
        });

        $sortBy = $filters['sortBy'] ?? 'score';
        $results = array_values($results);

        if ($sortBy === 'date') {
            usort($results, static fn(array $a, array $b): int => strcmp($b['publishedAt'], $a['publishedAt']));
        } else {
            usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        }

        return $results;
    }

    public function getArticle(string $url): ?array
    {
        foreach ($this->articles as $article) {
            if ($article['id'] === $url) {
                return $article + [
                    'summary' => 'La investigación demuestra que una combinación de proteínas plasmáticas permite anticipar el riesgo de Alzheimer con una precisión del 92%, reduciendo la dependencia de resonancias magnéticas.',
                    'keyPoints' => [
                        'Cohorte de 1.200 pacientes monitorizados durante 24 meses.',
                        'Comparativa con biomarcadores tradicionales muestra mejora del 18%.',
                        'Valida protocolo de extracción no invasivo aplicable en atención primaria.'
                    ],
                    'related' => array_values(array_filter($this->articles, static fn(array $item): bool => $item['id'] !== $url)),
                    'originalUrl' => $url
                ];
            }
        }

        return null;
    }

    public function getNews(?string $language = null, int $limit = 50): array
    {
        // Si no se especifica idioma, usar 'en' por defecto
        $lang = $language ?? 'en';
        
        // Filtrar noticias por idioma (en el mock, todas están en español por defecto)
        // Por ahora retornamos todas las noticias, pero se puede filtrar si se añade campo language
        
        // Aplicar límite
        $limit = max(1, min(100, $limit));
        return array_slice($this->news, 0, $limit);
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getLatestArticles(int $limit = 4, ?string $language = null): array
    {
        // Filtrar por idioma si se especifica
        $lang = $language ?? 'en';
        $filtered = array_filter($this->articles, static fn(array $article): bool => 
            ($article['language'] ?? 'en') === $lang
        );
        
        // Ordenar por fecha de publicación (más recientes primero) y retornar los últimos N
        $sorted = array_values($filtered);
        usort($sorted, static fn(array $a, array $b): int => strcmp($b['publishedAt'], $a['publishedAt']));
        $limit = max(1, min(100, $limit));
        return array_slice($sorted, 0, $limit);
    }

    public function getNewsPaginated(?string $language = null, int $page = 1, int $pageSize = 20): array
    {
        $lang = $language ?? 'en';
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        
        // Filtrar por idioma (en el mock, todas están en español por defecto)
        $filtered = $this->news;
        
        $total = count($filtered);
        $offset = ($page - 1) * $pageSize;
        $paginated = array_slice($filtered, $offset, $pageSize);
        
        $totalPages = (int)ceil($total / $pageSize);
        
        return [
            'data' => $paginated,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ]
        ];
    }

    public function getArticlesPaginated(?string $language = null, int $page = 1, int $pageSize = 20): array
    {
        $lang = $language ?? 'en';
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        
        // Filtrar por idioma
        $filtered = array_filter($this->articles, static fn(array $article): bool => 
            ($article['language'] ?? 'en') === $lang
        );
        
        // Ordenar por fecha de publicación (más recientes primero)
        $sorted = array_values($filtered);
        usort($sorted, static fn(array $a, array $b): int => strcmp($b['publishedAt'], $a['publishedAt']));
        
        $total = count($sorted);
        $offset = ($page - 1) * $pageSize;
        $paginated = array_slice($sorted, $offset, $pageSize);
        
        $totalPages = (int)ceil($total / $pageSize);
        
        return [
            'data' => $paginated,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $totalPages,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ]
        ];
    }
}


