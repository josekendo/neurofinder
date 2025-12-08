#!/usr/bin/env python3
"""
Script principal para ejecutar el evaluador localmente.
Carga variables de entorno desde .env usando python-dotenv.
Similar a la ejecución en Azure Functions pero con datos locales.
"""
import os
import sys
import json
from pathlib import Path
from dotenv import load_dotenv
import logging
from evaluador_service import EvaluadorService

# Configurar logging básico para ver la salida
logging.basicConfig(level=logging.INFO, format='%(levelname)s:%(name)s:%(message)s')

# Cargar variables de entorno desde .env (en el directorio raíz)
root_dir = Path(__file__).parent.parent
env_path = root_dir / '.env'
if env_path.exists():
    load_dotenv(env_path)
    logging.info(f"✅ Variables de entorno cargadas desde: {env_path}")
else:
    logging.warning(f"⚠️  Archivo .env no encontrado en: {env_path}")
    logging.info("   Usando variables del entorno del sistema...")

# Agregar el directorio actual al path para importar el servicio
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

def main():
    """Función principal - similar a la función de Azure"""
    
    logging.info("\n" + "="*60)
    logging.info("EVALUADOR - CONSTRUCCIÓN DE ÍNDICE FAISS (EJECUCIÓN LOCAL)")
    logging.info("="*60 + "\n")
    
    # ============================================
    # CONFIGURACIÓN
    # Puedes cargar items desde un archivo JSON o definirlos aquí
    # ============================================
    
    # Opción 1: Cargar desde archivo JSON
    # items_file = Path(__file__).parent.parent / "items_ejemplo.json"
    # if items_file.exists():
    #     with open(items_file, 'r', encoding='utf-8') as f:
    #         data = json.load(f)
    #         items = data.get('items', [])
    # else:
    #     logging.warning(f"Archivo no encontrado: {items_file}")
    #     items = []
    
    # Opción 2: Items de ejemplo (descomenta para usar)
    items = [
        {
            "url": "https://example.com/article1",
            "tipo": "article",
            "title": "Ejemplo de artículo sobre Alzheimer",
            "summary": "Este es un resumen del artículo sobre la enfermedad de Alzheimer y sus síntomas tempranos.",
            "key_points": [
                "La pérdida de memoria es un síntoma temprano",
                "Los biomarcadores pueden detectar la enfermedad",
                "El tratamiento temprano es importante"
            ]
        }
    ]
    
    # Nombre opcional para el archivo del índice
    nombre_archivo = None  # None para generar automáticamente con fecha/hora
    
    # ============================================
    
    try:
        # Crear servicio de evaluación
        service = EvaluadorService()
        
        # Ejecutar evaluación
        logging.info("Iniciando construcción del índice FAISS...")
        resultado = service.ejecutar_evaluacion(items, nombre_archivo)
        
        # Mostrar resultado (similar a la respuesta de Azure Function)
        logging.info("\n" + "="*60)
        logging.info("RESULTADO DE LA EVALUACIÓN")
        logging.info("="*60)
        logging.info(json.dumps(resultado, ensure_ascii=False, indent=2))
        logging.info("="*60)
        
        logging.info("\n✅ EVALUACIÓN COMPLETADA EXITOSAMENTE")
        
        return 0
        
    except KeyboardInterrupt:
        logging.warning("\n\n⚠️  Evaluación cancelada por el usuario.")
        return 130
        
    except Exception as e:
        logging.error("\n" + "="*60)
        logging.error("❌ ERROR EN LA EVALUACIÓN")
        logging.error("="*60)
        logging.error(f"Error: {str(e)}", exc_info=True)
        return 1


if __name__ == "__main__":
    sys.exit(main())

