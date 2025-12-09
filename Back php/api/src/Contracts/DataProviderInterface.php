<?php
declare(strict_types=1);

namespace NeuroFinder\Contracts;

interface DataProviderInterface
{
    /**
     * @param array<string,mixed> $request
     * @return array<int, array<string, mixed>>
     */
    public function search(array $request): array;

    /**
     * @param string $url URL del artículo (es el identificador único)
     * @return array<string, mixed>|null
     */
    public function getArticle(string $url): ?array;

    /**
     * @param string|null $language Idioma para filtrar las noticias (por defecto 'en' si es null)
     * @param int $limit Número máximo de noticias a retornar (por defecto 50)
     * @return array<int, array<string, mixed>>
     */
    public function getNews(?string $language = null, int $limit = 50): array;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;

    /**
     * @param int $limit Número máximo de artículos a retornar (por defecto 4)
     * @param string|null $language Idioma para filtrar los artículos (por defecto 'en' si es null)
     * @return array<int, array<string, mixed>>
     */
    public function getLatestArticles(int $limit = 4, ?string $language = null): array;
}


