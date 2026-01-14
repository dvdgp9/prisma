# Scratchpad: Transformar Prisma en Plataforma Colaborativa

## Background and Motivation

El usuario quiere transformar **Prisma** de una plataforma de uso individual a una **plataforma colaborativa de equipo**. Actualmente Prisma gestiona:
- Empresas (companies) con multi-tenancy básico
- Usuarios con roles (superadmin, admin, user)
- Aplicaciones/proyectos por empresa
- Requests (mejoras/bugs) con votos, prioridad, dificultad, estado
- Adjuntos y permisos por app

**Objetivo**: Convertir esto en una herramienta donde equipos puedan colaborar activamente en la gestión de mejoras/tareas.

---

## Key Challenges and Analysis

### Estado Actual
| Componente | Estado | Notas |
|------------|--------|-------|
| Multi-tenancy | ✅ Existe | Empresas y usuarios por empresa |
| Roles | ✅ Existe | superadmin, admin, user |
| Permisos por App | ✅ Existe | user_app_permissions |
| Requests/Mejoras | ✅ Existe | CRUD completo con votos |
| **Colaboración** | ❌ Falta | No hay forma de discutir/asignar |

### Funcionalidades Necesarias para Trabajo en Equipo

1. **Sistema de Comentarios** (Alta prioridad)
   - Comentarios en cada request para discusión del equipo
   - Historial de conversación visible

2. **Asignación de Tareas** (Alta prioridad)
   - Campo `assigned_to` en requests
   - Vista "Mis tareas asignadas"
   - Filtro por asignado

3. **Menciones @usuario** (Media prioridad)
   - En comentarios: notificar a usuarios mencionados
   - Autocompletado de usuarios

4. **Sistema de Notificaciones** (Media prioridad)
   - Notificaciones in-app (badge/dropdown)
   - Tipos: asignación, comentario, mención, cambio de estado
   - Marcar como leídas

5. **Historial de Actividad** (Media prioridad)
   - Timeline de cambios en cada request
   - Quién cambió qué y cuándo

6. **Etiquetas/Tags** (Baja prioridad)
   - Tags personalizables por empresa
   - Filtrar por tags

7. **Mejoras de UI** (Transversal)
   - Panel lateral de detalles de request
   - Indicadores visuales de asignación
   - Avatar del asignado junto al request

---

## High-level Task Breakdown

### Fase 1: Sistema de Comentarios (Fundamento de colaboración)
1. **DB**: Crear tabla `comments` (request_id, user_id, content, created_at)
2. **API**: Endpoint `/api/comments.php` (GET, POST, DELETE)
3. **UI**: Sección de comentarios en modal/panel de request
4. **UI**: Formulario para añadir comentario
5. **Test**: Verificar CRUD de comentarios

### Fase 2: Asignación de Tareas
1. **DB**: Añadir campo `assigned_to` en `requests`
2. **API**: Actualizar `/api/requests.php` para soportar asignación
3. **UI**: Selector de usuario para asignar en formulario de request
4. **UI**: Vista "Mis tareas" en sidebar
5. **UI**: Filtro por asignado en vista principal
6. **UI**: Avatar/nombre del asignado en tarjeta de request

### Fase 3: Sistema de Notificaciones
1. **DB**: Crear tabla `notifications` (user_id, type, reference_id, read, created_at)
2. **API**: Endpoint `/api/notifications.php`
3. **UI**: Icono campana con badge en header
4. **UI**: Dropdown con lista de notificaciones
5. **Backend**: Generar notificaciones en eventos (asignación, comentario, estado)

### Fase 4: Menciones @usuario
1. **Backend**: Parser de menciones en comentarios
2. **UI**: Autocompletado @usuario en textarea de comentarios
3. **Backend**: Crear notificación al mencionar
4. **UI**: Resaltar menciones en texto

### Fase 5: Historial de Actividad
1. **DB**: Crear tabla `activity_log` (request_id, user_id, action, old_value, new_value, created_at)
2. **API**: Endpoint para obtener actividad de un request
3. **UI**: Timeline de actividad en panel de request
4. **Backend**: Registrar cambios automáticamente

### Fase 6: Etiquetas (Opcional)
1. **DB**: Tablas `tags` y `request_tags`
2. **API**: CRUD de tags y asignación
3. **UI**: Selector de tags, filtro, badges de colores

---

## Project Status Board

### Fase 1: Comentarios
- [ ] DB: Crear tabla `comments` <!-- id: 1.1 -->
- [ ] API: `/api/comments.php` CRUD <!-- id: 1.2 -->
- [ ] UI: Sección de comentarios en request <!-- id: 1.3 -->
- [ ] UI: Formulario añadir comentario <!-- id: 1.4 -->
- [ ] Test: Verificar funcionamiento <!-- id: 1.5 -->

### Fase 2: Asignación
- [ ] DB: Campo `assigned_to` en requests <!-- id: 2.1 -->
- [ ] API: Soporte asignación en requests <!-- id: 2.2 -->
- [ ] UI: Selector de asignado <!-- id: 2.3 -->
- [ ] UI: Vista "Mis tareas" <!-- id: 2.4 -->
- [ ] UI: Filtro por asignado <!-- id: 2.5 -->
- [ ] UI: Avatar asignado en tarjetas <!-- id: 2.6 -->

### Fase 3: Notificaciones
- [ ] DB: Tabla `notifications` <!-- id: 3.1 -->
- [ ] API: `/api/notifications.php` <!-- id: 3.2 -->
- [ ] UI: Icono campana con badge <!-- id: 3.3 -->
- [ ] UI: Dropdown notificaciones <!-- id: 3.4 -->
- [ ] Backend: Generar notificaciones automáticas <!-- id: 3.5 -->

### Fase 4: Menciones
- [ ] Backend: Parser de @menciones <!-- id: 4.1 -->
- [ ] UI: Autocompletado @usuario <!-- id: 4.2 -->
- [ ] Backend: Notificación al mencionar <!-- id: 4.3 -->

### Fase 5: Historial
- [ ] DB: Tabla `activity_log` <!-- id: 5.1 -->
- [ ] API: Obtener actividad <!-- id: 5.2 -->
- [ ] UI: Timeline en panel de request <!-- id: 5.3 -->
- [ ] Backend: Registro automático de cambios <!-- id: 5.4 -->

---

## Executor's Feedback or Assistance Requests
- Esperando aprobación del plan por el usuario/Planner antes de comenzar ejecución.

## Lessons
- (None yet)

---

## Notas Técnicas

### Estructura de tablas propuestas

```sql
-- Comentarios
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notificaciones
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('assignment', 'comment', 'mention', 'status_change') NOT NULL,
    reference_type ENUM('request', 'comment') NOT NULL,
    reference_id INT NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity Log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    field_name VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Campo asignación (migración)
ALTER TABLE requests ADD COLUMN assigned_to INT NULL;
ALTER TABLE requests ADD FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;
```
