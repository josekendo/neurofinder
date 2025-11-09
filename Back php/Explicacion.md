## Backend PHP (`api/`)

Este servicio proporciona la capa REST que consume el frontend y centraliza la lógica de negocio que no se delega en Azure Functions.

Todo el backend está desarrollado con PHP 8.2, garantizando compatibilidad con las librerías y estándares más recientes del ecosistema.

La exposición pública del backend se realiza bajo el dominio `neurofinder.org`, asegurando un punto único de acceso para clientes internos y externos.

- **Perfiles ejecutables**:
  - `mock` (por defecto): responde con datos sintéticos alineados con los mocks históricos del frontend (`/search`, `/articles/{id}`, `/news/latest`, `/metrics`).
  - `active`: reservado para la integración real con bases de datos y Azure Functions. Todavía no está implementado y devuelve un error controlado.
  - El perfil se selecciona mediante la variable de entorno `APP_PROFILE`.
- **Estructura de despliegue**:
  - Todo lo necesario para publicar el backend está contenido en la carpeta `api/` (subirla tal cual a `/www/api`): `index.php`, `.htaccess`, `src/` y `openapi.yaml`.
  - Desplegado en OVH dentro de `/www/api`, se debe definir `APP_PROFILE=mock` y `APP_BASE_PATH=api` para que el router recorte el prefijo y atienda las rutas bajo `https://neurofinder.org/api/*`.
  - Si la variable `APP_BASE_PATH` no está presente, el router detecta automáticamente el directorio del script (`dirname($_SERVER['SCRIPT_NAME'])`).
  - El `.htaccess` incluido redirige todas las peticiones no estáticas a `index.php`, imprescindible para los rewrites en OVH.
- **Framework**: implementación ligera propia en PHP 8.2 (router minimalista sin dependencias externas). Pensado para evolucionar a SlimPHP 4 cuando se integre con los servicios activos.
- **Responsabilidades principales**:
  - Exponer endpoints `POST /search`, `GET /articles/{id}`, `GET /news/latest` y `GET /metrics`.
  - Orquestar peticiones hacia las Azure Functions de recopilación, procesamiento y evaluación mediante colas y webhooks.
  - Gestionar el almacenamiento relacional (MySQL/Aurora) para metadatos, usuarios y registros de auditoría.
  - Administrar los tokens de acceso a la base vectorial (Azure Cognitive Search o alternativa compatible con embeddings).
- **Integración con embeddings**:
  - Mantiene un cliente HTTP para consultas semánticas; recibe los vectores desde Azure Functions y ejecuta el ranking final.
  - Ofrece un endpoint interno (`/internal/embeddings/sync`) para refrescar cachés locales tras cada nueva ingesta.
- **Seguridad**:
  - Autenticación mediante JWT (usuarios registrados) y API keys (servicios internos).
  - Rate limiting por IP y validación de origen CORS.
- **Observabilidad**:
  - Registro estructurado con Monolog + Azure Application Insights.
  - Métricas expuestas en `/metrics` (formato Prometheus) para latencia y errores.

El despliegue se realiza en un contenedor Docker hosteado en Azure App Service. Se definen pipelines de CI/CD que ejecutan pruebas unitarias (PHPUnit) y de contrato (Pact).