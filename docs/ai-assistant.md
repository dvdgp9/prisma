# Asistente de Prisma — contratos IA1

Estado: IA1 e IA2 implementados y verificados; IA3–IA5 completarán las consultas y acciones sobre registros.
Plan y seguimiento: `.cursor/scratchpad.md`, bloque «Planner — Asistente unificado».

## Componentes disponibles

- `includes/ai.php`: configuración cifrada existente, cliente OpenRouter con transporte sustituible en tests, límites de contexto/salida, errores públicos sin cuerpos del proveedor, uso de tokens y validación local de respuestas estructuradas. No ejecuta herramientas ni escribe en BD.
- `api/ai-inbox.php`, `api/ai-summary.php`, `api/ai-settings.php?action=test`: usan ese cliente con sus contratos de respuesta existentes. Cierran el bloqueo de sesión después de resolver usuario/datos y antes de contactar al proveedor.
- `includes/work-records.php`: primitivas de creación de mejora, tarea y checklist utilizadas por las APIs existentes. Reciben campos validados; los controladores conservan sus comprobaciones y avisos. No hacen commit ni envían respuestas HTTP, para poder participar en una tarjeta atómica en IA4. No constituyen por sí solas una API autorizada del asistente.
- `migrations/020_ai_assistant.sql`: persistencia aditiva, todavía sin consumidores en producción. No es necesaria para el refactor de los tres endpoints actuales.

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

### Comprobación de LucusHost — 2 Nov 2026

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
