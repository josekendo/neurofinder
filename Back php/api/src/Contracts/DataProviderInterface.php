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
     * @return array<int, array<string, mixed>>
     */
    public function getNews(): array;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;
}


