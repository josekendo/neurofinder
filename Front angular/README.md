# Frontend Angular - Aplicación Web

El frontend es una **Single Page Application (SPA)** desarrollada con Angular 18 que proporciona la interfaz de usuario completa de NeuroFinder. Permite a usuarios buscar artículos científicos, consultar noticias y visualizar métricas del sistema.

## ¿Qué hace?

Es la cara visible de NeuroFinder, una aplicación web moderna que:

- **Ofrece una interfaz de búsqueda semántica** para encontrar artículos científicos sobre demencias
- **Muestra noticias recientes** relacionadas con el tema
- **Presenta métricas del sistema** (número de fuentes, artículos indexados, etc.)
- **Gestiona el estado de la aplicación** usando NgRx para búsquedas y filtros
- **Soporta internacionalización** (español e inglés)
- **Optimiza el SEO** con metadatos dinámicos y Open Graph

## ¿Cómo se comunica?

### Con el Backend PHP

Todas las peticiones van al backend PHP mediante HTTP REST a través del servicio `ApiService`:

- `POST /search` - Búsqueda de artículos (con query opcional y filtros)
- `POST /articles` - Detalle de artículo por URL
- `GET /news/latest` - Noticias recientes (con parámetros `language` y `limit`)
- `GET /articles/latest` - Últimos artículos (con parámetros `language` y `limit`)
- `GET /metrics` - Métricas del sistema
- `POST /report` - Reportar contenido

El servicio `ApiService` centraliza todas las llamadas HTTP y está configurado para apuntar a `https://neurofinder.org/api` en producción (configurable por entorno en `environment.ts`).

### Flujo de datos con NgRx

1. El usuario realiza una búsqueda o cambia filtros
2. Angular dispara una acción de NgRx (`SearchActions`)
3. Los efectos de NgRx (`SearchEffects`) interceptan la acción y llaman a `ApiService`
4. `ApiService` hace una petición HTTP al backend
5. La respuesta se guarda en el store de NgRx mediante `SearchActions.searchSuccess`
6. Los componentes suscritos al store se actualizan automáticamente

**Curiosidad técnica**: El sistema evita peticiones redundantes comparando el `lastRequest` almacenado en el estado. Si la búsqueda es idéntica a la anterior, no se vuelve a llamar al backend.

## Estructura

```
web/
├── src/
│   ├── app/
│   │   ├── core/
│   │   │   ├── services/
│   │   │   │   ├── api.service.ts      # Comunicación HTTP con backend
│   │   │   │   └── seo.service.ts      # Gestión de metadatos SEO dinámicos
│   │   │   └── models/
│   │   │       └── content.models.ts   # Interfaces TypeScript (ArticleSummary, SearchFilters, etc.)
│   │   ├── search/
│   │   │   ├── pages/
│   │   │   │   ├── home/              # Página de inicio con búsqueda, artículos destacados y noticias
│   │   │   │   └── results/           # Página de resultados con filtros
│   │   │   └── state/                 # NgRx (actions, effects, reducer)
│   │   ├── articles/
│   │   │   └── pages/
│   │   │       └── article-detail/     # Detalle completo de artículo con relacionados
│   │   ├── news/
│   │   │   └── pages/
│   │   │       └── news-list/          # Lista completa de noticias
│   │   ├── about/
│   │   │   └── pages/
│   │   │       └── about/              # Página "Quiénes somos"
│   │   ├── errors/
│   │   │   └── pages/
│   │   │       └── not-found/          # Página 404
│   │   └── shared/
│   │       └── components/
│   │           ├── article-card/       # Tarjeta de artículo/noticia reutilizable
│   │           ├── filters-panel/       # Panel de filtros avanzados
│   │           ├── metrics-banner/      # Banner con métricas del sistema
│   │           ├── news-grid/          # Grid responsivo de noticias
│   │           └── report-modal/        # Modal para reportar contenido
│   ├── assets/
│   │   ├── i18n/                      # Traducciones (es.json, en.json)
│   │   └── meta/                      # Imágenes Open Graph (og-default.png)
│   ├── environments/                  # Configuración por entorno (apiUrl)
│   └── index.html                     # HTML principal
├── angular.json                        # Configuración de Angular CLI
└── package.json                        # Dependencias del proyecto
```

**Curiosidad técnica**: Todos los componentes son standalone (sin módulos tradicionales). El routing usa lazy loading con `loadComponent` para cargar componentes bajo demanda, optimizando el bundle inicial.

## Tecnologías principales

- **Angular 18.2** con componentes standalone (sin módulos tradicionales)
- **NgRx Store + Effects** para gestión de estado reactivo
- **Angular Material 18.2** para componentes UI (form fields, buttons, cards, etc.)
- **@ngx-translate/core** para internacionalización (i18n)
- **RxJS 7.8** para programación reactiva
- **Angular Router** con lazy loading de componentes

## Puesta en marcha

```bash
cd "Front angular/web"
npm install
npm run start
```

La aplicación estará disponible en `http://localhost:4200`. Por defecto apunta al backend en `http://localhost:8080/api` (configurable en `environment.ts`).

### Scripts disponibles

- `npm run start` - Servidor de desarrollo (con hot reload)
- `npm run build` - Compilar para producción (genera `dist/web` con optimizaciones)
- `npm run watch` - Build en modo watch para desarrollo
- `npm test` - Ejecutar tests unitarios con Karma/Jasmine

## Características implementadas

### Sistema de búsqueda
- **Búsqueda semántica**: Formulario en página de inicio que navega a resultados
- **Filtros avanzados**: Panel completo con filtros por:
  - Tipos de demencia (múltiple selección)
  - Tipos de documento (artículos, noticias, papers, informes clínicos, guías, datasets)
  - Idiomas (múltiple selección)
  - Rango de fechas (desde/hasta)
  - Score mínimo
  - Ordenación (por relevancia o fecha)
- **Gestión de estado**: NgRx mantiene query, filtros y resultados en el store global
- **Cache inteligente**: Evita peticiones redundantes comparando requests anteriores

### Páginas implementadas
- **Página de inicio (`/`)**: Formulario de búsqueda, artículos destacados (4 últimos), noticias recientes (4), métricas del sistema
- **Resultados (`/search`)**: Muestra resultados con panel de filtros lateral, soporta query params (`q` para query, `tag` para filtrar por tag)
- **Detalle de artículo (`/articles`)**: Vista completa con resumen, puntos clave, artículos relacionados y botón de reporte
- **Lista de noticias (`/news`)**: Grid responsivo con todas las noticias disponibles
- **Quiénes somos (`/quienes-somos`)**: Página informativa sobre el proyecto
- **404 (`/404`)**: Página de error para rutas no encontradas (redirección automática)

### Componentes compartidos
- **ArticleCardComponent**: Tarjeta reutilizable para artículos y noticias, muestra título, resumen, fecha, fuente, score, tags y tipo. Permite filtrar por tags haciendo clic.
- **FiltersPanelComponent**: Panel completo de filtros con formularios reactivos, botones de aplicar y limpiar
- **MetricsBannerComponent**: Banner con métricas (fuentes, artículos, última actualización)
- **NewsGridComponent**: Grid responsivo para mostrar noticias con imágenes opcionales
- **ReportModalComponent**: Modal para reportar contenido con validación de email

### Internacionalización
- **Soporte completo**: Español e inglés con `@ngx-translate/core`
- **Cambio dinámico**: El cambio de idioma actualiza automáticamente:
  - Textos de la interfaz
  - Metadatos SEO
  - Consultas al backend (parámetro `language`)
  - Contenido cargado (artículos y noticias filtrados por idioma)
- **Archivos de traducción**: `assets/i18n/es.json` y `assets/i18n/en.json`

### SEO y metadatos sociales
- **SeoService**: Servicio centralizado que actualiza dinámicamente:
  - Título de página (`<title>`)
  - Meta descripción
  - Open Graph tags (og:title, og:description, og:image, og:url, og:type, og:locale)
  - Twitter Cards (twitter:card, twitter:title, twitter:description, twitter:image)
  - Meta robots
- **Metadatos por página**: Cada página actualiza sus metadatos específicos al cargar
- **Imagen Open Graph**: Imagen por defecto en `assets/meta/og-default.png` (1200x630px)

### Optimizaciones de rendimiento
- **Change Detection OnPush**: Componentes principales usan `OnPush` para reducir ciclos de detección
- **Lazy Loading**: Componentes cargados bajo demanda mediante `loadComponent` en rutas
- **Gestión de suscripciones**: Uso de `takeUntil` con `Subject` para prevenir memory leaks
- **Cache de resultados**: NgRx mantiene resultados anteriores, evitando peticiones redundantes
- **Manejo de errores**: `catchError` en observables para manejar errores HTTP sin romper la aplicación

### Integración con backend
- **ApiService centralizado**: Todas las llamadas HTTP pasan por este servicio
- **Manejo de errores**: Errores capturados y mostrados sin interrumpir la experiencia
- **Parámetros opcionales**: Soporte para `language` y `limit` en endpoints de noticias y artículos
- **Tipado fuerte**: Interfaces TypeScript para todas las respuestas del backend

**Curiosidad técnica**: El componente `HomePageComponent` carga artículos y noticias basándose en el idioma actual del usuario. Si el usuario cambia de idioma, se recargan automáticamente los datos correspondientes.

## Despliegue

El build de producción (`npm run build`) genera una carpeta `dist/web` con archivos estáticos optimizados (minificación, tree-shaking, code splitting). Estos archivos pueden ser servidos por cualquier servidor web estático.

**Nota**: Asegúrate de configurar el `apiUrl` correcto en `environment.prod.ts` antes de compilar para producción.

## Documentación

Consulta [`Explicacion.md`](Explicacion.md) para más detalles sobre la arquitectura y despliegue.

---

[← Volver al README principal](../README.md)

