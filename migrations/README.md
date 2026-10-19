# Migraciones de Base de Datos - Prisma

Este directorio contiene las migraciones SQL que deben ejecutarse en phpMyAdmin para actualizar la base de datos.

## 📝 Migración 001: User-App Permissions

**Archivo**: `001_user_app_permissions.sql`

**Propósito**: Agregar permisos granulares para que los usuarios puedan ver solo apps específicas.

**Cambios**:
- Nueva tabla `user_app_permissions` con campos:
  - `user_id`: Usuario
  - `app_id`: Aplicación
  - `can_view`: Puede ver la app
  - `can_create`: Puede crear peticiones
  - `can_edit`: Puede editar peticiones
  
**Ejecutar**:
1. Abre phpMyAdmin
2. Selecciona tu base de datos `umilpdfe_prisma`
3. Ve a la pestaña "SQL"
4. Copia y pega el contenido de `001_user_app_permissions.sql`
5. Haz clic en "Go" / "Continuar"

**Verificación**:
```sql
SHOW TABLES LIKE 'user_app_permissions';
SELECT * FROM user_app_permissions;
```

## 📝 Migración 015: Notas compartidas

**Archivo**: `015_notes.sql`

**Propósito**: Notas de equipo en Markdown que el autor comparte con todo el equipo o con personas concretas (mismo modelo que las tareas).

**Cambios** (solo añade, no toca datos existentes):
- Nueva tabla `notes`: empresa, autor, app opcional, título, contenido, `is_shared` (todo el equipo), `is_pinned`, `updated_by` (quién la editó por última vez).
- Nueva tabla `note_shares`: con qué personas se comparte cada nota.
- Nueva columna `notifications.note_id` (con índice) para el aviso "te han compartido una nota".

**Ejecutar**: igual que la 001 (phpMyAdmin → pestaña "SQL" → pegar el contenido de `015_notes.sql` → "Continuar"). **Una sola vez**: el `ALTER TABLE notifications` da error "Duplicate column name 'note_id'" si se repite. Si pasa, no afecta a nada, porque la columna ya está creada.

**Ojo**: si un superadmin borra un usuario, se borran también sus notas, incluidas las que había compartido (igual que sus notas de app). Las notas que otra persona editó por última vez se conservan.

**Verificación**:
```sql
SHOW TABLES LIKE 'note%';
SHOW COLUMNS FROM notifications LIKE 'note_id';
```

## 📝 Migración 016: Fecha de las mejoras

**Archivo**: `016_request_due_date.sql`

**Propósito**: Poder poner una fecha opcional a cada mejora, para que salga en la Vista global ("Mi trabajo") junto a las tareas.

**Cambios** (solo añade, no toca datos existentes):
- Nueva columna `requests.due_date` (`DATE`, sin hora, opcional). Las mejoras actuales quedan sin fecha.
- Índice `idx_due_date` para ordenar y filtrar por fecha.

**Ejecutar**: igual que la 001 (phpMyAdmin → pestaña "SQL" → pegar el contenido de `016_request_due_date.sql` → "Continuar"). **Una sola vez**: si se repite, da error "Duplicate column name 'due_date'". En ese caso no pasa nada: la columna ya existe.

**Verificación**:
```sql
SHOW COLUMNS FROM requests LIKE 'due_date';
SHOW INDEX FROM requests WHERE Key_name = 'idx_due_date';
```

## 🔒 Comportamiento de Permisos

### Superadmin
- Ve **todas las apps**
- No necesita permisos específicos
- Puede gestionar apps y usuarios

### Admin
- Ve **todas las apps de su empresa**
- No necesita permisos específicos
- Puede cambiar estado y prioridad de peticiones

### Usuario Normal
- Solo ve apps que tienen en `user_app_permissions`
- Puede crear peticiones en apps con `can_create = 1`
- Puede votar 1 vez por petición

## 🆕 Nueva Funcionalidad

### 1. Permisos Granulares por Usuario
Los usuarios regulares solo verán las apps para las que tienen permisos.

### 2. Editar Peticiones (Admin/Superadmin)
- Botón de edición (✏️) en cada tarjeta
- Modal con formulario para editar:
  - Título
  - Descripción
  - **Prioridad** (solo admins)
  - **Estado** (solo admins)
- Botón eliminar (solo superadmin)

### 3. API de Permisos
Nuevo endpoint: `/api/user-permissions.php`
- GET: Listar permisos de un usuario
- POST: Otorgar/actualizar permisos
- DELETE: Revocar permisos

## 📌 Próximos Pasos

Cuando subas al servidor:
1. Actualiza todos los archivos desde GitHub
2. Ejecuta la migración SQL
3. Prueba que los admins pueden editar el estado
4. Verifica que los usuarios solo ven sus apps autorizadas
