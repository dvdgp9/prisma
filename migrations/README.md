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
