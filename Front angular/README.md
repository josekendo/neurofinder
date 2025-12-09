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

Todas las peticiones van al backend PHP mediante HTTP REST:

```
Frontend Angular → POST /search → Backend PHP
Frontend Angular → GET /news/latest → Backend PHP
Frontend Angular → GET /metrics → Backend PHP
Frontend Angular → POST /articles → Backend PHP (detalle)
Frontend Angular → POST /report → Backend PHP (reportar contenido)
```

El servicio `ApiService` centraliza todas las llamadas HTTP y está configurado para apuntar a `https://neurofinder.org/api` en producción (configurable por entorno).

### Flujo de datos

1. El usuario realiza una búsqueda en la página de inicio
2. Angular dispara una acción de NgRx (`SearchActions`)
3. Los efectos de NgRx llaman a `ApiService.search()`
4. `ApiService` hace una petición POST al backend
5. La respuesta se guarda en el store de NgRx
6. Los componentes se actualizan automáticamente

## Estructura

```
web/
├── src/
│   ├── app/
│   │   ├── core/
│   │   │   ├── services/
│   │   │   │   ├── api.service.ts      # Comunicación con backend
│   │   │   │   └── seo.service.ts      # Gestión de metadatos SEO
│   │   │   └── models/
│   │   │       └── content.models.ts   # Tipos TypeScript
│   │   ├── search/                     # Módulo de búsqueda
│   │   │   ├── pages/
│   │   │   │   ├── home/              # Página de inicio
│   │   │   │   └── results/           # Resultados de búsqueda
│   │   │   └── state/                 # NgRx (actions, effects, reducer)
│   │   ├── articles/                  # Detalle de artículos
│   │   ├── news/                      # Lista de noticias
│   │   ├── about/                     # Página "Quiénes somos"
│   │   ├── errors/                    # Página 404
│   │   └── shared/                    # Componentes reutilizables
│   │       ├── article-card/
│   │       ├── filters-panel/
│   │       ├── metrics-banner/
│   │       └── news-grid/
│   ├── assets/
│   │   ├── i18n/                      # Traducciones (es.json, en.json)
│   │   └── meta/                      # Imágenes Open Graph
│   └── environments/                  # Configuración por entorno
├── angular.json
└── package.json
```

## Tecnologías principales

- **Angular 18.2** con componentes standalone
- **NgRx** para gestión de estado
- **Angular Material** para componentes UI
- **@ngx-translate/core** para i18n
- **RxJS** para programación reactiva

## Puesta en marcha

```bash
cd "Front angular/web"
npm install
npm run start
```

La aplicación estará disponible en `http://localhost:4200`. Por defecto apunta al backend en `http://localhost:8080/api` (configurable en `environment.ts`).

### Scripts disponibles

- `npm run start` - Servidor de desarrollo
- `npm run build` - Compilar para producción (genera `dist/web`)
- `npm test` - Ejecutar tests unitarios
- `npm run e2e` - Tests end-to-end (requiere configuración previa)

## Despliegue

El build de producción genera una carpeta `dist/web` con archivos estáticos listos para servir. En OVH, se sube esta carpeta al directorio `/www` del hosting.

## Documentación

Consulta [`Explicacion.md`](Explicacion.md) para más detalles sobre la arquitectura y despliegue.

---

[← Volver al README principal](../README.md)

