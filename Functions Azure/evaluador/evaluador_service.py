"""
Servicio evaluador que construye un índice FAISS con embeddings de Azure OpenAI
y lo sube al storage. Agrega al índice los puntos clave (key_points) y el resumen (summary).
"""

import os
import json
import logging
import pickle
import tempfile
from typing import List, Dict, Optional, Tuple
import numpy as np
import faiss
from openai import AzureOpenAI
from azure.storage.blob import BlobServiceClient, ContentSettings
from azure.core.exceptions import AzureError


class EvaluadorService:
    """Servicio para construir índices FAISS con embeddings"""
    
    # Dimensión de los embeddings de ada-002
    EMBEDDING_DIMENSION = 1536
    
    def __init__(self):
        """Inicializar servicio con configuración desde variables de entorno"""
        # Configuración de Azure OpenAI
        self.azure_openai_endpoint = os.getenv('AZURE_OPENAI_ENDPOINT', '').strip().strip('"').strip("'")
        self.azure_openai_api_key = os.getenv('AZURE_OPENAI_API_KEY', '').strip().strip('"').strip("'")
        self.azure_openai_api_version = os.getenv('AZURE_OPENAI_API_VERSION', '2023-05-15')
        self.azure_openai_embedding_deployment = os.getenv('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-ada-002')
        
        # Configuración de Azure Storage
        storage_conn = os.getenv('AZURE_STORAGE_CONNECTION_STRING') or os.getenv('AzureWebJobsStorage', '')
        self.storage_connection_string = storage_conn.strip().strip('"').strip("'") if storage_conn else ''
        self.storage_container = os.getenv('STORAGE_CONTAINER', 'recopilaciones')
        
        # Validar configuración
        if not self.azure_openai_endpoint or not self.azure_openai_api_key:
            logging.warning("Azure OpenAI no configurado para embeddings.")
        if not self.storage_connection_string:
            logging.warning("Azure Storage no configurado.")
    
    def generar_embedding(self, texto: str) -> Optional[np.ndarray]:
        """
        Genera un embedding para un texto usando Azure OpenAI ada-002.
        
        Args:
            texto: Texto para el cual generar el embedding
        
        Returns:
            Vector de embedding de dimensión 1536 o None si hay error
        """
        if not self.azure_openai_endpoint or not self.azure_openai_api_key:
            logging.error("Azure OpenAI no configurado para generar embeddings.")
            return None
        
        if not texto or len(texto.strip()) < 1:
            return None
        
        try:
            client = AzureOpenAI(
                api_key=self.azure_openai_api_key,
                api_version=self.azure_openai_api_version,
                azure_endpoint=self.azure_openai_endpoint
            )
            
            response = client.embeddings.create(
                model=self.azure_openai_embedding_deployment,
                input=texto[:8000]  # Limitar tamaño del texto
            )
            
            embedding = np.array(response.data[0].embedding, dtype=np.float32)
            return embedding
            
        except Exception as e:
            logging.error(f"Error al generar embedding: {e}", exc_info=True)
            return None
    
    def generar_embeddings_batch(self, textos: List[str], batch_size: int = 100) -> List[Optional[np.ndarray]]:
        """
        Genera embeddings para múltiples textos en lotes.
        
        Args:
            textos: Lista de textos para generar embeddings
            batch_size: Tamaño del lote para procesar
        
        Returns:
            Lista de embeddings (puede contener None si hay errores)
        """
        if not self.azure_openai_endpoint or not self.azure_openai_api_key:
            logging.error("Azure OpenAI no configurado para generar embeddings.")
            return [None] * len(textos)
        
        all_embeddings = []
        
        try:
            client = AzureOpenAI(
                api_key=self.azure_openai_api_key,
                api_version=self.azure_openai_api_version,
                azure_endpoint=self.azure_openai_endpoint
            )
            
            for i in range(0, len(textos), batch_size):
                batch = textos[i:i + batch_size]
                # Filtrar textos vacíos
                batch_validos = [(idx, texto) for idx, texto in enumerate(batch) if texto and len(texto.strip()) > 0]
                
                if not batch_validos:
                    all_embeddings.extend([None] * len(batch))
                    continue
                
                textos_validos = [texto for _, texto in batch_validos]
                
                try:
                    response = client.embeddings.create(
                        model=self.azure_openai_embedding_deployment,
                        input=textos_validos
                    )
                    
                    # Crear diccionario de embeddings por índice
                    embeddings_dict = {}
                    for idx, item in enumerate(response.data):
                        original_idx = batch_validos[idx][0]
                        embeddings_dict[original_idx] = np.array(item.embedding, dtype=np.float32)
                    
                    # Agregar embeddings en el orden correcto
                    for idx in range(len(batch)):
                        if idx in embeddings_dict:
                            all_embeddings.append(embeddings_dict[idx])
                        else:
                            all_embeddings.append(None)
                    
                    logging.info(f"Procesando lote {i//batch_size + 1}/{(len(textos)-1)//batch_size + 1}... ({len(textos_validos)} textos válidos)")
                    
                except Exception as e:
                    logging.error(f"Error al procesar lote {i//batch_size + 1}: {e}")
                    all_embeddings.extend([None] * len(batch))
            
            return all_embeddings
            
        except Exception as e:
            logging.error(f"Error al generar embeddings en lote: {e}", exc_info=True)
            return [None] * len(textos)
    
    def preparar_textos_para_indexar(self, items: List[Dict]) -> tuple[List[str], List[Dict]]:
        """
        Prepara los textos de summary y key_points para indexar.
        Combina summary y key_points en un solo texto por item.
        
        Args:
            items: Lista de items con summary y key_points
        
        Returns:
            Tupla con (textos para indexar, metadatos con URLs)
        """
        textos = []
        metadatos = []
        
        for item in items:
            url = item.get('url', '')
            if not url:
                continue
            
            # Combinar summary y key_points
            summary = item.get('summary', '') or ''
            key_points = item.get('key_points', [])
            
            # Construir texto combinado
            texto_combinado = summary
            
            if key_points and isinstance(key_points, list):
                puntos_texto = ' '.join([str(punto) for punto in key_points if punto])
                if puntos_texto:
                    texto_combinado += ' ' + puntos_texto
            
            # Solo agregar si hay texto
            if texto_combinado.strip():
                textos.append(texto_combinado.strip())
                metadatos.append({
                    'url': url,
                    'tipo': item.get('tipo', 'article'),
                    'title': item.get('title', '')
                })
        
        return textos, metadatos
    
    def obtener_indice_mas_reciente(self) -> Optional[Tuple[str, str]]:
        """
        Obtiene el índice FAISS más reciente del storage.
        
        Returns:
            Tupla con (nombre_indice, nombre_metadata) o None si no existe
        """
        if not self.storage_connection_string:
            return None
        
        try:
            from azure.storage.blob import BlobServiceClient
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            if not container_client.exists():
                return None
            
            # Listar todos los archivos de índice
            indices = []
            for blob in container_client.list_blobs(name_starts_with="faiss_index_"):
                nombre = blob.name
                if nombre.endswith('.idx'):
                    nombre_metadata = nombre[:-4] + '_metadata.pkl'
                    blob_meta = container_client.get_blob_client(nombre_metadata)
                    if blob_meta.exists():
                        indices.append((nombre, nombre_metadata, blob.last_modified))
            
            if not indices:
                return None
            
            # Ordenar por fecha de modificación (más reciente primero)
            indices.sort(key=lambda x: x[2], reverse=True)
            return (indices[0][0], indices[0][1])
            
        except Exception as e:
            logging.warning(f"Error al buscar índice existente: {e}")
            return None
    
    def cargar_indice_existente(self, nombre_indice: str, nombre_metadata: str) -> Optional[Dict]:
        """
        Carga un índice FAISS existente desde el storage.
        
        Args:
            nombre_indice: Nombre del archivo de índice
            nombre_metadata: Nombre del archivo de metadatos
        
        Returns:
            Diccionario con index, textos y metadatos o None si hay error
        """
        if not self.storage_connection_string:
            return None
        
        try:
            from azure.storage.blob import BlobServiceClient
            import tempfile
            import pickle
            
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            # Descargar índice
            blob_idx = container_client.get_blob_client(nombre_indice)
            if not blob_idx.exists():
                return None
            
            with tempfile.NamedTemporaryFile(delete=False, suffix='.idx') as tmp_idx:
                blob_idx.download_blob().readinto(tmp_idx)
                tmp_idx_path = tmp_idx.name
            
            index = faiss.read_index(tmp_idx_path)
            
            # Descargar metadatos
            blob_meta = container_client.get_blob_client(nombre_metadata)
            if not blob_meta.exists():
                import os
                os.unlink(tmp_idx_path)
                return None
            
            with tempfile.NamedTemporaryFile(delete=False, suffix='.pkl', mode='wb') as tmp_meta:
                blob_meta.download_blob().readinto(tmp_meta)
                tmp_meta_path = tmp_meta.name
            
            with open(tmp_meta_path, 'rb') as f:
                metadata_data = pickle.load(f)
            
            # Limpiar archivos temporales
            import os
            try:
                os.unlink(tmp_idx_path)
                os.unlink(tmp_meta_path)
            except:
                pass
            
            return {
                'index': index,
                'textos': metadata_data.get('textos', []),
                'metadatos': metadata_data.get('metadatos', []),
                'total_vectores': index.ntotal
            }
            
        except Exception as e:
            logging.error(f"Error al cargar índice existente: {e}", exc_info=True)
            return None
    
    def construir_indice_faiss(self, items: List[Dict], agregar_a_existente: bool = True) -> Optional[Dict]:
        """
        Construye un índice FAISS con los items proporcionados.
        Si ya existe un índice, agrega solo los items nuevos.
        
        Args:
            items: Lista de items con summary y key_points
            agregar_a_existente: Si True, intenta agregar a índice existente
        
        Returns:
            Diccionario con información del índice construido o None si hay error
        """
        logging.info("=== INICIANDO CONSTRUCCIÓN DE ÍNDICE FAISS ===")
        
        if not items:
            logging.warning("No hay items para indexar.")
            return None
        
        # Intentar cargar índice existente
        indice_existente = None
        urls_existentes = set()
        
        if agregar_a_existente:
            indices_info = self.obtener_indice_mas_reciente()
            if indices_info:
                nombre_indice, nombre_metadata = indices_info
                logging.info(f"Índice existente encontrado: {nombre_indice}")
                indice_existente = self.cargar_indice_existente(nombre_indice, nombre_metadata)
                
                if indice_existente:
                    # Crear set de URLs existentes para evitar duplicados
                    urls_existentes = {meta.get('url', '') for meta in indice_existente['metadatos']}
                    logging.info(f"Índice existente cargado: {indice_existente['total_vectores']} vectores, {len(urls_existentes)} URLs únicas")
        
        # Filtrar items que ya están indexados
        items_nuevos = [item for item in items if item.get('url', '') not in urls_existentes]
        
        if not items_nuevos:
            logging.info("Todos los items ya están indexados. No hay items nuevos para agregar.")
            if indice_existente:
                return indice_existente
            return None
        
        logging.info(f"Items nuevos para indexar: {len(items_nuevos)} (de {len(items)} totales)")
        
        # Preparar textos y metadatos solo para items nuevos
        textos, metadatos = self.preparar_textos_para_indexar(items_nuevos)
        
        if not textos:
            logging.warning("No hay textos válidos para indexar.")
            if indice_existente:
                return indice_existente
            return None
        
        logging.info(f"Textos preparados para indexar: {len(textos)}")
        
        # Generar embeddings
        logging.info("Generando embeddings para items nuevos...")
        embeddings = self.generar_embeddings_batch(textos, batch_size=100)
        
        # Filtrar textos y metadatos que tienen embeddings válidos
        textos_validos = []
        metadatos_validos = []
        embeddings_validos = []
        
        for texto, metadata, embedding in zip(textos, metadatos, embeddings):
            if embedding is not None:
                textos_validos.append(texto)
                metadatos_validos.append(metadata)
                embeddings_validos.append(embedding)
        
        if not embeddings_validos:
            logging.error("No se pudieron generar embeddings válidos.")
            if indice_existente:
                return indice_existente
            return None
        
        logging.info(f"Embeddings válidos generados: {len(embeddings_validos)}")
        
        # Convertir a array numpy
        embeddings_array = np.array(embeddings_validos, dtype=np.float32)
        
        # Normalizar vectores para similitud coseno
        faiss.normalize_L2(embeddings_array)
        
        # Si hay índice existente, agregar a él
        if indice_existente:
            index = indice_existente['index']
            textos_finales = indice_existente['textos'] + textos_validos
            metadatos_finales = indice_existente['metadatos'] + metadatos_validos
            
            # Agregar nuevos embeddings al índice existente
            index.add(embeddings_array)
            
            logging.info(f"Agregados {len(embeddings_validos)} vectores al índice existente. Total: {index.ntotal}")
        else:
            # Crear nuevo índice FAISS (usando similitud coseno)
            index = faiss.IndexFlatIP(self.EMBEDDING_DIMENSION)
            
            # Agregar embeddings al índice
            index.add(embeddings_array)
            
            textos_finales = textos_validos
            metadatos_finales = metadatos_validos
            
            logging.info(f"Índice FAISS nuevo construido con {index.ntotal} vectores")
        
        return {
            'index': index,
            'textos': textos_finales,
            'metadatos': metadatos_finales,
            'total_vectores': index.ntotal,
            'items_nuevos_agregados': len(embeddings_validos)
        }
    
    def subir_indice_a_storage(self, indice_data: Dict, nombre_archivo: str = None) -> Dict:
        """
        Sube el índice FAISS y metadatos al storage.
        
        Args:
            indice_data: Diccionario con index, textos y metadatos
            nombre_archivo: Nombre base para los archivos (sin extensión)
        
        Returns:
            Diccionario con URLs de los archivos subidos
        """
        if not self.storage_connection_string:
            logging.error("Azure Storage no configurado. No se puede subir el índice.")
            return {}
        
        if nombre_archivo is None:
            from datetime import datetime
            nombre_archivo = f"faiss_index_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        try:
            # Crear cliente de Blob Service
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            
            # Obtener cliente del contenedor
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            # Crear contenedor si no existe
            if not container_client.exists():
                logging.info(f"Creando contenedor '{self.storage_container}'...")
                container_client.create_container()
            
            # Guardar índice FAISS en archivo temporal
            with tempfile.NamedTemporaryFile(delete=False, suffix='.idx') as tmp_idx:
                faiss.write_index(indice_data['index'], tmp_idx.name)
                tmp_idx_path = tmp_idx.name
            
            # Guardar metadatos en archivo temporal
            with tempfile.NamedTemporaryFile(delete=False, suffix='.pkl', mode='wb') as tmp_meta:
                pickle.dump({
                    'textos': indice_data['textos'],
                    'metadatos': indice_data['metadatos'],
                    'dimension': self.EMBEDDING_DIMENSION,
                    'index_type': 'cosine',
                    'total_vectores': indice_data['total_vectores']
                }, tmp_meta)
                tmp_meta_path = tmp_meta.name
            
            # Subir índice FAISS
            nombre_indice = f"{nombre_archivo}.idx"
            blob_idx = container_client.get_blob_client(nombre_indice)
            with open(tmp_idx_path, 'rb') as f:
                content_settings = ContentSettings(content_type='application/octet-stream')
                blob_idx.upload_blob(f, overwrite=True, content_settings=content_settings)
            
            url_indice = blob_idx.url
            logging.info(f"Índice FAISS subido: {url_indice}")
            
            # Subir metadatos
            nombre_metadata = f"{nombre_archivo}_metadata.pkl"
            blob_meta = container_client.get_blob_client(nombre_metadata)
            with open(tmp_meta_path, 'rb') as f:
                content_settings = ContentSettings(content_type='application/octet-stream')
                blob_meta.upload_blob(f, overwrite=True, content_settings=content_settings)
            
            url_metadata = blob_meta.url
            logging.info(f"Metadatos subidos: {url_metadata}")
            
            # Limpiar archivos temporales
            import os
            try:
                os.unlink(tmp_idx_path)
                os.unlink(tmp_meta_path)
            except:
                pass
            
            return {
                'indice_url': url_indice,
                'metadata_url': url_metadata,
                'nombre_indice': nombre_indice,
                'nombre_metadata': nombre_metadata,
                'total_vectores': indice_data['total_vectores']
            }
            
        except AzureError as e:
            logging.error(f"Error de Azure Storage: {e}", exc_info=True)
            return {}
        except Exception as e:
            logging.error(f"Error al subir índice a storage: {e}", exc_info=True)
            return {}
    
    def ejecutar_evaluacion(self, items: List[Dict], nombre_archivo: str = None) -> Dict:
        """
        Ejecuta el proceso completo de evaluación: construye índice FAISS y lo sube al storage.
        Si ya existe un índice, agrega solo los items nuevos.
        
        Args:
            items: Lista de items con summary y key_points
            nombre_archivo: Nombre base para los archivos (sin extensión). Si None y existe índice, usa el existente.
        
        Returns:
            Diccionario con resultado de la evaluación
        """
        logging.info("=== INICIANDO EVALUACIÓN ===")
        logging.info(f"Items recibidos: {len(items)}")
        
        # Verificar si existe índice y obtener su nombre si no se especifica uno
        if nombre_archivo is None:
            indices_info = self.obtener_indice_mas_reciente()
            if indices_info:
                # Extraer nombre base del índice existente (sin extensión)
                nombre_indice_existente = indices_info[0]
                nombre_archivo = nombre_indice_existente.replace('.idx', '')
                logging.info(f"Usando índice existente: {nombre_archivo}")
        
        # Construir índice (o agregar a existente)
        indice_data = self.construir_indice_faiss(items, agregar_a_existente=True)
        
        if not indice_data:
            return {
                "error": "No se pudo construir el índice FAISS",
                "items_procesados": 0,
                "vectores_indexados": 0
            }
        
        # Si se agregaron items nuevos, usar el nombre del archivo existente o crear uno nuevo
        items_nuevos = indice_data.get('items_nuevos_agregados', 0)
        if items_nuevos == 0 and nombre_archivo:
            # No hay items nuevos, no es necesario actualizar
            logging.info("No hay items nuevos para agregar al índice existente.")
            return {
                "items_procesados": len(items),
                "vectores_indexados": indice_data['total_vectores'],
                "items_nuevos_agregados": 0,
                "mensaje": "Todos los items ya estaban indexados"
            }
        
        # Subir al storage (sobrescribirá el existente si se agregaron items)
        resultado_upload = self.subir_indice_a_storage(indice_data, nombre_archivo)
        
        if not resultado_upload:
            return {
                "error": "No se pudo subir el índice al storage",
                "items_procesados": len(items),
                "vectores_indexados": indice_data['total_vectores'],
                "items_nuevos_agregados": items_nuevos
            }
        
        logging.info("=== EVALUACIÓN COMPLETADA ===")
        
        return {
            "items_procesados": len(items),
            "vectores_indexados": indice_data['total_vectores'],
            "items_nuevos_agregados": items_nuevos,
            "indice_url": resultado_upload.get('indice_url', ''),
            "metadata_url": resultado_upload.get('metadata_url', ''),
            "nombre_indice": resultado_upload.get('nombre_indice', ''),
            "nombre_metadata": resultado_upload.get('nombre_metadata', '')
        }

