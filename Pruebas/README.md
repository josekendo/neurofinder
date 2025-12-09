# Pruebas y Experimentación

Esta carpeta contiene **notebooks de Jupyter** utilizados durante la fase de investigación y desarrollo del proyecto. Son herramientas de experimentación para probar integraciones con servicios externos antes de incorporarlas al código de producción.

## ¿Qué contiene?

Cada notebook prueba una funcionalidad específica:

- **`ncbi_alzheimer_publications.ipynb`** - Experimentación con la API de NCBI/PubMed para obtener publicaciones sobre Alzheimer
- **`thenewsapi_alzheimer_news.ipynb`** - Pruebas con TheNewsAPI para recopilar noticias relacionadas
- **`azure_storage_operations.ipynb`** - Experimentación con operaciones de Azure Blob Storage (subir, leer, listar archivos)
- **`azure_openai_operations.ipynb`** - Pruebas con Azure OpenAI para generar embeddings y procesar texto
- **`faiss_azure_embeddings.ipynb`** - Experimentación con la creación de índices FAISS y búsqueda vectorial

## Propósito

Estos notebooks fueron utilizados para:

1. **Validar APIs externas** antes de implementarlas en las Azure Functions
2. **Experimentar con modelos de embeddings** y encontrar la mejor configuración
3. **Probar la integración con Azure Storage** y entender el flujo de datos
4. **Desarrollar prototipos rápidos** sin necesidad de ejecutar funciones completas

## Uso

Estos archivos son principalmente de referencia histórica y documentación del proceso de investigación. Si quieres ejecutarlos:

```bash
# Instalar Jupyter
pip install jupyter notebook

# Ejecutar notebook
jupyter notebook nombre_del_notebook.ipynb
```

**Nota**: Necesitarás configurar las variables de entorno o credenciales necesarias para cada servicio que pruebes.

---

[← Volver al README principal](../README.md)

