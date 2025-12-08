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
        $this->profile = $profile ?? $this->getEnvVar('APP_PROFILE') ?: 'mock';
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
            $configFile = __DIR__ . '/../config.php';
            $configExists = file_exists($configFile);
            $config = $configExists ? require $configFile : [];
            
            $debug = [
                'status' => 'ok',
                'profile' => $this->profile,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'debug' => [
                    'apache_getenv_available' => function_exists('apache_getenv'),
                    'apache_getenv_APP_PROFILE' => function_exists('apache_getenv') ? apache_getenv('APP_PROFILE') : 'N/A',
                    '_SERVER_APP_PROFILE' => $_SERVER['APP_PROFILE'] ?? 'NOT_SET',
                    'getenv_APP_PROFILE' => getenv('APP_PROFILE') ?: 'NOT_SET',
                    'getEnvVar_result' => $this->getEnvVar('APP_PROFILE'),
                    'config_file_exists' => $configExists,
                    'config_file_path' => $configFile,
                    'config_APP_PROFILE' => $config['APP_PROFILE'] ?? 'NOT_SET',
                    'all_SERVER_vars_with_APP' => array_filter($_SERVER, fn($k) => str_starts_with($k, 'APP_'), ARRAY_FILTER_USE_KEY),
                    'all_SERVER_vars_with_DB' => array_filter($_SERVER, fn($k) => str_starts_with($k, 'DB_'), ARRAY_FILTER_USE_KEY),
                ]
            ];
            return Response::ok($debug);
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
        $envBasePath = $this->getEnvVar('APP_BASE_PATH');
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
            $configFile = __DIR__ . '/../config.php';
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
}


