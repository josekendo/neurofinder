## Backend PHP (`api/`)

Este servicio expone la API REST que consume el frontend y ofrece datos mock para validar la experiencia de usuario mientras se completa la integración con las fuentes reales.

- **Tecnología base**: PHP 8.2 puro, sin frameworks externos. Se utiliza un autoloader propio (`src/bootstrap.php`) y una clase `Application` que actúa como micro-router interno.
- **Estructura**:
  - `index.php` es el punto de entrada y únicamente instancia `NeuroFinder\Application`.
  - `Application` gestiona cabeceras CORS, normaliza rutas, controla errores y delega en el proveedor de datos configurado.
  - `Http\Response` centraliza la serialización JSON y los códigos HTTP (`ok`, `badRequest`, `notFound`, `serverError`, etc.).
  - `Contracts/DataProviderInterface` define el contrato de acceso a datos; las implementaciones viven en `Profiles/`.
- **Perfiles**:
  - `mock` (por defecto) responde con datos estáticos representativos del dominio (artículos, noticias, métricas). Esta es la modalidad utilizada en el TFM.
  - `active` reserva el contrato para la futura conexión con Azure Functions y un repositorio real; actualmente lanza una excepción controlada indicando que no está implementado.
  - El perfil se selecciona mediante la variable de entorno `APP_PROFILE`. `APP_BASE_PATH` permite indicar el prefijo bajo el que se sirve la API (`/api`, por ejemplo).
- **Endpoints disponibles**:
  - `GET /health`: estado básico del servicio, perfil activo y sello temporal.
  - `GET /metrics`: métricas agregadas mock (número de fuentes, artículos y fecha de actualización).
  - `GET /news/latest`: colección de noticias recientes simuladas.
  - `POST /search`: búsqueda en los mocks de artículos con filtros por tipo de demencia, idioma, score, rango de fechas y ordenación.
  - `GET /articles/{id}`: detalle ampliado de un artículo, con resumen, puntos clave y relacionados.
- **Documentación**: `openapi.yaml` describe la API completa (peticiones, respuestas y esquemas) y sirve de base para generar clientes o importar la colección en Postman.
- **Despliegue**: basta con publicar la carpeta `api/` en el entorno objetivo y definir las variables de entorno necesarias. El router interno detecta el directorio base y aplica los rewrites sin depender de `.htaccess`.

En resumen, se trata de un backend minimalista diseñado para ser sencillo de desplegar y mantener, que ya expone el contrato REST definitivo y permite evolucionar de datos simulados a datos reales sin reescribir la capa HTTP.