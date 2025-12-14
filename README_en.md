# NeuroFinder – Clinical Intelligence Platform for Dementia

> Read this documentation in Spanish: [README.md](README.md)

NeuroFinder is a research platform that centralizes clinical evidence about dementia and provides a semantic search engine accessible to both healthcare professionals and caregivers. The project consists of three main modules that work together:

## Public Resources

- **Application domain**: [`www.neurofinder.org`](https://www.neurofinder.org)
- **Figma design**: [`NeuroFinder`](https://www.figma.com/design/Nb5zOGw6UgJkumD34uuzMO/NeuroFinder?node-id=0-1&t=pMxAYJ03iCaGBp0O-1)

## System Architecture

### High-level View

![High-level diagram](Arquitectura/diagrama_alto_nivel.png)

### Detailed Architecture

![Architecture diagram](Arquitectura/diagrama_arquitectura.png)

### System Components

![Components diagram](Arquitectura/diagrama_componentes.png)

### Azure Functions

![Azure Functions diagram](Arquitectura/diagrama_funciones_azure.png)

## System Modules

- **Frontend SPA (`Front angular/web`)**: Angular 18 application providing the complete user experience (semantic search, filtered results, article detail, news and metrics).
- **PHP Backend (`Back php/api`)**: REST API acting as orchestrator, exposing public endpoints and coordinating business logic with Azure Functions and MySQL database.
- **Azure Functions (`Functions Azure`)**: Python serverless functions for data collection (NCBI, TheNewsAPI), AI processing, embedding generation and semantic search with FAISS.

## Repository Structure

The project is organized into independent modules that communicate with each other:

- **[`Back php/`](Back%20php/README.md)** - PHP REST API that orchestrates frontend requests and coordinates with Azure Functions and MySQL database
- **[`Front angular/`](Front%20angular/README.md)** - Angular 18 web application with NgRx, internationalization and dynamic SEO
- **[`Functions Azure/`](Functions%20Azure/README.md)** - Python serverless functions for data collection (NCBI, TheNewsAPI), AI processing, embedding generation and semantic search with FAISS
- **[`Pruebas/`](Pruebas/README.md)** - Jupyter notebooks for experimentation and testing with external services
- **[`Arquitectura/`](Arquitectura/)** - Architecture diagrams in PlantUML and PNG formats

## Prerequisites

- **Node.js 20 LTS** and npm 10 (for Angular CLI 18)
- **PHP 8.2** with JSON and PDO extensions enabled
- **Python 3.11+** and Azure Functions Core Tools v4
- **MySQL/MariaDB** (for backend `active` mode)
- **Azure Storage** or Azure Storage Emulator (to run Functions)
- **Azure OpenAI** (for embedding generation and AI processing)

## Local Setup

### 1. Angular Frontend

```bash
cd "Front angular/web"
npm install
npm run start
```

The app becomes available at `http://localhost:4200`. Environment files (`environment.ts`) are configured to point to the backend at `http://localhost:8080/api`.

**Available features:**
- Semantic search with advanced filters
- Article and news visualization
- Internationalization (Spanish/English)
- Dynamic SEO with Open Graph metadata

### 2. PHP Backend

```bash
cd "Back php/api"
php -S localhost:8080 index.php
```

**Implemented endpoints:**

- `GET /health` - Service status (verifies database and services)
- `GET /metrics` - Aggregated metrics (sources and articles)
- `GET /news/latest?language={lang}&limit={n}` - Recent news
- `GET /articles/latest?language={lang}&limit={n}` - Latest articles
- `POST /search` - Article search (with optional query and filters)
- `POST /articles` - Article detail by URL
- `POST /report` - Report content (saves to database and sends email)
- `GET /docs` - Interactive documentation (Swagger UI)
- `GET /openapi.yaml` - OpenAPI specification

**Configuration:**
- `APP_PROFILE`: Controls data source (`mock` by default, `active` for production with database and Azure Functions)
- `APP_BASE_PATH`: Defines route prefix if API is in a subdirectory (e.g., `api`)
- Database environment variables: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Azure variables: `AZURE_SEARCH_URL` (for semantic search)

### 3. Azure Functions

```bash
cd "Functions Azure"
python -m venv .venv
source .venv/bin/activate  # On Windows: .venv\Scripts\activate
pip install -r requirements.txt
func start
```

**Implemented HTTP functions:**

- `GET /api/recopilador?week={week}&year={year}` - Collects articles from NCBI and news from TheNewsAPI
- `GET /api/procesamiento` - Compacts JSON files from storage
- `POST /api/evaluacion` - Builds FAISS index with embeddings (receives items in body)
- `GET /api/buscador?q={query}&k={limit}&tag={tag}` - Semantic search in FAISS

**Required configuration:**
- `AzureWebJobsStorage` or `AZURE_STORAGE_CONNECTION_STRING` - Azure Storage connection
- `AZURE_OPENAI_ENDPOINT` - Azure OpenAI endpoint
- `AZURE_OPENAI_API_KEY` - Azure OpenAI API key
- `THENEWSAPI_TOKEN` - TheNewsAPI token (for news collection)
- `NCBI_API_KEY` - NCBI API key (optional, improves rate limits)

## Deployment

### Frontend and Backend (OVH Hosting)

1. **Build frontend:**
   ```bash
   cd "Front angular/web"
   npm run build
   ```
   This generates the `dist/web` folder with optimized static files.

2. **Upload files:**
   - Upload `dist/web/*` to the `/www` directory of the hosting
   - Upload `Back php/api/*` to the `/www/api` directory

3. **Configure backend:**
   - Define environment variables in `.htaccess` or server configuration:
     - `APP_PROFILE=active` (for production)
     - `APP_BASE_PATH=api`
     - Database variables: `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
     - Azure variables: `AZURE_SEARCH_URL`
   - Ensure `.htaccess` redirects requests to `index.php`

### Azure Functions

```bash
cd "Functions Azure"
func azure functionapp publish <app-name>
```

**Azure configuration:**
- Configure Application Settings with all required environment variables
- Ensure Azure Storage container exists (`recopilaciones` by default)
- Verify Azure OpenAI is configured and accessible

## Implemented Features

### Frontend
- ✅ Semantic search with advanced filters (dementia types, document types, languages, dates, score)
- ✅ State management with NgRx (Store + Effects)
- ✅ Complete internationalization (Spanish/English)
- ✅ Dynamic SEO with Open Graph and Twitter Cards metadata
- ✅ Reusable components (article-card, filters-panel, metrics-banner, news-grid, report-modal)
- ✅ Performance optimizations (OnPush, lazy loading, cache)

### PHP Backend
- ✅ Complete REST API with all endpoints implemented
- ✅ `mock` mode for development and `active` mode for production
- ✅ Integration with Azure Functions for semantic search
- ✅ MySQL/MariaDB connection for storage and queries
- ✅ Reporting system with database storage and email sending
- ✅ Health check with service verification
- ✅ Source reliability scoring system
- ✅ OpenAPI documentation with Swagger UI

### Azure Functions
- ✅ Article collection from NCBI (PubMed) and news from TheNewsAPI
- ✅ Processing with Azure OpenAI to extract summaries, key points and categorization
- ✅ JSON file compaction from storage
- ✅ Embedding generation with Azure OpenAI (`text-embedding-ada-002`)
- ✅ FAISS index construction for semantic search
- ✅ Semantic search with support for tag-specific indices
- ✅ Azure Blob Storage storage

## Technologies Used

- **Frontend**: Angular 18, NgRx, Angular Material, @ngx-translate/core, RxJS
- **Backend**: PHP 8.2, PDO (MySQL), JSON
- **Azure Functions**: Python 3.11, FAISS, Azure OpenAI SDK, Biopython, NumPy
- **Database**: MySQL/MariaDB
- **Storage**: Azure Blob Storage
- **AI**: Azure OpenAI (GPT for processing, ada-002 for embeddings)

## Data Flow

### Semantic Search

1. User performs search in Angular frontend
2. Frontend sends `POST /search` request to PHP backend
3. PHP backend (active mode):
   - If query exists: Calls `GET /api/buscador` from Azure Functions
   - Azure Functions generates query embedding and searches in FAISS index
   - PHP backend receives result URLs and searches articles in database
   - If Azure fails: Fallback to local database search with LIKE
4. If no query: Direct database search applying only filters
5. Results are returned to frontend and displayed to user

### Collection and Processing

1. `GET /api/recopilador` obtains articles from NCBI and news from TheNewsAPI
2. Each article/news is processed with Azure OpenAI to extract structured information
3. Results are saved in Azure Blob Storage as JSON files
4. `GET /api/procesamiento` compacts pending JSON files
5. `POST /api/evaluacion` generates embeddings and builds FAISS index
6. FAISS index is saved in Azure Blob Storage for future searches

## Module Documentation

To understand each part of the system in detail and how they communicate:

- **[Backend PHP](Back%20php/README.md)** - REST API, orchestration, integration with database and Azure Functions
- **[Frontend Angular](Front%20angular/README.md)** - User interface, NgRx, internationalization and SEO
- **[Azure Functions](Functions%20Azure/README.md)** - Data collection, AI processing, embeddings and semantic search
- **[Pruebas](Pruebas/README.md)** - Jupyter notebooks for experimentation with external services

## Architecture Diagrams

Architecture diagrams are available in the [`Arquitectura/`](Arquitectura/) folder:

- `diagrama_alto_nivel.png` - System overview
- `diagrama_arquitectura.png` - Detailed component architecture
- `diagrama_componentes.png` - Components and their interactions
- `diagrama_funciones_azure.png` - Azure Functions flow

Source files in PlantUML (`.puml`) are also available for editing.

---

For additional technical details, also check the `Explicacion.md` documents inside `Back php` and `Front angular`. For any additional questions, contact the team responsible for the Master's project.

