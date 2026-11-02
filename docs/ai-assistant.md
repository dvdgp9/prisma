# Asistente de Prisma — contratos IA1

Estado: IA1, IA2 e IA3 desplegados y verificados en LucusHost. IA4 e IA5 completarán las acciones y la integración de los accesos existentes.
Plan y seguimiento: `.cursor/scratchpad.md`, bloque «Planner — Asistente unificado».

## Componentes disponibles

- `includes/ai.php`: configuración cifrada existente, cliente OpenRouter con transporte sustituible en tests, límites de contexto/salida, errores públicos sin cuerpos del proveedor, uso de tokens y validación local de respuestas estructuradas. No ejecuta herramientas ni escribe en BD.
- `api/ai-inbox.php`, `api/ai-summary.php`, `api/ai-settings.php?action=test`: usan ese cliente con sus contratos de respuesta existentes. Cierran el bloqueo de sesión después de resolver usuario/datos y antes de contactar al proveedor.
- `includes/work-records.php`: primitivas de creación de mejora, tarea y checklist utilizadas por las APIs existentes. Reciben campos validados; los controladores conservan sus comprobaciones y avisos. No hacen commit ni envían respuestas HTTP, para poder participar en una tarjeta atómica en IA4. No constituyen por sí solas una API autorizada del asistente.
- `migrations/020_ai_assistant.sql`: persistencia de conversaciones, mensajes, fuentes y propuestas, desplegada en producción. No es necesaria para el refactor aislado de los tres endpoints anteriores.

## Contrato de contexto (IA2–IA3)

Objeto versionado: `{version: 1, scope: "mine"|"team"|"app"|"request"|"task"|"note", entity_id: int|null, filters: {...}, timezone: "Europe/Madrid", fixed: bool}`. Fecha actual enviada por servidor en cada consulta; zona IANA validada, sin confiar en fechas o permisos enviados por cliente. Lista cerrada de filtros: persona, estado y rango de fechas.

El navegador propone el contexto; el servidor valida cada referencia. `mine` y `team` deben coincidir con `work-helpers.js`/`my-work.js`, incluyendo tareas delegadas/compartidas y mejoras asignadas. No decidir el alcance solo mediante el rol ni confundir apps accesibles con trabajo propio. El servidor reconstruye títulos, URLs y capacidades, nunca los toma del modelo.

Cada mensaje guarda su contexto inmutable. Navegar solo cambia el siguiente mensaje; las propuestas pendientes siguen ligadas a su contexto original. El selector puede fijarse. El borrador local se identifica por usuario y conversación; se borra al cerrar sesión/cambiar usuario. Listado de conversaciones paginado, sin cargar todos los mensajes.

## Permisos e historial (IA2–IA3)

Todas las operaciones de conversación exigen sesión, propietario y empresa/sesión vigente. Un superadmin tampoco puede leer conversaciones ajenas. No reutilizar en caché privada respuestas entre usuarios.

Cada mensaje derivado de datos de Prisma registra sus fuentes en `ai_message_sources`. El conjunto incluye las fuentes heredadas de **todos** los mensajes/consultas que entraron en esa generación, también las citadas implícitamente. Los comentarios y checklist dependen de la mejora padre. El contexto de la conversación y un título generado también son datos derivados.

Al listar/abrir/retomar, revalidar fuentes y campos visibles con las capacidades actuales antes de devolver texto o enviarlo a IA. `access_fingerprint` es SHA-256 del ámbito y conjunto de campos autorizado, sin contenido. Un cambio de rol que mantenga `view` pero oculte datos de solicitantes también invalida el contenido derivado anterior.

Estrategia conservadora inicial: si desaparece una fuente o disminuye el acceso a sus campos, invalidar la conversación completa bajo transacción: sustituir mensajes por un aviso genérico, vaciar payloads/resultados de propuestas, marcar las pendientes `superseded`, restablecer contexto/título y eliminar dependencias. No devolver primero el título ni texto anterior, y no mandar ese historial al modelo. El usuario puede empezar otra conversación con su acceso vigente. No prometer revocar información ya mostrada en un navegador abierto; revalidar de nuevo en cada petición.

## Mensajes y concurrencia (IA2–IA3)

- `client_key` única por conversación para reintentos de envío; estados de mensaje: `pending`, `complete`, `failed`, `redacted`.
- Al enviar, bloquear brevemente la conversación y comprobar su `revision`; reservar el turno, guardar el mensaje y liberar transacción y sesión antes de la llamada lenta. Una consulta activa por conversación; otras conversaciones pueden avanzar.
- Completar con comparación de revisión/turno y revalidación de permisos/fuentes. Si ha cambiado el contexto de acceso, se descarta la respuesta. Recuperar un turno interrumpido no vuelve a escribir cambios de dominio.
- No mantener bloqueos de MySQL durante llamadas HTTP. Nunca reintentar automáticamente la aplicación de acciones desde una respuesta del modelo.
- Límite compartido actual: 40 mensajes, 100.000 caracteres de contexto, hasta 8.192 tokens de salida y 1 MiB de respuesta HTTP. IA3 seleccionará historial/fuentes relevantes dentro de esos límites y notificará cobertura parcial; no truncar una propuesta para ejecutarla.

## Propuestas y ejecución (IA4)

Una fila de `ai_proposals` representa una revisión de una tarjeta. Estados: `pending`, `superseded`, `applied`, `failed`, `needs_review`. `card_key` identifica la tarjeta; `revision` aumenta incluso al editar manualmente; `execution_key` es aleatoria y única, generada en servidor. La revisión anterior pasa a `superseded`. Las tarjetas ya aplicadas quedan inmutables; una nueva petición prepara otra operación.

Acciones inicialmente previstas: `create_request`, `create_task`, `update_request`, `update_task`, `add_comment`, `add_checklist`, `set_request_assignees`, `set_task_shares`. Cada una tendrá esquema y comprobación de capacidad explícitos. Las operaciones relacionadas con una creación se integran en su tarjeta. No exponer SQL ni llamar endpoints arbitrarios desde instrucciones del modelo.

Aplicar recibe ID y revisión de propuesta; el payload aplicado se lee de BD. Transacción por tarjeta: bloquear fila, verificar propietario/revisión/estado; si está `applied`, devolver resultado guardado; revalidar capacidades y destinatarios; bloquear registros afectados y comparar los valores originales de los campos que se modificarán (`expected_json`); ejecutar dominio y guardar resultado/estado antes del mismo commit. Si hay conflicto, `needs_review` sin mutaciones. Dos pestañas no pueden aplicar dos veces la misma tarjeta. El lote devuelve resultados por tarjeta y solo reintenta las pendientes.

Los efectos de BD (asignaciones, checklist y avisos) participan en la misma transacción cuando proceda. Para correo externo u otros efectos tras commit, IA4 debe introducir una cola de salida con clave única derivada de la ejecución antes de habilitarlos; no basta con dispararlos después de commit sin deduplicación. Un fallo de entrega no implica que haya fallado el guardado.

## Despliegue y comprobación

IA1 puede desplegar su refactor de endpoints sin la migración 020. Para habilitar IA2, comprobar esquema real, versión de MySQL/MariaDB, motores y tipos de `users.id`, ejecutar 020 una vez con copia de seguridad y comprobar las cuatro tablas. `CREATE TABLE IF NOT EXISTS` permite repetir la creación pero no repara tablas preexistentes con otro esquema. No ejecutar desde el navegador ni automáticamente durante peticiones.

Usar peticiones acotadas sin streaming por ahora. Comprobar con el hosting real el tiempo de respuesta, buffering y extensiones cURL/mbstring/OpenSSL/PDO. No cambiar modelo ni claves de administración durante el refactor. Pruebas locales con proveedor simulado, SQLite para primitivas de dominio y regresión de la suite existente. Prueba real del proveedor solo con entorno configurado y sin exponer credenciales o datos de usuarios en el informe.

### Comprobación previa de LucusHost en IA1 — 2 Nov 2026

Acceso SSH verificado con la clave local `~/.ssh/id_ed25519`, usuario `umilpdfe`, host `wthfox.lucusvirtual.es`, puerto 22, comprobación estricta de host. Aplicación: `/home/umilpdfe/prisma.wthefox.com`. No se ha guardado ninguna clave en el repositorio.

PHP CLI 8.3.36; cURL, PDO MySQL, mbstring y OpenSSL disponibles. BD MariaDB 11.4.13; `users`, `requests`, `tasks`, `request_checklist_items` y `app_settings` usan InnoDB; `users.id` es `int(11)` con signo. Las tablas 020 aún no existen. Modelo y clave IA ya configurados. `output_buffering=0` y `max_execution_time=0` son valores de CLI: no confirman los límites del servidor web.

Se ejecutó el cliente nuevo por stdin en una sesión SSH, sin instalarlo ni escribir en la aplicación/BD. Una consulta sintética estructurada devolvió `ok=true` con el modelo configurado y 61 tokens totales. No se enviaron registros de usuarios al proveedor en esta comprobación. Migración 020 preparada pero sin ejecutar; sigue pendiente validarla mediante ejecución en una BD de pruebas equivalente antes de habilitar IA2.

## IA2 implementado — 2 Nov 2026

### Interfaz y APIs

`includes/assistant-panel.php`, `assets/js/assistant.js` y `assets/css/assistant.css` forman el panel compartido, cargado desde la barra lateral. La entrada «Asistente» lo abre; los detalles de mejora y tarea tienen también acceso contextual. El panel sigue la vista o permite fijar su contexto, guarda el borrador local por usuario/empresa/conversación y recupera el chat al navegar o recargar. El historial permite retomar, renombrar y eliminar conversaciones. En móvil se presenta como diálogo a pantalla completa, con fondo inerte, foco contenido y cierre con Escape; en escritorio permite seguir usando la página. Los atajos del panel no activan los editores que queden debajo.

- `GET /api/ai-conversations.php`: historial paginado, 20 conversaciones por página en orden estable de creación, `before` como cursor.
- `GET ?id=N&before=M`: conversación y hasta 50 mensajes, con cursor para cargar anteriores.
- `GET ?action=context&context=JSON`: valida el contexto y resuelve su nombre real.
- `POST {context}`: crea conversación; `PUT {id,title,revision}`: renombra con control de revisión; `DELETE {id}`: borra conversación y sus dependencias mediante claves foráneas.
- `POST /api/ai-messages.php {conversation_id,content,client_key,revision,context}`: reserva el turno, libera sesión, consulta IA y guarda respuesta; devuelve el detalle. Las mutaciones requieren `X-Prisma-CSRF` con el token de sesión. Respuestas privadas con `Cache-Control: no-store`.

`includes/ai-assistant.php` implementa el ciclo de persistencia y turnos; `includes/ai-assistant-http.php` lo conecta con autenticación, permisos y HTTP. La cuenta se relee de BD antes y después de la llamada lenta, incluyendo rol, empresa y estado activo. Un superadmin tampoco puede leer conversaciones de otro propietario. El cliente descarta respuestas que pertenezcan a una selección anterior de conversación y conserva una clave de envío para recuperar respuestas perdidas. `ai_messages.turn_revision` identifica cada intento, para que una respuesta vieja no marque como fallido un intento nuevo del mismo mensaje. Los turnos interrumpidos pasan a fallidos tras 90 segundos y admiten reintento explícito.

Al cerrar sesión o cambiar de usuario se limpian los borradores y la selección local; el cambio se comunica entre pestañas. La página de login también limpia borradores, cubriendo un logout por URL directa. No se guardan nombres de fuentes en el estado local persistido. El historial se revalida antes de mostrarse o entrar en el contexto del modelo; al perder acceso se redactan mensajes, títulos y payloads derivados. En una recarga de una conversación redactada se restablece el contexto del panel.

### Alcance de conversación en esta entrega

La conversación ya usa el proveedor configurado y permite razonar/redactar sobre texto compartido por el usuario. El contexto de navegación contiene únicamente la referencia y el nombre autorizado del registro. **Las consultas al contenido real de mejoras, tareas, notas y comentarios pertenecen a IA3; las tarjetas y escrituras de dominio a IA4.** El sistema no afirma haber consultado esos contenidos ni aplicado cambios. «Crear mejoras y tareas desde apuntes» mantiene acceso al flujo anterior hasta su integración en IA5.

El historial enviado al modelo está acotado a los últimos 20 mensajes completos y 60.000 caracteres; la paginación de la interfaz permite leer mensajes anteriores. No se mantienen transacciones ni bloqueos de sesión durante llamadas al proveedor.

### Verificación y límites

- 42 scripts de tests en verde: los 40 anteriores y los nuevos `tests/ai-assistant.php` y `tests/ai-assistant-access.php`. Incluyen aislamiento por propietario/empresa, actualización de rol, notas y tareas privadas/compartidas, revocación, downgrade de capacidades, doble envío, intento antiguo frente a reintento, turno expirado, borrado en cascada y paginación.
- Migración 020 ejecutada y repetida correctamente en una **MariaDB 12.2.2 temporal local**, con datos ficticios, sin red y usando PDO con prepares nativos. Mismo ciclo de persistencia probado también en SQLite. La versión temporal difiere de MariaDB 11.4 de producción; queda pendiente aplicar y verificar allí al desplegar.
- Integración HTTP sobre copias de los PHP reales y una BD temporal: CSRF, propiedad, contexto de equipo, envío con proveedor simulado, reintento sin duplicados, renombrado/borrado, revocación de fuente y cuenta desactivada. El único reemplazo de IA del arnés es el transporte HTTP; no consume claves reales ni datos de producción.
- Navegador: conversación y borrador conservados entre Inicio/Tareas/Notas; contexto de nota y tarea; fijar contexto y navegar; historial y renombrado; error de proveedor y reintento sin duplicado; cambiar de conversación durante respuesta lenta; cambio de usuario sin borrador ajeno; desktop 1440×1000 y móvil 375×812, sin scroll horizontal, foco circular y Escape; consola sin errores en la pasada comprobada.
- Capturas de pruebas guardadas fuera del repositorio en los artefactos de esta conversación. Arnés y BD temporal en `/private/tmp/prisma-ia2-preview` y `/private/tmp/prisma-ia2-db`, sin archivos de prueba en producción.
- No se ha desplegado ni ejecutado 020 en producción. El cliente común de IA había pasado una consulta real desde LucusHost en IA1; en IA2 el recorrido completo se verifica con transporte simulado. No se ha hecho una prueba completa de navegador contra producción.

### Orden de despliegue IA1 + IA2

1. Copia de seguridad y migración `020_ai_assistant.sql` final (incluye `turn_revision`); comprobar las cuatro tablas y sus claves foráneas. No basta con repetir `CREATE IF NOT EXISTS` si se creó un borrador anterior de 020.
2. Subir `includes/ai.php`, `includes/work-records.php`, `includes/ai-assistant.php`, `includes/ai-assistant-http.php` y `includes/assistant-panel.php`.
3. Subir endpoints IA1 actualizados (`api/ai-inbox.php`, `api/ai-summary.php`, `api/ai-settings.php`) y los de dominio que usan los helpers (`api/requests.php`, `api/tasks.php`, `api/request-checklist.php`), más `api/ai-conversations.php` y `api/ai-messages.php`.
4. Subir `assets/css/assistant.css` y `assets/js/assistant.js`; después `includes/sidebar.php`, `index.php`, `tasks.php` y `login.php`.
5. Subir `sw.js` al final (prisma-v48 / runtime-v36) y verificar en HTTP tanto el panel como el proveedor real. Si falta la migración, los endpoints del asistente responden 503 con mensaje de configuración, sin fallar la aplicación principal.

Tests y fixtures no son necesarios en producción. IA3 es el siguiente bloque del plan.

## Despliegue IA1 + IA2 — 2 Nov 2026

Base local/GitHub/servidor: `0c9a555` en `main`. Copia privada previa en `/home/umilpdfe/.prisma-deploy-backups/ia2-20261102-170820-056592`: `database.sql`, `code.tar.gz` y `manifest.json`, con permisos restringidos. El usuario autoriza expresamente publicar IA1+IA2 en el repositorio público `dvdgp9/prisma` y desplegar. La migración 020 se ha aplicado en MariaDB 11.4 de producción y se han verificado las cuatro tablas y `ai_messages.turn_revision`. Publicación del código y verificación HTTP en curso.

### Resultado de producción IA1 + IA2

Commit `bdae3f2`, publicado y desplegado el 2 Nov 2026 mediante fast-forward desde `0c9a555`. La migración 020 se ha aplicado en MariaDB 11.4.13 de LucusHost y se han comprobado las cuatro tablas, `turn_revision` y la sintaxis de 17 archivos PHP. Los archivos untracked previos del servidor se han conservado.

Comprobación por HTTPS con sesión temporal: carga de página y assets, respuesta 401 sin sesión, 403 sin CSRF, creación de conversación, respuesta del proveedor real («Prisma está listo.»), reintento sin duplicados y recuperación del historial. Conversación y sesión sintéticas eliminadas al terminar. No se alteraron registros de trabajo del usuario.

## IA3 — consultas con evidencia

Implementación en `includes/ai-queries.php` y `includes/ai-assistant-response.php`, integrada en el envío de mensajes. El primer paso transforma la pregunta en una consulta estructurada de lectura; el servidor ejecuta SQL parametrizado y filtros cerrados; el segundo redacta usando los registros autorizados. No hay SQL generado por IA ni escrituras de dominio. Sigue pendiente IA4 para tarjetas y aplicación de cambios.

- Recursos: mejoras, tareas y notas. El detalle de una mejora añade comentarios y checklist. Las notas privadas y las tareas privadas ajenas no entran en las consultas.
- Alcance: «Mi trabajo» reproduce asignaciones y destinatarios de la vista actual, excluyendo tareas delegadas y de equipo; «Trabajo del equipo» conserva la visibilidad real, incluyendo delegadas/equipo. Apps acotan resultados a esa app; desde un registro se consulta el detalle o registros relacionados de su app. Un registro sin app no permite ampliar arbitrariamente la búsqueda.
- Filtros: texto (incluye comentarios autorizados), aplicación por nombre, persona, estado, fechas de vencimiento/creación/actividad/finalización, vencidas/hoy/próximos siete días y orden. En mejoras, la última actividad tiene en cuenta comentarios recientes; no se interpreta antigüedad como bloqueo confirmado.
- Límites: 20 filas por tipo y una adicional para saber si hay más; offset hasta 200. No se presentan esos recuentos como totales globales. La fila adicional también se registra como dependencia de acceso. Presupuesto de datos de 28.000 caracteres; notas hasta 12.000, comentarios recientes hasta 20 con 400 caracteres cada uno y checklist hasta 30 pasos con texto acotado. Se señala cobertura parcial, texto recortado o página posterior.
- Evidencia: las keys `request:ID`, `task:ID`, `note:ID` se validan contra los registros recuperados. Los títulos y URLs se construyen en servidor. El panel muestra referencias inline y una lista de enlaces, junto con el alcance de la consulta. Una key inventada nunca se convierte en enlace.
- Privacidad: dependencias registradas antes de enviar datos al proveedor y revalidadas al terminar. Los campos de contacto de solicitantes no se seleccionan. Cambios de permiso redactan también los metadatos de fuentes. Las dependencias de ámbito de IA2 se actualizan al nuevo fingerprint con el conjunto de apps accesibles, conservando el historial previo sin consultas a registros.
- Continuidad: referencias y consulta de respuestas anteriores se incorporan como datos del historial para preguntas como «la segunda» o «los siguientes resultados». Una referencia ambigua debe pedir aclaración. El proveedor recibe historial acotado y no afirma haber aplicado cambios.
- Tiempo: planificación hasta 20 segundos y respuesta hasta 50, dentro de un presupuesto de 75 segundos; sin transacción ni bloqueo de sesión mientras espera al proveedor.

### Datos y validación IA3

`migrations/021_ai_message_metadata.sql` añade `ai_messages.metadata_json`, donde se guardan solo referencias verificadas, cobertura y consulta normalizada. Los endpoints nuevos deben desplegarse después de esa migración. El cliente usa assistant.js/css v2 y la caché PWA prisma-v49 / runtime-v37.

43 scripts pasan en local. Nuevo `tests/ai-queries.php`: alcance personal/equipo, permisos por app, tareas delegadas, notas privadas/compartidas, filtro por persona/estado/fecha, búsqueda en comentarios, última actividad, aislamiento por app, IDs inaccesibles, texto malicioso tratado como parámetro, límites/paginación y referencias inventadas. Persistencia ampliada para fuentes de consultas, metadatos, actualización de fingerprints de IA2 y redacción. Consultas y migraciones probadas también en MariaDB temporal con prepares nativos.

Integración HTTP local sobre PHP reales y transporte simulado: conserva creación/reintento/propiedad/CSRF y revocación. Navegador: resumen de nota con referencias inline y enlaces que abren el registro, persistencia tras navegar, consulta parcial de trabajo, siguiente página, desktop/móvil y ancho 375 sin scroll lateral. Capturas `prisma-ia3-desktop.jpg` y `prisma-ia3-mobile.jpg` fuera del repositorio. Consola sin errores en la pasada de comprobación.

Además, se ejecutaron en memoria en LucusHost las dos llamadas de IA3 contra el proveedor real usando una nota sintética: el plan identificó `detail/note/12` y la respuesta citó `note:12` con URL reconstruida en servidor. No se enviaron registros reales de trabajo en esa prueba.

### Despliegue IA3 en curso

Copia privada previa: `/home/umilpdfe/.prisma-deploy-backups/ia3-20261102-173829-722d1f`, con base `bdae3f2`, dump de 217.322 bytes y código de 956.426 bytes. Orden: aplicar 021, publicar y actualizar PHP/JS/CSS, verificar el recorrido HTTPS y registrar el resultado. La migración es aditiva; el código IA2 sigue funcionando con la columna nueva.


### Resultado de producción IA3 — 2 Nov 2026

Commit funcional `f351a5d`, publicado en GitHub y desplegado mediante fast-forward desde `bdae3f2`. Migración 021 aplicada en MariaDB 11.4.13 y verificada como `metadata_json LONGTEXT NULL`, SHA-256 del SQL `8cde5738ef196416c9c4f01ad3910cf54a2a5e31c90dcbe589962c4199103c84`. Siete PHP nuevos/modificados pasan la comprobación de sintaxis en el servidor; untracked previos conservados.

Recorrido HTTPS real: página y assets, 401/403 esperados, nota privada sintética, conversación contextual, plan y respuesta del proveedor real, referencia validada que abre esa nota, persistencia y reintento sin duplicados. Además, consultas SQL de mejoras+tareas ejecutadas en la BD real con un término aleatorio sin coincidencias, sin enviar registros reales de trabajo al modelo. Conversación, nota sintética y sesión temporal eliminadas al finalizar; ningún registro de trabajo existente se ha modificado.

IA1–IA3 quedan operativos en `https://prisma.wthefox.com`. Las copias privadas anteriores quedan disponibles para recuperación. El siguiente bloque funcional es IA4 (tarjetas y aplicación de cambios), seguido de IA5 (integración de creación/resumen existentes).


## Acceso al asistente desde la cabecera — 2 Nov 2026

El menú lateral contiene destinos de navegación. El asistente se abre como herramienta del espacio de trabajo desde «Preguntar a Prisma», junto a la campana de notificaciones en la cabecera. El icono de panel y el estado expandido anticipan el comportamiento; Ctrl/Cmd+J permite abrir/cerrar sin buscar el botón. Los accesos contextuales de mejoras y tareas se conservan.

`includes/workspace-tools.php` comparte ambos botones en Inicio, Tareas, Notas, creación con IA, Changelog, Admin, aplicaciones y Releases. Las acciones propias de la pantalla ocupan una fila separada. En móvil el acceso se acorta a «Asistente», permanece visible con el menú cerrado y ofrece objetivos táctiles de 44 px. El panel conserva su comportamiento de diálogo a pantalla completa.

Solo puede estar abierto uno de los paneles (asistente/notificaciones). Los botones exponen `aria-controls` y `aria-expanded`; el foco vuelve al disparador al cerrar y entra en el compositor después de cargar el historial. La etiqueta accesible sigue el texto visible en móvil. Se conserva el borrador al navegar y se respeta movimiento reducido. La campana actualiza también el atributo `hidden` del contador para ser compatible con las reglas de Notas.

Validación: 43 scripts existentes, sintaxis PHP/JS y diff. Revisión de navegador a 1440×1000 y 375×812, sin scroll horizontal; apertura/cierre, atajo, continuidad del borrador, alternancia con notificaciones, contador de no leídas y vuelta al modal de tarea con Escape. Consola sin errores en la pasada final. Capturas con datos ficticios fuera del repositorio: `prisma-header-tools-desktop.jpg`, `prisma-header-tools-mobile.jpg` y `prisma-header-tools-panel.jpg`.

Sin cambios de BD ni migraciones. Assets assistant v3, sidebar.js v2.2 y caché PWA prisma-v50 / runtime-v38. Se despliega mediante fast-forward y comprobación HTTPS de las ocho cabeceras autenticadas y sus assets.


Desplegado como `a97d01b` en `main`, mediante fast-forward desde `74c9411`. Copia privada del código anterior: `/home/umilpdfe/.prisma-deploy-backups/header-tools-20261102-222537-d0c189` (972.979 bytes). Once PHP pasan sintaxis en LucusHost. Smoke HTTPS autenticado correcto en las ocho páginas: botones únicos en cabecera, accesos antiguos ausentes del menú y versiones de assets/caché correctas. Sesión temporal eliminada; sin cambios de BD. Los untracked previos del servidor se conservan.

## Revisión UX del panel del asistente — 2 Nov 2026

El usuario aclara que su revisión se refiere al panel derecho del asistente. Se aplica `design-taste-frontend` a la conversación, contexto, historial y compositor, conservando PHP/JS/CSS y el sistema visual de Prisma.

- Panel de 460 px, lectura a 14 px y más espacio para mensajes. Accesos visibles a Conversación, Historial y Nueva.
- El contexto pasa junto al compositor: una fila muestra el registro/ámbito y si sigue la pantalla o está fijado. Se despliegan selector y fijación cuando se necesitan. Escape cierra primero el control abierto, la gestión o el historial, y después el panel.
- Fuentes agrupadas en un desplegable nativo; las citas inline siguen disponibles. La cobertura completa queda dentro del desplegable; los avisos de consulta parcial o contenido recortado permanecen visibles.
- Compositor que crece con el texto, botón Enviar con etiqueta, atajo y borrador. La pregunta aparece mientras se espera al proveedor. Las sugerencias conservan el texto que ya había escrito el usuario. El enlace de creación desde apuntes cierra el panel para mostrar su pantalla.
- Historial con fecha y nombres reconocibles: si el título almacenado es «Nueva conversación», el servidor deriva una etiqueta de hasta 80 caracteres Unicode a partir del primer mensaje del usuario, después de revalidar el acceso. Es una representación de lectura; no reescribe registros ni utiliza IA adicional. Los nombres personalizados y la redacción por pérdida de acceso tienen prioridad.
- Retorno al último mensaje al leer hacia atrás, continuidad del borrador y carga de mensajes anteriores sin desplazar el punto de lectura. Cargas de historial protegidas frente a cambios de vista; error/reintento, vacío, renombrado y cancelación explícitos.

Verificación: 43 scripts pasan; `tests/ai-assistant.php` amplía cobertura de etiquetas derivadas, nombres personalizados, normalización, longitud Unicode y ausencia de reescritura. Sintaxis PHP/JS y diff correctos. Navegador con proveedor simulado: consulta/citas/fuentes, historial/renombrado/borradores, fijación y selector de contexto, conversación larga y último mensaje, fallo y reintento sin duplicar, historial vacío y error recuperable inducidos, cambio de usuario con conversaciones sin acceso, móvil 375×812 sin scroll horizontal, Tab y Escape. Consola sin errores nuevos en la pasada final. Capturas con datos ficticios fuera del repositorio: `prisma-assistant-ux-desktop.jpg` y `prisma-assistant-ux-mobile.jpg`.

Assets assistant v4, caché PWA v52/runtime40. Sin migración. Publicación y despliegue en el mismo destino autorizado, con copia privada del código y smoke HTTPS de ocho pantallas, assets e historial.


Desplegado el 2 Nov 2026 como `f3a608d`, mediante fast-forward desde `251d590`. Copia privada previa del código: `/home/umilpdfe/.prisma-deploy-backups/assistant-panel-ux-20261102-231119-61ef79` (979.827 bytes). PHP válido en LucusHost. Smoke HTTPS correcto en las ocho páginas: nuevos controles del panel, assets assistant v4, PWA v52/runtime40 e historial autenticado con etiquetas legibles. Sesión temporal eliminada; sin cambios de BD ni llamadas adicionales al proveedor. Untracked previos conservados. Disponible para revisión en `https://prisma.wthefox.com`.

## Acciones directas y ámbito visible — 3 Nov 2026

Revisión a partir de cuatro problemas señalados por el usuario: capacidad poco clara, sugerencias que solo rellenaban texto, selector de ámbito difícil de descubrir y espacio inicial excesivo. Se aplica `design-taste-frontend` conservando el stack y el sistema visual existentes.

- Una cabecera compacta y una explicación concreta de consultas y trabajo con texto. En el estado inicial, la escritura sigue a las acciones, en vez de quedar separada al pie por un área vacía. Al conversar, el historial de mensajes vuelve a tener scroll y el compositor permanece disponible.
- «Consulta en un clic»: los botones contextuales envían la consulta y muestran la respuesta directamente. El texto que ya estuviera en la caja se conserva y no se envía como parte de la acción. Los botones se bloquean durante la operación. Las consultas rápidas cambian según el ámbito y el registro abierto.
- Opciones nativas visibles para Mi trabajo, Equipo y el registro/app de la pantalla. Texto explicativo del alcance; Equipo solo aparece con los roles permitidos. Se elimina la interacción de desplegar contexto y después elegir/fijar. Las selecciones guardadas de registros anteriores siguen accesibles como «Nota elegida», etc. El servidor conserva todas sus validaciones.
- «Ordenar un texto» abre un campo de apuntes con una instrucción y un botón Organizar. No inserta una frase prefabricada. El texto se conserva como mensaje propio y el modo se persiste en `ai_messages.metadata_json.input_mode`. La API admite `question` (por defecto) y `notes`; un reintento conserva el modo del mensaje original.
- El modo notes evita el planificador y la consulta de registros: genera el resultado con el texto del último mensaje, sin incorporar el historial anterior, en una sola llamada al proveedor. Mantiene autenticación, CSRF, revalidación del actor, propiedad, dependencias y límites del ciclo de conversación. Títulos derivados «Apuntes: …» para distinguir los chats nuevos de texto.
- «Crear tareas y mejoras» lleva al editor existente con los apuntes preparados, tomándolos del borrador o del último mensaje de apuntes si la caja ya se ha vaciado. Traslado de un solo uso por sessionStorage, limitado a usuario/empresa y diez minutos, con limpieza al cambiar de identidad/salir. El editor sigue requiriendo analizar, revisar y confirmar; la navegación no ejecuta escrituras de dominio.
- En escritorio, Enter envía una pregunta y Mayús+Enter añade una línea. En apuntes, Enter añade líneas y Ctrl/Cmd+Enter organiza. En móvil, Intro añade líneas y se usa el botón de envío. Abrir el panel o ejecutar una consulta rápida no enfoca automáticamente la caja en móvil; elegir escribir sí lo hace.
- La recuperación de un intento compara contenido, modo y ámbito, y descarta el intento local cuando se confirma completado, para no reutilizar una respuesta anterior al hacer una consulta nueva.

Validación: 43 scripts existentes y sintaxis PHP/JS correctos. Ampliación de tests de persistencia/reintentos/títulos y consulta de texto sin registros, sin planificador y sin datos del chat anterior. Navegador con proveedor simulado: acción directa con un único turno y borrador intacto; radios y teclado; notas multilínea/organización/continuidad; traslado al editor antes y después de organizar, sin analizar ni crear automáticamente; roles sin Equipo; regreso del historial/Escape; escritorio 1440×900 y móvil 375×812/320×740 sin scroll horizontal. Consola sin errores nuevos en la pasada final. Capturas con datos ficticios: `prisma-assistant-v5-desktop.jpg`, `prisma-assistant-v5-mobile.jpg`, `prisma-assistant-v5-text.jpg`, fuera del repositorio.

Assets assistant v5, ai-inbox.js ai7 y caché PWA v53/runtime41. Sin migración: se usa la columna metadata_json ya desplegada con IA3. Publicación en el destino autorizado con copia privada previa y comprobación HTTPS; se verifica además el proveedor real con una nota y unos apuntes sintéticos que se eliminan después.
