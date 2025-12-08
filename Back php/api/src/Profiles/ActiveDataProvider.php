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
}


