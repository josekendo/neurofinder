# Backend PHP - API REST

El backend PHP actúa como **orquestador** entre el frontend Angular y los servicios de Azure Functions. Su función principal es exponer una API REST limpia y sencilla que el frontend consume, mientras gestiona internamente las conexiones con los servicios de backend.

## ¿Qué hace?

Es una API REST minimalista escrita en PHP 8.2 puro (sin frameworks pesados) que:

- **Expone endpoints públicos** para búsqueda, artículos, noticias y métricas
- **Gestiona la lógica de negocio** delegando en proveedores de datos según el perfil configurado
- **Aplica CORS y manejo de errores** de forma centralizada
- **Soporta dos modos de operación**:
  - **Modo `mock`**: Devuelve datos simulados para desarrollo y testing
  - **Modo `active`**: Conecta con Azure Functions y base de datos MySQL/MariaDB real

## ¿Cómo se comunica?

### Con el Frontend Angular

El frontend realiza peticiones HTTP REST a esta API. Todas las respuestas son JSON y el backend aplica CORS automáticamente para permitir peticiones desde cualquier dominio (`Access-Control-Allow-Origin: *`).

### Con Azure Functions (modo `active`)

En modo `active`, cuando hay una query de búsqueda, el backend llama al servicio de búsqueda de Azure Functions (`AZURE_SEARCH_URL`) para obtener resultados semánticos. Si Azure no devuelve resultados o falla, el sistema hace fallback automático a búsqueda local en la base de datos usando LIKE.

### Con la Base de Datos

En modo `active`, el backend se conecta a MySQL/MariaDB para:
- **Búsqueda local**: Cuando no hay query o Azure falla, busca directamente en la tabla `items`
- **Almacenar reportes**: Los reportes de usuarios se guardan en la tabla `reportes`
- **Consultar artículos y noticias**: Obtiene datos de la tabla `items` filtrados por tipo (`article` o `news`)
- **Gestionar métricas**: Lee estadísticas de la tabla `estadisticas`
- **Artículos relacionados**: Calcula artículos relacionados basándose en tags compartidos usando `JSON_CONTAINS`

**Curiosidad técnica**: Las URLs en la base de datos tienen sufijos de idioma (ej: `url#en`, `url#es`). El sistema maneja esto automáticamente cuando hay filtros de idioma, buscando solo las variantes correspondientes.

## Estructura

```
api/
├── index.php                 # Punto de entrada
├── config.php                # Configuración (variables de entorno como fallback)
├── openapi.yaml              # Documentación OpenAPI de la API
├── .htaccess                 # Rewrite rules para Apache
├── src/
│   ├── Application.php       # Router principal y lógica HTTP
│   ├── bootstrap.php         # Autoloader PSR-4 simple
│   ├── Http/
│   │   └── Response.php      # Utilidades para respuestas HTTP (JSON, HTML, raw)
│   ├── Contracts/
│   │   └── DataProviderInterface.php  # Contrato para proveedores de datos
│   ├── Profiles/
│   │   ├── MockDataProvider.php       # Datos simulados en memoria
│   │   └── ActiveDataProvider.php     # Conexión real con BD y Azure Functions
│   └── Services/
│       └── SourceReliabilityService.php  # Gestión de scores de fiabilidad de fuentes
└── scripts/                  # Scripts de utilidad
    └── generate_sources_json.php  # Genera JSON de fuentes desde la BD
```

**Curiosidad técnica**: El sistema de configuración lee variables de entorno en este orden de prioridad:
1. `apache_getenv()` (si PHP es módulo de Apache)
2. `$_SERVER` (donde Apache SetEnv coloca las variables)
3. `getenv()` (variables de entorno del sistema)
4. `config.php` (fallback cuando SetEnv no funciona, útil para PHP-FPM)

## Puesta en marcha

```bash
cd "Back php/api"
php -S localhost:8080 index.php
```

La API estará disponible en `http://localhost:8080`. El perfil por defecto es `mock`, pero puedes cambiarlo definiendo la variable de entorno `APP_PROFILE=active`.

### Endpoints disponibles

- `GET /health` - Estado del servicio (incluye verificación de BD y servicios)
- `GET /metrics` - Métricas agregadas (fuentes y artículos totales)
- `GET /news/latest?language={lang}&limit={n}` - Noticias recientes (filtro por idioma, límite 1-100)
- `GET /articles/latest?language={lang}&limit={n}` - Últimos artículos (filtro por idioma, límite 1-100)
- `POST /search` - Búsqueda de artículos (con query opcional y filtros avanzados)
- `POST /articles` - Detalle de artículo (por URL)
- `POST /report` - Reportar contenido (guarda en BD y envía email)
- `GET /docs` - Documentación interactiva (Swagger UI)
- `GET /openapi.yaml` - Especificación OpenAPI

**Curiosidad técnica**: El endpoint `/search` implementa una estrategia híbrida:
- Si hay `query`: Primero intenta búsqueda semántica en Azure Functions, filtra resultados con score >= 0.78, luego busca esos artículos en la BD. Si Azure falla o no devuelve resultados, hace fallback a búsqueda local con LIKE.
- Si no hay `query`: Búsqueda directa en BD aplicando solo los filtros (tipos de demencia, idiomas, fechas, etc.)

**Sistema de scores**: Los artículos tienen un score de fiabilidad que se obtiene en este orden:
1. Score almacenado en la BD (columna `score`)
2. Si no existe, se busca en `data/sources_reliability.json` usando `SourceReliabilityService`
3. Si tampoco existe, se usa 0.1 por defecto

El servicio `SourceReliabilityService` normaliza las fuentes (minúsculas, trim, elimina caracteres especiales) para hacer matching exacto con el JSON.

## Características implementadas

### Modo Mock (`MockDataProvider`)
- Datos simulados en memoria para desarrollo
- Búsqueda con filtros básicos (query, tipos de demencia, idiomas, fechas, score mínimo)
- Ordenamiento por score o fecha
- Soporte para artículos y noticias

### Modo Active (`ActiveDataProvider`)
- **Búsqueda híbrida**: Azure Functions + fallback a BD local
- **Búsqueda local en BD**: Con filtros SQL (tipos de documento, demencia, idiomas, fechas, score)
- **Búsqueda por palabras clave**: Usa LIKE en título, excerpt y summary cuando Azure falla
- **Obtención de artículos**: Por URL con artículos relacionados basados en tags compartidos
- **Noticias**: Filtradas por idioma, con soporte para noticias sin idioma específico (se muestran como inglés)
- **Métricas**: Consulta la tabla `estadisticas` de la BD
- **Artículos recientes**: Endpoint `/articles/latest` con filtro por idioma
- **Sistema de scores**: Integración con `SourceReliabilityService` para obtener scores de fiabilidad

### Sistema de reportes
- Guarda reportes en la tabla `reportes` de la BD
- Envía email de notificación usando `mail()` de PHP
- Validación de email y campos requeridos

### Health check
- Verifica conexión a base de datos (con timeout de 2 segundos)
- Verifica disponibilidad de servicios (mail, PHP)
- Retorna estado general: `ok`, `warning` o `degraded`

### Documentación
- Swagger UI integrado en `/docs`
- Especificación OpenAPI 3.1.0 en `/openapi.yaml`

## Scripts de utilidad

- `scripts/generate_sources_json.php`: Genera un JSON con todas las fuentes únicas de la BD, normalizadas y con estructura para asignar scores. Preserva scores existentes si el archivo ya existe.

## Documentación

Consulta [`Explicacion.md`](Explicacion.md) para más detalles técnicos sobre la arquitectura y despliegue.

---

[← Volver al README principal](../README.md)

