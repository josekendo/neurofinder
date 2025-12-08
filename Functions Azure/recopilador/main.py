#!/usr/bin/env python3
"""
Script principal para ejecutar el recopilador localmente.
Carga variables de entorno desde .env usando python-dotenv.
Similar a la ejecución en Azure Functions pero con parámetros manuales.
"""
import os
import sys
import json
from pathlib import Path
from dotenv import load_dotenv
import logging
from recopilador_service import RecopiladorService

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
    
    logging.info('Recopilador comenzando.')
    
    # ============================================
    # CONFIGURACIÓN DE PARÁMETROS
    # Edita estos valores según necesites
    # ============================================
    week_param = 40  # Número de semana (1-53) o None para semana actual
    year_param = 2025  # Año (YYYY) o None para año actual
    
    # Ejemplo: Para buscar semana 45 del 2024
    # week_param = "45"
    # year_param = "2024"
    # ============================================
    
    try:
        # Crear servicio de recopilación
        service = RecopiladorService()
        
        # Ejecutar recopilación
        resultado = service.ejecutar_recopilacion(
            week=week_param,
            year=year_param
        )
        
        # Mostrar resultado como JSON (similar a la respuesta de Azure Function)
        print("\n" + "="*60)
        print("RESULTADO DE LA RECOPILACIÓN")
        print("="*60)
        print(json.dumps(resultado, ensure_ascii=False, indent=2))
        print("="*60)
        
        return 0
        
    except Exception as e:
        logging.error(f'Error en recopilador: {str(e)}', exc_info=True)
        print("\n" + "="*60)
        print("❌ ERROR EN LA RECOPILACIÓN")
        print("="*60)
        print(json.dumps({
            "error": str(e),
            "articulos_encontrados": 0,
            "noticias_encontradas": 0,
            "total_procesados": 0
        }, ensure_ascii=False, indent=2))
        return 1

if __name__ == "__main__":
    sys.exit(main())