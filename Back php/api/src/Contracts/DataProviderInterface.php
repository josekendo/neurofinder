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
     * @return array<int, array<string, mixed>>
     */
    public function getNews(?string $language = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;
}


