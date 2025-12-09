"""
Servicio procesador que lee archivos JSON del storage, los compacta y genera el índice FAISS.
Lee todos los archivos que NO tengan "ok" en el nombre.
"""

import os
import json
import logging
from typing import List, Dict, Optional
from azure.storage.blob import BlobServiceClient
from azure.core.exceptions import AzureError


class ProcesadorService:
    """Servicio para procesar archivos JSON del storage"""
    
    def __init__(self):
        """Inicializar servicio con configuración desde variables de entorno"""
        # Configuración de Azure Storage
        storage_conn = os.getenv('AZURE_STORAGE_CONNECTION_STRING') or os.getenv('AzureWebJobsStorage', '')
        # Limpiar comillas y espacios que puedan venir del .env
        self.storage_connection_string = storage_conn.strip().strip('"').strip("'") if storage_conn else ''
        self.storage_container = os.getenv('STORAGE_CONTAINER', 'recopilaciones')
        
        if not self.storage_connection_string:
            logging.warning("AZURE_STORAGE_CONNECTION_STRING o AzureWebJobsStorage no configurado.")
    
    def listar_archivos_pendientes(self) -> List[str]:
        """
        Lista todos los archivos JSON en el storage que NO tengan "ok" en el nombre.
        
        Returns:
            Lista de nombres de archivos pendientes de procesar
        """
        if not self.storage_connection_string:
            logging.warning("Storage no configurado. No se pueden listar archivos.")
            return []
        
        try:
            # Crear cliente de Blob Service
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            
            # Obtener cliente del contenedor
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            if not container_client.exists():
                logging.warning(f"El contenedor '{self.storage_container}' no existe.")
                return []
            
            # Listar todos los blobs
            archivos_pendientes = []
            for blob in container_client.list_blobs(name_starts_with="recopilacion_"):
                nombre_archivo = blob.name
                # Filtrar archivos que NO tengan "ok" en el nombre
                if "ok" not in nombre_archivo.lower():
                    archivos_pendientes.append(nombre_archivo)
            
            logging.info(f"Archivos pendientes encontrados: {len(archivos_pendientes)}")
            return archivos_pendientes
            
        except AzureError as e:
            logging.error(f"Error de Azure Storage al listar archivos: {e}", exc_info=True)
            return []
        except Exception as e:
            logging.error(f"Error inesperado al listar archivos: {e}", exc_info=True)
            return []
    
    def leer_archivo_json(self, nombre_archivo: str) -> List[Dict]:
        """
        Lee un archivo JSON del storage, retorna su contenido y lo renombra agregando "ok".
        
        Args:
            nombre_archivo: Nombre del archivo en el storage
        
        Returns:
            Lista de items del archivo JSON
        """
        if not self.storage_connection_string:
            return []
        
        try:
            # Crear cliente de Blob Service
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            
            # Obtener cliente del contenedor
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            # Obtener cliente del blob
            blob_client = container_client.get_blob_client(nombre_archivo)
            
            if not blob_client.exists():
                logging.warning(f"El archivo '{nombre_archivo}' no existe en el storage.")
                return []
            
            # Descargar y leer el archivo
            contenido = blob_client.download_blob().readall()
            items = json.loads(contenido.decode('utf-8'))
            
            if not isinstance(items, list):
                logging.warning(f"El archivo '{nombre_archivo}' no contiene un array JSON válido.")
                items = []
            
            # Renombrar archivo agregando "ok" antes de la extensión
            self._renombrar_archivo_ok(container_client, nombre_archivo)
            
            logging.info(f"Archivo '{nombre_archivo}' leído: {len(items)} items y renombrado")
            return items
            
        except json.JSONDecodeError as e:
            logging.error(f"Error al parsear JSON del archivo '{nombre_archivo}': {e}")
            # Renombrar incluso si hay error de parseo
            try:
                blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
                container_client = blob_service_client.get_container_client(self.storage_container)
                self._renombrar_archivo_ok(container_client, nombre_archivo)
            except:
                pass
            return []
        except AzureError as e:
            logging.error(f"Error de Azure Storage al leer archivo '{nombre_archivo}': {e}", exc_info=True)
            # Intentar renombrar incluso si hay error
            try:
                blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
                container_client = blob_service_client.get_container_client(self.storage_container)
                self._renombrar_archivo_ok(container_client, nombre_archivo)
            except:
                pass
            return []
        except Exception as e:
            logging.error(f"Error inesperado al leer archivo '{nombre_archivo}': {e}", exc_info=True)
            # Intentar renombrar incluso si hay error
            try:
                blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
                container_client = blob_service_client.get_container_client(self.storage_container)
                self._renombrar_archivo_ok(container_client, nombre_archivo)
            except:
                pass
            return []
    
    def _normalizar_item(self, item: Dict) -> Dict:
        """
        Normaliza un item para asegurar que siempre tenga el campo language.
        
        Args:
            item: Diccionario con información del item
            
        Returns:
            Diccionario con el item normalizado
        """
        # Idiomas válidos
        idiomas_validos = ['es', 'en', 'fr', 'de', 'it', 'pt']
        
        # Obtener idioma del item o usar 'en' por defecto
        language = item.get('language', 'en')
        
        # Si el idioma es una cadena, limpiarla y normalizar
        if isinstance(language, str):
            language = language.strip().lower()
            # Si el idioma contiene múltiples valores separados por coma, tomar el primero
            if ',' in language:
                language = language.split(',')[0].strip()
            # Si el idioma no es válido, usar 'en' por defecto
            if language not in idiomas_validos:
                language = 'en'
        else:
            # Si no es string o está vacío, usar 'en' por defecto
            language = 'en'
        
        # Asegurar que el item siempre tenga el campo language
        item['language'] = language
        
        return item
    
    def _renombrar_archivo_ok(self, container_client, nombre_archivo: str) -> None:
        """
        Renombra un archivo agregando "_ok" antes de la extensión.
        
        Args:
            container_client: Cliente del contenedor de Azure Storage
            nombre_archivo: Nombre del archivo original
        """
        try:
            # Generar nuevo nombre: agregar "_ok" antes de .json
            if nombre_archivo.endswith('.json'):
                nuevo_nombre = nombre_archivo[:-5] + '_ok.json'
            else:
                nuevo_nombre = nombre_archivo + '_ok'
            
            # Obtener clientes de blob
            blob_origen = container_client.get_blob_client(nombre_archivo)
            blob_destino = container_client.get_blob_client(nuevo_nombre)
            
            # Copiar el blob con el nuevo nombre
            blob_destino.start_copy_from_url(blob_origen.url)
            
            # Esperar a que la copia se complete
            import time
            props = blob_destino.get_blob_properties()
            copy_id = props.copy.id if props.copy else None
            while props.copy.status == 'pending':
                time.sleep(0.5)
                props = blob_destino.get_blob_properties()
                if props.copy.status != 'pending':
                    break
            
            # Si la copia fue exitosa, eliminar el archivo original
            if props.copy.status == 'success':
                blob_origen.delete_blob()
                logging.info(f"Archivo renombrado: '{nombre_archivo}' -> '{nuevo_nombre}'")
            else:
                logging.warning(f"Error al copiar archivo '{nombre_archivo}': {props.copy.status}")
                
        except Exception as e:
            logging.error(f"Error al renombrar archivo '{nombre_archivo}': {e}", exc_info=True)
    
    def compactar_archivos(self) -> Dict:
        """
        Lee todos los archivos pendientes, los compacta y genera el índice FAISS.
        
        Returns:
            Diccionario con todos los items compactados, estadísticas y resultado del índice FAISS
        """
        logging.info("=== INICIANDO PROCESAMIENTO ===")
        
        # Listar archivos pendientes
        archivos_pendientes = self.listar_archivos_pendientes()
        
        if not archivos_pendientes:
            logging.info("No hay archivos pendientes de procesar.")
            return {
                "items": [],
                "total_items": 0,
                "archivos_procesados": 0,
                "archivos": [],
                "indice_faiss": None
            }
        
        # Leer todos los archivos y compactar
        todos_items = []
        archivos_procesados = []
        
        for nombre_archivo in archivos_pendientes:
            logging.info(f"Leyendo archivo: {nombre_archivo}")
            items = self.leer_archivo_json(nombre_archivo)
            
            if items:
                # Normalizar items para asegurar que todos tengan idioma
                items_normalizados = [self._normalizar_item(item) for item in items]
                todos_items.extend(items_normalizados)
                archivos_procesados.append(nombre_archivo)
                logging.info(f"  ✓ {len(items)} items agregados")
            else:
                logging.warning(f"  ⚠ Archivo '{nombre_archivo}' vacío o con errores")
        
        logging.info(f"Total de items compactados: {len(todos_items)}")
        logging.info(f"Archivos procesados: {len(archivos_procesados)}")
        
        # Generar índice FAISS con los items compactados
        resultado_indice = None
        if todos_items:
            logging.info("=== GENERANDO ÍNDICE FAISS ===")
            resultado_indice = self._generar_indice_faiss(todos_items)
        
        return {
            "items": todos_items,
            "total_items": len(todos_items),
            "archivos_procesados": len(archivos_procesados),
            "archivos": archivos_procesados,
            "indice_faiss": resultado_indice
        }
    
    def _generar_indice_faiss(self, items: List[Dict]) -> Optional[Dict]:
        """
        Genera el índice FAISS usando el servicio evaluador.
        
        Args:
            items: Lista de items para indexar
        
        Returns:
            Diccionario con resultado del índice FAISS o None si hay error
        """
        try:
            # Importar evaluador (importación dinámica para evitar dependencias circulares)
            import sys
            import os
            evaluador_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'evaluador')
            if evaluador_path not in sys.path:
                sys.path.insert(0, evaluador_path)
            
            from evaluador_service import EvaluadorService
            
            # Crear servicio evaluador
            evaluador = EvaluadorService()
            
            # Ejecutar evaluación (construye índice y lo sube al storage)
            resultado = evaluador.ejecutar_evaluacion(items)
            
            if resultado.get('error'):
                logging.error(f"Error al generar índice FAISS: {resultado.get('error')}")
                return None
            
            logging.info("Índice FAISS generado y subido exitosamente")
            return resultado
            
        except ImportError as e:
            logging.error(f"Error al importar evaluador_service: {e}", exc_info=True)
            return None
        except Exception as e:
            logging.error(f"Error al generar índice FAISS: {e}", exc_info=True)
            return None

