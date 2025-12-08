"""
Servicio buscador que busca en el índice FAISS usando embeddings de Azure OpenAI.
Devuelve los resultados más cercanos a la query proporcionada.
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
from azure.storage.blob import BlobServiceClient
from azure.core.exceptions import AzureError


class BuscadorService:
    """Servicio para buscar en índices FAISS con embeddings"""
    
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
    
    def obtener_indice_mas_reciente(self, tag: str = None) -> Optional[Tuple[str, str]]:
        """
        Obtiene el índice FAISS más reciente del storage.
        Si se proporciona un tag, busca el índice específico de ese tag.
        
        Args:
            tag: Tag específico para buscar (ej: "tnm.alzheimer"). Si None, busca el índice general.
        
        Returns:
            Tupla con (nombre_indice, nombre_metadata) o None si no existe
        """
        if not self.storage_connection_string:
            logging.error("Azure Storage no configurado.")
            return None
        
        try:
            # Crear cliente de Blob Service
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            
            # Obtener cliente del contenedor
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            if not container_client.exists():
                logging.error(f"El contenedor '{self.storage_container}' no existe.")
                return None
            
            # Si hay tag, buscar índice específico
            if tag:
                # Normalizar tag para nombre de archivo
                tag_normalizado = tag.replace('.', '-').replace('/', '-')
                nombre_base = f"faiss-{tag_normalizado}"
                nombre_indice = f"{nombre_base}.idx"
                nombre_metadata = f"{nombre_base}_metadata.pkl"
                
                blob_idx = container_client.get_blob_client(nombre_indice)
                blob_meta = container_client.get_blob_client(nombre_metadata)
                
                if blob_idx.exists() and blob_meta.exists():
                    logging.info(f"Índice específico encontrado para tag '{tag}': {nombre_indice}")
                    return (nombre_indice, nombre_metadata)
                else:
                    logging.warning(f"Índice específico para tag '{tag}' no encontrado, usando índice general")
                    # Si no existe el índice específico, usar el general
                    tag = None
            
            # Buscar índice general (faiss-general)
            if not tag:
                nombre_indice = "faiss-general.idx"
                nombre_metadata = "faiss-general_metadata.pkl"
                
                blob_idx = container_client.get_blob_client(nombre_indice)
                blob_meta = container_client.get_blob_client(nombre_metadata)
                
                if blob_idx.exists() and blob_meta.exists():
                    logging.info(f"Índice general encontrado: {nombre_indice}")
                    return (nombre_indice, nombre_metadata)
            
            # Si no existe el general, buscar cualquier índice faiss_*
            indices = []
            for blob in container_client.list_blobs(name_starts_with="faiss"):
                nombre = blob.name
                if nombre.endswith('.idx'):
                    nombre_metadata = nombre[:-4] + '_metadata.pkl'
                    blob_meta = container_client.get_blob_client(nombre_metadata)
                    if blob_meta.exists():
                        indices.append((nombre, nombre_metadata, blob.last_modified))
            
            if not indices:
                logging.error("No se encontraron índices FAISS en el storage.")
                return None
            
            # Ordenar por fecha de modificación (más reciente primero)
            indices.sort(key=lambda x: x[2], reverse=True)
            nombre_indice, nombre_metadata, _ = indices[0]
            logging.info(f"Índice más reciente encontrado: {nombre_indice}")
            
            return (nombre_indice, nombre_metadata)
            
        except AzureError as e:
            logging.error(f"Error de Azure Storage: {e}", exc_info=True)
            return None
        except Exception as e:
            logging.error(f"Error al obtener índice más reciente: {e}", exc_info=True)
            return None
    
    def cargar_indice(self, nombre_indice: str, nombre_metadata: str) -> Optional[Dict]:
        """
        Carga un índice FAISS y sus metadatos desde el storage.
        
        Args:
            nombre_indice: Nombre del archivo de índice en el storage
            nombre_metadata: Nombre del archivo de metadatos en el storage
        
        Returns:
            Diccionario con index, textos y metadatos o None si hay error
        """
        if not self.storage_connection_string:
            logging.error("Azure Storage no configurado.")
            return None
        
        try:
            # Crear cliente de Blob Service
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            
            # Obtener cliente del contenedor
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            # Descargar índice
            blob_idx = container_client.get_blob_client(nombre_indice)
            if not blob_idx.exists():
                logging.error(f"El índice '{nombre_indice}' no existe.")
                return None
            
            # Guardar índice en archivo temporal
            with tempfile.NamedTemporaryFile(delete=False, suffix='.idx') as tmp_idx:
                blob_idx.download_blob().readinto(tmp_idx)
                tmp_idx_path = tmp_idx.name
            
            # Leer índice FAISS
            index = faiss.read_index(tmp_idx_path)
            
            # Descargar metadatos
            blob_meta = container_client.get_blob_client(nombre_metadata)
            if not blob_meta.exists():
                logging.error(f"Los metadatos '{nombre_metadata}' no existen.")
                import os
                os.unlink(tmp_idx_path)
                return None
            
            # Guardar metadatos en archivo temporal
            with tempfile.NamedTemporaryFile(delete=False, suffix='.pkl', mode='wb') as tmp_meta:
                blob_meta.download_blob().readinto(tmp_meta)
                tmp_meta_path = tmp_meta.name
            
            # Cargar metadatos
            with open(tmp_meta_path, 'rb') as f:
                metadata_data = pickle.load(f)
            
            # Limpiar archivos temporales
            import os
            try:
                os.unlink(tmp_idx_path)
                os.unlink(tmp_meta_path)
            except:
                pass
            
            logging.info(f"Índice cargado: {index.ntotal} vectores")
            
            return {
                'index': index,
                'textos': metadata_data.get('textos', []),
                'metadatos': metadata_data.get('metadatos', []),
                'total_vectores': index.ntotal
            }
            
        except Exception as e:
            logging.error(f"Error al cargar índice: {e}", exc_info=True)
            return None
    
    def buscar(self, query: str, k: int = 40, nombre_indice: str = None, nombre_metadata: str = None, tag: str = None) -> List[Dict]:
        """
        Busca los k documentos más similares a la query en el índice FAISS.
        
        Args:
            query: Texto de consulta
            k: Número de resultados a retornar (por defecto 40)
            nombre_indice: Nombre del índice a usar (None para usar el más reciente o por tag)
            nombre_metadata: Nombre de los metadatos a usar (None para usar el más reciente o por tag)
            tag: Tag específico para buscar en índice filtrado (ej: "tnm.alzheimer")
        
        Returns:
            Lista de resultados con url, score, tipo, title
        """
        logging.info(f"=== INICIANDO BÚSQUEDA ===")
        logging.info(f"Query: {query}")
        logging.info(f"Resultados solicitados: {k}")
        if tag:
            logging.info(f"Tag filtrado: {tag}")
        
        # Obtener índice más reciente si no se especifica
        if nombre_indice is None or nombre_metadata is None:
            indices = self.obtener_indice_mas_reciente(tag=tag)
            if not indices:
                logging.error("No se pudo obtener el índice.")
                return []
            nombre_indice, nombre_metadata = indices
        
        # Cargar índice
        indice_data = self.cargar_indice(nombre_indice, nombre_metadata)
        if not indice_data:
            logging.error("No se pudo cargar el índice.")
            return []
        
        index = indice_data['index']
        textos = indice_data['textos']
        metadatos = indice_data['metadatos']
        
        if index.ntotal == 0:
            logging.warning("El índice está vacío.")
            return []
        
        # Generar embedding de la query
        logging.info("Generando embedding de la query...")
        query_embedding = self.generar_embedding(query)
        if query_embedding is None:
            logging.error("No se pudo generar el embedding de la query.")
            return []
        
        # Preparar vector de consulta
        query_vector = query_embedding.reshape(1, -1).astype(np.float32)
        
        # Normalizar para similitud coseno (el índice usa IndexFlatIP con normalización)
        faiss.normalize_L2(query_vector)
        
        # Buscar en el índice
        k_real = min(k, index.ntotal)
        logging.info(f"Buscando {k_real} resultados más cercanos...")
        
        distances, indices = index.search(query_vector, k_real)
        
        # Construir resultados
        resultados = []
        for i, (distance, idx) in enumerate(zip(distances[0], indices[0])):
            if idx != -1 and idx < len(metadatos):
                # Para IndexFlatIP, la distancia es el producto interno (mayor = más similar)
                # Convertir a score de similitud (0-1)
                score = float(distance)  # Ya es un score de similitud coseno
                
                metadata = metadatos[idx]
                resultado = {
                    'url': metadata.get('url', ''),
                    'score': score,
                    'tipo': metadata.get('tipo', 'article'),
                    'title': metadata.get('title', '')
                }
                resultados.append(resultado)
        
        logging.info(f"Resultados encontrados: {len(resultados)}")
        
        return resultados
    
    def ejecutar_busqueda(self, query: str, k: int = 40, tag: str = None) -> Dict:
        """
        Ejecuta el proceso completo de búsqueda.
        
        Args:
            query: Texto de consulta
            k: Número de resultados a retornar
            tag: Tag específico para buscar en índice filtrado (ej: "tnm.alzheimer")
        
        Returns:
            Diccionario con resultados de la búsqueda
        """
        logging.info("=== INICIANDO BÚSQUEDA ===")
        
        if not query or not query.strip():
            return {
                "error": "La query no puede estar vacía",
                "resultados": [],
                "total": 0
            }
        
        # Buscar
        resultados = self.buscar(query, k, tag=tag)
        
        return {
            "query": query,
            "tag": tag,
            "resultados": resultados,
            "total": len(resultados)
        }

