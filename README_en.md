# NeuroFinder – Clinical Intelligence Platform for Dementia

> Read this documentation in Spanish: [README.md](README.md)

NeuroFinder is a research initiative focused on centralising clinical evidence about dementia and providing a semantic search engine for healthcare professionals as well as caregivers. The project is organised into three main parts:

- **Frontend SPA (`Front angular/web`)**: Angular 18 application providing the complete user experience (search, results, article detail and metrics).
- **Lightweight PHP backend (`Back php/api`)**: REST API acting as orchestrator, exposing public endpoints and coordinating business logic with internal services.
- **Azure Functions (`Functions Azure`)**: Collection of serverless functions responsible for ingesting, processing and evaluating external sources that feed the search engine.

## Public Resources

- **Application domain**: [`www.neurofinder.org`](https://www.neurofinder.org)
- **Figma design**: [`NeuroFinder`](https://www.figma.com/design/Nb5zOGw6UgJkumD34uuzMO/NeuroFinder?node-id=0-1&t=pMxAYJ03iCaGBp0O-1)

## Repository Structure

```text
Back php/
  ├── api/                # REST API source (PHP 8.2)
  └── Explicacion.md      # Functional details and deployment notes for the backend
Front angular/
  ├── web/                # Angular project (src/, assets/, dist/)
  └── Explicacion.md      # Deployment and architecture guide for the frontend
Functions Azure/
  ├── function_app.py     # Registry of HTTP functions and available routes
  ├── requirements.txt    # Python dependencies for Azure Functions
  ├── host.json           # Global Functions configuration
  └── */                  # Per-function directories (search, ingestion, processing, etc.)
```

## Prerequisites

- Node.js 20 LTS and npm 10 (Angular CLI 18).
- PHP 8.2 with JSON extension enabled.
- Python 3.11 and Azure Functions Core Tools v4.
- Azure Storage Emulator or a real storage account to run Functions locally.
- Docker (optional) to match deployment environments.

## Local Setup

### 1. Angular Frontend

```bash
cd "Front angular/web"
npm install
npm run start
```

The app becomes available at `http://localhost:4200`. Environment files (`environment.ts`) are configured to point to the mock backend at `http://localhost:8080/api` —update the URL if you change the port or base path.

### 2. PHP Backend

```bash
cd "Back php/api"
php -S localhost:8080 index.php
```

Exposed endpoints:

- `POST /search`
- `GET /articles/{id}`
- `GET /news/latest`
- `GET /metrics`
- `GET /health`

The `APP_PROFILE` variable controls the data source (`mock` by default, `active` reserved for the real integration). If you serve the API under a subdirectory (e.g. `/api`), set `APP_BASE_PATH=api` so the router trims the prefix correctly.

### 3. Azure Functions

```bash
cd "Functions Azure"
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
func start
```

HTTP-accessible functions:

- `GET /api/recopilador`
- `GET /api/procesamiento`
- `GET /api/evaluacion`
- `GET /api/buscador`

Remember to configure the `AzureWebJobsStorage` connection string via local environment variables or `local.settings.json`. If you share the repository, remove or mask any production credentials before publishing.

## Recommended Deployment

- **OVH Hosting**  
  - Upload `Front angular/web/dist/web` to the `/www` directory.  
  - Deploy `Back php/api` under `/www/api`, setting `APP_PROFILE=mock` and `APP_BASE_PATH=api`.  
  - Keep the `.htaccess` file to route non-static requests towards `index.php`.
- **Azure Functions**  
  - Publish via `func azure functionapp publish <app-name>` after provisioning the storage account and Application Insights.  
  - Enable queues/events according to the final ingestion strategy.

## Testing and Quality

- **Frontend**: unit tests with Jest (`npm test`), end-to-end tests with Cypress (`npm run e2e`).
- **Backend**: integrating PHPUnit and Pact is recommended to validate endpoints and contracts with the frontend.
- **Observability**: both the SPA and the API integrate with Azure Application Insights and expose basic metrics.

## Suggested Next Steps

- Complete the real logic for `ActiveDataProvider` and connect it with the ingestion/processing Functions.
- Automate build and deployment through CI/CD pipelines (GitHub Actions, Azure DevOps or GitLab CI).
- Document data schemas and the embedding model used by search-related functions.
- Add contribution guidelines (`CONTRIBUTING.md`) and issue templates to ease collaboration.

---

For module-specific details check the `Explicacion.md` documents inside `Back php` and `Front angular`. For any additional questions, contact the team responsible for the Master’s project.

