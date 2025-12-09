# Azure Functions - Procesamiento Serverless

Las Azure Functions son el **cerebro de procesamiento** de NeuroFinder. Son funciones serverless en Python que se encargan de recopilar datos, procesarlos, generar embeddings y realizar búsquedas semánticas.

## ¿Qué hace?

Este módulo contiene 4 funciones principales que trabajan en cadena:

1. **`recopilador`** - Recopila artículos científicos de NCBI y noticias de TheNewsAPI
2. **`procesador`** - Compacta y organiza los datos recopilados
3. **`evaluador`** - Genera embeddings con IA y construye índices FAISS para búsqueda semántica
4. **`buscador`** - Realiza búsquedas en el índice FAISS usando embeddings

## ¿Cómo se comunica?

### Con el Backend PHP

El backend PHP llama a estas funciones cuando está en modo `active`:

```
Backend PHP → GET /api/buscador?q=query → Azure Functions
Backend PHP → GET /api/procesamiento → Azure Functions
```

### Con Azure Storage

Todas las funciones utilizan **Azure Blob Storage** para:
- Almacenar artículos y noticias en JSON
- Guardar índices FAISS generados
- Mantener metadatos del sistema

### Con servicios externos

- **NCBI (PubMed)**: Para obtener artículos científicos sobre demencias
- **TheNewsAPI**: Para recopilar noticias recientes
- **Azure OpenAI**: Para generar embeddings y procesar texto con IA

### Flujo de trabajo típico

```
1. recopilador → Obtiene datos de fuentes externas → Guarda en Azure Storage
2. procesador → Lee archivos JSON del storage → Compacta en un único conjunto
3. evaluador → Genera embeddings con Azure OpenAI → Crea índice FAISS → Guarda en storage
4. buscador → Recibe query → Genera embedding de la query → Busca en FAISS → Devuelve resultados
```

## Estructura

```
Functions Azure/
├── function_app.py              # Registro de todas las funciones HTTP
├── requirements.txt             # Dependencias Python
├── host.json                    # Configuración global
├── local.settings.json          # Variables de entorno locales
├── recopilador/
│   ├── main.py                 # Entry point de la función
│   └── recopilador_service.py  # Lógica de recopilación
├── procesador/
│   ├── main.py
│   └── procesador_service.py   # Lógica de procesamiento
├── evaluador/
│   ├── main.py
│   └── evaluador_service.py    # Lógica de embeddings e índices
└── buscador/
    ├── main.py
    └── buscador_service.py     # Lógica de búsqueda semántica
```

## Funciones disponibles

### GET /api/recopilador

Recopila artículos y noticias. Parámetros opcionales:
- `week` - Número de semana (1-53)
- `year` - Año (YYYY)

### GET /api/procesamiento

Compacta archivos JSON almacenados en Azure Storage en un único conjunto de datos.

### POST /api/evaluacion

Construye un índice FAISS con embeddings. Recibe en el body:
```json
{
  "items": [...],
  "nombre_archivo": "indice_2024" // opcional
}
```

### GET /api/buscador

Busca en el índice FAISS. Parámetros:
- `q` o `query` - Texto a buscar (requerido)
- `k` o `limit` - Número de resultados (1-100, por defecto 40)
- `tag` - Etiqueta para buscar en índice específico (opcional)

## Puesta en marcha local

```bash
cd "Functions Azure"
python -m venv .venv
source .venv/bin/activate  # En Windows: .venv\Scripts\activate
pip install -r requirements.txt
func start
```

Asegúrate de configurar las variables de entorno en `local.settings.json`:
- `AzureWebJobsStorage` - Cadena de conexión a Azure Storage
- `AZURE_OPENAI_ENDPOINT` - Endpoint de Azure OpenAI
- `AZURE_OPENAI_API_KEY` - Clave API de Azure OpenAI
- `NCBI_API_KEY` - (Opcional) Clave API de NCBI
- `THENEWSAPI_KEY` - Clave API de TheNewsAPI

## Tecnologías principales

- **Azure Functions v2** con Python 3.11
- **FAISS** para búsqueda vectorial eficiente
- **Azure OpenAI** para generación de embeddings
- **Azure Blob Storage** para persistencia

## Documentación

Para más detalles técnicos sobre cada función, consulta los comentarios en los archivos `*_service.py` de cada directorio.

---

[← Volver al README principal](../README.md)

