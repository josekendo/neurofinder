<?php
declare(strict_types=1);

namespace NeuroFinder\Services;

/**
 * Servicio para gestionar las puntuaciones de fiabilidad de las fuentes.
 * 
 * Busca las puntuaciones en un archivo JSON y normaliza las fuentes
 * para encontrar coincidencias exactas.
 */
final class SourceReliabilityService
{
    private const DEFAULT_SCORE = 0.1;
    private const MAX_SCORE = 1.0;
    private const MIN_SCORE = 0.0;
    private const JSON_FILE = __DIR__ . '/../../data/sources_reliability.json';

    /**
     * @var array<string, array{original: string, score: float|null}>|null
     */
    private ?array $sourcesData = null;

    /**
     * Obtiene la puntuación de fiabilidad para una fuente.
     * 
     * @param string|null $source La fuente original (puede ser null)
     * @return float Puntuación entre 0.0 y 1.0 (0.1 por defecto si no se encuentra)
     */
    public function getReliabilityScore(?string $source): float
    {
        // Si la fuente es null o vacía, retornar score por defecto
        if ($source === null || trim($source) === '') {
            return self::DEFAULT_SCORE;
        }

        // Normalizar la fuente
        $normalized = $this->normalizeSource($source);

        // Si está vacía después de normalizar, retornar score por defecto
        if ($normalized === '') {
            return self::DEFAULT_SCORE;
        }

        // Cargar datos si no están cargados
        if ($this->sourcesData === null) {
            $this->loadSourcesData();
        }

        // Buscar en los datos cargados
        if (isset($this->sourcesData[$normalized])) {
            $score = $this->sourcesData[$normalized]['score'] ?? null;
            
            if ($score !== null && is_numeric($score)) {
                $score = (float)$score;
                // Validar que el score esté en el rango válido
                if ($score >= self::MIN_SCORE && $score <= self::MAX_SCORE) {
                    return $score;
                }
            }
        }

        // Si no se encuentra o no tiene score válido, retornar por defecto
        return self::DEFAULT_SCORE;
    }

    /**
     * Normaliza una fuente: convierte a minúsculas, elimina espacios extra
     */
    private function normalizeSource(string $source): string
    {
        // Convertir a minúsculas
        $normalized = strtolower(trim($source));
        
        // Eliminar múltiples espacios en blanco
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Eliminar caracteres especiales al inicio/final
        $normalized = trim($normalized, ".,;:!?()[]{}");
        
        return $normalized;
    }

    /**
     * Carga los datos de fuentes desde el archivo JSON
     */
    private function loadSourcesData(): void
    {
        $this->sourcesData = [];

        if (!file_exists(self::JSON_FILE)) {
            // Si el archivo no existe, simplemente usar array vacío
            // Esto hará que todas las fuentes usen el score por defecto
            return;
        }

        $jsonContent = file_get_contents(self::JSON_FILE);
        if ($jsonContent === false) {
            return;
        }

        $data = json_decode($jsonContent, true);
        
        if (!is_array($data)) {
            return;
        }

        // Cargar datos, asegurándose de que el formato sea correcto
        foreach ($data as $normalized => $sourceData) {
            if (is_array($sourceData)) {
                // Formato nuevo: { "fuente": { "original": "...", "score": 0.9 } }
                $this->sourcesData[$normalized] = [
                    'original' => $sourceData['original'] ?? $normalized,
                    'score' => isset($sourceData['score']) && is_numeric($sourceData['score']) 
                        ? (float)$sourceData['score'] 
                        : null
                ];
            } elseif (is_numeric($sourceData)) {
                // Formato antiguo: { "fuente": 0.9 } (compatibilidad)
                $this->sourcesData[$normalized] = [
                    'original' => $normalized,
                    'score' => (float)$sourceData
                ];
            }
        }
    }
}
