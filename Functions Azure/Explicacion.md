## Azure Functions (Python)

Conjunto de funciones HTTP escritas en Python (runtime 3.11) que representan la futura orquestación de ingesta, procesamiento, evaluación y consulta de contenidos. Actualmente ofrece endpoints stub para validar el flujo extremo a extremo desde el backend PHP.

- **Estructura del proyecto**:
  - `function_app.py` registra cada función dentro de un `FunctionApp` con nivel de autenticación `FUNCTION`.
  - Directorios `recopilador/`, `procesador/`, `evaluador/` y `buscador/` reservan el espacio para el código específico de cada etapa (por ahora vacíos).
  - `host.json` configura Application Insights y la versión del runtime; `requirements.txt` declara la dependencia `azure-functions`.
  - `local.settings.json` incluye valores de desarrollo (almacenamiento y `FUNCTIONS_WORKER_RUNTIME`). En producción deben moverse a Azure Key Vault o variables de aplicación.

- **Funciones expuestas**:
  - `GET /api/recopilador`: stub que registra en logs el inicio de la ingesta y responde `Recopilando...`.
  - `GET /api/procesamiento`: stub para la etapa de normalización y enriquecimiento (`Procesamiento...`).
  - `GET /api/evaluacion`: stub para evaluación de calidad y scoring (`Evaluando...`).
  - `GET /api/buscador`: stub del endpoint que servirá respuestas semánticas (`Buscando...`).
  Todas las funciones se declaran como HTTP GET/POST compatibles (`@app.route` sin restricción explícita) y actualmente retornan texto plano con `status_code=200`.

- **Flujo previsto**:
  1. **Recopilador**: conectará con fuentes externas (RSS, APIs científicas) y almacenará los documentos crudos en Blob Storage.
  2. **Procesador**: transformará los documentos, extraerá metadatos y generará embeddings.
  3. **Evaluador**: aplicará reglas de calidad y modelos de clasificación para determinar relevancia y fiabilidad.
  4. **Buscador**: expondrá un endpoint optimizado que consultará la base vectorial y devolverá resultados al backend PHP.
  Cada etapa se comunicará mediante colas y disparadores (pendiente de implementación); el contrato HTTP actual sirve como marcador funcional.

- **Despliegue**:
  - Requiere Azure Functions Core Tools (`func azure functionapp publish <nombre_app>`).
  - Antes de desplegar, revisar credenciales en `local.settings.json` y sustituirlas por referencias seguras.
  - Para desarrollo local: `python -m venv .venv`, activar entorno, instalar dependencias con `pip install -r requirements.txt` y ejecutar `func start`.

En el estado actual las funciones operan como mocks, permitiendo validar la integración desde el frontend hasta la capa serverless. La migración a lógica real consistirá en completar cada directorio con el código correspondiente y ampliar los bindings (Storage Queue, Timer, etc.) según el flujo definitivo.

