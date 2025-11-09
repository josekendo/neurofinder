<?php
declare(strict_types=1);

namespace NeuroFinder;

use NeuroFinder\Contracts\DataProviderInterface;
use NeuroFinder\Http\Response;
use NeuroFinder\Profiles\ActiveDataProvider;
use NeuroFinder\Profiles\MockDataProvider;

final class Application
{
    private DataProviderInterface $provider;
    private string $profile;
    private string $basePath;

    public function __construct(
        ?string $profile = null,
        ?DataProviderInterface $provider = null,
        ?string $basePath = null
    ) {
        $this->profile = $profile ?? getenv('APP_PROFILE') ?: 'mock';
        $this->basePath = $basePath ?? $this->detectBasePath();
        $this->provider = $provider ?? $this->resolveProvider($this->profile);
    }

    public function run(): void
    {
        $this->applyCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            Response::noContent()->send();
            return;
        }

        try {
            $response = $this->dispatch($_SERVER['REQUEST_METHOD'], $this->cleanPath($_SERVER['REQUEST_URI'] ?? '/'));
        } catch (\InvalidArgumentException $exception) {
            Response::badRequest($exception->getMessage())->send();
            return;
        } catch (\RuntimeException $exception) {
            Response::serverError($exception->getMessage())->send();
            return;
        }

        $response->send();
    }

    private function dispatch(string $method, string $path): Response
    {
        if ($method === 'GET' && $path === '/health') {
            return Response::ok([
                'status' => 'ok',
                'profile' => $this->profile,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ]);
        }

        if ($method === 'GET' && $path === '/metrics') {
            return Response::ok($this->provider->getMetrics());
        }

        if ($method === 'GET' && $path === '/news/latest') {
            return Response::ok($this->provider->getNews());
        }

        if ($method === 'POST' && $path === '/search') {
            $payload = $this->getJsonBody();
            return Response::ok($this->provider->search($payload));
        }

        if ($method === 'GET' && preg_match('#^/articles/(?P<id>[A-Za-z0-9_\-]+)$#', $path, $matches) === 1) {
            $article = $this->provider->getArticle($matches['id']);
            if ($article === null) {
                return Response::notFound('Artículo no encontrado');
            }

            return Response::ok($article);
        }

        return Response::notFound('Ruta no encontrada');
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('El cuerpo de la petición debe ser un objeto JSON.');
        }

        return $decoded;
    }

    private function resolveProvider(string $profile): DataProviderInterface
    {
        return match (strtolower($profile)) {
            'active' => new ActiveDataProvider(),
            default => new MockDataProvider()
        };
    }

    private function cleanPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null || $path === false) {
            return '/';
        }

        $normalized = rtrim($path, '/') ?: '/';

        if ($this->basePath !== '' && str_starts_with($normalized, $this->basePath)) {
            $normalized = substr($normalized, strlen($this->basePath)) ?: '/';
        }

        return $normalized === '' ? '/' : $normalized;
    }

    private function applyCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Content-Type: application/json; charset=utf-8');
    }

    private function detectBasePath(): string
    {
        $envBasePath = getenv('APP_BASE_PATH');
        if (is_string($envBasePath) && $envBasePath !== '') {
            return '/' . trim($envBasePath, '/');
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $directory = trim(str_replace('\\', '/', dirname($script)), '/');

        if ($directory === '') {
            return '';
        }

        if (str_ends_with($directory, '/public')) {
            $directory = substr($directory, 0, -7); // elimina '/public'
        }

        return $directory === '' ? '' : '/' . ltrim($directory, '/');
    }
}


