-- ============================================
-- Esquema de Base de Datos para NeuroFinder
-- Base de datos: MariaDB
-- ============================================

-- Crear base de datos (opcional, descomentar si es necesario)
-- CREATE DATABASE IF NOT EXISTS neurofinder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE neurofinder;

-- ============================================
-- Tabla: items
-- Almacena tanto noticias como artículos científicos
-- ============================================
CREATE TABLE IF NOT EXISTS items (
    url VARCHAR(767) NOT NULL PRIMARY KEY COMMENT 'URL única del item (clave primaria, máximo 767 caracteres por límite de InnoDB)',
    tipo ENUM('article', 'news') NOT NULL COMMENT 'Tipo de item: article (artículo científico) o news (noticia)',
    
    -- Campos comunes
    title VARCHAR(500) NOT NULL COMMENT 'Título del item',
    published_at DATE NOT NULL COMMENT 'Fecha de publicación original',
    
    -- Campos específicos de artículos científicos
    excerpt TEXT NULL COMMENT 'Resumen breve (solo para artículos)',
    processed_at DATE NULL COMMENT 'Fecha de procesamiento (solo para artículos)',
    score DECIMAL(5,2) NULL COMMENT 'Puntuación de relevancia 0.00-1.00 (solo para artículos)',
    source VARCHAR(255) NULL COMMENT 'Fuente/publicación (solo para artículos)',
    language VARCHAR(10) NULL COMMENT 'Idioma del contenido (solo para artículos)',
    summary TEXT NULL COMMENT 'Resumen completo (artículos) o summary (noticias)',
    key_points JSON NULL COMMENT 'Puntos clave en formato JSON array (solo para artículos)',
    
    -- Campos específicos de noticias
    image_url VARCHAR(1000) NULL COMMENT 'URL de imagen asociada (solo para noticias, opcional)',
    
    -- Metadatos
    tags JSON NOT NULL COMMENT 'Etiquetas en formato JSON array',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
    
    -- Índices para optimizar búsquedas
    INDEX idx_tipo (tipo),
    INDEX idx_published_at (published_at),
    INDEX idx_processed_at (processed_at),
    INDEX idx_score (score),
    INDEX idx_language (language),
    INDEX idx_source (source),
    FULLTEXT INDEX idx_title_excerpt (title, excerpt, summary) COMMENT 'Índice de texto completo para búsquedas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal que almacena artículos científicos y noticias';

-- ============================================
-- Tabla: estadisticas
-- Almacena métricas generales del sistema
-- Solo debe contener un único registro
-- ============================================
CREATE TABLE IF NOT EXISTS estadisticas (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1 PRIMARY KEY COMMENT 'Identificador único (siempre 1)',
    sources INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número total de fuentes',
    articles INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número total de artículos procesados',
    updated_at TIMESTAMP NOT NULL COMMENT 'Fecha y hora de última actualización',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
    
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de estadísticas generales del sistema (solo un registro)';

-- ============================================
-- Tabla: reportes
-- Almacena reportes de usuarios sobre items
-- ============================================
CREATE TABLE IF NOT EXISTS reportes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del reporte',
    item_url VARCHAR(767) NULL COMMENT 'URL del item reportado (puede no existir en la tabla items, máximo 767 caracteres)',
    comentario TEXT NOT NULL COMMENT 'Comentario del usuario sobre el item',
    correo VARCHAR(255) NOT NULL COMMENT 'Correo electrónico del usuario que reporta',
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de publicación del reporte',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
    
    -- Índices para optimizar búsquedas
    INDEX idx_item_url (item_url),
    INDEX idx_correo (correo),
    INDEX idx_fecha_publicacion (fecha_publicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de reportes de usuarios sobre items';

-- ============================================
-- Inicialización de datos
-- ============================================

-- Insertar registro inicial de estadísticas
-- Fuentes totales: 0, Artículos procesados: 0, Fecha: 1 de enero de 2025 a las 9:00
INSERT INTO estadisticas (id, sources, articles, updated_at) 
VALUES (1, 0, 0, '2025-01-01 09:00:00')
ON DUPLICATE KEY UPDATE 
    sources = 0,
    articles = 0,
    updated_at = '2025-01-01 09:00:00';

-- ============================================
-- Notas sobre el diseño:
-- ============================================
-- 1. La tabla 'items' unifica noticias y artículos usando el campo 'tipo'
-- 2. La clave primaria es 'url' (máximo 767 caracteres por límite de InnoDB con utf8mb4)
--    Si una URL excede este límite, considerar usar un hash (SHA256) de la URL como clave primaria
-- 3. Los campos NULL permiten que un item solo tenga los campos de su tipo
-- 4. Los tags se almacenan como JSON para flexibilidad
-- 5. Se usa FULLTEXT INDEX para búsquedas de texto completo en MariaDB
-- 6. Los índices optimizan las consultas más comunes (filtros por tipo, fecha, score, etc.)
-- 7. La tabla estadisticas tiene un solo registro que se actualiza periódicamente
-- 8. La tabla reportes permite que item_url sea NULL o contenga URLs que no existen en items
-- 9. No se usa clave foránea en reportes para permitir reportar cualquier URL, exista o no en items
-- ============================================

