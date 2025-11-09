import azure.functions as func
import logging

app = func.FunctionApp(http_auth_level=func.AuthLevel.FUNCTION)

@app.function_name(name="recopilador")
@app.route(route="recopilador")
def recopilador(req: func.HttpRequest) -> func.HttpResponse:
    logging.info('Recopilador comenzando.')

    return func.HttpResponse(
            "Recopilando...",
            status_code=200
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