# Backend PHP - API REST

El backend PHP actúa como **orquestador** entre el frontend Angular y los servicios de Azure Functions. Su función principal es exponer una API REST limpia y sencilla que el frontend consume, mientras gestiona internamente las conexiones con los servicios de backend.

## ¿Qué hace?

Es una API REST minimalista escrita en PHP 8.2 puro (sin frameworks pesados) que:

- **Expone endpoints públicos** para búsqueda, artículos, noticias y métricas
- **Gestiona la lógica de negocio** delegando en proveedores de datos según el perfil configurado
- **Aplica CORS y manejo de errores** de forma centralizada
- **Soporta dos modos de operación**:
  - **Modo `mock`**: Devuelve datos simulados para desarrollo y testing
  - **Modo `active`**: Conecta con Azure Functions y base de datos real (pendiente de implementación completa)

## ¿Cómo se comunica?

### Con el Frontend Angular

El frontend realiza peticiones HTTP REST a esta API:

```
Frontend → POST /search → Backend PHP
Frontend → GET /articles/{id} → Backend PHP  
Frontend → GET /news/latest → Backend PHP
Frontend → GET /metrics → Backend PHP
```

Todas las respuestas son JSON y el backend aplica CORS automáticamente para permitir peticiones desde el dominio del frontend.

### Con Azure Functions (modo `active`)

Cuando está en modo `active`, el backend llama a las Azure Functions para obtener datos reales:

```
Backend PHP → GET /api/buscador → Azure Functions
Backend PHP → GET /api/procesamiento → Azure Functions
```

Actualmente el modo `active` está parcialmente implementado en `ActiveDataProvider.php`.

### Con la Base de Datos

En modo `active`, el backend se conecta a MySQL/MariaDB para:
- Almacenar reportes de usuarios
- Consultar artículos y noticias indexadas
- Gestionar métricas del sistema

## Estructura

```
api/
├── index.php                 # Punto de entrada
├── config.php                # Configuración (variables de entorno)
├── openapi.yaml              # Documentación de la API
├── src/
│   ├── Application.php       # Router principal y lógica HTTP
│   ├── bootstrap.php         # Autoloader de clases
│   ├── Http/
│   │   └── Response.php      # Utilidades para respuestas HTTP
│   ├── Contracts/
│   │   └── DataProviderInterface.php  # Contrato para proveedores
│   └── Profiles/
│       ├── MockDataProvider.php       # Datos simulados
│       └── ActiveDataProvider.php     # Conexión real (pendiente)
└── scripts/                  # Scripts de utilidad
```

## Puesta en marcha

```bash
cd "Back php/api"
php -S localhost:8080 index.php
```

La API estará disponible en `http://localhost:8080`. El perfil por defecto es `mock`, pero puedes cambiarlo definiendo la variable de entorno `APP_PROFILE=active`.

### Endpoints disponibles

- `GET /health` - Estado del servicio
- `GET /metrics` - Métricas agregadas
- `GET /news/latest` - Noticias recientes
- `POST /search` - Búsqueda de artículos
- `POST /articles` - Detalle de artículo (por URL)
- `POST /report` - Reportar contenido
- `GET /docs` - Documentación interactiva (Swagger)
- `GET /openapi.yaml` - Especificación OpenAPI

## Documentación

Consulta [`Explicacion.md`](Explicacion.md) para más detalles técnicos sobre la arquitectura y despliegue.

---

[← Volver al README principal](../README.md)

