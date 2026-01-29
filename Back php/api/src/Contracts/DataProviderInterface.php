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

    /**
     * Obtiene noticias paginadas por idioma
     * @param string|null $language Idioma para filtrar las noticias (por defecto 'en' si es null)
     * @param int $page Número de página (empezando en 1)
     * @param int $pageSize Tamaño de página (número de elementos por página)
     * @return array<string, mixed> Array con 'data' (noticias), 'pagination' (info de paginación)
     */
    public function getNewsPaginated(?string $language = null, int $page = 1, int $pageSize = 20): array;

    /**
     * Obtiene artículos paginados por idioma
     * @param string|null $language Idioma para filtrar los artículos (por defecto 'en' si es null)
     * @param int $page Número de página (empezando en 1)
     * @param int $pageSize Tamaño de página (número de elementos por página)
     * @return array<string, mixed> Array con 'data' (artículos), 'pagination' (info de paginación)
     */
    public function getArticlesPaginated(?string $language = null, int $page = 1, int $pageSize = 20): array;
}


