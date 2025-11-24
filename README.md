# Prisma - Dashboard de Gestión de Desarrollo

Prisma es un dashboard centralizado para gestionar el desarrollo y mantenimiento de tu ecosistema de aplicaciones. Permite volcar peticiones de múltiples canales, clasificarlas y visualizarlas ordenadas por importancia crítica.

## 🌟 Características

- **Vista Global**: Visualiza todas las peticiones de todas las apps en un solo lugar
- **Vistas por App**: Navega rápido entre aplicaciones con menú lateral
- **Priorización**: Ordena por fecha, prioridad o votos
- **Sistema de Votos**: Los usuarios pueden votar las peticiones más importantes
- **Adjuntos**: Sube capturas de pantalla y documentos a las peticiones
- **Multi-rol**: Superadmin, Admin y Usuario con permisos diferenciados
- **Diseño Moderno**: Interfaz limpia con colores vibrantes y animaciones suaves

## 🎨 Paleta de Colores

- **Primario**: Teal/Turquoise (#00C9B7)
- **Secundario**: Coral/Orange (#FF6B6B)
- **Acento**: Sunny Yellow (#FFD93D)
- **Éxito**: Fresh Green (#6BCF7F)
- **Crítico**: Bright Red (#FF4757)

## 📋 Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache con mod_rewrite (o similar)
- Hosting con cPanel (compartido compatible)

## 🚀 Instalación

### 1. Crear Base de Datos

1. Accede a tu cPanel
2. Ve a **MySQL Databases**
3. Crea una nueva base de datos (ej: `prisma_db`)
4. Crea un usuario con contraseña
5. Asigna el usuario a la base de datos con **ALL PRIVILEGES**

### 2. Importar Schema SQL

1. Ve a **phpMyAdmin** en cPanel
2. Selecciona tu base de datos recién creada
3. Ve a la pestaña **Import**
4. Selecciona el archivo `schema.sql`
5. Haz clic en **Go**

### 3. Subir Archivos

1. Accede al **File Manager** de cPanel
2. Navega al directorio donde quieres instalar Prisma (ej: `public_html/prisma`)
3. Sube todos los archivos del proyecto manteniendo la estructura:
   ```
   prisma/
   ├── api/
   ├── assets/
   ├── config/
   ├── includes/
   ├── uploads/
   └── *.php files
   ```

### 4. Configurar Base de Datos

1. Edita el archivo `config/database.php`
2. Actualiza las siguientes líneas con tus credenciales:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'tu_nombre_base_datos');
   define('DB_USER', 'tu_usuario_mysql');
   define('DB_PASS', 'tu_contraseña_mysql');
   ```

### 5. Configurar Permisos

1. La carpeta `uploads/` debe tener permisos de escritura
2. En File Manager, haz clic derecho en `uploads/`
3. Selecciona **Permissions**
4. Establece **755** o **777** (dependiendo de tu servidor)

### 6. Acceder a Prisma

1. Visita `https://prisma.wthefox.com` (o tu URL configurada)
2. Usa las credenciales por defecto:
   - **Usuario**: `admin`
   - **Contraseña**: `admin123`
3. **⚠️ IMPORTANTE**: Cambia esta contraseña inmediatamente después del primer login

## 📖 Uso

### Como Usuario

1. **Ver Peticiones**: Navega por la vista global o selecciona una app específica
2. **Crear Petición**: Click en "Nueva Petición", completa el formulario
3. **Votar**: Haz click en ▲ para votar una petición que consideres importante
4. **Adjuntar Archivos**: Arrastra archivos o haz click para seleccionar

### Como Admin

- Todas las funciones de usuario
- Puede votar múltiples veces (up y down)
- Puede cambiar prioridad y estado de peticiones
- Ve todas las peticiones de su empresa

### Como Superadmin

- Todas las funciones de admin
- Puede crear, editar y eliminar aplicaciones
- Acceso completo al sistema

## 🗂️ Estructura del Proyecto

```
prisma/
├── api/                    # Endpoints de API REST
│   ├── apps.php           # CRUD de aplicaciones
│   ├── requests.php       # CRUD de peticiones
│   ├── votes.php          # Sistema de votación
│   └── upload.php         # Subida de archivos
├── assets/
│   ├── css/
│   │   └── styles.css     # Estilos principales
│   └── js/
│       ├── main.js        # JavaScript principal
│       └── manage-apps.js # Gestión de apps
├── config/
│   ├── database.php       # Configuración de BD
│   └── session.php        # Configuración de sesiones
├── includes/
│   └── auth.php           # Sistema de autenticación
├── uploads/               # Archivos subidos
├── index.php              # Dashboard principal
├── login.php              # Página de login
├── logout.php             # Cerrar sesión
├── manage-apps.php        # Gestión de apps (superadmin)
└── schema.sql             # Schema de base de datos
```

## 🔒 Seguridad

- ✅ Passwords hasheados con `password_hash()`
- ✅ Consultas preparadas (PDO) para prevenir SQL injection
- ✅ Validación de tipos de archivo en uploads
- ✅ Sesiones con configuración segura
- ✅ Escape de HTML para prevenir XSS
- ✅ Control de acceso basado en roles

## 🛠️ Solución de Problemas

### Error de conexión a base de datos
- Verifica que las credenciales en `config/database.php` sean correctas
- Asegúrate de que el usuario tenga permisos sobre la base de datos

### No se pueden subir archivos
- Verifica que la carpeta `uploads/` tenga permisos de escritura (755 o 777)
- Revisa el límite de tamaño de archivo en PHP (php.ini)

### Página en blanco
- Activa `display_errors` en PHP para ver errores
- Revisa los logs de error de Apache/PHP en cPanel

### Las peticiones no se cargan
- Abre la consola del navegador (F12) para ver errores JavaScript
- Verifica que las rutas de API sean correctas

## 📝 Datos de Ejemplo

El schema incluye datos de ejemplo:
- **Empresa**: Default Company
- **Usuario**: admin / admin123 (superadmin)
- **Apps**: Puri, App 2, Internal Tools
- **Peticiones**: 4 ejemplos con diferentes prioridades

Puedes eliminar estos datos manualmente desde phpMyAdmin si prefieres empezar desde cero.

## 🎯 Próximos Pasos (Opcional)

- Añadir notificaciones por email
- Implementar comentarios en peticiones
- Añadir dashboard de estadísticas
- Integración con Slack/Discord
- Export de peticiones a CSV/Excel
- Sistema de etiquetas/categorías

## 📄 Licencia

Uso interno - Todos los derechos reservados.

## 🤝 Soporte

Para dudas o problemas, contacta con el equipo de desarrollo.
