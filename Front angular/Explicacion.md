## Frontend Angular (`web/`)

Aplicación SPA desarrollada con Angular 18.2 y componentes standalone. Se apoya en Angular Material para los elementos de interfaz (inputs, botones, paneles) y sigue un enfoque responsive, pensado para consumo desde escritorio y tablet.

- **Arquitectura principal**:
  - Routing basado en `app.routes.ts` con lazy loading por componente (`home`, `search`, `articles/:id`, `news`, `quienes-somos` y página 404).
  - Módulo `core` con servicios singleton: `ApiService` (comunicación REST hacia `environment.apiUrl`) y `SeoService` (metadatos y Open Graph).
  - Módulo `search` que concentra páginas, estado global y efectos relacionados con la búsqueda de artículos.
  - Módulos `articles`, `news`, `about` y `errors` para el resto de vistas, cada uno organizado en carpetas `pages`.
  - `shared` agrupa componentes reutilizables (`article-card`, `news-grid`, `filters-panel`, `metrics-banner`, etc.).

- **Gestión de estado**:
  - NgRx (`@ngrx/store` + `@ngrx/effects`) mantiene el estado del buscador: `query`, filtros activos, resultados y estados de carga/errores.
  - Los efectos coordinan las llamadas a `ApiService` y despachan acciones de éxito o fallo, reutilizando la última petición cuando se re-ejecuta la búsqueda sin cambios.

- **Internacionalización y SEO**:
  - Se usa `@ngx-translate/core` con ficheros `assets/i18n/es.json` y `en.json`. El cambio de idioma se refleja en textos y etiquetas SEO.
  - `SeoService` actualiza títulos, descripciones y metadatos sociales según la pantalla y el idioma activo.

- **Integración con el backend**:
  - Todas las peticiones REST apuntan a `https://neurofinder.org/api` (configurable por entorno).
  - Los endpoints consumidos son: `POST /search`, `GET /articles/{id}`, `GET /news/latest` y `GET /metrics`.

- **Build y despliegue**:
  - `npm install` + `npm run build` genera `dist/web` listo para servir como sitio estático.
  - Para OVH o hosting similar basta con subir el contenido de `dist/web` al directorio público (`/www`). El backend mock se mantiene en `/www/api`.

- **Otras consideraciones**:
  - Estilos globales en `src/styles.scss` siguiendo variables CSS y tipografía accesible.
  - La app aprovecha la detección de cambios `OnPush` en los componentes principales para mejorar rendimiento.
  - Integración prevista con herramientas de analítica mediante `SeoService` y eventos de NgRx (pendiente de activar según políticas de datos).