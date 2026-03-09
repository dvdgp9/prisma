# Scratchpad: Transformar Prisma en Plataforma Colaborativa

## Background and Motivation

El usuario quiere transformar **Prisma** de una plataforma de uso individual a una **plataforma colaborativa de equipo**. Actualmente Prisma gestiona:
- Empresas (companies) con multi-tenancy básico
- Usuarios con roles (superadmin, admin, user)
- Aplicaciones/proyectos por empresa
- Requests (mejoras/bugs) con votos, prioridad, dificultad, estado
- Adjuntos y permisos por app

**Objetivo**: Convertir esto en una herramienta donde equipos puedan colaborar activamente en la gestión de mejoras/tareas.

### Funcionalidades Completadas
1. ✅ **Archivos adjuntos visibles** - Ver y eliminar adjuntos de cada mejora
2. ✅ **Changelog restringido** - Solo muestra apps del usuario
3. ✅ **Zona de Tareas Rápidas** - Botón flotante + página Mis Tareas
4. ✅ **Archivos por Aplicación** - Sección colapsable en vista de app
5. ✅ **Recursos del proyecto (27 Enero 2026)** - Añadir enlaces y notas a las apps

### Nuevas Funcionalidades Solicitadas (21 Enero 2026)

1. **Modularizar Sidebar** - Unificar la barra lateral que está duplicada en 5 archivos
2. **Sistema Multi-Empresa** - Permitir que un usuario pertenezca a varias empresas

---

## Key Challenges and Analysis

### Análisis: Modularización del Sidebar (21 Enero 2026)

**Problema actual**: El sidebar está duplicado en 5 archivos con variaciones:

| Archivo | Logo | Perfil editable | Pendientes | Tareas | Apps | Admin | Logout |
|---------|------|-----------------|------------|--------|------|-------|--------|
| `index.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `changelog.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `tasks.php` | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| `manage-apps.php` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| `admin.php` | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |

**Solución propuesta**: Crear `includes/sidebar.php` como componente reutilizable.

```php
// includes/sidebar.php
// Recibe: $current_page (para marcar active)
// Usa: $user (ya disponible via auth.php)
// Renderiza: sidebar completo con todas las secciones
```

**Mejoras adicionales propuestas**:
1. **Búsqueda rápida** - Input en la parte superior para filtrar apps
2. **Agrupación por empresa** - Las apps se muestran agrupadas bajo su empresa
3. **Selector de empresa activa** - Dropdown para cambiar entre empresas (multi-empresa)
4. **Colapsar/expandir grupos** - Mejor organización visual
5. **Contador de items** - Badge con número de mejoras pendientes por app

---

### Análisis: Sistema Multi-Empresa (21 Enero 2026)

**Problema actual**: 
- Usuario tiene `company_id` (FK a companies) → solo 1 empresa
- Apps tienen `company_id` → pertenecen a 1 empresa
- El usuario solo ve apps de SU empresa

**Caso de uso del usuario**: "Trabajo para varios clientes, necesito ver las apps de cada uno"

**Solución propuesta**: Tabla intermedia `user_companies`

```sql
-- Relación muchos a muchos: usuarios <-> empresas
CREATE TABLE user_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    role ENUM('viewer', 'member', 'admin') DEFAULT 'member',
    is_default BOOLEAN DEFAULT FALSE,  -- Empresa por defecto al login
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_company (user_id, company_id),
    INDEX idx_user (user_id),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Cambios necesarios**:
1. Migrar datos existentes: `INSERT INTO user_companies SELECT id, user_id, company_id, 'admin', true FROM users WHERE company_id IS NOT NULL`
2. Modificar `get_user_apps()` para traer apps de TODAS las empresas del usuario
3. Modificar sidebar para agrupar apps por empresa
4. Añadir selector de "empresa activa" o mostrar todas agrupadas
5. Panel admin: gestión de asignación usuario-empresa

**UX del Sidebar con multi-empresa**:
```
🏢 Empresa A           [▼]
   📱 App 1
   📱 App 2
   
🏢 Empresa B           [▼]
   📱 App 3
   
🏢 Empresa C           [▼]
   📱 App 4
   📱 App 5
```

---

### Análisis: Zona de Tareas Rápidas

**Objetivo del usuario**: Crear tareas lo más rápido posible, con el menor número de clics. Similar a "Notas del iPhone" - abrir, apuntar, cerrar.

**Requisitos identificados**:
- Acceso instantáneo (1 clic desde cualquier parte)
- Creación ultrarrápida (solo título obligatorio)
- Sin modal pesado ni formularios complejos
- Poder adjuntar archivos opcionalmente
- Tareas separadas de las "mejoras/requests" existentes

**Opciones de diseño**:

| Opción | Pros | Contras |
|--------|------|---------|
| A) Botón flotante + sidebar deslizable | Siempre visible, no interrumpe | Puede ser intrusivo |
| B) Tecla rápida (Ctrl+N) + input inline | Súper rápido para power users | No visible para nuevos |
| C) Sección "Tareas" en sidebar + quick-add | Integrado, consistente | Un clic más |

**Recomendación**: Combinar **A + C**
- Botón flotante "+" en esquina inferior derecha
- Al hacer clic: input inline que aparece al instante
- Sección "Mis Tareas" en sidebar para ver/gestionar
- Enter para guardar, Escape para cancelar
- Opción de expandir para añadir descripción/adjuntos

### Análisis: Archivos por Aplicación

**Objetivo del usuario**: Tener archivos importantes del proyecto accesibles al entrar en una aplicación.

**Requisitos identificados**:
- Archivos asociados a la app, no a una mejora específica
- Accesibles desde la vista de la aplicación
- Poder subir/descargar/eliminar
- Organización simple

**Diseño propuesto**:
- Nueva sección "Archivos" cuando se está en vista de una app
- Tabla/grid de archivos con: nombre, tamaño, fecha, subido por
- Botón para subir nuevos archivos
- Usar la misma infraestructura de uploads existente

---

## High-level Task Breakdown

### Fase A: Zona de Tareas Rápidas

#### A.1 Base de datos
- Crear tabla `tasks` con campos: id, user_id, company_id, title, description, is_completed, created_at, updated_at
- Crear tabla `task_attachments` para archivos

**Criterio de éxito**: Tablas creadas y migración lista

#### A.2 API de Tareas
- Endpoint `/api/tasks.php` con GET, POST, PUT, DELETE
- GET: Obtener tareas del usuario (filtros: completadas/pendientes)
- POST: Crear tarea (solo title obligatorio)
- PUT: Actualizar/completar tarea
- DELETE: Eliminar tarea

**Criterio de éxito**: CRUD funcional via API

#### A.3 UI - Botón flotante + Quick Add
- Botón "+" flotante en esquina inferior derecha
- Al hacer clic: input inline que aparece
- Enter guarda, Escape cancela
- Animación suave de aparición

**Criterio de éxito**: Poder crear tarea en <3 segundos

#### A.4 UI - Sección "Mis Tareas" en sidebar
- Nuevo item en sidebar: "Mis Tareas"
- Vista con lista de tareas pendientes/completadas
- Checkbox para marcar como completada
- Swipe/botón para eliminar

**Criterio de éxito**: Vista completa de gestión de tareas

#### A.5 Adjuntos en Tareas
- Botón para expandir y añadir descripción/adjuntos
- Reutilizar componente de upload existente

**Criterio de éxito**: Poder adjuntar archivos a tareas

### Fase B: Archivos por Aplicación

#### B.1 Base de datos
- Crear tabla `app_files` con: id, app_id, filename, original_filename, file_path, file_size, mime_type, uploaded_by, created_at

**Criterio de éxito**: Tabla creada

#### B.2 API de Archivos de App
- Endpoint `/api/app-files.php` con GET, POST, DELETE
- GET: Listar archivos de una app
- POST: Subir archivo a app
- DELETE: Eliminar archivo

**Criterio de éxito**: CRUD funcional

#### B.3 UI - Sección de archivos en vista de app
- Tab o sección "Archivos" cuando se está viendo una app específica
- Grid/lista de archivos
- Botón de subir archivo
- Preview/descarga al hacer clic

**Criterio de éxito**: Poder ver y gestionar archivos de cada app

---

## Project Status Board

### ✅ Fase A: Zona de Tareas Rápidas (COMPLETADA)
- [x] A.1: DB - Crear tablas `tasks` y `task_attachments`
- [x] A.2: API - `/api/tasks.php` CRUD
- [x] A.3: UI - Quick Add inline en página de tareas
- [x] A.4: UI - Sección "Mis Tareas" en sidebar + vista completa
- [x] A.5: Adjuntos en tareas

### ✅ Fase B: Archivos por Aplicación (COMPLETADA)
- [x] B.1: DB - Crear tabla `app_files`
- [x] B.2: API - `/api/app-files.php` CRUD
- [x] B.3: UI - Sección archivos en vista de app (lista colapsable)

### ✅ Fase G: Exportar Mejoras a CSV (COMPLETADA - 30 Enero 2026)
- [x] G.1: Botón "Exportar" junto a "Nueva Mejora" en index.php
- [x] G.2: Modal de selección de empresa
- [x] G.3: API `/api/export-requests.php` para generar CSV
- [x] G.4: Funciones JS para manejar exportación

### ✅ Fase F: Recursos del Proyecto - Enlaces y Notas (COMPLETADA - 27 Enero 2026)
- [x] F.1: DB - Migración `010_app_resources.sql` para tabla `app_resources`
- [x] F.2: API - `/api/app-resources.php` CRUD (links y notes)
- [x] F.3: UI - Sección "Recursos del proyecto" con tabs (Archivos, Enlaces, Notas)
- [x] F.4: CSS - Estilos para tabs, enlaces y notas
- [x] F.5: JS - Funciones para gestionar recursos (añadir, ver, eliminar)

### ✅ Fase C: Modularización Sidebar (COMPLETADA)
- [x] C.1: Crear `includes/sidebar.php` con sidebar unificado
- [x] C.2: Refactorizar las 5 páginas para usar el include
- [x] C.3: Añadir búsqueda rápida de apps (filtro en tiempo real)
- [x] C.4: Mejorar estilos del sidebar (grupos de empresa, buscador)

### ✅ Fase E: Release Planner (COMPLETADA - 22 Enero 2026)
- [x] E.1: DB - Migración `009_scheduled_releases.sql`
- [x] E.2: API - `/api/releases.php` CRUD (solo superadmin)
- [x] E.3: UI - `releases.php` con vista calendario + lista
- [x] E.4: Sidebar - Enlace solo para superadmin

### ✅ Fase D: Sistema Multi-Empresa (COMPLETADA)
- [x] D.1: DB - Crear tabla `user_companies` (schema.sql actualizado)
- [x] D.2: DB - SQL de migración preparado (ver abajo)
- [x] D.3: Modificar `auth.php` - `get_user_apps()`, `get_user_companies()`, `can_access_app()`
- [x] D.4: API - `/api/user-companies.php` para gestionar asignaciones
- [x] D.5: UI - Sidebar con apps agrupadas por empresa (colapsables)
- [x] D.6: UI - Panel admin con checkboxes para asignar múltiples empresas

### Tareas completadas (20 Enero 2026)
- [x] Archivos adjuntos visibles en mejoras
- [x] Changelog restringido a apps del usuario
- [x] Zona de tareas rápidas completa
- [x] Archivos por aplicación completo
- [x] Botón flotante de tareas a la derecha

---

## Executor's Feedback or Assistance Requests

### 🚀 Fase E: Release Planner - Panel de Anuncios Programados (22 Enero 2026)

**Objetivo**: Panel exclusivo para SUPERADMIN donde programar cuándo anunciar funcionalidades completadas.

**Requisitos**:
- Solo accesible por superadmin
- Vista calendario + vista lista
- Marcar como "presentado" manualmente
- Campo opcional de enlace

**Tabla `scheduled_releases`**:
```sql
CREATE TABLE scheduled_releases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    internal_notes TEXT,
    link VARCHAR(500),
    completed_at DATE NOT NULL,
    announce_at DATE NOT NULL,
    status ENUM('draft','scheduled','announced') DEFAULT 'scheduled',
    app_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE SET NULL,
    INDEX idx_announce_at (announce_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Archivos a crear**:
- `migrations/006_scheduled_releases.sql`
- `api/releases.php`
- `releases.php`

---

### 🔄 Plan pendiente de aprobación (21 Enero 2026)

**Fase C: Modularización Sidebar**
- Crear `includes/sidebar.php` reutilizable
- Refactorizar 5 archivos para usar el include
- Añadir búsqueda rápida de apps
- Unificar estilos

**Fase D: Sistema Multi-Empresa**
- Nueva tabla `user_companies` (relación N:M)
- Migración de datos existentes
- Apps agrupadas por empresa en sidebar (colapsables)
- Panel admin para asignar empresas a usuarios

**Preguntas para el usuario**:
1. ¿Ejecuto primero la Fase C (sidebar) o prefieres empezar por la D (multi-empresa)?
2. Para multi-empresa: ¿el superadmin ve TODAS las empresas, o solo las asignadas?
3. ¿El rol del usuario es global o puede variar por empresa? (ej: admin en Empresa A, user en Empresa B)

---

## Lessons

- Los archivos adjuntos se guardan en `/uploads/` y en tabla `attachments`
- `get_user_apps()` retorna las apps a las que el usuario tiene acceso

---

## Notas Técnicas

### Estructura de tablas propuestas (Tareas)

```sql
-- Tareas rápidas
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_completed (is_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adjuntos de tareas
CREATE TABLE task_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Archivos por aplicación
CREATE TABLE app_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_app (app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Nueva Funcionalidad: Rol Programador + Comentarios (9 Marzo 2026)

### Requisitos
1. **Rol "Programador"**: Permisos CRU de mejoras (sin Delete)
2. **Asignación de tareas**: Campo para saber quién tiene asignada cada mejora
3. **Comentarios con menciones**: Sistema de comentarios con @menciones en las mejoras
4. **Mejora de interfaz**: Rediseño de cards para mostrar asignación clara

### Jerarquía de Roles (actualizada)
- `superadmin`: Todo (CRUD completo + admin panel)
- `admin`: CRUD de mejoras + gestión de usuarios de su empresa
- `programador`: CRU de mejoras (sin delete) + ver apps asignadas
- `user`: Solo lectura + crear mejoras + votar

### SQL a ejecutar

```sql
-- 1. Añadir campo assigned_to a requests
ALTER TABLE requests 
ADD COLUMN assigned_to INT NULL AFTER created_by,
ADD CONSTRAINT fk_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

-- 2. Crear tabla de comentarios
CREATE TABLE request_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_request (request_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crear tabla de menciones (para notificaciones futuras)
CREATE TABLE comment_mentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    mentioned_user_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES request_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comment (comment_id),
    INDEX idx_mentioned_user (mentioned_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
