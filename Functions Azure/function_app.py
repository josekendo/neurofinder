import azure.functions as func
import logging
import os
import json
import sys

# Agregar el directorio recopilador al path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'recopilador'))

from recopilador_service import RecopiladorService

app = func.FunctionApp(http_auth_level=func.AuthLevel.FUNCTION)

@app.function_name(name="recopilador")
@app.route(route="recopilador")
def recopilador(req: func.HttpRequest) -> func.HttpResponse:
    """
    Azure Function que recopila artículos de NCBI y noticias de TheNewsAPI,
    los procesa con IA y los almacena en Azure Storage.
    """
    logging.info('Recopilador comenzando.')
    
    try:
        # Obtener parámetros opcionales de la query string
        week_param = req.params.get('week')
        year_param = req.params.get('year')
        
        # Crear servicio de recopilación
        service = RecopiladorService()
        
        # Ejecutar recopilación
        resultado = service.ejecutar_recopilacion(
            week=week_param,
            year=year_param
        )
        
        # Retornar resultado como JSON
        return func.HttpResponse(
            json.dumps(resultado, ensure_ascii=False, indent=2),
            status_code=200,
            mimetype="application/json"
        )
        
    except Exception as e:
        logging.error(f'Error en recopilador: {str(e)}', exc_info=True)
        return func.HttpResponse(
            json.dumps({
                "error": str(e),
                "articulos_encontrados": 0,
                "noticias_encontradas": 0,
                "total_procesados": 0
            }, ensure_ascii=False),
            status_code=500,
            mimetype="application/json"
        )

@app.function_name(name="procesador")
@app.route(route="procesamiento")
def procesamiento(req: func.HttpRequest) -> func.HttpResponse:
    """
    Azure Function que lee archivos JSON del storage y los compacta en una única respuesta.
    """
    logging.info('Procesamiento comenzando.')
    
    try:
        # Agregar el directorio procesador al path
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'procesador'))
        from procesador_service import ProcesadorService
        
        # Crear servicio de procesamiento
        service = ProcesadorService()
        
        # Compactar archivos
        resultado = service.compactar_archivos()
        
        # Retornar resultado como JSON
        return func.HttpResponse(
            json.dumps(resultado, ensure_ascii=False, indent=2),
            status_code=200,
            mimetype="application/json"
        )
        
    except Exception as e:
        logging.error(f'Error en procesador: {str(e)}', exc_info=True)
        return func.HttpResponse(
            json.dumps({
                "error": str(e),
                "items": [],
                "total_items": 0,
                "archivos_procesados": 0
            }, ensure_ascii=False),
            status_code=500,
            mimetype="application/json"
        )

@app.function_name(name="evaluador")
@app.route(route="evaluacion", methods=["POST"])
def evaluacion(req: func.HttpRequest) -> func.HttpResponse:
    """
    Azure Function que construye un índice FAISS con embeddings y lo sube al storage.
    Recibe items en el body de la petición POST.
    """
    logging.info('Evaluacion comenzando.')
    
    try:
        # Agregar el directorio evaluador al path
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'evaluador'))
        from evaluador_service import EvaluadorService
        
        # Obtener items del body
        body = req.get_body()
        if not body:
            return func.HttpResponse(
                json.dumps({"error": "No se proporcionaron items en el body"}, ensure_ascii=False),
                status_code=400,
                mimetype="application/json"
            )
        
        data = json.loads(body.decode('utf-8'))
        items = data.get('items', [])
        
        if not items:
            return func.HttpResponse(
                json.dumps({"error": "La lista de items está vacía"}, ensure_ascii=False),
                status_code=400,
                mimetype="application/json"
            )
        
        # Obtener nombre de archivo opcional
        nombre_archivo = data.get('nombre_archivo')
        
        # Crear servicio de evaluación
        service = EvaluadorService()
        
        # Ejecutar evaluación
        resultado = service.ejecutar_evaluacion(items, nombre_archivo)
        
        # Retornar resultado como JSON
        return func.HttpResponse(
            json.dumps(resultado, ensure_ascii=False, indent=2),
            status_code=200,
            mimetype="application/json"
        )
        
    except json.JSONDecodeError as e:
        logging.error(f'Error al parsear JSON: {str(e)}', exc_info=True)
        return func.HttpResponse(
            json.dumps({"error": f"Error al parsear JSON: {str(e)}"}, ensure_ascii=False),
            status_code=400,
            mimetype="application/json"
        )
    except Exception as e:
        logging.error(f'Error en evaluador: {str(e)}', exc_info=True)
        return func.HttpResponse(
            json.dumps({
                "error": str(e),
                "items_procesados": 0,
                "vectores_indexados": 0
            }, ensure_ascii=False),
            status_code=500,
            mimetype="application/json"
        )

@app.function_name(name="buscador")
@app.route(route="buscador")
def buscador(req: func.HttpRequest) -> func.HttpResponse:
    """
    Azure Function que busca en el índice FAISS usando embeddings.
    Recibe la query como parámetro 'q' o 'query' en la query string.
    Devuelve los 40 resultados más cercanos.
    """
    logging.info('Buscador comenzando.')
    
    try:
        # Agregar el directorio buscador al path
        sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'buscador'))
        from buscador_service import BuscadorService
        
        # Obtener query de los parámetros
        query = req.params.get('q') or req.params.get('query')
        
        if not query:
            return func.HttpResponse(
                json.dumps({
                    "error": "Parámetro 'q' o 'query' requerido",
                    "resultados": [],
                    "total": 0
                }, ensure_ascii=False),
                status_code=400,
                mimetype="application/json"
            )
        
        # Obtener número de resultados (opcional, por defecto 40)
        k_param = req.params.get('k') or req.params.get('limit')
        k = 40
        if k_param:
            try:
                k = int(k_param)
                if k < 1 or k > 100:
                    k = 40  # Limitar entre 1 y 100
            except ValueError:
                k = 40
        
        # Obtener tag opcional para buscar en índice específico
        tag = req.params.get('tag')
        
        # Crear servicio de búsqueda
        service = BuscadorService()
        
        # Ejecutar búsqueda
        resultado = service.ejecutar_busqueda(query, k, tag=tag)
        
        # Retornar resultado como JSON
        return func.HttpResponse(
            json.dumps(resultado, ensure_ascii=False, indent=2),
            status_code=200,
            mimetype="application/json"
        )
        
    except Exception as e:
        logging.error(f'Error en buscador: {str(e)}', exc_info=True)
        return func.HttpResponse(
            json.dumps({
                "error": str(e),
                "resultados": [],
                "total": 0
            }, ensure_ascii=False),
            status_code=500,
            mimetype="application/json"
        )