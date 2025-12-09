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
     * @return array<string, mixed>|null
     */
    public function getArticle(string $id): ?array;

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
}


