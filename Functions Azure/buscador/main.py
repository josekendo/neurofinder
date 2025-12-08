#!/usr/bin/env python3
"""
Script principal para ejecutar el buscador localmente.
Carga variables de entorno desde .env usando python-dotenv.
Similar a la ejecución en Azure Functions pero con parámetros manuales.
"""
import os
import sys
import json
from pathlib import Path
from dotenv import load_dotenv
import logging
from buscador_service import BuscadorService

# Cargar variables de entorno desde .env (en el directorio raíz)
root_dir = Path(__file__).parent.parent
env_path = root_dir / '.env'
if env_path.exists():
    load_dotenv(env_path)
    print(f"✅ Variables de entorno cargadas desde: {env_path}")
else:
    load_dotenv()  # Intenta cargar desde el directorio actual
    print("⚠️  Archivo .env no encontrado, usando variables del sistema...")

logging.basicConfig(level=logging.INFO)

# Agregar el directorio actual al path para importar el servicio
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

def main():
    """Función principal - similar a la función de Azure"""
    
    logging.info("\n" + "="*60)
    logging.info("BUSCADOR - BÚSQUEDA EN ÍNDICE FAISS (EJECUCIÓN LOCAL)")
    logging.info("="*60 + "\n")
    
    # ============================================
    # CONFIGURACIÓN DE PARÁMETROS
    # Edita estos valores según necesites
    # ============================================
    query = "síntomas tempranos de Alzheimer"  # Query de búsqueda
    k = 40  # Número de resultados (por defecto 40)
    
    # Ejemplo de otras queries:
    # query = "biomarcadores para detectar demencia"
    # query = "tratamientos para Alzheimer"
    # ============================================
    
    try:
        # Crear servicio de búsqueda
        service = BuscadorService()
        
        # Ejecutar búsqueda
        logging.info(f"Iniciando búsqueda con query: '{query}'")
        resultado = service.ejecutar_busqueda(query, k)
        
        # Mostrar resultado (similar a la respuesta de Azure Function)
        logging.info("\n" + "="*60)
        logging.info("RESULTADO DE LA BÚSQUEDA")
        logging.info("="*60)
        logging.info(json.dumps(resultado, ensure_ascii=False, indent=2))
        logging.info("="*60)
        
        logging.info("\n✅ BÚSQUEDA COMPLETADA EXITOSAMENTE")
        
        return 0
        
    except KeyboardInterrupt:
        logging.warning("\n\n⚠️  Búsqueda cancelada por el usuario.")
        return 130
        
    except Exception as e:
        logging.error("\n" + "="*60)
        logging.error("❌ ERROR EN LA BÚSQUEDA")
        logging.error("="*60)
        logging.error(f"Error: {str(e)}", exc_info=True)
        return 1


if __name__ == "__main__":
    sys.exit(main())

