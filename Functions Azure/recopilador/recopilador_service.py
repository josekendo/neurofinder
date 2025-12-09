"""
Servicio de recopilación de artículos y noticias sobre Alzheimer y demencia.
Recopila desde NCBI (artículos científicos) y TheNewsAPI (noticias),
los procesa con Azure OpenAI y los almacena en Azure Storage.
"""

import os
import json
import logging
from datetime import datetime, timedelta
from typing import List, Dict, Optional
import time
import random

# Importar bibliotecas necesarias
try:
    from Bio import Entrez
    from Bio.Entrez import efetch, esearch, esummary
    BIO_AVAILABLE = True
except ImportError:
    BIO_AVAILABLE = False
    logging.warning("Biopython no está disponible. No se podrán obtener artículos de NCBI.")

import requests
from openai import AzureOpenAI
from azure.storage.blob import BlobServiceClient, ContentSettings
from azure.core.exceptions import AzureError


class RecopiladorService:
    """Servicio principal de recopilación"""
    
    def __init__(self):
        """Inicializar servicio con configuración desde variables de entorno"""
        # Configuración de NCBI
        self.ncbi_api_key = os.getenv('NCBI_API_KEY', '')
        self.ncbi_email = os.getenv('NCBI_EMAIL', '')
        
        if BIO_AVAILABLE and self.ncbi_api_key:
            Entrez.api_key = self.ncbi_api_key
            Entrez.email = self.ncbi_email
        
        # Configuración de TheNewsAPI
        self.thenewsapi_token = os.getenv('THENEWSAPI_TOKEN', '')
        self.thenewsapi_base_url = "https://api.thenewsapi.com/v1/news"
        
        # Configuración de Azure OpenAI
        self.azure_openai_endpoint = os.getenv('AZURE_OPENAI_ENDPOINT', '')
        self.azure_openai_api_key = os.getenv('AZURE_OPENAI_API_KEY', '')
        self.azure_openai_api_version = os.getenv('AZURE_OPENAI_API_VERSION', '2024-02-15-preview')
        self.azure_openai_deployment = os.getenv('AZURE_OPENAI_DEPLOYMENT_NAME', '')
        
        # Configuración de Azure Storage
        # Intentar primero con variable específica, luego con AzureWebJobsStorage como fallback
        storage_conn = os.getenv('AZURE_STORAGE_CONNECTION_STRING') or os.getenv('AzureWebJobsStorage', '')
        # Limpiar comillas y espacios que puedan venir del .env
        self.storage_connection_string = storage_conn.strip().strip('"').strip("'") if storage_conn else ''
        self.storage_container = os.getenv('STORAGE_CONTAINER', 'recopilaciones')
        
        # Configuración de límites
        self.max_articulos = int(os.getenv('MAX_ARTICULOS', '100'))
        self.max_noticias = int(os.getenv('MAX_NOTICIAS', '100'))
        
        # Términos de búsqueda
        self.terminos_busqueda = ['Alzheimer', 'dementia']
        
        # Tipos de demencia para categorización (nombres que puede extraer la IA)
        self.tipos_demencia_nombres = [
            "Alzheimer de inicio temprano",
            "Alzheimer de inicio tardío",
            "Alzheimer familiar",
            "Alzheimer esporádico",
            "Alzheimer con demencia vascular",
            "Alzheimer con cuerpos de Lewy",
            "Deterioro cognitivo leve (DCL)",
            "Demencia frontotemporal",
            "Demencia mixta",
            "Alzheimer",
            "Demencia vascular",
            "Cuerpos de Lewy",
            "Demencia por cuerpos de Lewy",
            "Demencia frontotemporal",
            "Demencia traumática",
            "Demencia por sustancias",
            "Demencia por priones",
            "Demencia asociada a Parkinson",
            "Enfermedad de Huntington",
            "Demencia asociada a VIH",
            "Esclerosis múltiple",
            "Demencia metabólica",
            "Demencia por epilepsia",
            "Hidrocefalia normotensiva",
            "Demencia nutricional",
            "Demencia tumoral",
            "Traumatismos repetitivos",
            "Insuficiencia hepática o renal"
        ]
        
        # Mapeo de nombres extraídos por IA a claves de tags del frontend
        self.mapeo_tags = {
            # Alzheimer
            "Alzheimer": "tnm.alzheimer",
            "Alzheimer de inicio temprano": "tnm.alzheimer.early",
            "Alzheimer de inicio tardío": "tnm.alzheimer.late",
            "Alzheimer familiar": "tnm.alzheimer",
            "Alzheimer esporádico": "tnm.alzheimer",
            "Alzheimer con demencia vascular": "tnm.alzheimer.mixed",
            "Alzheimer con cuerpos de Lewy": "tnm.lewy",
            # Demencia vascular
            "Demencia vascular": "tnm.vascular",
            "Alzheimer con demencia vascular": "tnm.alzheimer.mixed",
            # Cuerpos de Lewy
            "Cuerpos de Lewy": "tnm.lewy",
            "Demencia por cuerpos de Lewy": "tnm.lewy",
            "Demencia con cuerpos de Lewy": "tnm.lewy",
            # Frontotemporal
            "Demencia frontotemporal": "tnm.frontotemporal",
            # Traumática
            "Demencia traumática": "tnm.traumatic",
            "Traumatismo craneoencefálico": "tnm.traumatic",
            "Traumatismos repetitivos": "tnm.repetitive_trauma",
            # Sustancias
            "Demencia por sustancias": "tnm.substances",
            "Demencia por medicamentos": "tnm.substances",
            # Priones
            "Demencia por priones": "tnm.prions",
            # Parkinson
            "Demencia asociada a Parkinson": "tnm.parkinson",
            "Parkinson": "tnm.parkinson",
            # Huntington
            "Enfermedad de Huntington": "tnm.huntington",
            "Huntington": "tnm.huntington",
            # VIH
            "Demencia asociada a VIH": "tnm.hiv",
            "VIH": "tnm.hiv",
            # Esclerosis múltiple
            "Esclerosis múltiple": "tnm.sclerosis",
            # Metabólica
            "Demencia metabólica": "tnm.metabolic",
            "Demencia endócrina": "tnm.metabolic",
            # Epilepsia
            "Demencia por epilepsia": "tnm.epilepsy",
            "Epilepsia": "tnm.epilepsy",
            # Hidrocefalia
            "Hidrocefalia normotensiva": "tnm.hydrocephalus",
            # Nutricional
            "Demencia nutricional": "tnm.nutritional",
            "Déficit nutricional": "tnm.nutritional",
            # Tumoral
            "Demencia tumoral": "tnm.tumoral",
            "Tumores": "tnm.tumoral",
            "Neoplasias": "tnm.tumoral",
            # Hepática/Renal
            "Insuficiencia hepática o renal": "tnm.hepatic_renal",
            "Insuficiencia hepática": "tnm.hepatic_renal",
            "Insuficiencia renal": "tnm.hepatic_renal",
            # Mixta
            "Demencia mixta": "tnm.mixed",
            "Deterioro cognitivo leve (DCL)": "tnm.alzheimer.early"
        }
        
        # Validar configuración crítica
        if not self.thenewsapi_token:
            logging.warning("THENEWSAPI_TOKEN no configurado. No se podrán obtener noticias.")
        if not self.azure_openai_endpoint or not self.azure_openai_api_key:
            logging.warning("Azure OpenAI no configurado correctamente. No se podrá procesar con IA.")
        if not self.storage_connection_string:
            logging.warning("AZURE_STORAGE_CONNECTION_STRING o AzureWebJobsStorage no configurado. No se podrán subir archivos.")
    
    def obtener_fechas_semana(self, week: Optional[str] = None, year: Optional[str] = None) -> tuple:
        """
        Obtiene las fechas de inicio y fin de la semana vigente o especificada.
        
        Args:
            week: Número de semana (1-53) o None para semana actual
            year: Año (YYYY) o None para año actual
        
        Returns:
            Tuple con (fecha_inicio, fecha_fin) en formato YYYY-MM-DD
        """
        logging.info(f'Recopilador fechas en obtener fechas: week={week}, year={year}')
        hoy = datetime.now()
        
        if year:
            try:
                year_int = int(year)
            except ValueError:
                year_int = hoy.year
        else:
            year_int = hoy.year
        
        if week:
            try:
                week_int = int(week)
                # Calcular fecha de inicio de la semana ISO manualmente
                # La semana 1 es la primera semana con al menos 4 días en el año
                # Encontrar el 4 de enero del año (siempre está en la semana 1)
                jan_4 = datetime(year_int, 1, 4)
                # El lunes de la semana que contiene el 4 de enero es el inicio de la semana 1
                # weekday() devuelve 0=lunes, 6=domingo
                week1_start = jan_4 - timedelta(days=jan_4.weekday())
                # Calcular el inicio de la semana solicitada
                fecha_inicio = week1_start + timedelta(weeks=week_int - 1)
                fecha_fin = fecha_inicio + timedelta(days=6)
                
                # Validar que la fecha de inicio pertenece al año especificado
                # Si la semana calculada cae en otro año, la semana no existe en ese año
                # Usar la última semana del año especificado
                if fecha_inicio.year != year_int:
                    logging.warning(f"La semana {week_int} del año {year_int} no existe (la fecha calculada cae en {fecha_inicio.year}). Usando última semana del año {year_int}.")
                    # Calcular la última semana del año especificado
                    # La última semana ISO es la que contiene el 28 de diciembre
                    dec_28 = datetime(year_int, 12, 28)
                    # El lunes de la semana que contiene el 28 de diciembre
                    last_week_start = dec_28 - timedelta(days=dec_28.weekday())
                    fecha_inicio = last_week_start
                    fecha_fin = fecha_inicio + timedelta(days=6)
                    # Asegurar que fecha_fin no exceda el año
                    if fecha_fin.year > year_int:
                        fecha_fin = datetime(year_int, 12, 31)
            except (ValueError, OverflowError) as e:
                logging.warning(f"Error al calcular fecha de semana {week_int} del año {year_int}: {e}. Usando semana actual.")
                # Si falla, usar semana actual
                fecha_inicio = hoy - timedelta(days=hoy.weekday())
                fecha_fin = fecha_inicio + timedelta(days=6)
        else:
            # Semana actual (lunes a domingo)
            fecha_inicio = hoy - timedelta(days=hoy.weekday())
            fecha_fin = fecha_inicio + timedelta(days=6)
        
        return fecha_inicio.strftime('%Y-%m-%d'), fecha_fin.strftime('%Y-%m-%d')
    
    def buscar_articulos_ncbi(self, fecha_desde: str, fecha_hasta: str) -> List[Dict]:
        """
        Busca artículos científicos en NCBI/PubMed.
        
        Args:
            fecha_desde: Fecha de inicio (YYYY-MM-DD)
            fecha_hasta: Fecha de fin (YYYY-MM-DD)
        
        Returns:
            Lista de diccionarios con información de artículos
        """
        if not BIO_AVAILABLE:
            logging.warning("Biopython no disponible. Saltando búsqueda de NCBI.")
            return []
        
        if not self.ncbi_api_key:
            logging.warning("NCBI_API_KEY no configurado. Saltando búsqueda de NCBI.")
            return []
        
        try:
            # Construir query de búsqueda
            query_terms = " OR ".join([f'"{term}"[Title/Abstract]' for term in self.terminos_busqueda])
            query = f"({query_terms}) AND journal article[Publication Type]"
            
            # Agregar filtro de fecha
            fecha_desde_ncbi = fecha_desde.replace('-', '/')
            fecha_hasta_ncbi = fecha_hasta.replace('-', '/')
            query += f" AND {fecha_desde_ncbi}:{fecha_hasta_ncbi}[Publication Date]"
            
            logging.info(f"Buscando artículos en NCBI con query: {query}")
            
            # Buscar en PubMed
            handle = esearch(
                db="pubmed",
                term=query,
                retmax=self.max_articulos,
                retmode="xml",
                sort="pub_date",
                sort_order="desc"
            )
            
            results = Entrez.read(handle)
            handle.close()
            
            pmids = results["IdList"]
            total_encontrados = int(results["Count"])
            
            logging.info(f"Total de artículos encontrados en NCBI: {total_encontrados}")
            logging.info(f"IDs obtenidos: {len(pmids)}")
            
            if not pmids:
                return []
            
            # Obtener detalles de los artículos
            articulos = self._obtener_detalles_articulos(pmids)
            
            return articulos
            
        except Exception as e:
            logging.error(f"Error al buscar artículos en NCBI: {e}", exc_info=True)
            return []
    
    def _obtener_detalles_articulos(self, pmids: List[str], batch_size: int = 100) -> List[Dict]:
        """Obtiene detalles completos de artículos desde sus IDs de PubMed"""
        articulos = []
        
        try:
            for i in range(0, len(pmids), batch_size):
                batch = pmids[i:i + batch_size]
                logging.info(f"Obteniendo detalles del lote {i//batch_size + 1}/{(len(pmids)-1)//batch_size + 1}...")
                
                # Obtener resúmenes
                handle = esummary(db="pubmed", id=",".join(batch), retmode="xml")
                summaries = Entrez.read(handle)
                handle.close()
                
                # Obtener detalles completos
                handle = efetch(db="pubmed", id=",".join(batch), retmode="xml")
                records = Entrez.read(handle)
                handle.close()
                
                # Procesar cada artículo
                for summary, record in zip(summaries, records):
                    articulo = self._extraer_info_articulo(summary, record)
                    if articulo:
                        articulos.append(articulo)
                
                # Respetar límite de rate de NCBI
                time.sleep(0.34)
            
            logging.info(f"Se obtuvieron detalles de {len(articulos)} artículos")
            return articulos
            
        except Exception as e:
            logging.error(f"Error al obtener detalles de artículos: {e}", exc_info=True)
            return articulos
    
    def _extraer_info_articulo(self, summary, record) -> Optional[Dict]:
        """Extrae información relevante de un artículo de PubMed"""
        try:
            pmid = str(summary.get("Id", ""))
            if not pmid:
                return None
            
            # Título
            titulo = str(summary.get("Title", ""))
            
            # Fecha de publicación
            fecha_pub = str(summary.get("PubDate", ""))
            # Intentar parsear fecha
            fecha_parseada = self._parsear_fecha_pubmed(fecha_pub)
            
            # Revista
            revista = str(summary.get("Source", ""))
            
            # Abstract
            abstract = str(summary.get("Abstract", ""))
            
            # DOI
            doi = ""
            if "ELocationID" in summary:
                eloc_list = summary["ELocationID"]
                if isinstance(eloc_list, list):
                    for eloc in eloc_list:
                        eloc_str = str(eloc)
                        if eloc_str.startswith("10."):
                            doi = eloc_str
                            break
                else:
                    eloc_str = str(eloc_list)
                    if eloc_str.startswith("10."):
                        doi = eloc_str
            
            # URL de PubMed
            url = f"https://pubmed.ncbi.nlm.nih.gov/{pmid}/"
            
            # Detectar idioma inicial del artículo (los artículos de PubMed suelen ser en inglés)
            # Se normalizará después en procesar_con_ia
            language_inicial = "en"  # Por defecto inglés para artículos científicos de PubMed
            
            return {
                "pmid": pmid,
                "titulo": titulo,
                "fecha_publicacion": fecha_parseada or fecha_pub,
                "revista": revista,
                "language": language_inicial,
                "abstract": abstract,
                "doi": doi,
                "url": url,
                "tipo": "article"
            }
            
        except Exception as e:
            logging.error(f"Error al extraer información de artículo: {e}")
            return None
    
    def _parsear_fecha_pubmed(self, fecha_str: str) -> Optional[str]:
        """Intenta parsear fecha de PubMed a formato YYYY-MM-DD"""
        try:
            # Formato común: "2024 Dec" o "2024 Dec 15"
            partes = fecha_str.split()
            if len(partes) >= 2:
                año = partes[0]
                mes = partes[1]
                dia = partes[2] if len(partes) >= 3 else "01"
                
                meses = {
                    "Jan": "01", "Feb": "02", "Mar": "03", "Apr": "04",
                    "May": "05", "Jun": "06", "Jul": "07", "Aug": "08",
                    "Sep": "09", "Oct": "10", "Nov": "11", "Dec": "12"
                }
                
                mes_num = meses.get(mes, "01")
                return f"{año}-{mes_num}-{dia.zfill(2)}"
        except:
            pass
        return None
    
    def buscar_noticias_thenewsapi(self, fecha_desde: str, fecha_hasta: str) -> List[Dict]:
        """
        Busca noticias en TheNewsAPI.
        
        Args:
            fecha_desde: Fecha de inicio (YYYY-MM-DD)
            fecha_hasta: Fecha de fin (YYYY-MM-DD)
        
        Returns:
            Lista de diccionarios con información de noticias
        """
        if not self.thenewsapi_token:
            logging.warning("THENEWSAPI_TOKEN no configurado. Saltando búsqueda de noticias.")
            return []
        
        try:
            # Construir término de búsqueda
            search_term = " OR ".join(self.terminos_busqueda)
            
            url = f"{self.thenewsapi_base_url}/all"
            params = {
                "api_token": self.thenewsapi_token,
                "search": search_term,
                "limit": self.max_noticias,
                "published_after": fecha_desde,
                "published_before": fecha_hasta,
                "language": "es,en"
            }
            
            logging.info(f"Buscando noticias en TheNewsAPI: {search_term}")
            
            response = requests.get(url, params=params, timeout=30)
            response.raise_for_status()
            
            data = response.json()
            
            if "data" in data:
                noticias = data["data"]
                logging.info(f"Se encontraron {len(noticias)} noticias")
                
                # Formatear noticias
                noticias_formateadas = []
                for noticia in noticias:
                    noticia_formateada = {
                        "uuid": noticia.get("uuid", ""),
                        "titulo": noticia.get("title", ""),
                        "descripcion": noticia.get("description", ""),
                        "url": noticia.get("url", ""),
                        "image_url": noticia.get("image_url", ""),
                        "fecha_publicacion": noticia.get("published_at", ""),
                        "source": noticia.get("source", ""),
                        "language": self._normalizar_idioma(noticia.get("language", ""), "en"),
                        "tipo": "news"
                    }
                    noticias_formateadas.append(noticia_formateada)
                
                return noticias_formateadas
            else:
                logging.warning("No se encontraron noticias en TheNewsAPI")
                return []
                
        except Exception as e:
            logging.error(f"Error al buscar noticias en TheNewsAPI: {e}", exc_info=True)
            return []
    
    def procesar_con_ia(self, item: Dict) -> Dict:
        """
        Procesa un artículo o noticia con Azure OpenAI para generar resumen,
        puntos clave y métricas.
        
        Args:
            item: Diccionario con información del artículo/noticia
        
        Returns:
            Diccionario con información procesada
        """
        # Inicializar tags por defecto
        if "tags" not in item or not item.get("tags"):
            item["tags"] = ["tnm.alzheimer"]  # Tag por defecto
        
        if not self.azure_openai_endpoint or not self.azure_openai_api_key:
            logging.warning("Azure OpenAI no configurado. Saltando procesamiento con IA.")
            # Asegurar que el item tenga idioma normalizado
            language = item.get("language", "en")
            item["language"] = self._normalizar_idioma(language, "en")
            # Mantener tags por defecto
            return item
        
        try:
            # Crear cliente de Azure OpenAI
            client = AzureOpenAI(
                api_key=self.azure_openai_api_key,
                api_version=self.azure_openai_api_version,
                azure_endpoint=self.azure_openai_endpoint
            )
            
            # Obtener texto a procesar
            texto = ""
            if item.get("tipo") == "article":
                texto = item.get("abstract", "")
                # Si no hay abstract, usar título como mínimo
                if not texto or len(texto) < 50:
                    texto = item.get("titulo", "")
            else:
                texto = item.get("descripcion", "") or item.get("snippet", "")
            
            # Detectar idioma del texto
            idioma_detectado = "en"  # Por defecto inglés
            if texto:
                # Detección simple: buscar palabras comunes
                palabras_es = ["de", "la", "el", "en", "y", "con", "para", "por", "del", "los", "las", "es", "un", "una"]
                palabras_en = ["the", "of", "and", "in", "to", "for", "with", "on", "at", "from", "is", "a", "an"]
                texto_lower = texto.lower()
                count_es = sum(1 for palabra in palabras_es if f" {palabra} " in f" {texto_lower} " or texto_lower.startswith(f"{palabra} "))
                count_en = sum(1 for palabra in palabras_en if f" {palabra} " in f" {texto_lower} " or texto_lower.startswith(f"{palabra} "))
                idioma_detectado = "es" if count_es > count_en else "en"
            
            # Usar idioma del item si está disponible, sino el detectado
            idioma_item = item.get("language", idioma_detectado)
            # Normalizar idioma
            idioma_item = self._normalizar_idioma(idioma_item, idioma_detectado)
            item["language"] = idioma_item
            
            # Inicializar valores por defecto
            resumen = ""
            puntos_clave = []
            metricas = {"fecha_publicacion": None, "categorias": []}
            
            # Procesar con IA solo si hay texto suficiente
            if texto and len(texto) >= 50:
                # Generar resumen en el idioma del artículo
                resumen = self._generar_resumen(client, texto, idioma_item)
                
                # Extraer puntos clave en el idioma del artículo
                puntos_clave = self._extraer_puntos_clave(client, texto, idioma_item)
                
                # Extraer métricas (no dependen del idioma)
                metricas = self._extraer_metricas(client, texto)
            else:
                logging.warning(f"Texto demasiado corto para procesar con IA: {item.get('titulo', 'Sin título')[:50]}...")
                # Usar abstract como resumen si está disponible
                if item.get("tipo") == "article" and item.get("abstract"):
                    resumen = item.get("abstract", "")[:500]
            
            # Actualizar item con información procesada
            item["summary"] = resumen
            item["key_points"] = puntos_clave
            
            # Actualizar fecha si se encontró en métricas
            if metricas.get("fecha_publicacion") and not item.get("fecha_publicacion"):
                item["fecha_publicacion"] = metricas["fecha_publicacion"]
            
            # Mapear categorías a tags del frontend y agregar términos de búsqueda
            tags_mapeados = []
            categorias = metricas.get("categorias", [])
            
            # Mapear cada categoría a su clave de tag del frontend
            for categoria in categorias:
                tag_mapeado = self.mapeo_tags.get(categoria)
                if tag_mapeado:
                    tags_mapeados.append(tag_mapeado)
                else:
                    # Si no hay mapeo exacto, buscar coincidencias parciales
                    categoria_lower = categoria.lower()
                    for nombre, tag_key in self.mapeo_tags.items():
                        if nombre.lower() in categoria_lower or categoria_lower in nombre.lower():
                            tags_mapeados.append(tag_key)
                            break
            
            # Agregar tags básicos de búsqueda (si no están ya)
            tags_basicos = ["tnm.alzheimer"]  # Tag básico por defecto para búsquedas de Alzheimer/dementia
            # Nota: Si se busca "dementia" puede ser cualquier tipo, por eso usamos el tag genérico
            
            # Combinar todos los tags únicos
            tags_finales = list(set(tags_mapeados + tags_basicos))
            item["tags"] = tags_finales if tags_finales else ["tnm.alzheimer"]  # Al menos un tag por defecto
            
            # Para artículos, agregar campos adicionales
            if item.get("tipo") == "article":
                # Excerpt: usar resumen o abstract como fallback
                excerpt = resumen[:500] if resumen else (item.get("abstract", "")[:500] if item.get("abstract") else "")
                item["excerpt"] = excerpt if excerpt else None
                
                # Source: mapear desde revista
                item["source"] = item.get("revista", "") or None
                
                # Language: intentar detectar o usar "en" por defecto
                language_metrics = metricas.get("language", item.get("language", "en"))
                item["language"] = self._normalizar_idioma(language_metrics, "en")
                
                # Score: se calculará después si es necesario
                item["score"] = None
                
                # Fecha de procesamiento
                item["processed_at"] = datetime.now().strftime('%Y-%m-%d')
            
            return item
            
        except Exception as e:
            logging.error(f"Error al procesar con IA: {e}", exc_info=True)
            # Asegurar que el item tenga idioma normalizado incluso si hay error
            language = item.get("language", "en")
            item["language"] = self._normalizar_idioma(language, "en")
            return item
    
    def _normalizar_idioma(self, language: str, idioma_default: str = "en") -> str:
        """
        Normaliza el campo language para asegurar que siempre tenga un valor válido.
        
        Args:
            language: Idioma a normalizar (puede ser string, None, o vacío)
            idioma_default: Idioma por defecto si no se puede determinar
            
        Returns:
            Código de idioma normalizado (es, en, fr, de, it, pt)
        """
        # Idiomas válidos
        idiomas_validos = ['es', 'en', 'fr', 'de', 'it', 'pt']
        
        # Si es None o no es string, usar default
        if not language or not isinstance(language, str):
            return idioma_default if idioma_default in idiomas_validos else 'en'
        
        # Limpiar y normalizar
        language = language.strip().lower()
        
        # Si contiene múltiples idiomas separados por coma, tomar el primero
        if ',' in language:
            language = language.split(',')[0].strip()
        
        # Validar contra idiomas válidos
        if language in idiomas_validos:
            return language
        
        # Si no es válido, usar default
        return idioma_default if idioma_default in idiomas_validos else 'en'
    
    def _generar_resumen(self, client: AzureOpenAI, texto: str, idioma: str = "es") -> str:
        """Genera un resumen del texto usando Azure OpenAI en el idioma especificado"""
        try:
            # Limitar tamaño del texto
            texto_limite = texto[:4000]
            
            idioma_nombre = "español" if idioma == "es" else "inglés"
            
            prompt = f"""Genera un resumen conciso y completo del siguiente texto en {idioma_nombre}. 
El resumen debe capturar las ideas principales y ser informativo.

Texto:
{texto_limite}

Resumen:"""
            
            response = client.chat.completions.create(
                model=self.azure_openai_deployment,
                messages=[
                    {"role": "system", "content": f"Eres un asistente experto en generar resúmenes concisos y precisos de textos científicos y médicos en {idioma_nombre}."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=500,
                temperature=0.3
            )
            
            return response.choices[0].message.content.strip()
            
        except Exception as e:
            logging.error(f"Error al generar resumen: {e}")
            return ""
    
    def _extraer_puntos_clave(self, client: AzureOpenAI, texto: str, idioma: str = "es") -> List[str]:
        """Extrae puntos clave del texto usando Azure OpenAI en el idioma especificado"""
        try:
            idioma_nombre = "español" if idioma == "es" else "inglés"
            
            prompt = f"""Extrae los 5 puntos más relevantes del siguiente texto.
Presenta cada punto en una línea separada, de forma clara y concisa en {idioma_nombre}.

Texto:
{texto[:4000]}

Puntos relevantes:"""
            
            response = client.chat.completions.create(
                model=self.azure_openai_deployment,
                messages=[
                    {"role": "system", "content": f"Eres un asistente experto en analizar textos científicos y médicos para extraer los puntos más relevantes. Responde siempre en {idioma_nombre}."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=800,
                temperature=0.3
            )
            
            contenido = response.choices[0].message.content.strip()
            puntos = [p.strip() for p in contenido.split('\n') if p.strip()]
            
            # Limpiar numeración
            puntos_limpios = []
            for punto in puntos:
                punto_limpio = punto.lstrip('0123456789.-•* ').strip()
                if punto_limpio:
                    puntos_limpios.append(punto_limpio)
            
            return puntos_limpios[:5]  # Máximo 5 puntos
            
        except Exception as e:
            logging.error(f"Error al extraer puntos clave: {e}")
            return []
    
    def _extraer_metricas(self, client: AzureOpenAI, texto: str) -> Dict:
        """Extrae métricas (fecha, categorías) del texto usando Azure OpenAI"""
        try:
            lista_tipos = "\n".join([f"- {tipo}" for tipo in self.tipos_demencia_nombres])
            
            prompt = f"""Analiza el siguiente texto y extrae:

1. FECHA DE PUBLICACIÓN: Busca cualquier fecha de publicación. Si encuentras una, devuélvela en formato YYYY-MM-DD. Si no, devuelve null.

2. CATEGORÍAS DE ALZHEIMER: Identifica qué tipos de Alzheimer se mencionan. Solo selecciona de esta lista:
{lista_tipos}
Si no se menciona ningún tipo, devuelve una lista vacía.

Devuelve la respuesta en formato JSON:
{{
    "fecha_publicacion": "YYYY-MM-DD o null",
    "categorias": ["tipo1", "tipo2", ...]
}}

Texto:
{texto[:4000]}"""
            
            response = client.chat.completions.create(
                model=self.azure_openai_deployment,
                messages=[
                    {"role": "system", "content": "Eres un asistente experto en analizar textos científicos y médicos para extraer información estructurada. Siempre responde en formato JSON válido."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=500,
                temperature=0.2,
                response_format={"type": "json_object"}
            )
            
            contenido = response.choices[0].message.content.strip()
            metricas = json.loads(contenido)
            
            # Validar categorías (aceptar cualquier categoría, se mapeará después)
            categorias = metricas.get("categorias", [])
            if not isinstance(categorias, list):
                categorias = []
            # Aceptar todas las categorías, se mapearán a tags del frontend después
            metricas["categorias"] = categorias
            
            return metricas
            
        except Exception as e:
            logging.error(f"Error al extraer métricas: {e}")
            return {"fecha_publicacion": None, "categorias": []}
    
    def _traducir_contenido(self, client: AzureOpenAI, texto: str, idioma_destino: str) -> str:
        """
        Traduce contenido usando Azure OpenAI.
        
        Args:
            client: Cliente de Azure OpenAI
            texto: Texto a traducir
            idioma_destino: 'es' para español, 'en' para inglés
        
        Returns:
            Texto traducido
        """
        if not texto or len(texto.strip()) < 10:
            return texto
        
        try:
            idioma_nombre = "español" if idioma_destino == "es" else "inglés"
            
            prompt = f"""Traduce el siguiente texto al {idioma_nombre}. 
Mantén el tono profesional y científico. 
Traduce de forma precisa y natural.

Texto:
{texto[:3000]}

Traducción:"""
            
            response = client.chat.completions.create(
                model=self.azure_openai_deployment,
                messages=[
                    {"role": "system", "content": f"Eres un traductor experto especializado en textos científicos y médicos. Traduce al {idioma_nombre} de forma precisa y natural."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=1000,
                temperature=0.3
            )
            
            return response.choices[0].message.content.strip()
            
        except Exception as e:
            logging.error(f"Error al traducir contenido: {e}")
            return texto  # Retornar texto original si falla la traducción
    
    def _generar_version_idioma(self, item: Dict, idioma_destino: str) -> Optional[Dict]:
        """
        Genera una versión del artículo/noticia en otro idioma.
        
        Args:
            item: Item original procesado
            idioma_destino: 'es' para español, 'en' para inglés
        
        Returns:
            Item traducido o None si no se puede traducir
        """
        if not self.azure_openai_endpoint or not self.azure_openai_api_key:
            return None
        
        # Obtener idioma original y normalizarlo
        idioma_original = item.get("language", "en")
        idioma_original = self._normalizar_idioma(idioma_original, "en")
        
        # Si ya está en el idioma destino, no hacer nada
        if idioma_original == idioma_destino:
            return None
        
        try:
            # Crear cliente de Azure OpenAI
            client = AzureOpenAI(
                api_key=self.azure_openai_api_key,
                api_version=self.azure_openai_api_version,
                azure_endpoint=self.azure_openai_endpoint
            )
            
            # Crear copia del item
            item_traducido = item.copy()
            
            # Traducir título
            titulo_original = item.get("titulo", "") or item.get("title", "")
            if titulo_original:
                item_traducido["titulo"] = self._traducir_contenido(client, titulo_original, idioma_destino)
                item_traducido["title"] = item_traducido["titulo"]
            
            # Traducir summary
            summary_original = item.get("summary", "")
            if summary_original:
                item_traducido["summary"] = self._traducir_contenido(client, summary_original, idioma_destino)
            
            # Traducir excerpt
            excerpt_original = item.get("excerpt", "")
            if excerpt_original:
                item_traducido["excerpt"] = self._traducir_contenido(client, excerpt_original, idioma_destino)
            
            # Traducir key_points
            key_points_original = item.get("key_points", [])
            if key_points_original and isinstance(key_points_original, list):
                key_points_traducidos = []
                for punto in key_points_original:
                    if punto:
                        punto_traducido = self._traducir_contenido(client, str(punto), idioma_destino)
                        key_points_traducidos.append(punto_traducido)
                item_traducido["key_points"] = key_points_traducidos
            
            # Actualizar idioma
            item_traducido["language"] = idioma_destino
            
            # Mantener la misma URL (es el mismo artículo)
            # Los tags se mantienen iguales
            
            logging.info(f"Versión traducida generada: {idioma_original} -> {idioma_destino}")
            return item_traducido
            
        except Exception as e:
            logging.error(f"Error al generar versión en {idioma_destino}: {e}", exc_info=True)
            return None
    
    def formatear_para_bd(self, item: Dict) -> Dict:
        """
        Formatea un artículo/noticia según el esquema de la base de datos.
        
        Args:
            item: Diccionario con información del artículo/noticia
        
        Returns:
            Diccionario formateado según esquema de BD
        """
        # Obtener URL (clave primaria)
        url = item.get("url", "")
        if not url:
            # Generar URL temporal si no existe
            if item.get("tipo") == "article":
                url = f"https://pubmed.ncbi.nlm.nih.gov/{item.get('pmid', 'unknown')}/"
            else:
                url = item.get("uuid", f"news_{item.get('titulo', 'unknown')[:50]}")
        
        # Formatear fecha
        fecha_pub = item.get("fecha_publicacion", "")
        if fecha_pub:
            # Intentar parsear fecha
            try:
                # Formato ISO
                if 'T' in fecha_pub:
                    fecha_pub = fecha_pub.split('T')[0]
                # Validar formato YYYY-MM-DD
                datetime.strptime(fecha_pub, '%Y-%m-%d')
            except:
                fecha_pub = datetime.now().strftime('%Y-%m-%d')
        else:
            fecha_pub = datetime.now().strftime('%Y-%m-%d')
        
        # Obtener tags (asegurar que sea lista y tenga al menos un tag)
        tags = item.get("tags", [])
        if isinstance(tags, str):
            try:
                tags = json.loads(tags)
            except:
                tags = []
        if not isinstance(tags, list):
            tags = []
        # Asegurar que siempre haya al menos un tag
        if not tags:
            tags = ["tnm.alzheimer"]  # Tag por defecto
        
        # Obtener key_points (asegurar que sea lista)
        key_points = item.get("key_points", [])
        if isinstance(key_points, str):
            try:
                key_points = json.loads(key_points)
            except:
                key_points = []
        if not isinstance(key_points, list):
            key_points = []
        
        # Construir item formateado
        item_formateado = {
            "url": url[:767],  # Límite de BD
            "tipo": item.get("tipo", "article"),
            "title": item.get("titulo", "")[:500],  # Límite de BD
            "published_at": fecha_pub,
            "tags": tags,  # Array para JSON, se convertirá a string JSON en BD si es necesario
            "summary": item.get("summary", "") or item.get("descripcion", "") or item.get("abstract", "")
        }
        
        # Campos específicos de artículos
        if item.get("tipo") == "article":
            excerpt = item.get("excerpt", "")
            item_formateado["excerpt"] = excerpt[:500] if excerpt else None
            
            item_formateado["processed_at"] = item.get("processed_at")
            item_formateado["score"] = item.get("score")
            
            source = item.get("source", "") or item.get("revista", "")
            item_formateado["source"] = source[:255] if source else None
            
            # Normalizar idioma para asegurar que siempre tenga valor válido
            language = item.get("language", "en")
            language_normalizado = self._normalizar_idioma(language, "en")
            item_formateado["language"] = language_normalizado
            
            item_formateado["key_points"] = key_points if key_points else None
        
        # Campos específicos de noticias
        if item.get("tipo") == "news":
            item_formateado["image_url"] = item.get("image_url", "")[:1000] if item.get("image_url") else None
            # Las noticias también deben tener idioma siempre
            language_news = item.get("language", "en")
            item_formateado["language"] = self._normalizar_idioma(language_news, "en")
        
        return item_formateado
    
    def subir_a_storage(self, items: List[Dict], nombre_archivo: str) -> str:
        """
        Sube los items procesados a Azure Storage como JSON.
        
        Args:
            items: Lista de items procesados
            nombre_archivo: Nombre del archivo en storage
        
        Returns:
            URL del archivo subido
        """
        if not self.storage_connection_string:
            logging.warning("AZURE_STORAGE_CONNECTION_STRING o AzureWebJobsStorage no configurado. No se puede subir a storage.")
            return ""
        
        try:
            # Crear JSON
            json_data = json.dumps(items, ensure_ascii=False, indent=2, default=str)
            
            # Crear cliente de Blob Service
            blob_service_client = BlobServiceClient.from_connection_string(self.storage_connection_string)
            
            # Obtener cliente del contenedor
            container_client = blob_service_client.get_container_client(self.storage_container)
            
            # Crear contenedor si no existe
            if not container_client.exists():
                logging.info(f"Creando contenedor '{self.storage_container}'...")
                container_client.create_container()
            
            # Obtener cliente del blob
            blob_client = container_client.get_blob_client(nombre_archivo)
            
            # Subir el archivo
            logging.info(f"Subiendo {len(items)} items a storage como '{nombre_archivo}'...")
            content_settings = ContentSettings(content_type='application/json')
            blob_client.upload_blob(json_data.encode('utf-8'), overwrite=True, content_settings=content_settings)
            
            url_archivo = blob_client.url
            logging.info(f"Archivo subido exitosamente. URL: {url_archivo}")
            
            return url_archivo
            
        except AzureError as e:
            logging.error(f"Error de Azure Storage: {e}", exc_info=True)
            return ""
        except Exception as e:
            logging.error(f"Error al subir a storage: {e}", exc_info=True)
            return ""
    
    def ejecutar_recopilacion(self, week: Optional[str] = None, year: Optional[str] = None) -> Dict:
        """
        Ejecuta el proceso completo de recopilación.
        
        Args:
            week: Número de semana (1-53) o None para semana actual
            year: Año (YYYY) o None para año actual
        
        Returns:
            Diccionario con resultado de la recopilación
        """
        inicio = time.time()
        logging.info("=== INICIANDO RECOPILACIÓN ===")
        
        # Obtener fechas de la semana
        logging.info(f'Recopilador fechas: week={week}, year={year}')
        fecha_desde, fecha_hasta = self.obtener_fechas_semana(week, year)
        logging.info(f"Buscando artículos y noticias del {fecha_desde} al {fecha_hasta}")
        
        # Buscar artículos de NCBI
        logging.info("Buscando artículos en NCBI...")
        articulos = self.buscar_articulos_ncbi(fecha_desde, fecha_hasta)
        logging.info(f"Artículos encontrados: {len(articulos)}")
        
        # Buscar noticias de TheNewsAPI
        logging.info("Buscando noticias en TheNewsAPI...")
        noticias = self.buscar_noticias_thenewsapi(fecha_desde, fecha_hasta)
        logging.info(f"Noticias encontradas: {len(noticias)}")
        
        # Combinar todos los items
        todos_items = articulos + noticias
        logging.info(f"Total de items encontrados: {len(todos_items)}")
        
        # Procesar con IA y generar versiones en ambos idiomas
        items_procesados = []
        for i, item in enumerate(todos_items, 1):
            logging.info(f"Procesando item {i}/{len(todos_items)}: {item.get('titulo', 'Sin título')[:50]}...")
            
            # Procesar con IA
            item_procesado = self.procesar_con_ia(item)
            
            # Detectar idioma original y normalizarlo
            idioma_original = item_procesado.get("language", "en")
            if idioma_original not in ["es", "en"]:
                # Intentar detectar desde el texto
                texto_deteccion = item_procesado.get("titulo", "") or item_procesado.get("abstract", "")
                if texto_deteccion:
                    # Detección simple: buscar palabras comunes en español
                    palabras_es = ["de", "la", "el", "en", "y", "con", "para", "por", "del", "los", "las"]
                    palabras_en = ["the", "of", "and", "in", "to", "for", "with", "on", "at", "from"]
                    texto_lower = texto_deteccion.lower()
                    count_es = sum(1 for palabra in palabras_es if f" {palabra} " in f" {texto_lower} ")
                    count_en = sum(1 for palabra in palabras_en if f" {palabra} " in f" {texto_lower} ")
                    idioma_detectado = "es" if count_es > count_en else "en"
                    idioma_original = self._normalizar_idioma(idioma_original, idioma_detectado)
                else:
                    idioma_original = self._normalizar_idioma(idioma_original, "en")
            else:
                # Asegurar que está normalizado
                idioma_original = self._normalizar_idioma(idioma_original, "en")
            
            item_procesado["language"] = idioma_original
            
            # Formatear versión original
            item_formateado = self.formatear_para_bd(item_procesado)
            items_procesados.append(item_formateado)
            
            # Generar versión en el otro idioma si es español o inglés
            if idioma_original in ["es", "en"]:
                idioma_destino = "en" if idioma_original == "es" else "es"
                logging.info(f"Generando versión en {idioma_destino}...")
                
                item_traducido = self._generar_version_idioma(item_procesado, idioma_destino)
                if item_traducido:
                    item_formateado_traducido = self.formatear_para_bd(item_traducido)
                    items_procesados.append(item_formateado_traducido)
                    logging.info(f"Versión en {idioma_destino} generada exitosamente")
            
            # Delay para respetar rate limits
            if i % 10 == 0:
                time.sleep(1)
            else:
                time.sleep(0.2)
        
        # Generar nombre de archivo con fecha y hora
        nombre_archivo = f"recopilacion_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        
        # Subir a storage
        url_archivo = self.subir_a_storage(items_procesados, nombre_archivo)
        
        # Calcular tiempo transcurrido
        tiempo_transcurrido = time.time() - inicio
        
        # Preparar resultado
        resultado = {
            "articulos_encontrados": len(articulos),
            "noticias_encontradas": len(noticias),
            "total_procesados": len(items_procesados),
            "fecha_desde": fecha_desde,
            "fecha_hasta": fecha_hasta,
            "nombre_archivo": nombre_archivo,
            "url_archivo": url_archivo,
            "tiempo_segundos": round(tiempo_transcurrido, 2)
        }
        
        logging.info("=== RECOPILACIÓN COMPLETADA ===")
        logging.info(f"Resultado: {json.dumps(resultado, ensure_ascii=False, indent=2)}")
        
        return resultado

