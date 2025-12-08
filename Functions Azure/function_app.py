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
    logging.info('Procesamiento comenzando.')

    return func.HttpResponse(
            "Procesamiento...",
            status_code=200
    )

@app.function_name(name="evaluador")
@app.route(route="evaluacion")
def evaluacion(req: func.HttpRequest) -> func.HttpResponse:
    logging.info('Evaluacion comenzando.')

    return func.HttpResponse(
            "Evaluando...",
            status_code=200
    )

@app.function_name(name="buscador")
@app.route(route="buscador")
def buscador(req: func.HttpRequest) -> func.HttpResponse:
    logging.info('Buscador comenzando.')

    return func.HttpResponse(
            "Buscando...",
            status_code=200
    )