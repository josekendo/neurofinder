# NeuroFinder – Plataforma de inteligencia clínica para demencias

> Consulta esta documentación en inglés: [README_en.md](README_en.md)

NeuroFinder es una iniciativa de investigación orientada a centralizar evidencias clínicas sobre demencias y ofrecer un buscador semántico accesible tanto para profesionales sanitarios como para cuidadores. El proyecto se divide en tres grandes piezas:

## Recursos públicos

- **Dominio de la aplicación**: [`www.neurofinder.org`](https://www.neurofinder.org)
- **Diseño en Figma**: [`NeuroFinder`](https://www.figma.com/design/Nb5zOGw6UgJkumD34uuzMO/NeuroFinder?node-id=0-1&t=pMxAYJ03iCaGBp0O-1)

- **Frontend SPA (`Front angular/web`)**: aplicación Angular 18 que ofrece la experiencia de usuario completa (búsqueda, resultados, detalle de artículos y métricas).
- **Backend ligero en PHP (`Back php/api`)**: API REST que actúa como orquestador, expone los endpoints públicos y coordina la lógica de negocio con servicios internos.
- **Azure Functions (`Functions Azure`)**: conjunto de funciones serverless destinadas a la ingesta, procesamiento y evaluación de las fuentes externas que alimentan el buscador.

## Estructura del repositorio

```text
Back php/
  ├── api/                # Código fuente de la API REST (PHP 8.2)
  └── Explicacion.md      # Detalles funcionales y de despliegue del backend
Front angular/
  ├── web/                # Proyecto Angular (src/, assets/, dist/)
  └── Explicacion.md      # Guía de despliegue y arquitectura del frontend
Functions Azure/
  ├── function_app.py     # Registro de las funciones HTTP y rutas disponibles
  ├── requirements.txt    # Dependencias Python para Azure Functions
  ├── host.json           # Configuración global de Functions
  └── */                  # Directorios por función (buscador, procesador, etc.)
```

## Requisitos previos

- Node.js 20 LTS y npm 10 (Angular CLI 18).
- PHP 8.2 con extensión JSON habilitada.
- Python 3.11 y Azure Functions Core Tools v4.
- Azure Storage Emulator o cuenta de almacenamiento real para ejecutar Functions.
- Docker (opcional) para alinear entornos de despliegue.

## Puesta en marcha local

### 1. Frontend Angular

```bash
cd "Front angular/web"
npm install
npm run start
```

La aplicación queda disponible en `http://localhost:4200`. Los entornos (`environment.ts`) están configurados para apuntar al backend mock en `http://localhost:8080/api` —ajusta la URL si cambias el puerto o el contexto.

### 2. Backend PHP

```bash
cd "Back php/api"
php -S localhost:8080 index.php
```

Endpoints expuestos:

- `POST /search`
- `GET /articles/{id}`
- `GET /news/latest`
- `GET /metrics`
- `GET /health`

La variable `APP_PROFILE` controla el origen de datos (`mock` por defecto, `active` reservado para la integración real). Si sirves la API tras un subdirectorio (p. ej. `/api`), define `APP_BASE_PATH=api` para que el router recorte el prefijo correctamente.

### 3. Azure Functions

```bash
cd "Functions Azure"
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
func start
```

Las funciones accesibles por HTTP son:

- `GET /api/recopilador`
- `GET /api/procesamiento`
- `GET /api/evaluacion`
- `GET /api/buscador`

Recuerda configurar la cadena de conexión `AzureWebJobsStorage` mediante variables de entorno locales o `local.settings.json`. Si compartes el repositorio, elimina o sobrescribe las credenciales reales antes de publicar.

## Despliegue recomendado

- **Hosting OVH**  
  - Subir `Front angular/web/dist/web` al directorio `/www`.  
  - Desplegar `Back php/api` en `/www/api`, definiendo `APP_PROFILE=mock` y `APP_BASE_PATH=api`.  
  - Mantener el `.htaccess` para reenviar las peticiones no estáticas hacia `index.php`.
- **Azure Functions**  
  - Publicar mediante `func azure functionapp publish <nombre-app>` tras configurar los recursos de almacenamiento y Application Insights.  
  - Habilitar colas/eventos según la estrategia de ingesta que defina el despliegue definitivo.

## Pruebas y calidad

- **Frontend**: pruebas unitarias con Jest (`npm test`), end-to-end con Cypress (`npm run e2e`).
- **Backend**: es recomendable incorporar PHPUnit y Pact para validar endpoints y contratos con el frontend.
- **Observabilidad**: tanto la SPA como la API están preparadas para integrarse con Azure Application Insights y exponen métricas básicas.

## Próximos pasos sugeridos

- Completar la lógica real de `ActiveDataProvider` y conectar con las Functions de ingesta/procesamiento.
- Automatizar la construcción y despliegue mediante pipelines CI/CD (GitHub Actions, Azure DevOps o GitLab CI).
- Documentar los esquemas de datos y el modelo de embeddings que utilizarán las funciones de búsqueda.
- Añadir guías de contribución (`CONTRIBUTING.md`) y plantillas de incidencias para facilitar la colaboración.

---

Para más detalles específicos de cada módulo consulta los documentos `Explicacion.md` dentro de las carpetas `Back php` y `Front angular`. Cualquier duda adicional puede dirigirse al equipo responsable del TFM.

