#!/usr/bin/env python3
"""
Script principal para ejecutar el procesador localmente.
Carga variables de entorno desde .env usando python-dotenv.
Similar a la ejecución en Azure Functions pero con parámetros manuales.
"""
import os
import sys
import json
from pathlib import Path
from dotenv import load_dotenv
import logging
from procesador_service import ProcesadorService

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

def main():
    """Función principal - similar a la función de Azure"""
    
    logging.info('Procesador comenzando.')
    
    try:
        # Crear servicio de procesamiento
        service = ProcesadorService()
        
        # Compactar archivos
        resultado = service.compactar_archivos()
        
        # Mostrar resultado (similar a la respuesta de Azure Function)
        print("\n" + "="*60)
        print("RESULTADO DEL PROCESAMIENTO")
        print("="*60)
        print(json.dumps(resultado, ensure_ascii=False, indent=2))
        print("="*60)
        
        return 0
        
    except Exception as e:
        logging.error(f'Error en procesador: {str(e)}', exc_info=True)
        print("\n" + "="*60)
        print("❌ ERROR EN EL PROCESAMIENTO")
        print("="*60)
        print(json.dumps({
            "error": str(e),
            "items": [],
            "total_items": 0,
            "archivos_procesados": 0
        }, ensure_ascii=False, indent=2))
        return 1

if __name__ == "__main__":
    sys.exit(main())

