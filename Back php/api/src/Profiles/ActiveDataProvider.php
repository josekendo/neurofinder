<?php
declare(strict_types=1);

namespace NeuroFinder\Profiles;

use NeuroFinder\Contracts\DataProviderInterface;

final class ActiveDataProvider implements DataProviderInterface
{
    public function search(array $request): array
    {
        throw new \RuntimeException('El perfil active aún no está implementado.');
    }

    public function getArticle(string $id): ?array
    {
        throw new \RuntimeException('El perfil active aún no está implementado.');
    }

    public function getNews(): array
    {
        throw new \RuntimeException('El perfil active aún no está implementado.');
    }

    public function getMetrics(): array
    {
        throw new \RuntimeException('El perfil active aún no está implementado.');
    }
}


