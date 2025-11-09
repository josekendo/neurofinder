## Frontend Angular (`web/`)

Aplicación SPA desarrollada con Angular 18 y Angular Material, diseñada con enfoque responsive y criterios AA de accesibilidad. El despliegue final se realizará mediante `ng build --configuration=production` y subida manual por FTP a la infraestructura indicada por el tutor.

El sitio público se sirve desde `neurofinder.org`, donde se alojan todas las rutas de la SPA y los recursos estáticos generados; las llamadas REST se dirigen a `https://neurofinder.org/api`.

### Despliegue en OVH
- **Ruta frontend**: subir el contenido de `dist/web` al directorio `/www` para que las vistas queden en `https://neurofinder.org/`.
- **Ruta backend**: la API mock se publica en `/www/api`; la SPA ya está configurada para consumir `https://neurofinder.org/api`.
- **Variables necesarias**: en el hosting establecer `APP_PROFILE=mock` y `APP_BASE_PATH=api` para alinear el backend con la estructura `/www/api`.
- **Reescrituras**: mantener el `.htaccess` del backend dentro de `/www/api/public` para que el router gestione todas las peticiones dinámicas.

### Estructura de módulos
- `core`: servicios compartidos, guardas, interceptores y configuración de traducciones.
- `search`: vistas y componentes relacionados con el buscador principal y los resultados.
- `articles`: detalle de artículos/medios, lectura de resúmenes y navegación entre contenidos relacionados.
- `news`: listado de novedades curadas y destacados semanales.
- `shared`: componentes reutilizables (cards, chips de etiquetas, breadcrumbs, indicadores de fiabilidad).

### Pantallas clave
1. **Inicio (`/home`)**  
   - Hero central con buscador semántico (input, botón buscar, sugerencias automáticas).  
   - Bloque inferior con las 6 noticias más recientes mostradas en dos filas de tres cards, ordenadas cronológicamente.  
   - Cintillo lateral con métricas en tiempo real (número de fuentes indexadas, última actualización).

2. **Resultados (`/search`)**  
   - Cabecera con enlaces útiles (guías DSM/CIE, soporte, documentación del proyecto).  
   - Panel izquierdo con filtros dinámicos: tipo de demencia, tipo de documento, rango de fechas, idioma, puntuación mínima.  
   - Sección derecha con tarjetas de resultados que muestran título, extracto, etiquetas y puntuación de fiabilidad.  
   - Paginación infinita y posibilidad de guardar búsquedas (cuando el usuario está autenticado).

3. **Detalle de medio (`/articles/:id`)**  
   - Información destacada: fecha de publicación, fecha de procesado, institución emisora y nivel de fiabilidad.  
   - Resumen generado, puntos clave enumerados y listado de etiquetas taxonómicas.  
   - Enlace claro a la fuente original, botón para reportar incidencias y módulo de medios relacionados (hasta 6, ordenados por similitud semántica).  
   - Panel de “Contexto” con visualización de relaciones (grafo simplificado o chips enlazados).

### Consideraciones adicionales
- **Estado global**: uso de NgRx para gestionar búsquedas recientes, filtros activos y caché temporal de resultados.
- **Internacionalización**: soporte inicial en español e inglés con `@ngx-translate/core`; arquitectura preparada para añadir más idiomas.
- **Observabilidad**: integración con Azure Application Insights para trazabilidad de eventos y métricas de uso.
- **Testing**: pruebas unitarias con Jest, end-to-end con Cypress (escenarios críticos: búsqueda, aplicación de filtros, visualización de un artículo).

El proyecto incluye una guía de estilos (Figma) basada en diseño limpio y tipografía accesible, enfatizando claridad y jerarquía visual para usuarios no expertos.