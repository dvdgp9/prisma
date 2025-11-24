# Guía de Instalación de Prisma

## Pasos Detallados para Instalación en cPanel

### PASO 1: Preparar la Base de Datos

1. **Accede a cPanel**
   - Inicia sesión en tu cPanel

2. **Crear Base de Datos**
   - Busca y haz clic en **MySQL® Databases**
   - En "Create New Database", introduce: `prisma_db`
   - Haz clic en **Create Database**

3. **Crear Usuario**
   - Baja a la sección "MySQL Users"
   - En "Username", introduce: `prisma_user`
   - Genera una contraseña fuerte o introduce una
   - **⚠️ ANOTA ESTA CONTRASEÑA** - la necesitarás más tarde
   - Haz clic en **Create User**

4. **Asignar Usuario a la Base de Datos**
   - Baja a "Add User to Database"
   - Selecciona el usuario `prisma_user`
   - Selecciona la base de datos `prisma_db`
   - Haz clic en **Add**
   - En la siguiente pantalla, marca **ALL PRIVILEGES**
   - Haz clic en **Make Changes**

### PASO 2: Importar el Schema SQL

1. **Abrir phpMyAdmin**
   - En cPanel, busca y haz clic en **phpMyAdmin**
   - Se abrirá en una nueva pestaña

2. **Seleccionar Base de Datos**
   - En el panel izquierdo, haz clic en `prisma_db`

3. **Importar Schema**
   - Haz clic en la pestaña **Import** (Importar)
   - Haz clic en **Choose File** (Elegir archivo)
   - Selecciona el archivo `schema.sql` de tu carpeta de Prisma
   - Deja las opciones por defecto
   - Haz clic en **Go** (Continuar) al final de la página

4. **Verificar Importación**
   - Deberías ver un mensaje verde: "Import has been successfully finished"
   - En el panel izquierdo, verás las tablas: `apps`, `attachments`, `companies`, `requests`, `users`, `votes`

### PASO 3: Subir Archivos al Servidor

1. **Abrir File Manager**
   - En cPanel, busca y haz clic en **File Manager**

2. **Navegar al Directorio Correcto**
   - Si Prisma estará en `prisma.wthefox.com`, navega a `public_html/`
   - Si será un subdominio, primero crea el subdominio en cPanel y luego navega a su carpeta

3. **Crear Carpeta Prisma** (si es necesario)
   - Haz clic en **+ Folder**
   - Nombra la carpeta `prisma`
   - Entra en la carpeta

4. **Subir Archivos**
   - Haz clic en **Upload** en la barra superior
   - Arrastra TODOS los archivos y carpetas de Prisma:
     - Carpeta `api/`
     - Carpeta `assets/`
     - Carpeta `config/`
     - Carpeta `includes/`
     - Carpeta `uploads/` (vacía)
     - Archivos: `index.php`, `login.php`, `logout.php`, `manage-apps.php`, `schema.sql`, `README.md`
   - Espera a que todos los archivos se suban (barra de progreso)

5. **Verificar Estructura**
   - Asegúrate de que la estructura sea correcta:
   ```
   prisma/
   ├── api/
   │   ├── apps.php
   │   ├── requests.php
   │   ├── votes.php
   │   └── upload.php
   ├── assets/
   │   ├── css/
   │   │   └── styles.css
   │   └── js/
   │       ├── main.js
   │       └── manage-apps.js
   ├── config/
   │   ├── database.php
   │   └── session.php
   ├── includes/
   │   └── auth.php
   ├── uploads/
   ├── index.php
   ├── login.php
   ├── logout.php
   └── manage-apps.php
   ```

### PASO 4: Configurar la Conexión a Base de Datos

1. **Editar database.php**
   - En File Manager, navega a `config/database.php`
   - Haz clic derecho y selecciona **Edit**
   - Si aparece un diálogo, haz clic en **Edit** de nuevo

2. **Actualizar Credenciales**
   - Busca estas líneas (alrededor de la línea 8-11):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

3. **Cambiar con tus Datos**
   - Reemplaza con:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'cpanel_usuario_prisma_db');  // ⚠️ IMPORTANTE
   define('DB_USER', 'cpanel_usuario_prisma_user');
   define('DB_PASS', 'tu_contraseña_que_anotaste');
   ```
   
   **⚠️ NOTA CRÍTICA**: En cPanel, el nombre real de la base de datos y usuario incluye tu nombre de usuario de cPanel como prefijo. Por ejemplo:
   - Si tu usuario de cPanel es `usuario123`
   - Tu base de datos será: `usuario123_prisma_db`
   - Tu usuario será: `usuario123_prisma_user`

4. **Guardar Archivo**
   - Haz clic en **Save Changes** (esquina superior derecha)
   - Cierra el editor

### PASO 5: Configurar Permisos de Uploads

1. **Seleccionar Carpeta Uploads**
   - En File Manager, navega a la carpeta `uploads/`
   - Haz clic derecho en la carpeta `uploads`
   - Selecciona **Permissions**

2. **Establecer Permisos**
   - Marca las casillas para obtener **755**:
     - Owner: Read, Write, Execute
     - Group: Read, Execute
     - World: Read, Execute
   - Si 755 no funciona, prueba con **777** (todas las casillas marcadas)
   - Haz clic en **Change Permissions**

### PASO 6: Primer Acceso

1. **Abrir Prisma**
   - Ve a `https://prisma.wthefox.com` en tu navegador
   - O `https://tudominio.com/prisma/` si está en una subcarpeta

2. **Login Inicial**
   - **Usuario**: `admin`
   - **Contraseña**: `admin123`

3. **⚠️ CAMBIAR CONTRASEÑA (IMPORTANTE)**
   - Por ahora, la contraseña está en la base de datos
   - Para cambiarla, accede a phpMyAdmin:
     - Ve a la tabla `users`
     - Edita el registro del admin
     - Genera un nuevo hash de contraseña usando [este generador online](https://bcrypt-generator.com/)
     - Usa rounds: 10
     - Copia el hash y pégalo en el campo `password`

### PASO 7: Configurar Aplicaciones

1. **Ir a Gestión de Apps**
   - Una vez logueado, ve al menú lateral
   - Haz clic en **Gestionar Apps**

2. **Eliminar Apps de Ejemplo** (opcional)
   - Haz clic en "Editar" en cada app de ejemplo
   - Haz clic en **Eliminar**
   - Confirma la eliminación

3. **Crear tus Apps**
   - Haz clic en **+ Nueva App**
   - Introduce el nombre (ej: "Puri")
   - Añade una descripción
   - Haz clic en **Crear App**

### PASO 8: Verificar Funcionamiento

✅ **Checklist de Verificación:**

- [ ] Puedes acceder a la página de login
- [ ] Puedes iniciar sesión con admin/admin123
- [ ] El dashboard se carga correctamente
- [ ] Puedes crear una nueva app
- [ ] Puedes crear una nueva petición
- [ ] Puedes votar en una petición
- [ ] Puedes subir un archivo adjunto
- [ ] Los filtros funcionan (prioridad, estado)
- [ ] El ordenamiento funciona (fecha, prioridad, votos)

## 🚨 Solución de Problemas Comunes

### Error: "Database connection failed"

**Causa**: Credenciales incorrectas en `config/database.php`

**Solución**:
1. Verifica que el nombre de la BD incluya el prefijo de cPanel
2. Verifica el usuario y contraseña
3. Asegúrate de que el usuario tiene permisos sobre la BD

### Página completamente en blanco

**Causa**: Error de PHP no mostrado

**Solución**:
1. En File Manager, edita `.htaccess` (créalo si no existe)
2. Añade estas líneas:
   ```
   php_flag display_errors on
   php_value error_reporting E_ALL
   ```
3. Recarga la página para ver el error específico

### Error 500 - Internal Server Error

**Causa**: Problemas de permisos o sintaxis PHP

**Solución**:
1. Verifica los permisos de archivos (644 para .php)
2. Verifica los permisos de carpetas (755)
3. Revisa los logs de error en cPanel > Error Log

### Las imágenes/CSS no cargan

**Causa**: Rutas incorrectas

**Solución**:
1. Verifica que las rutas en index.php apunten correctamente
2. Si Prisma está en una subcarpeta, ajusta las rutas:
   - Cambia `/assets/css/styles.css` 
   - Por `/prisma/assets/css/styles.css`

### No se pueden subir archivos

**Causa**: Permisos de la carpeta uploads

**Solución**:
1. Verifica permisos de `uploads/` (755 o 777)
2. Verifica límites de PHP:
   - En cPanel > MultiPHP INI Editor
   - Aumenta `upload_max_filesize` y `post_max_size` a al menos 10M

## 📞 ¿Necesitas Ayuda?

Si sigues estos pasos y algo no funciona:
1. Anota el mensaje de error exacto
2. Verifica qué paso específico falló
3. Revisa los logs de error en cPanel
4. Contacta con soporte técnico con esta información

## 🎉 ¡Listo!

Una vez completados todos los pasos, Prisma estará funcionando completamente. 

**Próximos pasos recomendados:**
1. Crear tus aplicaciones reales
2. Invitar a tu equipo a crear cuentas
3. Comenzar a registrar peticiones y bugs
4. Usar el sistema de votos para priorizar trabajo
