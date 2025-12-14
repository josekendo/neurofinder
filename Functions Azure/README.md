# Azure Functions - Procesamiento Serverless

Las Azure Functions son el **cerebro de procesamiento** de NeuroFinder. Son funciones serverless en Python que se encargan de recopilar datos, procesarlos, generar embeddings y realizar búsquedas semánticas.

## ¿Qué hace?

Este módulo contiene 4 funciones HTTP principales que trabajan en cadena:

1. **`recopilador`** - Recopila artículos científicos de NCBI (PubMed) y noticias de TheNewsAPI, los procesa con Azure OpenAI y los almacena en Azure Storage
2. **`procesador`** - Lee archivos JSON del storage y los compacta en un único conjunto de datos
3. **`evaluador`** - Genera embeddings con Azure OpenAI y construye índices FAISS para búsqueda semántica
4. **`buscador`** - Realiza búsquedas semánticas en el índice FAISS usando embeddings

## ¿Cómo se comunica?

### Con el Backend PHP

El backend PHP llama a estas funciones cuando está en modo `active`:

- `GET /api/buscador?q={query}&k={limit}&tag={tag}` - Búsqueda semántica (usado por el backend)
- `GET /api/procesamiento` - Compactar archivos JSON del storage
- `GET /api/recopilador?week={week}&year={year}` - Recopilar nuevos datos
- `POST /api/evaluacion` - Construir índice FAISS (recibe items en el body)

### Con Azure Storage

Todas las funciones utilizan **Azure Blob Storage** para:
- **Almacenar artículos y noticias**: Archivos JSON con prefijo `recopilacion_` (se renombran con `_ok` después de procesar)
- **Guardar índices FAISS**: Archivos `.idx` (índice) y `_metadata.pkl` (metadatos)
- **Índices por tag**: Soporte para índices específicos por tag (ej: `faiss-tnm-alzheimer.idx`)

**Curiosidad técnica**: El procesador solo lee archivos que NO tengan "ok" en el nombre. Después de procesarlos, los renombra agregando "_ok" para marcarlos como procesados.

### Con servicios externos

- **NCBI (PubMed)**: Obtiene artículos científicos sobre demencias usando Biopython y la API de Entrez
- **TheNewsAPI**: Recopila noticias recientes sobre demencias
- **Azure OpenAI**: 
  - Genera embeddings con `text-embedding-ada-002` (dimensión 1536)
  - Procesa texto con GPT para extraer resúmenes, puntos clave y categorización

### Flujo de trabajo típico

```
1. recopilador → Obtiene datos de NCBI y TheNewsAPI → Procesa con Azure OpenAI → Guarda JSON en Azure Storage
2. procesador → Lee archivos JSON pendientes del storage → Compacta en un único conjunto → Retorna items
3. evaluador → Recibe items → Genera embeddings con Azure OpenAI → Crea índice FAISS → Guarda en storage
4. buscador → Recibe query → Genera embedding de la query → Busca en FAISS → Devuelve resultados ordenados por similitud
```

## Estructura

```
Functions Azure/
├── function_app.py              # Registro de todas las funciones HTTP (Azure Functions v2)
├── requirements.txt             # Dependencias Python
├── host.json                    # Configuración global de Azure Functions
├── .funcignore                  # Archivos ignorados en el despliegue
├── recopilador/
│   ├── main.py                 # Entry point HTTP de la función
│   └── recopilador_service.py  # Lógica completa de recopilación (NCBI + TheNewsAPI + OpenAI)
├── procesador/
│   ├── main.py                 # Entry point HTTP de la función
│   └── procesador_service.py   # Lógica de lectura y compactación de JSON
├── evaluador/
│   ├── main.py                 # Entry point HTTP de la función
│   └── evaluador_service.py    # Lógica de embeddings e índices FAISS
└── buscador/
    ├── main.py                 # Entry point HTTP de la función
    └── buscador_service.py     # Lógica de búsqueda semántica en FAISS
```

**Curiosidad técnica**: Cada función usa `sys.path.insert()` para agregar su directorio al path de Python, permitiendo importar los servicios desde `function_app.py`. Esto es necesario porque Azure Functions ejecuta todo desde el directorio raíz.

## Funciones disponibles

### GET /api/recopilador

Recopila artículos científicos de NCBI (PubMed) y noticias de TheNewsAPI sobre demencias. Procesa cada artículo/noticia con Azure OpenAI para extraer resúmenes, puntos clave y categorización.

**Parámetros opcionales:**
- `week` - Número de semana (1-53) para filtrar por semana específica
- `year` - Año (YYYY) para filtrar por año específico

**Retorna:**
- Número de artículos encontrados y procesados
- Número de noticias encontradas y procesadas
- Total de items procesados
- Nombre del archivo JSON guardado en Azure Storage

**Características implementadas:**
- Búsqueda en PubMed con términos relacionados con demencias
- Procesamiento con Azure OpenAI para extraer información estructurada
- Categorización automática por tipo de demencia
- Extracción de tags y metadatos
- Almacenamiento en Azure Blob Storage con nombres únicos por fecha

### GET /api/procesamiento

Lee todos los archivos JSON del storage que NO tengan "ok" en el nombre, los compacta en un único array y los retorna. Después de leer cada archivo, lo renombra agregando "_ok" para marcarlo como procesado.

**Retorna:**
- Array de `items` compactados
- Total de items
- Número de archivos procesados

**Curiosidad técnica**: Solo procesa archivos con prefijo `recopilacion_` que no contengan "ok" en el nombre. Esto permite un procesamiento incremental sin duplicados.

### POST /api/evaluacion

Construye un índice FAISS con embeddings de Azure OpenAI y lo sube al storage. Genera embeddings para título, resumen y puntos clave de cada item.

**Body requerido:**
```json
{
  "items": [
    {
      "url": "...",
      "title": "...",
      "summary": "...",
      "key_points": [...],
      "tags": [...],
      "tipo": "..."
    }
  ],
  "nombre_archivo": "indice_2024"  // opcional, por defecto "faiss-general"
}
```

**Retorna:**
- Número de items procesados
- Número de vectores indexados
- Nombre del índice creado
- Ruta en el storage

**Características implementadas:**
- Generación de embeddings en batch (lotes de 100)
- Construcción de índice FAISS con `IndexFlatIP` (producto interno para similitud coseno)
- Normalización L2 de vectores
- Almacenamiento de metadatos en pickle
- Soporte para índices específicos por tag (ej: `faiss-tnm-alzheimer`)

### GET /api/buscador

Realiza búsqueda semántica en el índice FAISS usando embeddings. Genera un embedding de la query y busca los k documentos más similares.

**Parámetros:**
- `q` o `query` - Texto a buscar (requerido)
- `k` o `limit` - Número de resultados (1-100, por defecto 40)
- `tag` - Etiqueta para buscar en índice específico (opcional, ej: "tnm.alzheimer")

**Retorna:**
```json
{
  "query": "...",
  "tag": "...",
  "resultados": [
    {
      "url": "...",
      "score": 0.95,
      "tipo": "article",
      "title": "..."
    }
  ],
  "total": 40
}
```

**Características implementadas:**
- Búsqueda por índice general (`faiss-general`) o por tag específico
- Selección automática del índice más reciente si no se especifica tag
- Normalización de vectores para similitud coseno
- Scores de similitud (0-1, mayor = más similar)
- Manejo de errores con respuestas JSON estructuradas

## Puesta en marcha local

```bash
cd "Functions Azure"
python -m venv .venv
source .venv/bin/activate  # En Windows: .venv\Scripts\activate
pip install -r requirements.txt
func start
```

Asegúrate de configurar las variables de entorno en `local.settings.json` (o en la configuración de Azure Functions en producción):

**Variables requeridas:**
- `AzureWebJobsStorage` o `AZURE_STORAGE_CONNECTION_STRING` - Cadena de conexión a Azure Storage
- `AZURE_OPENAI_ENDPOINT` - Endpoint de Azure OpenAI
- `AZURE_OPENAI_API_KEY` - Clave API de Azure OpenAI
- `AZURE_OPENAI_EMBEDDING_DEPLOYMENT` - Nombre del deployment de embeddings (por defecto: `text-embedding-ada-002`)
- `AZURE_OPENAI_EMBEDDING_API_VERSION` - Versión de la API (por defecto: `2023-05-15`)
- `STORAGE_CONTAINER` - Nombre del contenedor de Azure Blob Storage (por defecto: `recopilaciones`)

**Variables opcionales:**
- `NCBI_API_KEY` - Clave API de NCBI (opcional, mejora límites de rate)
- `NCBI_EMAIL` - Email para NCBI (opcional)
- `THENEWSAPI_TOKEN` - Token de TheNewsAPI (requerido para recopilar noticias)
- `AZURE_OPENAI_DEPLOYMENT_NAME` - Deployment de GPT para procesamiento (requerido para recopilador)
- `AZURE_OPENAI_API_VERSION` - Versión de la API de GPT (por defecto: `2024-02-15-preview`)
- `MAX_ARTICULOS` - Límite de artículos a recopilar (por defecto: 100)
- `MAX_NOTICIAS` - Límite de noticias a recopilar (por defecto: 100)

## Tecnologías principales

- **Azure Functions v2** con Python 3.11+ (programming model v2)
- **FAISS (faiss-cpu)** para búsqueda vectorial eficiente con índices en memoria
- **Azure OpenAI** para generación de embeddings (`text-embedding-ada-002`) y procesamiento de texto (GPT)
- **Azure Blob Storage** para persistencia de JSON e índices FAISS
- **Biopython** para acceso a la API de NCBI/PubMed
- **NumPy** para operaciones con vectores y arrays
- **OpenAI SDK** para Python (compatible con Azure OpenAI)

## Características implementadas

### Recopilador
- **Búsqueda en PubMed**: Usa Biopython para buscar artículos sobre demencias con términos específicos
- **Búsqueda en TheNewsAPI**: Obtiene noticias recientes sobre demencias
- **Procesamiento con IA**: Cada artículo/noticia se procesa con Azure OpenAI GPT para:
  - Extraer resumen estructurado
  - Generar puntos clave (key_points)
  - Categorizar por tipo de demencia
  - Extraer tags relevantes
- **Almacenamiento**: Guarda resultados en JSON en Azure Blob Storage con nombres únicos por fecha
- **Manejo de errores**: Continúa procesando aunque falle algún item individual

### Procesador
- **Lectura incremental**: Solo procesa archivos que no tienen "ok" en el nombre
- **Compactación**: Combina múltiples archivos JSON en un único array
- **Marcado de procesados**: Renombra archivos agregando "_ok" después de procesarlos
- **Filtrado**: Solo procesa archivos con prefijo `recopilacion_`

### Evaluador
- **Generación de embeddings**: Usa Azure OpenAI `text-embedding-ada-002` (dimensión 1536)
- **Procesamiento en batch**: Genera embeddings en lotes de 100 para eficiencia
- **Índice FAISS**: Construye índice `IndexFlatIP` con normalización L2 para similitud coseno
- **Metadatos**: Almacena textos y metadatos en archivo pickle separado
- **Índices por tag**: Soporte para crear índices específicos filtrados por tag
- **Límite de texto**: Trunca textos a 8000 caracteres antes de generar embeddings

### Buscador
- **Búsqueda semántica**: Genera embedding de la query y busca en FAISS
- **Selección de índice**: Busca automáticamente el índice más reciente o uno específico por tag
- **Normalización**: Normaliza vectores para similitud coseno
- **Resultados ordenados**: Retorna resultados ordenados por score de similitud (mayor = más similar)
- **Manejo de índices**: Carga índices desde Azure Storage a archivos temporales
- **Fallback**: Si no encuentra índice específico por tag, usa el índice general

**Curiosidad técnica**: El buscador soporta búsqueda por tag para usar índices filtrados (ej: solo artículos sobre Alzheimer). Si el índice específico no existe, automáticamente hace fallback al índice general.

## Dependencias principales

- `azure-functions` - Runtime de Azure Functions
- `azure-storage-blob` - Cliente de Azure Blob Storage
- `openai` - SDK de OpenAI (compatible con Azure OpenAI)
- `faiss-cpu` - Biblioteca FAISS para búsqueda vectorial
- `numpy` - Operaciones numéricas con arrays
- `biopython` - Acceso a APIs de NCBI
- `requests` - Peticiones HTTP a TheNewsAPI
- `python-dotenv` - Carga de variables de entorno (opcional)

## Documentación

Para más detalles técnicos sobre cada función, consulta los comentarios en los archivos `*_service.py` de cada directorio. Cada servicio está completamente documentado con docstrings explicando métodos y parámetros.

---

[← Volver al README principal](../README.md)

