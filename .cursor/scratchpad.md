# Scratchpad: Transformar Prisma en Plataforma Colaborativa

## Background and Motivation

El usuario quiere transformar **Prisma** de una plataforma de uso individual a una **plataforma colaborativa de equipo**. Actualmente Prisma gestiona:
- Empresas (companies) con multi-tenancy básico
- Usuarios con roles (superadmin, admin, user)
- Aplicaciones/proyectos por empresa
- Requests (mejoras/bugs) con votos, prioridad, dificultad, estado
- Adjuntos y permisos por app

**Objetivo**: Convertir esto en una herramienta donde equipos puedan colaborar activamente en la gestión de mejoras/tareas.

### Nuevas Funcionalidades Solicitadas (20 Enero 2026)

1. ✅ **Archivos adjuntos visibles** - Ahora se pueden ver y eliminar los adjuntos de cada mejora
2. ✅ **Changelog restringido** - Solo muestra apps a las que el usuario tiene acceso
3. **Zona de Tareas Rápidas** - Sistema de notas/tareas ultrarrápido tipo "Notas de iPhone"
4. **Archivos por Aplicación** - Repositorio de archivos importantes accesibles desde cada app

---

## Key Challenges and Analysis

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

### Fase A: Zona de Tareas Rápidas
- [ ] A.1: DB - Crear tablas `tasks` y `task_attachments`
- [ ] A.2: API - `/api/tasks.php` CRUD
- [ ] A.3: UI - Botón flotante + Quick Add inline
- [ ] A.4: UI - Sección "Mis Tareas" en sidebar + vista
- [ ] A.5: Adjuntos en tareas

### Fase B: Archivos por Aplicación
- [ ] B.1: DB - Crear tabla `app_files`
- [ ] B.2: API - `/api/app-files.php` CRUD
- [ ] B.3: UI - Sección archivos en vista de app

### Tareas completadas hoy (20 Enero 2026)
- [x] Archivos adjuntos visibles en mejoras (api/attachments.php + UI)
- [x] Changelog restringido a apps del usuario

---

## Executor's Feedback or Assistance Requests

**Plan listo para revisión**. Puntos a confirmar antes de ejecutar:

1. **Tareas**: ¿Las tareas son personales (solo del usuario) o compartidas (visibles para el equipo)?
2. **Tareas**: ¿Quieres que estén asociadas a una app específica o sean generales del usuario?
3. **Archivos de app**: ¿Solo admins pueden subir o cualquier usuario con acceso a la app?

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
