# Scratchpad: Transformar Prisma en Plataforma Colaborativa

## Background and Motivation

Actualización Planner (9 Marzo 2026): el usuario solicita un **análisis completo de la aplicación** para entender con claridad qué producto es Prisma hoy, cuál es su propuesta de valor real, qué limitaciones presenta, y cuál debería ser un **plan de mejoras transversal** en diseño, experiencia de usuario, funcionalidades, arquitectura, seguridad, rendimiento y operación.

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

### Análisis Global del Producto (Planner - 9 Marzo 2026)

#### 1. ¿Qué es Prisma?

Prisma es una **plataforma interna de gestión de demanda de desarrollo**. Sirve como punto central para recoger, priorizar, organizar y ejecutar trabajo relacionado con múltiples aplicaciones y múltiples empresas/clientes.

No es solo un "tablón de ideas". Por la estructura actual del sistema, Prisma ya actúa como una mezcla de:
- **Portal de entrada de solicitudes**
- **Backlog de producto / mejoras / bugs**
- **Panel operativo para desarrollo**
- **Herramienta ligera de coordinación de equipo**
- **Mini service desk interno**

#### 2. Casos de uso reales que hoy cubre

- Usuarios de negocio o cliente envían solicitudes externas mediante `solicitud.php`
- Usuarios internos visualizan y votan mejoras para priorización
- Admins y superadmins revisan solicitudes pendientes y las aprueban o rechazan
- Equipo técnico organiza el trabajo por app, prioridad, dificultad y estado
- Superadmin administra empresas, usuarios, permisos y aplicaciones
- Usuarios pueden gestionar tareas rápidas personales (`tasks.php`)
- Se mantiene contexto mediante adjuntos, comentarios, menciones y asignaciones
- Ya existe una base para release planning, changelog y recursos por proyecto

#### 3. Propuesta de valor actual

Fortalezas del producto hoy:
- **Centraliza trabajo disperso** entre apps y clientes
- **Reduce pérdida de contexto** gracias a adjuntos, comentarios y asignación
- **Permite priorización visible** mediante votos, estado, dificultad y prioridad
- **Tiene base multiempresa** y permisos por rol
- **Es usable como herramienta interna real** sin depender de suites externas complejas

#### 4. Diagnóstico de madurez actual

Prisma está en una fase de **producto funcional con crecimiento orgánico**:
- La funcionalidad principal existe y resuelve necesidades reales
- La UI ya tiene intención de diseño y consistencia visual
- El sistema ha evolucionado añadiendo módulos útiles sin una capa fuerte de producto/plataforma unificada
- Hay señales de deuda técnica normal de producto interno: JS grande, estilos extensos, lógica distribuida, flujos potentes pero no completamente sistematizados

#### 5. Problemas estructurales detectados

##### Producto
- Prisma mezcla varios conceptos: solicitud, mejora, tarea, incidencia, release, comentario, notificación
- Falta una definición clara del ciclo de vida end-to-end de una petición
- No está completamente separado lo que es "captura de demanda" de lo que es "ejecución técnica"

##### UX / Navegación
- La app tiene mucha potencia, pero todavía depende de que el usuario "sepa cómo funciona"
- La navegación lateral es útil, aunque el descubrimiento de funciones sigue siendo bajo
- Varias acciones importantes viven en iconos o flujos implícitos

##### Arquitectura frontend
- `assets/js/main.js` concentra demasiadas responsabilidades
- El HTML generado inline en JS complica mantenimiento y pruebas
- Parte de la lógica compartida está duplicada o repartida entre páginas

##### Arquitectura backend
- El backend PHP por endpoints es válido, pero le falta una capa más clara de servicios / dominio
- La evolución funcional parece haber sido incremental; eso puede generar reglas de negocio repetidas

##### Datos / reporting
- Hay mucha operativa, pero poca analítica agregada
- Faltan métricas nativas para decidir mejor: throughput, lead time, aging, carga por responsable, salud por app

##### Operación
- El producto ya tiene valor diario, pero aún no está completamente preparado como plataforma robusta de equipo en escalado

#### 6. Oportunidades claras de evolución

Prisma puede evolucionar hacia uno de estos dos posicionamientos:

##### Opción A: Backlog Colaborativo Ligero
Foco en captura, priorización, comentarios, asignación y seguimiento simple.

##### Opción B: Plataforma Operativa de Desarrollo
Foco en intake + priorización + ejecución + releases + reporting + comunicación con stakeholders.

La mejor estrategia parece una evolución gradual desde A hacia B, sin convertir Prisma en un clon pesado de Jira.

### Plan Maestro de Mejoras (Planner - 9 Marzo 2026)

#### Objetivo general

Transformar Prisma en una **plataforma colaborativa, clara y escalable** para gestionar la demanda de desarrollo de múltiples apps y clientes, con una experiencia excelente para tres perfiles:
- **Solicitante**
- **Gestor/administrador**
- **Equipo técnico**

#### Principios de diseño del roadmap

1. **Claridad antes que complejidad**
2. **Reducir fricción en flujos frecuentes**
3. **Mejorar visibilidad del trabajo**
4. **Mantener la ligereza del producto**
5. **Escalar sin romper el modelo actual**

#### Pilar 1: Diseño visual y consistencia

##### Diagnóstico
- Hay una base visual moderna, pero la interfaz ha crecido por capas
- Existen varios patrones de botones, badges, paneles y acciones rápidas
- Parte del UI depende de estilos inline desde JS

##### Mejoras propuestas
1. **Sistema de diseño ligero**
   - Definir tokens de color, espaciado, radios, sombras y estados
   - Crear patrones reutilizables para cards, tables, badges, chips, dropdowns y modales
   - Reducir al mínimo estilos inline en JS

2. **Jerarquía visual más fuerte**
   - Reforzar diferencias entre título, metadata, estado y acciones
   - Hacer más evidente qué es importante y qué es secundario

3. **Unificación de densidad visual**
   - Revisar paddings, tamaños de icono, altura de inputs y badges
   - Definir modo compacto para listados densos

##### Criterio de éxito
- La UI se percibe más coherente y profesional
- Menos excepciones visuales por pantalla
- Menor esfuerzo para mantener estilos

#### Pilar 2: UX e interfaz principal

##### Diagnóstico
- La vista principal ya es potente, pero todavía puede ser más legible y más accionable
- La información está presente, pero no siempre bien sintetizada

##### Mejoras propuestas
1. **Toolbar superior más útil**
   - Guardar filtros activos visibles
   - Búsqueda global real por título, descripción, solicitante y comentarios
   - Filtros rápidos por responsable, estado, prioridad, app, empresa y "mías"

2. **Vistas guardadas**
   - "Mis asignadas"
   - "Pendientes de triage"
   - "En progreso"
   - "Bloqueadas"
   - "Sin asignar"

3. **Mejoras de card/listado**
   - Alternar entre vista card y vista tabla compacta
   - Mostrar fechas clave: creación, última actividad, fecha objetivo
   - Destacar items envejecidos o sin movimiento

4. **Modal de detalle más potente**
   - Convertirlo en panel de detalle tipo side panel o modal ancho estructurado por tabs
   - Tabs: Resumen, Comentarios, Archivos, Historial, Actividad

##### Criterio de éxito
- Menos clics para encontrar trabajo relevante
- Mejor comprensión del estado de cada item
- Mejor experiencia para usuarios intensivos

#### Pilar 3: Funcionalidad de producto

##### Diagnóstico
- Prisma ya cubre intake, votación y gestión básica
- Le faltan piezas para cerrar el ciclo operativo

##### Mejoras propuestas
1. **Workflow más completo**
   - Estados más claros: `new`, `triage`, `ready`, `in_progress`, `blocked`, `done`, `discarded`
   - Motivos de descarte / aplazamiento
   - Historial de cambios de estado

2. **Tipologías de trabajo**
   - Diferenciar: mejora, bug, incidencia, tarea técnica, deuda técnica
   - Filtros y badges específicos por tipo

3. **Campos de negocio útiles**
   - Impacto
   - Esfuerzo estimado
   - Urgencia
   - Valor negocio
   - Fecha objetivo
   - Bloqueadores / dependencias

4. **Subtareas / checklist**
   - Muy útil para ejecución ligera sin salir de Prisma

5. **Relaciones entre items**
   - Duplicado de
   - Bloquea a
   - Relacionado con
   - Derivado de solicitud externa

6. **Centro de actividad**
   - Feed por request con cambios, comentarios, asignaciones, archivos y menciones

##### Criterio de éxito
- Prisma deja de ser solo un inbox + backlog y pasa a soportar ejecución real de trabajo

#### Pilar 4: Portal del solicitante / experiencia externa

##### Diagnóstico
- `solicitud.php` resuelve la captura externa, pero el solicitante tiene poca visibilidad posterior

##### Mejoras propuestas
1. **Portal de seguimiento para solicitantes**
   - Estado de su solicitud
   - Historial básico
   - Comentarios públicos del equipo

2. **Confirmaciones mejores**
   - Número de ticket o referencia
   - Tiempo estimado de revisión

3. **Notificaciones por email**
   - Solicitud recibida
   - Solicitud aprobada/rechazada
   - Comentario nuevo
   - Solicitud completada

4. **Formulario más inteligente**
   - Sugerencias de solicitudes similares antes de enviar
   - Plantillas por tipo de solicitud
   - Campos condicionales por app

##### Criterio de éxito
- Menos incertidumbre del solicitante
- Menos preguntas repetidas al equipo
- Mayor calidad de las solicitudes entrantes

#### Pilar 5: Colaboración de equipo

##### Diagnóstico
- Ya existe una base muy valiosa: comentarios, menciones, asignaciones múltiples, inbox

##### Mejoras propuestas
1. **Inbox avanzado**
   - Filtros por tipo
   - Agrupación por request
   - Marcar como no leído
   - Preferencias de notificación

2. **Presencia y responsabilidad**
   - Owner principal
   - Colaboradores
   - Última persona que tocó el item

3. **Actividad personal**
   - "Lo que te menciona"
   - "Lo asignado a ti"
   - "Lo que espera tu respuesta"

4. **Notas internas vs públicas**
   - Especialmente importante si el solicitante externo llega a tener portal

##### Criterio de éxito
- Mejor coordinación del equipo sin depender tanto de chat externo

#### Pilar 6: Gestión operativa y reporting

##### Diagnóstico
- Hay gestión, pero faltan instrumentos de dirección y capacidad

##### Mejoras propuestas
1. **Dashboard ejecutivo**
   - Volumen por app
   - Volumen por empresa
   - Pendientes vs completadas
   - Tiempo medio hasta resolución
   - Carga por responsable

2. **Dashboard operativo**
   - Aging de items
   - Items bloqueados
   - Sin asignar
   - Sin actividad > X días

3. **Reporting por release**
   - Qué entra en cada release
   - Qué quedó fuera
   - Qué se desplegó

4. **Exportación avanzada**
   - CSV/Excel con filtros activos
   - Resúmenes por app o cliente

##### Criterio de éxito
- Decisiones basadas en datos, no solo percepción

#### Pilar 7: Arquitectura y mantenibilidad

##### Diagnóstico
- `main.js` es un punto de riesgo por tamaño y responsabilidades
- CSS principal es extenso y mezcla capas del sistema
- La lógica de rendering está muy acoplada al DOM

##### Mejoras propuestas
1. **Modularización frontend**
   - Separar por dominios: requests, comments, notifications, assignments, uploads, filters, sidebar
   - Extraer renderers reutilizables

2. **Reducir HTML inline generado en JS**
   - Usar templates más organizados o funciones pequeñas por componente

3. **Capa de API cliente**
   - Unificar fetch, manejo de errores, parseo y toasts

4. **Backend por servicios**
   - Mover reglas de negocio repetidas a helpers/servicios PHP
   - Estandarizar respuestas API

5. **Migraciones versionadas**
   - Evitar depender de SQL manual disperso en conversaciones o scratchpad

##### Criterio de éxito
- Código más fácil de tocar sin regresiones
- Menos duplicación
- Mejor velocidad de evolución

#### Pilar 8: Seguridad y robustez

##### Diagnóstico
- La base es razonable: PDO, roles, escape HTML, passwords hasheados
- Pero al crecer como plataforma colaborativa necesita un nivel más alto de robustez

##### Mejoras propuestas
1. **CSRF protection** en formularios y acciones sensibles
2. **Auditoría de permisos** endpoint por endpoint
3. **Validación centralizada** de inputs
4. **Rate limiting** en login, comentarios y creación de solicitudes
5. **Historial/auditoría** de acciones críticas
6. **Política de archivos** más estricta: tamaño, tipo, antivirus si aplica

##### Criterio de éxito
- Menor superficie de riesgo operativo y de seguridad

#### Pilar 9: Rendimiento y escalabilidad

##### Diagnóstico
- La app funciona, pero el crecimiento de datos y usuarios puede tensar vistas y endpoints

##### Mejoras propuestas
1. **Paginación real** en requests, comentarios y notificaciones
2. **Carga incremental / lazy loading**
3. **Índices DB** para filtros más usados
4. **Reducir recargas completas** tras acciones pequeñas
5. **Cache ligera** de datos estables (apps, permisos, catálogos)

##### Criterio de éxito
- Mejor respuesta percibida en instalaciones con volumen real

#### Pilar 10: Calidad, testing y operación

##### Diagnóstico
- Falta formalización de pruebas y de ciclo de despliegue

##### Mejoras propuestas
1. **Checklist de QA** por flujo crítico
2. **Tests mínimos** para endpoints clave
3. **Logs de error más estructurados**
4. **Entorno staging** si no existe
5. **Backups y plan de rollback**
6. **Documentación viva** de roles, estados y workflows

##### Criterio de éxito
- Menos regresiones y más confianza al desplegar

### Roadmap priorizado recomendado

#### Fase 1 - Consolidación UX y claridad del producto
- Definir modelo de estados y tipos de item
- Reforzar filtros, búsqueda y vistas guardadas
- Mejorar panel/modal de detalle
- Unificar patrones visuales principales

#### Fase 2 - Colaboración y seguimiento
- Inbox avanzado
- Historial de actividad
- Owner principal + colaboradores
- Notificaciones email básicas

#### Fase 3 - Ejecución operativa real
- Subtareas / checklist
- Dependencias y bloqueos
- Fechas objetivo y aging
- Dashboards operativos

#### Fase 4 - Plataforma madura
- Portal del solicitante
- Reporting ejecutivo
- Arquitectura modular y migraciones versionadas
- Endurecimiento de seguridad y observabilidad

### Recomendación de enfoque

La mejor decisión no es añadir funciones indiscriminadamente, sino **ordenar el producto** alrededor de 3 flujos principales:

1. **Captura de demanda**
2. **Priorización y triage**
3. **Ejecución y seguimiento**

Si Prisma se optimiza alrededor de esos 3 flujos, puede convertirse en una herramienta muy sólida y diferencial: ligera, clara y muy adaptada al proceso real del equipo.

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
@@
## High-level Task Breakdown

### Fase H: Consolidación UX y Claridad del Producto (NUEVA - Marzo 2026)

#### Decisión de producto confirmada por el usuario
- **No** se implementarán campos ricos adicionales por ahora
- **No** se ampliará el workflow de estados
- Se mantienen los estados actuales: `pending`, `in_progress`, `completed`, `discarded`
- Se ejecutarán las antiguas **Fases 1, 2 y 3** del plan maestro, adaptadas a esta restricción

#### H.1 Fase 1 adaptada - UX principal y claridad
- Reforzar búsqueda y filtros sin alterar el modelo de datos principal
- Añadir vistas rápidas útiles con los campos ya existentes
- Mejorar la presentación del detalle de mejora sin introducir complejidad funcional extra
- Unificar patrones visuales principales de listados, toolbars y detalle

**Criterio de éxito**: Encontrar, filtrar y entender mejoras es más rápido sin cambiar el workflow actual

#### H.2 Fase 2 adaptada - Colaboración y seguimiento
- Mejorar inbox/notificaciones existentes
- Añadir mayor visibilidad de actividad por mejora
- Introducir noción de responsable principal sin rediseñar todo el dominio
- Mejorar vistas personales: asignado a mí, menciones, pendientes de revisar

**Criterio de éxito**: El equipo coordina mejor su trabajo dentro de Prisma con menos fricción

#### H.3 Fase 3 adaptada - Ejecución operativa ligera
- Añadir subtareas o checklist ligero dentro de la mejora
- Añadir bloqueos/dependencias de forma simple si el coste es razonable
- Mejorar visibilidad temporal con fechas existentes o indicadores de antigüedad
- Añadir primeras vistas operativas sobre carga y seguimiento

**Criterio de éxito**: Prisma soporta mejor la ejecución diaria sin convertirse en una herramienta pesada

#### Orden de ejecución propuesto
1. **H.1.1** Toolbar de filtros y vistas rápidas
2. **H.1.2** Mejora del panel/modal de detalle
3. **H.1.3** Unificación visual de listados y acciones
4. **H.2.1** Inbox avanzado y vistas personales
5. **H.2.2** Actividad por mejora
6. **H.2.3** Responsable principal
7. **H.3.1** Checklist/subtareas ligeras
8. **H.3.2** Indicadores operativos básicos

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

### 🔄 Fase H: Consolidación UX + Colaboración + Ejecución Ligera (EN PREPARACIÓN)
- [x] H.1.1: Toolbar de filtros y vistas rápidas usando campos actuales
- [x] H.1.2: Mejorar panel/modal de detalle de mejora
- [x] H.1.3: Unificar visualmente listados y acciones principales
- [x] H.2.1: Mejorar inbox con filtros/vistas personales
- [ ] H.2.2: Añadir actividad visible por mejora
- [ ] H.2.3: Introducir responsable principal
- [ ] H.3.1: Añadir checklist/subtareas ligeras
- [ ] H.3.2: Añadir indicadores operativos básicos

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

### 🔧 Ejecución sidebar visual (4 Junio 2026)

El usuario reporta que, tras el restyling visual, el menú lateral quedó mal: en la captura las apps ocupan demasiado alto, la sección "Herramientas" queda mezclada con el listado y el footer de usuario compite con la navegación.

#### Diagnóstico
- El sidebar tenía `sidebar-nav` como único contenedor con scroll para navegación primaria, apps y herramientas.
- Con muchas apps, el listado empujaba "Herramientas" hacia abajo y hacía que el bloque pareciera intercalado con aplicaciones.
- En páginas fuera de `index.php`, `assets/js/sidebar.js` renderizaba los grupos de empresa con una estructura distinta a `main.js`.

#### Implementado en esta pasada
- `assets/css/styles.css`: el sidebar queda dividido en navegación superior, listado de apps con scroll propio, herramientas fijas bajo apps y footer de usuario fijo abajo.
- `assets/js/sidebar.js`: render de grupos de empresa alineado con `main.js`, incluyendo grupo único y botón de colapsar.

#### Criterio de validación
- El usuario puede hacer scroll dentro de aplicaciones sin que "Herramientas" se mezcle con la lista.
- El footer de usuario permanece visible y no tapa elementos navegables.
- La estructura se mantiene consistente entre dashboard, tareas, changelog, releases, admin y gestionar apps.

### 🔧 Ejecución aprobada por el usuario (10 Marzo 2026)

El usuario confirma proceder en **modo executor** con las fases equivalentes a 1, 2 y 3 del plan maestro, con dos restricciones funcionales importantes:

- Se mantienen los **estados actuales**: `pending`, `in_progress`, `completed`, `discarded`
- No se implementarán **campos ricos nuevos** por ahora

#### Siguiente bloque a ejecutar
Propuesta del executor: empezar por **H.1.1 Toolbar de filtros y vistas rápidas**, porque ofrece el mayor impacto inmediato con bajo riesgo y sin requerir cambios profundos de dominio.

##### Alcance propuesto de H.1.1
- Añadir búsqueda más clara en la vista principal
- Añadir filtros rápidos por:
  - estado
  - prioridad
  - asignadas a mí
  - sin asignar
  - con comentarios
- Añadir vistas rápidas tipo:
  - Mis asignadas
  - En progreso
  - Pendientes
  - Completadas

##### Criterio de validación antes de pasar al siguiente bloque
- El usuario puede localizar trabajo relevante con menos clics
- Los filtros no rompen la vista global, por app ni por empresa
- No se modifica el modelo de estados existente

### ✅ Progreso executor realizado (10 Marzo 2026)

#### Implementado en esta pasada
- **Toolbar mejorada** con buscador visible en cabecera
- **Vistas rápidas**: Todas, Mis asignadas, En progreso, Pendientes, Completadas, Sin asignar, Con comentarios
- **Filtros operativos extra** sin tocar el modelo de datos:
  - asignadas a mí
  - sin asignar
  - con comentarios
  - limpiar filtros
- **Resumen operativo superior** con métricas visibles del conjunto filtrado
- **Resumen dentro del modal de edición** con estado, prioridad, dificultad, fecha de creación, antigüedad, comentarios y adjuntos
- **Inbox filtrable** por tipo y por no leídas
- **Actividad visible por mejora** en el modal con timeline ligero de creación, asignación y comentarios
- **Responsable principal ligero** derivado del primer asignado visible en cards y modal
- **Indicadores operativos básicos** en cards: antigüedad, responsable principal y señal de actividad

#### Pendiente para siguiente iteración
- H.3.1 Checklist/subtareas ligeras
- H.3.2 Indicadores operativos básicos más profundos

#### Solicitud de validación manual al usuario
- Revisar que la nueva barra superior de búsqueda/filtros resulte útil
- Verificar que las vistas rápidas devuelven resultados coherentes
- Probar el resumen del modal de edición
- Probar filtros del inbox
- Probar timeline y bloque de actividad en el modal
- Confirmar si la convención de "primer asignado = responsable principal" encaja con producto

### 🔧 Progreso executor adicional (10 Marzo 2026 - H.3.1 en curso)

#### Subbloque ejecutado
- **Checklist/subtareas ligeras dentro de cada mejora**
  - nuevo endpoint backend dedicado para checklist por request
  - integración del bloque en el modal de edición
  - alta de subtarea
  - marcar / desmarcar completada
  - renombrar
  - eliminar
  - progreso visible en modal
  - progreso agregado visible en cards (`x/y`)

#### Dependencia externa pendiente
- Ejecutar SQL de creación de la tabla `request_checklist_items` en phpMyAdmin

#### Validación manual requerida antes de pasar a la vista tabla
- Abrir una mejora y comprobar que aparece el bloque checklist
- Crear varias subtareas
- Marcar/desmarcar completadas
- Renombrar y eliminar una subtarea
- Confirmar que el progreso (`x/y`) se refleja en el modal y en las cards

#### Siguiente bloque propuesto tras validación
- **Vista alternable tarjetas / tabla comprimida** reutilizando filtros y búsqueda actuales

### ✅ Progreso executor adicional (10 Marzo 2026 - vista operativa)

#### Subbloque ejecutado
- **Alternancia entre vista tarjetas y vista tabla comprimida**
  - toggle `Tarjetas / Tabla`
  - persistencia local del modo de vista
  - reutilización del mismo dataset filtrado
  - tabla compacta con columnas operativas:
    - prioridad
    - estado
    - título
    - app
    - responsable
    - asignados
    - comentarios
    - checklist
    - antigüedad
    - acciones

#### Criterio de validación manual
- Cambiar entre tarjetas y tabla sin perder filtros ni búsqueda
- Verificar que el número de resultados es consistente entre ambas vistas
- Comprobar que abrir una fila abre el detalle correctamente
- Comprobar que checklist, comentarios y responsable se ven bien en tabla

### ✅ Progreso executor adicional (10 Marzo 2026 - refinado tabla y toolbar)

#### Subbloque ejecutado
- **Tabla operativa refinada**
  - prioridad editable inline desde la tabla
  - estado editable inline desde la tabla
  - resto de la fila mantiene apertura del modal
  - ordenación por clic en cabeceras
  - indicadores visuales de dirección de orden

- **Toolbar / filtros refinados**
  - eliminación del botón redundante de filtros
  - filtros integrados siempre visibles
  - mejor alineación y densidad visual en la barra superior

- **Navegación corregida**
  - el acceso a `Solicitudes pendientes de aprobar` ya usa una vista unificada y debe funcionar también desde modo tabla

#### Validación manual requerida
- Editar prioridad inline desde la tabla y comprobar persistencia
- Editar estado inline desde la tabla y comprobar que mantiene el estilo visual correcto
- Ordenar por varias columnas y verificar asc/desc
- Confirmar que la fila sigue abriendo el modal salvo en acciones inline
- Confirmar que `Pendientes Aprobar` funciona tanto estando en tarjetas como en tabla

### ✅ Progreso executor adicional (11 Marzo 2026 - toolbar adaptativa por vista)

#### Subbloque ejecutado
- **Toolbar diferenciada por vista**
  - recuperación de una `requests-toolbar-shell` común para controlar layout por modo
  - en `cards` se mantiene la barra completa con búsqueda + ordenación multinivel
  - en `table` se compacta la cabecera
  - en `table` se ocultan los selects de orden global porque la ordenación principal pasa a los encabezados de tabla
  - se mantienen visibles búsqueda, quick views y toggle de vista

#### Motivo UX
- La vista de tabla ya ofrece una ordenación primaria más natural desde los encabezados.
- Mantener simultáneamente la ordenación multinivel completa generaba ruido visual y duplicidad mental.
- La vista de tarjetas sí sigue aprovechando mejor la barra de ordenación global.

#### Validación manual requerida
- Cambiar entre `Tarjetas` y `Tabla` y confirmar que la cabecera se reconfigura
- En `Tabla`, verificar que ya no aparecen los tres selects de orden global
- En `Tabla`, comprobar que siguen funcionando búsqueda, quick views y cambio de vista
- En `Tarjetas`, confirmar que la barra completa sigue presente y funcional

### ✅ Progreso executor adicional (11 Marzo 2026 - métricas activas y tabla sin completadas)

#### Subbloque ejecutado
- **Summary superior ajustado**
  - `Visibles`, `En progreso`, `Pendientes`, `Sin asignar` y `Con comentarios` ahora calculan solo sobre solicitudes activas
  - las solicitudes `completed` y `discarded` dejan de contaminar esos contadores

- **Vista tabla ajustada**
  - la tabla ahora renderiza únicamente solicitudes activas
  - las solicitudes finalizadas permanecen únicamente en la experiencia de tarjetas, bajo su bloque separado
  - si los filtros devuelven solo finalizadas, la tabla muestra un estado vacío de “solicitudes activas”

#### Validación manual requerida
- Comprobar que los KPIs superiores ya no cuentan completadas/descartadas
- Confirmar que los números del summary cuadran con las tarjetas activas visibles
- Entrar en `Tabla` y verificar que no aparecen solicitudes completadas
- Probar un filtro/quick view que deje solo resultados finalizados y verificar el estado vacío de tabla

### 🧠 Actualización Planner (10 Marzo 2026 - nueva decisión del usuario)

El usuario indica que, para continuar, **prefiere priorizar dos cosas** por encima de otros indicadores operativos más amplios:

1. **Subtareas / checklist dentro de cada mejora**
2. **Alternancia entre vista de tarjetas y vista de tabla comprimida**

Esta nueva preferencia reajusta el foco de **H.3**. En vez de ampliar reporting genérico, conviene centrar la siguiente iteración en mejorar la **ejecución diaria** y la **densidad de información**.

#### Evaluación Planner: checklist/subtareas dentro de la mejora

##### Viabilidad
- **Sí, es viable y recomendable**
- Tiene encaje directo con el objetivo de H.3: dar soporte a la ejecución diaria sin convertir Prisma en una herramienta pesada
- Se puede implementar como un modelo **muy ligero** separado de los campos principales de la request

##### Propuesta funcional mínima
- Añadir una tabla tipo `request_checklist_items`
- Cada item tendría:
  - `id`
  - `request_id`
  - `title`
  - `is_completed`
  - `position`
  - `created_by`
  - `created_at`
- Operaciones mínimas:
  - crear item
  - marcar/desmarcar completado
  - renombrar item
  - eliminar item
- Presentación inicial dentro del **modal de detalle/edición**
- Mostrar también un **resumen compacto** en cards o tabla cuando haya checklist:
  - `0/3`
  - `2/5`

##### Decisiones de alcance para no sobredimensionar
- **No** convertirlo en un sistema de tareas hijo con estados propios
- **No** añadir fechas, responsables por subtarea ni dependencias en esta primera versión
- **No** mezclarlo con el workflow principal de la request

##### Criterio de éxito
- El equipo puede descomponer una mejora en pasos ejecutables pequeños
- El progreso de ejecución se entiende sin entrar en herramientas externas
- El coste cognitivo sigue siendo bajo

#### Evaluación Planner: alternancia tarjetas / tabla comprimida

##### Viabilidad
- **Sí, también es viable y muy valiosa**
- Complementa bien la mejora anterior:
  - **cards** para exploración y contexto
  - **tabla** para revisión masiva, priorización y seguimiento denso

##### Propuesta funcional mínima
- Añadir un toggle global de vista:
  - `Tarjetas`
  - `Tabla`
- Mantener los mismos filtros, búsqueda y quick views para ambas vistas
- La tabla debería ser **compacta, legible y accionable**

##### Columnas recomendadas para la tabla comprimida
- prioridad
- estado
- título
- app
- responsable principal
- nº asignados
- comentarios
- checklist progreso
- antigüedad
- acciones rápidas

##### Decisiones de alcance para no disparar complejidad
- Reutilizar `loadRequests()` y la misma fuente de datos
- Evitar una tabla excesivamente editable en primera versión
- Mantener acciones clave rápidas, pero sin convertir la tabla en un Excel

##### Criterio de éxito
- El usuario puede revisar muchas mejoras en menos scroll
- El cambio entre vistas no rompe filtros ni contexto
- La tabla es claramente más densa pero sigue siendo usable

#### Orden recomendado para ejecución posterior
1. **H.3.1a** Checklist/subtareas ligeras dentro de la mejora
2. **H.1/H.3 transversal** Alternancia entre vista tarjetas y vista tabla comprimida
3. **H.3.2b** Añadir progreso de checklist visible en card/tabla/resumen modal

#### Riesgos y notas de diseño
- La tabla comprimida exige cuidar mucho el responsive; en móvil probablemente conviene mantener cards por defecto
- El checklist sí requiere backend y tabla nueva, pero el dominio es acotado y de bajo riesgo
- La combinación de tabla + checklist es especialmente potente porque permite ver progreso real sin abrir cada mejora

#### High-level Task Breakdown (nueva propuesta Planner)
1. **Checklist ligero por mejora**
   - Crear persistencia y API CRUD mínima para items de checklist
   - Integrar el bloque en el modal de mejora
   - Mostrar progreso agregado por mejora
   - **Criterio verificable**: se pueden crear, completar y eliminar items y el progreso se refleja correctamente

2. **Toggle de vista tarjetas/tabla**
   - Añadir selector de modo de visualización
   - Reutilizar filtros y búsqueda existentes en ambas vistas
   - Construir tabla compacta con columnas operativas clave
   - **Criterio verificable**: el mismo conjunto filtrado puede visualizarse en ambos modos sin inconsistencias

3. **Pulido de densidad operativa**
   - Añadir progreso checklist en tabla/cards/modal
   - Ajustar jerarquía visual y responsive
   - **Criterio verificable**: la vista tabla aporta más densidad sin perder claridad y el checklist aporta seguimiento real

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

### SQL Fase 1 (ya ejecutado)
- assigned_to en requests, request_comments, comment_mentions

### SQL Fase 2 (pendiente de ejecutar)
```sql
-- 1. Tabla de asignaciones múltiples
CREATE TABLE request_assignments (...);
-- 2. Migrar datos de assigned_to
INSERT INTO request_assignments SELECT id, assigned_to FROM requests WHERE assigned_to IS NOT NULL;
-- 3. Tabla de notificaciones
CREATE TABLE notifications (...);
```

### Cambios Fase 2 (9 Marzo 2026)
- **Inbox/Notificaciones**: Panel lateral con notificaciones de menciones, comentarios y asignaciones
- **Asignación múltiple**: Tags + buscador en modal de edición
- **Cards mejoradas**: Votos y botones de acción en filas separadas
- **Emojis eliminados**: Reemplazados por texto limpio en selects del modal
- **Mentions mejorados**: Dropdown compacto que aparece arriba del input, sin @ en los items
- **Notificaciones automáticas**: Al mencionar, comentar en tareas asignadas, o asignar tareas

---

## Auditoría UX/UI Global (27 Mayo 2026)

### Background and Motivation (anexo)
El usuario reporta que Prisma "es regular" tanto en trabajo diario como en paneles de gestión. Solicita revisión completa y libertad para cambiar lo necesario, usando el enfoque de `/design-taste-frontend`. Modo elegido: **Planner → auditoría completa primero, luego decidir qué atacar**.

Puntos de dolor confirmados por el usuario:
- **Diario**: crear/editar peticiones farragoso · lista difícil de escanear · notas/comentarios incómodos.
- **Gestión**: gestión apps/usuarios poco usable · difícil priorizar trabajo del equipo · estética desfasada/inconsistente.

### Key Challenges and Analysis (anexo)

**Hallazgos del audit (resumen — detalle en sección anterior de conversación):**

1. 🔴 **BUG BLOQUEANTE**: `api/assignments.php` consulta tabla `request_assignments` que **no existe** en `schema.sql` ni en `migrations/`. Cualquier asignación rompe en producción nueva. *Verificar primero si en BD real existe vía migración manual no documentada.*
2. 🔴 **Modales de crear/editar petición sobrecargados**: 2 columnas, 8+ campos, adjuntos drag-drop, solicitante colapsable, checklist, comentarios timeline, asignados, zona peligrosa. Estimado 2+ min por petición.
3. 🟠 **Lista sin paginación ni virtualización**: `renderRequests()` mete todas las cards en DOM; `api/requests.php` no tiene `LIMIT`.
4. 🟠 **3 dropdowns de orden en cascada + 7 chips quickview + búsqueda**: barra de filtros sobrecargada y poco intuitiva.
5. 🟠 **Inconsistencia visual**: ~16 colores, prioridad con gradientes, estado outline, dificultad con barras de 32px. Tres lenguajes visuales para tres propiedades análogas.
6. 🟠 **Notas/comentarios/checklist desintegrados**: notas de app en home, comentarios escondidos en modal, checklist invisible fuera del modal.
7. 🟠 **Panel admin denso**: grid de empresas + grid de permisos por app en un mismo modal sin jerarquía.
8. 🟡 **No hay vistas de carga por programador / KPIs de equipo** → priorización a ojo.
9. 🟡 **Código duplicado**: modal crear vs editar casi clonados; 13 modales inline sin reutilizar.
10. 🟡 **API destructiva sin partial updates**: assignments hace DELETE+INSERT completo; no hay PATCH.

**Archivos clave detectados**:
- `index.php` (981 líneas) — modales y layout principal
- `assets/js/main.js` (3380 líneas) — renderRequests, createRequestCard, submitNewRequest, submitEditRequest
- `assets/css/styles.css` (4057 líneas) — paleta y badges
- `admin.php` (450+ líneas) — panel superadmin denso
- `api/assignments.php` — tabla fantasma
- `api/requests.php` — sin LIMIT/paginación

### High-level Task Breakdown (anexo)

Propongo dividir el trabajo en **5 fases** para que el usuario pueda elegir orden/alcance. Cada fase es entregable y verificable de forma independiente.

#### Fase 0 — Saneamiento crítico (0.5 días)
- **T0.1** Verificar en BD real si `request_assignments` existe; si no, añadir migración + actualizar `schema.sql`.
  *Éxito*: asignar usuario a petición en local funciona end-to-end sin error SQL.
- **T0.2** Añadir `LIMIT` + paginación server-side a `api/requests.php` (default 50, scroll/botón "cargar más").
  *Éxito*: con 500 peticiones seed, la home carga <500ms y DOM <100 cards.

#### Fase 1 — Sistema visual unificado (1–2 días)  ← donde brilla `/design-taste-frontend`
- **T1.1** Definir design tokens (CSS custom properties): paleta reducida (6 colores semánticos, no 16), spacing scale, radius, shadows, type scale. Documentar en `assets/css/tokens.css`.
  *Éxito*: todos los colores hardcoded reemplazados por tokens; ningún `#xxxxxx` fuera de `tokens.css`.
- **T1.2** Unificar lenguaje de **prioridad / estado / dificultad** en un mismo componente "pill/chip" con la misma anatomía (color = severidad, icono opcional, texto). Eliminar gradientes y outlines mezclados.
  *Éxito*: visualmente las tres propiedades se leen como variantes del mismo componente.
- **T1.3** Rediseñar la **request card** con jerarquía clara: línea 1 = título + app (chip discreto), línea 2 = estado + prioridad + dificultad alineadas, línea 3 = meta (responsable, comentarios, edad). Eliminar el borde izquierdo coloreado o convertirlo en un indicador más sutil.
  *Éxito*: en 1 segundo se identifica prioridad y estado de una card sin leer texto.
- **T1.4** Rediseñar **filter bar**: 1 search + 1 dropdown "orden" (no 3) + chips de estado como segmented control. Mover orden secundario a un popover "avanzado".
  *Éxito*: la barra cabe en una línea en desktop sin scroll lateral.

#### Fase 2 — Flujo diario fluido (2–3 días)
- **T2.1** **Quick-add inline** de petición desde la home: una sola fila (título + app + prioridad → Enter). Modal completo solo si el usuario pide "más detalles".
  *Éxito*: crear una petición básica en <10 segundos sin abrir modal.
- **T2.2** **Edición inline** de prioridad/estado/dificultad/responsable directamente en la card (ya parcial; consolidar).
  *Éxito*: cambiar estado de 5 peticiones sin abrir ningún modal.
- **T2.3** **Detalle de petición = panel lateral deslizante** (drawer), no modal central. Permite ver lista + detalle a la vez. Comentarios y checklist visibles sin scroll.
  *Éxito*: navegar entre 3 peticiones sin perder contexto de la lista.
- **T2.4** **Comentarios y checklist** con preview en card (contador clickeable abre directamente esa sección del drawer).
  *Éxito*: usuario llega a comentarios en 1 click desde la card.

#### Fase 3 — Paneles de gestión usables (2 días)
- **T3.1** **Vista "Equipo"**: tabla de programadores con columnas (activas, en progreso, completadas-mes, edad media). Click → filtra peticiones de ese programador.
  *Éxito*: superadmin identifica al programador más cargado en <5 segundos.
- **T3.2** Rediseñar **modal de usuario** en `admin.php`: pestañas internas (Datos · Empresas · Permisos por app) en vez de un solo formulario denso.
  *Éxito*: editar permisos de un usuario en empresa con 20 apps cabe sin scroll horizontal.
- **T3.3** **Dashboard de KPIs** (nueva página o widget en home admin): peticiones por estado (donut), backlog por app (bar), envejecimiento (peticiones >30 días sin tocar).
  *Éxito*: al entrar como admin, la primera pantalla responde a "¿qué hay que mover hoy?".

#### Fase 4 — Refactor y limpieza (1 día)
- **T4.1** Extraer componente único `requestModal` reutilizado por crear/editar (eliminar duplicación HTML+JS).
- **T4.2** Añadir endpoints PATCH para updates parciales (`PATCH /api/requests/{id}` con solo el campo cambiado).
- **T4.3** Mover los 13 modales inline a partials PHP en `includes/modals/`.
  *Éxito*: `index.php` <500 líneas; cambios futuros tocan un solo archivo por modal.

### Project Status Board (anexo)
- [ ] **Fase 0.1** — Verificar/crear tabla `request_assignments`
- [ ] **Fase 0.2** — Paginación `api/requests.php`
- [ ] **Fase 1.1** — Design tokens
- [ ] **Fase 1.2** — Componente pill/chip unificado
- [ ] **Fase 1.3** — Rediseño request card
- [ ] **Fase 1.4** — Filter bar simplificada
- [ ] **Fase 2.1** — Quick-add inline
- [ ] **Fase 2.2** — Edición inline consolidada
- [ ] **Fase 2.3** — Drawer lateral en lugar de modal
- [ ] **Fase 2.4** — Previews de comentarios/checklist en card
- [ ] **Fase 3.1** — Vista Equipo (carga por programador)
- [ ] **Fase 3.2** — Modal de usuario con tabs
- [ ] **Fase 3.3** — Dashboard KPIs
- [ ] **Fase 4.1** — Componente requestModal reutilizable
- [ ] **Fase 4.2** — PATCH endpoints
- [ ] **Fase 4.3** — Modales a partials

### Executor's Feedback or Assistance Requests (anexo)
Plan pendiente de aprobación del usuario. Preguntas abiertas antes de pasar a Executor:
1. ¿Empezamos por **Fase 0** (saneamiento) o saltamos directos a **Fase 1** (sistema visual) por impacto percibido?
2. ¿Hay restricciones de compatibilidad (navegadores antiguos, soporte móvil prioritario, etc.)?
3. ¿Volumen real de peticiones por empresa hoy? (decide si Fase 0.2 paginación es urgente o cosmético)
4. ¿Quieres mantener el branding actual (teal #00C9B7) o estás abierto a evolución de paleta en Fase 1.1?

### Current Status / Progress Tracking (27 May 2026 — Fase 1 entrega 1)

**Hecho:**
- ✅ T1.1 · `assets/css/tokens.css` creado con sistema de design tokens (ink scale, brand, semantic, priority/status/difficulty ramps, type scale Geist, spacing 4px-grid, radius, shadows tintadas, motion). Incluye **aliases legacy** para mantener viva `styles.css` sin reescribirla entera.
- ✅ T1.2 · Componente `.chip` unificado (variantes `--solid / --soft / --ghost / --dot`, tonos por `data-tone` / `data-priority` / `data-status` / `data-difficulty`).
- ✅ T1.3 · Bloque "v2.4 Redesign overrides" añadido al final de `styles.css` que:
  - Reestila `.priority-badge` como chip soft con punto, sin gradientes, sin animación heroica (pulse sutil solo en critical vía box-shadow tintado).
  - Reestila `.status-badge` y `.status-badge-display` como chips con dot.
  - Convierte las barras de dificultad (`.difficulty-bar`) en **3 puntos** ●●○ con colores ok/warn/danger.
  - Refina `.card`: borde 1px ink-200 + shadow-xs, hover translateY(-1px) con shadow-md, sin elevación dramática.
  - Nuevo `.request-card-topline` con app + #ID en monoespaciada.
  - `.status-actions` y `.status-action-btn` más planos (26px, fondo neutro).
- ✅ T1.4 · Filter bar simplificada:
  - "Más filtros" toggle (`#toolbar-more-btn`) que añade clase `.is-advanced` y revela secondary/tertiary sort + chips secundarios (Mis · Sin asignar · Comentarios).
  - Quick-views como **segmented control** (4 chips primarios: Todas / Pendientes / En curso / Hechas).
  - View toggle cards/tabla a iconos sin texto, alineado a la derecha.
  - Summary stat cards con números en mono (Geist Mono).
- ✅ `createRequestCard()` rediseñada: topline (app · #id · difficulty · status), título limpio sin prefijo de app, descripción truncada vía `-webkit-line-clamp:2`, prioridad como chip en insights row, footer existente respetado.
- ✅ Fuente Geist + Geist Mono cargada en todas las páginas (`index`, `tasks`, `admin`, `manage-apps`, `releases`, `changelog`, `login`, `solicitud`). Adiós Inter.
- ✅ `tokens.css` enlazado antes de `styles.css` en todas esas páginas. Cache buster `?v=2.4`.

**Pendiente verificación visual del usuario** antes de seguir con:
- Modales (crear/editar petición) — siguen con look anterior, se ajustarán en una entrega 2 de Fase 1 si pasa la primera revisión.
- Sidebar / navegación / login — no tocados aún (esperan a confirmar dirección visual).
- Tabla `requests-table` — no tocada aún.

**Sin cambios funcionales/JS** salvo:
- Nueva función `toggleToolbarAdvanced()` en `main.js` (5 líneas).
- `createRequestCard()` reorganizado pero todos los handlers y IDs preservados.

### Current Status / Progress Tracking (27 May 2026 — Fase 1 entrega 2)

**Hecho (orden A completado):**
- ✅ **Summary fix**: `.requests-summary-bar` ahora es una tira horizontal con divisores verticales (sin card-overuse). "Con comentarios" eliminado (la info se ve en chips de filter bar y en insights row de cada card). 4 stats: Visibles · En progreso · Pendientes · Sin asignar. Números en Geist Mono.
- ✅ **Card footer**: una sola fila, creator sutil con ellipsis, assigned-tags compactos, vote pill (24×24 redondo en ink-50), cluster acciones (rocket/edit/delete) con hover tintado por semántica. Sin duplicaciones de count comments/attachments.
- ✅ **Modales visual refresh**: backdrop con blur, modal-content con borde 1px y shadow-lg, modal-header-icon en chip brand-soft, modal-title 18px semibold, side-section con borde y label uppercase mini, file-upload-area en ink-50 + brand-soft hover, animación de entrada `modal-pop`. Botones solid (no gradient).
- ✅ **Sidebar reimaginado**:
  - Header limpio: logo + wordmark tri-color discreto.
  - Nav primaria: Vista global · Mis tareas · Por aprobar · Notificaciones (todos con icono + texto + counter pill cuando aplica).
  - Apps section con search inline en el header.
  - Counter por app (en mono) con conteo de peticiones activas — implementado en `updateAppCounters()`, llamado tras cada `loadRequests`.
  - "Herramientas" agrupa Release Planner · Changelog · Panel Admin · Gestionar apps.
  - User pill en footer con avatar (gradient tri-color) + nombre + rol uppercase + caret. Click despliega menu con Mi perfil y Cerrar sesión (este último en rojo).
  - `toggleSidebarUserMenu()` con cierre on outside-click.
  - `assets/js/sidebar.js` deja de inyectar el título "Aplicaciones" duplicado.
- ✅ **Admin panel**:
  - `.page-title` reducido a 22px.
  - `.tabs` ahora segmented control (chip group), tab activa con surface y brand icon.
  - Tablas con borde 1px ink-200, header en ink-50, hover en ink-50, sin sombras pesadas.
  - `.badge-*` mapeados a chip-soft del sistema (superadmin solid ink-950, admin brand-soft, programador warn-soft, active ok-soft, etc.).
  - `.actions-cell` con botones 28px bordered.

**Bug operativo descubierto durante test local:**
- En el entorno de pre-producción, varias páginas (`admin.php`, `manage-apps.php`, `tasks.php`, `releases.php`, `changelog.php`) llaman `require_once auth.php` DESPUÉS de emitir `<head>...</head>`. En prod funciona porque `output_buffering` está en On por defecto en hosting compartido; en local con PHP CLI viene a 0 y rompe `session_start` + `header(Location:)`. Solución: `.claude/launch.json` ahora arranca con `-d output_buffering=On -d display_errors=Off`.

**Bug de schema persistido:**
- `migrations/013_local_bootstrap.sql` añade las tablas/columnas que existían en prod pero no en `schema.sql`: `request_assignments`, `request_comments`, `comment_mentions`, `request_checklist_items`, `notifications`, `requests.difficulty`, `requests.assigned_to`. Cualquier nueva instalación funciona sin parches.

**Pendiente para próxima iteración:**
- Refinar pillpas de modales largos (Editar petición con checklist + comentarios + asignados).
- Quizás drawer lateral en lugar de modal central (Fase 2 T2.3).
- Refresh visual de `manage-apps.php` y `tasks.php` (heredan los tokens pero no se ha verificado uno por uno).
- Vista equipo (carga por programador) — Fase 3.
- Dashboard KPIs — Fase 3.

---

# Feature: AI Inbox — Nota rápida procesada con IA (Planner, 10 Junio 2026)

## Background and Motivation (AI Inbox)

El usuario hoy abre la app "Notas" de Apple durante las reuniones y apunta en bruto. Luego tiene que transcribir manualmente a Prisma. Objetivo: una vista de "Nota rápida" en Prisma donde vuelca texto libre, y una capa de IA (vía OpenRouter, modelo Gemini Flash-lite o similar) que propone automáticamente qué crear: mejoras (requests), subtareas (checklist items) y tareas rápidas (tasks), asignadas a la app correcta.

**Requisito explícito del usuario**: la pantalla de revisión debe ser MUY clara, con UX óptima, explicaciones visibles y posibilidad total de cambio/edición antes de crear nada.

## Key Challenges and Analysis (AI Inbox)

1. **Infraestructura ya resuelta**: 
   - Crear mejoras: `POST api/requests.php` (app_id + title obligatorios; description, priority opcionales).
   - Subtareas: ya existen como `request_checklist_items` (`api/request-checklist.php`), se crean tras la mejora.
   - Tareas rápidas: `POST api/tasks.php`.
   - Cifrado de secretos: `includes/encryption.php` (AES-256-CBC, patrón usado para SMTP) → reutilizar para la API key de OpenRouter.
2. **Llamada a OpenRouter desde PHP en hosting compartido**: cURL HTTPS estándar, sin dependencias nuevas. Usar `response_format` con JSON Schema (structured outputs) para garantizar salida parseable. Timeout generoso (~60s) y manejo de error claro.
3. **Clasificación contra apps reales**: el prompt debe incluir la lista de apps a las que el usuario tiene acceso (id + nombre + descripción) para que el modelo asigne `app_id` válidos. Si no está seguro, debe poder devolver `app_id: null` ("sin asignar") y la UI obliga a elegir.
4. **Modelo**: el usuario propone "Gemini 3.1 Flash-lite" vía OpenRouter. ⚠️ Verificar ID exacto y pricing en openrouter.ai/models antes de implementar (pedir al usuario búsqueda web de docs actuales, según norma del proyecto). El modelo debe ser configurable (constante o campo en config), no hardcodeado en varios sitios.
5. **El riesgo principal es la confianza**: si la IA clasifica mal y se crean cosas erróneas, el usuario dejará de usarlo. Mitigación: **nunca crear nada automáticamente** — siempre pantalla de revisión con edición completa, y solo se persiste al confirmar.
6. **UX de la pantalla de revisión** (requisito central):
   - Cada item propuesto = una tarjeta con: tipo (badge "Mejora" / "Tarea rápida"), app asignada (selector editable), título (input editable), descripción (textarea editable), prioridad (selector), subtareas (lista editable: añadir/quitar/renombrar).
   - Razonamiento de la IA visible: campo `reasoning` corto por item ("Lo asigné a App X porque mencionas...") mostrado como texto explicativo en la tarjeta.
   - Checkbox por item para incluir/descartar; botón "Descartar" visual.
   - Resumen superior: "La IA ha detectado N mejoras y M tareas. Revisa, edita y confirma. No se creará nada hasta que pulses Confirmar."
   - Estado vacío y errores explicados en lenguaje natural.
   - Tras confirmar: resumen de lo creado con enlaces a cada elemento.
7. **Seguridad**: endpoint solo para usuarios autenticados con permisos de creación; la API key nunca viaja al frontend; sanitizar/limitar tamaño de la nota (p.ej. 10.000 caracteres).
8. **Simplicidad (no overengineering)**: sin colas, sin historial de notas en BD en v1 (la nota se procesa y se descarta; opcional guardar la nota original como referencia en una tabla simple si se ve necesario más adelante).

## High-level Task Breakdown (AI Inbox)

> Cada tarea se ejecuta de una en una; el Executor espera verificación del usuario antes de continuar.

- **T0. Verificación de docs de OpenRouter** (bloqueante)
  - El usuario lanza búsqueda web de la doc actual de OpenRouter (endpoint chat/completions, structured outputs, ID y precio del modelo Gemini Flash-lite vigente).
  - Crear `docs/openrouter-api.md` con lo aprendido.
  - ✅ Éxito: archivo .md creado con endpoint, headers, formato structured outputs, ID de modelo confirmado.

- **T1. Configuración y almacenamiento de la API key**
  - Migración `014_ai_settings.sql`: tabla o filas de settings para `openrouter_api_key` (cifrada) y `ai_model`.
  - UI mínima en panel admin para guardar la key (reutilizar patrón SMTP) + botón "Probar conexión".
  - ✅ Éxito: key guardada cifrada en BD; "Probar conexión" devuelve OK con una llamada real mínima.

- **T2. Endpoint `api/ai-inbox.php` (acción: analizar)**
  - POST con `{ note: "texto" }` → auth, validación de longitud → construye prompt con lista de apps del usuario → llama a OpenRouter con JSON Schema → devuelve `{ items: [...] }` con tipo, app_id, title, description, priority, subtasks[], reasoning.
  - No escribe nada en BD.
  - ✅ Éxito: probado con una nota real de reunión, devuelve JSON válido y razonable; errores (key inválida, timeout) devuelven mensaje claro.

- **T3. Vista "Nota rápida" (entrada)**
  - Nueva página `ai-inbox.php` + entrada en sidebar: textarea grande, contador de caracteres, botón "Analizar con IA", estado de carga con explicación ("Analizando tu nota..."), CSS en `styles.css`.
  - ✅ Éxito: se puede pegar una nota y lanzar el análisis; loading y errores visibles y comprensibles.

- **T4. Pantalla de revisión (núcleo UX)**
  - Render de tarjetas editables según el diseño del punto 6 del análisis: todo editable, razonamiento visible, incluir/descartar, resumen superior explicativo.
  - ✅ Éxito: el usuario puede cambiar app, tipo, título, descripción, prioridad y subtareas de cada item, y descartar items, antes de confirmar. Nada se crea aún.

- **T5. Confirmación y creación**
  - Al pulsar "Confirmar": el frontend crea cada item aceptado vía APIs existentes (`requests.php` → luego `request-checklist.php` por subtarea; `tasks.php` para tareas rápidas). Manejo de fallos parciales (mostrar qué se creó y qué falló).
  - Pantalla final de resumen con enlaces a lo creado.
  - ✅ Éxito: flujo completo nota → revisión → elementos visibles en sus vistas correspondientes de Prisma.

- **T6. Pulido y prueba end-to-end**
  - Probar con 2-3 notas reales de reuniones del usuario; ajustar prompt si clasifica mal; revisar móvil/PWA.
  - ✅ Éxito: el usuario confirma que el flujo reemplaza su nota de Apple Notas en un caso real.

## Project Status Board (AI Inbox)

- [x] T0. Verificar docs OpenRouter + crear docs/openrouter-api.md ✅
- [x] T1. Config + API key cifrada + probar conexión ✅
- [x] T2. Endpoint api/ai-inbox.php (analizar) ✅
- [x] T3. Vista Nota rápida (entrada) ✅
- [x] T4. Pantalla de revisión editable ✅
- [x] T5. Confirmación y creación vía APIs existentes ✅
- [x] T6. Pulido y prueba end-to-end ✅

## Current Status / Progress Tracking (AI Inbox)

- 10 Jun 2026 (Executor): **T0 completada**. Doc oficial de OpenRouter verificada vía web. Modelo confirmado: `google/gemini-3.1-flash-lite` ($0.25/$1.50 por 1M tokens, contexto 1M, soporta structured outputs). Creado `docs/openrouter-api.md` con endpoint, headers, schema JSON exacto para el inbox y notas de implementación PHP. Pendiente verificación del usuario para continuar con T1.

## Lessons (AI Inbox)

- Las URLs de docs de OpenRouter cambiaron: ahora viven bajo `openrouter.ai/docs/guides/...` y `openrouter.ai/docs/api/...` (las rutas antiguas `docs/features/...` dan 404).
- Structured outputs: usar siempre `strict: true` + `additionalProperties: false` + `description` en cada propiedad; el JSON viene como string en `choices[0].message.content`.
- 10 Jun 2026 (Executor): **T1 implementada** (pendiente de que el usuario ejecute la migración y verifique). Creados: `migrations/014_ai_settings.sql` (tabla `app_settings` clave/valor + modelo por defecto), `api/ai-settings.php` (superadmin; GET ajustes sin exponer key, POST guardar key cifrada/modelo, POST ?action=test llamada real a OpenRouter), pestaña "IA" en `admin.php`, funciones en `assets/js/admin.js`, estilos en `assets/css/styles.css`. Sintaxis PHP/JS verificada con php -l y node --check. No se puede probar en navegador hasta que la migración esté ejecutada en la BD.
- 10 Jun 2026 (Executor): Bug T1 reportado por usuario (PWA servía admin.js antiguo cacheado → "testAiConnection is not defined"). Fix: cache-busting `admin.js?v=ai1` en admin.php, bump `CACHE_NAME` a prisma-v2 en sw.js, y guard en sw.js para ignorar esquemas no-http (error chrome-extension en cache.put).

## Lessons (PWA)
- El service worker usa network-first pero `fetch(request)` pasa por la caché HTTP del navegador: al cambiar JS/CSS hay que versionar la URL (`?v=...`) y/o subir `CACHE_NAME` en sw.js.
- `cache.put` falla con peticiones `chrome-extension://`; filtrar por `url.startsWith('http')` en el handler de fetch.
- 10 Jun 2026 (Executor): T1 verificada por el usuario ✅ (key guardada y test de conexión OK en producción). **T2 implementada**: `api/ai-inbox.php` — POST {note}, valida longitud (10k), construye prompt con apps reales del usuario (get_user_apps), llama a OpenRouter con structured outputs (strict json_schema), valida en servidor que los app_id devueltos existen (si no → null), sanea campos y devuelve items sin escribir en BD. Sintaxis OK. Prueba real pendiente: se probará junto con la UI (T3/T4), o el usuario puede probar vía curl autenticado.
- 10 Jun 2026 (Executor): **T3+T4+T5 implementadas como un único flujo verificable**: `ai-inbox.php` (página con 4 estados: nota → analizando → revisión → resumen), `assets/js/ai-inbox.js` (render de tarjetas editables, incluir/descartar, validación de mejoras sin app, creación vía requests.php + request-checklist.php + tasks.php con manejo de fallos parciales), entrada "Nota rápida" en sidebar.php, estilos en styles.css (con prefers-reduced-motion). Verificado contrato real de las APIs (checklist espera request_id en body; tasks no requiere app). Sintaxis PHP/JS OK. Pendiente prueba end-to-end del usuario (T6).
- 10 Jun 2026 (Executor): Restyling de la pantalla de revisión por feedback del usuario (tarjetas ocupaban demasiado): cabecera en una línea (checkbox "Se creará" + selectores compactos de tipo/prioridad/app), título y descripción como campos inline sin label (borde visible solo en hover/focus), subtareas compactas con borde lateral, razonamiento como línea discreta sin caja. Versiones de caché subidas (styles v2.6, ai-inbox.js ai2).
- 10 Jun 2026 (Executor): Segundo pase de diseño de la revisión, verificado visualmente en preview local con harness estático (`preview-ai-inbox.html`, NO subir a producción). Causa raíz del layout roto: `select { width: 100% }` y `textarea { min-height: 90px }` globales. Solución: selects como pills compactas (override width:auto, appearance none, chevron SVG), prioridad tintada con los colores soft existentes (--prio-*-soft/-ink), app sin asignar en mejoras con pill naranja de aviso (y "Sin aplicación" neutra en tareas), checkbox "Se creará" como chip teal, descripción con field-sizing:content, razonamiento como pie con borde discontinuo. Versiones: styles v2.7, ai-inbox.js ai3. Consola sin errores.
- 10 Jun 2026 (Executor): Revisión agrupada por aplicación (petición del usuario): bloques con cabecera de app (nombre + empresa + contador incluidos/total), grupo destacado en naranja "Sin aplicación asignada", grupo "Tareas rápidas" al final. Grid responsivo auto-fill minmax(380px,1fr) (2 columnas en escritorio, 1 en <860px), vista de revisión ensanchada a 1100px. Verificado en preview con harness. Al cambiar la app de una tarjeta se reagrupa automáticamente (renderReview re-render).
- 10 Jun 2026 (Executor): Masonry en la revisión (CSS columns:2 + break-inside:avoid, 1 columna <860px) para que tarjetas de distinta altura no dejen huecos. Razonamiento de la IA ahora condicional: prompt y schema de api/ai-inbox.php instruyen reasoning vacío salvo clasificación dudosa, falta de información o aviso importante (el frontend ya solo lo pinta si viene relleno). Verificado en preview. styles v2.8.
- 10 Jun 2026: **Feature AI Inbox COMPLETADA y verificada por el usuario en producción** (T0-T6). Flujo: Nota rápida → análisis con OpenRouter (google/gemini-3.1-flash-lite, structured outputs) → revisión agrupada por app (masonry 2 col, todo editable, razonamiento solo cuando aporta) → creación vía APIs existentes. Nota: `preview-ai-inbox.html` y `.claude/launch.json` son harness de desarrollo local, no subir al servidor.

## Current Status / Progress Tracking (15 Jun 2026 — Asignación en alta + restyling formularios)

Petición del usuario (Executor directo): (1) añadir asignación de responsable al alta de mejoras (modal "Nueva Petición" y nota IA), (2) mejorar UX/estilo de los formularios de alta y edición, botones y selectores feos.

Decisiones del usuario: asignación en alta solo para rol programador+ (igual que el modal de edición); en la nota IA asignar **solo cuando se nombre explícitamente un responsable** en el elemento.

Implementado:
- **A. Asignación en "Nueva Petición"** (`index.php`): nueva sección lateral "Asignados" (tags + buscador), gated `has_role('programador')`. `assets/js/main.js`: funciones de asignación generalizadas con parámetro `prefix` ('edit' por defecto, 'new' para el alta) y estado separado `window.newAssignments`; al crear, POST a `api/assignments.php` con los user_ids seleccionados. Sin cambios en el backend (reutiliza `assignments.php`).
- **C. Asignación por IA** (`api/ai-inbox.php`): nuevo campo `assignee_name` en prompt+schema (solo si la nota nombra al responsable explícitamente; "" si no). Emparejado server-side conservador con `match_assignee()` (exacto username/nombre completo/primer nombre; null si ambiguo) contra usuarios activos → devuelve `assignee_id`/`assignee_name` solo en mejoras. `assets/js/ai-inbox.js`: chip "Responsable" en la tarjeta (con botón quitar) y, al confirmar, POST a `assignments.php` tras crear la mejora.
- **B. Restyling** (`assets/css/styles.css`): selects con chevron propio (appearance none + SVG, hover/focus con tokens), botones aplanados (color sólido brand/semántico en vez de degradados, hover sutil con `--shadow-sm` y translateY(-1px), active scale), placeholders y hover de inputs con tokens. Ajuste de padding-right del select en secciones laterales del modal.
- Cache-busting subido: `styles.css?v=2.9` en todas las páginas, `ai-inbox.js?v=ai4`, `main.js?v=2.9`.

Verificación: `php -l` OK (ai-inbox.php, index.php), `node --check` OK (main.js, ai-inbox.js). **Pendiente verificación visual/funcional del usuario en navegador autenticado** (el dashboard está tras `require_login()` + BD, no accesible desde el harness autónomo). Sugerencia de pruebas: (1) crear mejora como programador asignando responsable; (2) nota IA con texto tipo "...que lo haga Juan" comprobando que aparece el chip de responsable y se asigna al crear; (3) revisar botones y selects en alta/edición.

## Lessons (Asignación + restyling)
- `background: <color>` (shorthand) resetea `background-image`; para selects con chevron usar `background-color` o redefinir el chevron en la regla específica (caso `.sort-select` que ya lo hacía bien).
- Las funciones de asignación de `main.js` ahora aceptan `prefix`; el modal de edición sigue llamándolas sin args (default 'edit'), no romper esa compatibilidad.
- `api/assignments.php` POST exige `can_edit_requests()`; si un usuario sin permiso usa la nota IA, la asignación falla en silencio (la mejora se crea igual) — comportamiento aceptado.

---

# PLAN: Revamp de Tareas (Creación rápida NLP + Agenda/Dashboard) — 2026-06-19

## Background and Motivation
La parte de Mejoras funciona bien y es "atemporal". La parte de Tareas es mejorable en dos frentes:
1. **Creación**: rápida para el título, pero poner fecha/app es tedioso (date picker nativo + abrir "más opciones"). Se quiere mantener la creación rapidísima pero poder configurar sobre la marcha.
2. **El "después"**: faltan vistas que muestren tareas futuras y avisen claramente de lo que toca.

Decisiones tomadas con el usuario (Planner, 2026-06-19):
- Creación: **Parser local de lenguaje natural** (Opción A). Sin IA por tarea (latencia/coste/offline). El AI Inbox ya cubre notas largas.
- "Después": **Vista Agenda en la página de tareas** + **widget en la vista global (index.php, home)**. (Email y Web Push quedan fuera de este alcance.)
- Prioridad: **NO** se añade campo de prioridad. Solo fecha y app.
- Principios UX (de taste-skill, adaptados a PHP plano): jerarquía por peso/color en vez de cajas, sin puntos de color decorativos, sin em-dashes, densidad media, agrupación temporal clara.

## Key Challenges and Analysis
- **Sin build / hosting compartido**: todo JS plano en `assets/js/`, CSS en `assets/css/`. Versionar `?v=` y `CACHE_NAME` de sw.js al tocar assets (PWA cachea).
- **Parser NLP en español, client-side**: detectar fecha y app dentro del texto del input y limpiarlas del título. Sin dependencias externas (escribir parser propio pequeño).
  - Fechas soportadas: `hoy`, `mañana`, `pasado mañana`, días de semana (`lunes`..`domingo` y abreviaturas `lun`,`mar`...), `en N días/semanas`, `próxima semana`/`semana que viene`, fechas numéricas `15/07`, `15-07`, `15/7/2026`.
  - App: `@nombre` o `#nombre` (match difuso contra apps del usuario por prefijo/inclusión, case-insensitive, sin acentos).
  - Salida: `{ cleanTitle, dueDate (YYYY-MM-DD|null), appId (int|null), appName }`.
  - Resolución de fechas relativas en horario local del navegador (cuidado con `new Date('YYYY-MM-DD')` que es UTC; construir con `new Date(y,m,d)`).
- **Confirmación visual ("chips en vivo")**: bajo el input, mostrar pills con lo detectado a medida que se escribe (debounce input). El usuario ve "📅 mañana · @Puri" antes de pulsar Enter. Pills con botón "x" para quitar el token detectado (vuelve a literal en el título). Reusar estilo de chips existente, no inventar puntos de color.
- **Compatibilidad API**: `api/tasks.php` POST ya acepta `title`, `app_id`, `due_date`. NO requiere cambios de backend para la creación. El parser solo rellena ese payload.
- **Vista Agenda**: agrupar las tareas ya devueltas por `api/tasks.php` (que ya ordena por due_date) en buckets en el cliente: Vencidas / Hoy / Mañana / Esta semana / Más adelante / Sin fecha. Cálculo de buckets en horario local. Tareas completadas quedan fuera de buckets (o en su sección actual con el filtro existente).
- **Widget home (index.php / vista global)**: index.php usa carga por JS (`loadView('global')` en main.js). Añadir una tarjeta "Qué toca" con contadores (Vencidas / Hoy / Esta semana) que enlacen a tasks.php con filtro. Necesita un endpoint o reutilizar `api/tasks.php` GET (shared=0). Lo más simple: fetch a `/api/tasks.php`, contar en cliente. Evaluar si la vista global ya hace fetch de algo reutilizable.
- **Riesgos**: el override global de `styles.css` (`input/select width:100%`, `textarea min-height:90px`) obliga a overrides por clase en cualquier UI compacta (pills, contadores).

## High-level Task Breakdown

### FASE 1 — Parser NLP en creación rápida (página tareas) — IMPLEMENTADA (pend. verificación usuario)
- [x] 1.1 Escribir `parseQuickTask(text, userApps)` en `assets/js/tasks.js` (o nuevo `assets/js/task-parser.js`) que devuelva `{cleanTitle, dueDate, appId, appName, matchedTokens}`. 
  - Éxito: con casos de prueba manuales ("Llamar a Juan mañana @puri", "Revisar informe viernes", "Pagar dominio 15/07 #reservas") devuelve título limpio + fecha + app correctos.
- [x] 1.2 Render de chips en vivo bajo el input (debounce ~150ms) mostrando fecha y app detectadas, con "x" para descartar token. CSS en `assets/css/tasks.css` (sin puntos decorativos).
  - Éxito: al teclear, aparecen/desaparecen los chips correctos; quitar un chip devuelve el literal al título al crear.
- [x] 1.3 Conectar al submit (Enter): usar `cleanTitle`/`dueDate`/`appId` del parser en el payload POST existente. Mantener fallback "más opciones" manual.
  - Éxito: Enter crea la tarea con fecha/app sin abrir el panel; el input se limpia y re-enfoca.
- [x] 1.4 Pequeña ayuda/hint visible (placeholder o tooltip) explicando la sintaxis (`mañana`, `@app`, `15/07`).
  - Éxito: usuario nuevo entiende la sintaxis sin documentación externa.
- [x] 1.5 Versionar assets (`?v=`) y `CACHE_NAME` de sw.js.

### FASE 2 — Vista Agenda (página tareas) — IMPLEMENTADA (pend. verificación usuario)
- [x] 2.1 Agrupar tareas en buckets temporales en `renderTasks()` (Vencidas/Hoy/Mañana/Esta semana/Más adelante/Sin fecha). Encabezados de sección con jerarquía por peso/color (Vencidas en rojo de tokens, no punto decorativo).
  - Éxito: las tareas aparecen bajo el bucket correcto según `due_date` y fecha local; secciones vacías no se muestran.
- [x] 2.2 Toggle de vista (Agenda / Lista plana) **REQUISITO FIRME del usuario**, recordando preferencia en localStorage.
  - Éxito: cambiar vista persiste entre recargas.
- [x] 2.3 Versionar assets.

### FASE 3 — Widget "Qué toca" en vista global (home) — IMPLEMENTADA (pend. verificación usuario)
- [x] 3.1 Identificar dónde inyectar la tarjeta en la vista global de index.php/main.js.
- [x] 3.2 Tarjeta con contadores Vencidas / Hoy / Esta semana (reusando `api/tasks.php` GET), cada uno enlazando a tasks.php (con filtro/anchor de bucket).
  - Éxito: contadores correctos; clic lleva a la sección/bucket correspondiente en tareas.
- [x] 3.3 Versionar assets.

## Notas
- Email diario y Web Push: documentados como opciones descartadas en este alcance; retomar si el usuario lo pide.

## Current Status / Progress Tracking (19 Jun 2026 — Revamp Tareas, Fase 1)
**Fase 1 (parser NLP en creación rápida) IMPLEMENTADA.** Archivos:
- `assets/js/task-parser.js` (NUEVO): `parseQuickTask(text, apps)` + `stripQuickMatch()`. Detecta fecha (hoy/mañana/pasado mañana/día de semana/en N días/próxima semana/numérica DD/MM[/AAAA]) y app (@/# difuso sin acentos). Devuelve `{date:{value,match}, app:{id,name,match}}`.
- `assets/js/tasks.js`: estado `quickIgnore`, `renderQuickPreview()` (chips en vivo, debounce 120ms), `dismissQuickChip()`, `resetQuickPreview()`; `createQuickTask()` limpia el título con los tokens no descartados; el panel "más opciones" manual sigue teniendo prioridad si se rellena.
- `tasks.php`: `QUICK_APPS` (JSON apps), include `task-parser.js?v=1` + `tasks.js?v=2`, contenedor `#quick-add-chips`, placeholder con pista de sintaxis, `tasks.css?v=2.5`.
- `assets/css/tasks.css`: `.quick-add-chips`, `.quick-chip`, `.quick-chip-remove`.
- `sw.js`: `CACHE_NAME`/`RUNTIME_CACHE` → v4.
- Harness local NUEVO `preview-tasks-quickadd.html` + config `prisma-preview` (puerto 8799) en `.claude/launch.json`. **No subir a producción.**

Verificación hecha (Executor): `node --check` OK (parser, tasks.js), `php -l tasks.php` OK; 10 casos de parser correctos vía node (incl. 31/02 inválido descartado, viernes→próximo viernes); render visual de chips + descarte verificado en preview (sin errores de consola). Sin cambios de backend (`api/tasks.php` ya acepta title/app_id/due_date).

**Pendiente: verificación del usuario en el dashboard autenticado** (subir assets + refrescar PWA). Tras OK, continuar con Fase 2 (Agenda + toggle Agenda/Lista persistente).

## Lessons (Revamp Tareas)
- El `php -S :8765` que suele estar levantado NO sirve este proyecto (404). Para preview visual usar la config `prisma-preview` (puerto 8799).
- Fechas relativas: construir con `new Date(y, m-1, d)` (local); `new Date('YYYY-MM-DD')` es UTC y desplaza el día.

## Current Status / Progress Tracking (19 Jun 2026 — Fase 2 Agenda + toggle)
**Fase 2 IMPLEMENTADA.** Vista Agenda con buckets (Vencidas/Hoy/Mañana/Esta semana/Más adelante/Sin fecha/Completadas) + toggle Agenda/Lista persistente en localStorage (`prisma_tasks_view`, default 'agenda').
- `assets/js/tasks.js`: refactor de `renderTasks()` → `buildTaskCard()` (helper, mismo HTML de tarjeta), `getTaskBucket()`, `renderAgenda()`, `setTasksView()`, `updateViewToggleUI()`, `getTasksView()`. `lastTasks` cachea la última tanda para re-render al cambiar de vista sin refetch. `diffDaysFromToday` usa `Math.round` (local). Buckets: <0 vencida, 0 hoy, 1 mañana, ≤7 esta semana, resto más adelante; completadas siempre al final.
- `tasks.php`: segmented control `#view-toggle` en `.header-actions`; `tasks.css?v=2.6`, `tasks.js?v=3`.
- `assets/css/tasks.css`: `.view-toggle(-btn)`, `.agenda-section(-header/-title/-count/-items)`, color de "Vencidas" (rojo) y "Hoy" (naranja) por jerarquía de color (sin puntos decorativos).
- `sw.js`: caché → v6.
- Harness NUEVO `preview-tasks-agenda.html` (carga tasks.js real con fetch simulado). No subir a producción.

Verificación (Executor): `node --check` + `php -l` OK; en preview los 7 buckets salen en orden con counts correctos, toggle a Lista da 7 tarjetas planas / 0 secciones, preferencia persiste tras recarga, sin errores de consola. **Pendiente verificación del usuario.** Tras OK → Fase 3 (widget "Qué toca" en vista global de index.php).

## Current Status / Progress Tracking (19 Jun 2026 — Fase 3 Widget "Qué toca")
**Fase 3 IMPLEMENTADA. Las 3 fases del plan de revamp de Tareas están completas (pend. verificación del usuario en producción).**
- `index.php`: contenedor `#tasks-widget` (oculto por defecto) tras `.content-header`, con head (título + enlace "Ir a Mis tareas") y `#tasks-widget-stats`.
- `assets/js/main.js`: `loadTasksWidget()` (fetch `/api/tasks.php?completed=0&shared=0`), `computeTasksWidgetCounts()` (overdue / hoy / próximos 7 días, excluye completadas y sin fecha), `renderTasksWidget()` (3 tiles enlazando a `/tasks.php#overdue|#today|#week`), `updateTasksWidgetVisibility()` (solo `currentView==='global'`, carga perezosa). Enganchado en init y en `loadView()`.
- `assets/js/tasks.js`: `maybeScrollToBucket()` tras render en `loadTasks()` — si la URL trae `#overdue|#today|#week`, fuerza vista Agenda y hace scroll a `.agenda-<bucket>`.
- `assets/css/styles.css`: bloque `.tasks-widget*` (grid 3 col, 1 col <600px; tonos por severidad; tiles `.is-empty` apagados).
- Versiones: `styles.css?v=3.5`, `main.js?v=3.4` (index.php), `tasks.js?v=4` (tasks.php), caché PWA → v7.
- Harness NUEVO `preview-tasks-widget.html` (evalúa main.js real tras 'load' para no disparar su init; fetch simulado solo en `/api/`). No subir a producción.

Verificación (Executor): `node --check` (main.js, tasks.js) + `php -l` (index.php, tasks.php) OK; en preview con main.js REAL los contadores salen correctos (2 vencidas / 1 hoy / 2 próximos 7 días con el mock), hrefs por bucket correctos, 3 col en escritorio y 1 col en móvil, sin errores de consola. **Pendiente verificación del usuario.**

Archivos a subir (Fase 3): `index.php`, `assets/js/main.js`, `assets/js/tasks.js`, `assets/css/styles.css`, `sw.js`. (Harness `preview-tasks-*.html` y la config `prisma-preview` de launch.json NO subir.)

## Current Status / Progress Tracking (19 Jun 2026 — Parser en botón flotante)
**Añadido el parseo NLP también al botón flotante de tarea rápida** (solo está en `index.php`, no en toda la app pese a parecerlo).
- `index.php`: incluido `task-parser.js?v=2` antes de `main.js?v=3.5`; placeholder del input flotante con pista de sintaxis.
- `assets/js/main.js`: `submitFloatingTask()` ahora parsea con `parseQuickTask(raw, apps)` (reusa el array global `apps` ya poblado por `loadApps()`), limpia el título con `stripQuickMatch`, envía `due_date`/`app_id`, y el toast muestra lo detectado (fecha + @app). Nuevo helper `formatFloatingDate()`.
- `sw.js`: caché → v8.
- Harness NUEVO `preview-floating-task.html` (no subir).

Verificación (Executor): `node --check` + `php -l` OK; end-to-end con main.js+parser reales y `apps` poblado vía `loadApps`: "...mañana @puri" → {due_date, app_id:1, title limpio}; "...30 junio #reservas" → {due_date 30/06, app_id:7, title limpio}. Pendiente verificación del usuario.

Archivos a subir: `index.php`, `assets/js/main.js`, `sw.js`. (No subir harness `preview-*.html`.)

## Current Status / Progress Tracking (19 Jun 2026 — Ajustes sidebar)
**Ajustes de sidebar (cerrado y aprobado por el usuario):**
- "Por aprobar" movido de la nav principal a la fila de iconos inferior (`nav-tools-row`, 1ª posición); conserva onclick/href y el contador `#pending-count` ahora como badge en esquina (`.nav-tools-row .nav-item .nav-count` absoluto).
- "Gestionar apps" eliminado para superadmin (duplica Panel Admin → Apps) pero **role-aware**: se muestra a admin NO superadmin (que no tiene acceso a `admin.php`), preservando su única vía a `manage-apps.php`.
- `includes/sidebar.php`, `assets/css/styles.css`. Versionado: `styles.css?v=3.6` en TODAS las páginas, caché PWA → v9.

**Lección:** `manage-apps.php` requiere rol `admin`; `admin.php` (con pestaña Aplicaciones) requiere `superadmin`. No son equivalentes en acceso aunque sí en función → al "deduplicar" en UI, gating por rol para no dejar sin acceso a admins normales.

## High-level Task Breakdown (contadores globales del sidebar)
- [x] 1. Mantener los contadores de pendientes de todas las aplicaciones al navegar a una app o empresa, sin cambiar el filtro de solicitudes de la vista central. IMPLEMENTADO, pendiente de verificación manual del usuario.
  - Éxito: los badges usan el conjunto global de solicitudes accesibles; la vista central conserva solo las solicitudes de la app/empresa activa; el JS supera validación sintáctica.

## Project Status Board (contadores globales del sidebar)
- [x] Corregir el origen de datos de `updateAppCounters()` y verificar sintaxis.
- [ ] Verificación manual del usuario en el dashboard autenticado.

## Executor's Feedback or Assistance Requests (contadores globales del sidebar)
- Diagnóstico: `loadRequests()` reemplaza `requests` con la respuesta filtrada por `app_id`/`company_id`, y `updateAppCounters()` calcula todos los badges desde ese mismo array. Por eso desaparecen los contadores de las apps no activas.
- Implementación: `appCounterRequests` se carga sin filtro de vista mediante el endpoint existente (que conserva el scope de permisos del usuario); `requests` sigue alimentando exclusivamente el panel central filtrado. Versionado `main.js?v=3.6` y caché PWA v10.
- Verificación Executor: `node --check assets/js/main.js`, `php -l index.php`, `php -l api/requests.php` y `git diff --check`, todos correctos. Se solicita comprobar manualmente que al entrar en una app siguen visibles los badges de las demás apps.

## Lessons (contadores globales del sidebar)
- Los badges globales no deben derivarse del array `requests`, porque ese estado representa la consulta y los filtros de la vista central activa.

## Background and Motivation (crear tarea desde mejora)
El usuario quiere poder crear una tarea directamente desde una mejora, pulsando un botón y eligiendo solo la fecha. La intención es reducir fricción entre el backlog de mejoras y la ejecución diaria en "Mis tareas".

La tarea debe tener un nombre natural, aunque la mejora tenga títulos técnicos tipo `Add: xxx`, `Fix: xxx`, `feat: xxx` o similares. Recomendación Planner: empezar sin IA. Ya existe un parser local de tareas y el coste/latencia de IA no parece justificado para este flujo. La primera versión debe usar una normalización determinista, testeada y fácil de ajustar; si en uso real los títulos siguen saliendo raros, se podrá añadir una capa IA ligera después.

Principios UX a aplicar con `design-taste-frontend`, adaptados al stack actual PHP/JS/CSS plano:
- Botón integrado en las acciones de la mejora, con icono existente y etiqueta/tooltip claro.
- Modal compacto: título propuesto editable, fecha obligatoria o destacada, contexto de la mejora visible sin ruido.
- Estados completos: cargando, éxito, error inline, prevención de doble submit.
- CSS siempre en `assets/css/styles.css`, sin inline nuevo.
- Mantener densidad media, colores sobrios y coherencia con los patrones actuales; no introducir librerías nuevas salvo necesidad verificada.

## Key Challenges and Analysis (crear tarea desde mejora)
- La app ya tiene `api/tasks.php` con POST para `title`, `description`, `app_id`, `due_date` e `is_shared`. Esto permite crear la tarea sin backend nuevo si no se exige vínculo formal con la mejora.
- Hay que revisar el esquema real de `tasks` antes de decidir si añadir `source_request_id` o similar. Por la regla de cuidado con base de datos, cualquier migración debe ser pequeña, reversible y documentada antes de ejecutarse.
- Conviene crear una función pura para convertir título de mejora en título natural de tarea:
  - Quitar prefijos convencionales: `add:`, `fix:`, `feat:`, `bug:`, `hotfix:`, `update:`, `mejora:`, `arreglar:`.
  - Limpiar corchetes/tags iniciales si existen (`[App]`, `[BUG]`) solo cuando sean metadatos evidentes.
  - Colapsar espacios, conservar mayúsculas significativas y no inventar contenido.
  - Si el resultado queda vacío o ambiguo, usar el título original limpio.
- Si se crea vínculo con la mejora, el Executor debe confirmar permisos: solo crear tareas para mejoras visibles/editables por el usuario y preservar `company_id`/`app_id` correctos.
- El flujo debe funcionar tanto desde card/listado como desde modal de detalle si ambos muestran acciones de mejora.
- Hay que versionar assets (`?v=`) y revisar `sw.js` si se toca JS/CSS cacheado por PWA.

## High-level Task Breakdown (crear tarea desde mejora)

### FASE CTM.1 — Reconocimiento técnico y decisión de vínculo
- [ ] Revisar estructura de `requests`, `tasks`, render de acciones de mejora y endpoints actuales.
  - Éxito: queda claro si la tarea puede crearse con `api/tasks.php` actual o si hace falta migración para enlazar `tasks.source_request_id`.
- [ ] Si hace falta migración, documentar SQL mínimo y pedir confirmación antes de ejecutarla.
  - Éxito: no se toca base de datos sin una decisión explícita y entendida.

### FASE CTM.2 — Normalización determinista del título
- [ ] Añadir una función pura de normalización de título de mejora a tarea, preferiblemente en un archivo JS existente o helper pequeño reutilizable.
  - Éxito: casos como `Add: filtros por fecha`, `fix: error botón móvil`, `[BUG] Login no responde` producen títulos naturales y conservan el sentido.
- [ ] Crear/verificar pruebas manuales o harness local para la función.
  - Éxito: el Executor puede mostrar una tabla de entradas/salidas y validar que no destruye títulos normales.

### FASE CTM.3 — UI de creación desde mejora
- [ ] Añadir botón "Crear tarea" en las acciones de mejora siguiendo el patrón visual actual.
  - Éxito: aparece en card/listado o modal donde tenga más sentido, no rompe responsive y no compite con acciones críticas.
- [ ] Añadir modal compacto con título propuesto editable y selector de fecha.
  - Éxito: el usuario solo necesita escoger fecha y confirmar; puede corregir el título si quiere.
- [ ] Implementar estados de loading/error/success y bloqueo de doble envío.
  - Éxito: errores de API se ven en el modal; éxito cierra modal y muestra toast claro.

### FASE CTM.4 — Creación y verificación end-to-end
- [ ] Conectar submit a `api/tasks.php` o endpoint específico si se decide enlazar formalmente.
  - Éxito: la tarea se crea con `title`, `due_date`, `app_id` de la mejora y descripción/contexto mínimo si procede.
- [ ] Verificar con lint/syntax checks y preview manual.
  - Éxito: `node --check`/`php -l` pasan, no hay errores de consola, y la tarea aparece en `tasks.php` bajo la fecha elegida.
- [ ] Versionar assets y documentar archivos tocados.
  - Éxito: PWA/browser no sirve JS/CSS antiguo tras despliegue.

## Project Status Board (crear tarea desde mejora)
- [x] CTM.1 Reconocimiento técnico y decisión de vínculo.
- [x] CTM.2 Normalización determinista del título.
- [x] CTM.3 UI de creación desde mejora.
- [x] CTM.4 Creación y verificación end-to-end.

## Executor's Feedback or Assistance Requests (crear tarea desde mejora)
- Planner recomienda empezar sin IA. La IA solo debería considerarse si, tras probar la normalización determinista, los títulos reales siguen siendo poco naturales.
- Antes de cualquier cambio de base de datos, el Executor debe revisar el esquema y pedir confirmación si la implementación requiere migración o nuevo campo de relación.
- Al trabajar UI, usar el skill `design-taste-frontend` con criterio conservador para esta app: microinteracciones y estados sí; dependencias nuevas o motion avanzado no, salvo justificación clara.

Actualización Executor (2026-06-23, CTM.1):
- Revisado `api/tasks.php`: el POST actual permite crear tareas con `title`, `description`, `app_id`, `due_date` e `is_shared`. No hace falta backend nuevo para una primera versión.
- Revisado `api/requests.php`: el GET devuelve `id`, `title`, `description`, `app_id`, `app_name`, permisos por apps visibles y metadatos suficientes para alimentar el modal.
- Revisado `assets/js/main.js`: las acciones de mejora se renderizan en `renderRequestsTable()` y `createRequestCard()`. El punto natural para el botón es el cluster de acciones de la card y, opcionalmente, el footer del modal de edición.
- Revisado `index.php`: el modal de edición ya tiene footer con acciones; se puede añadir un modal compacto separado para "Crear tarea desde mejora".
- Revisado `schema.sql` y migraciones: no hay tabla `tasks` documentada en `schema.sql` ni relación formal `tasks.source_request_id` / `tasks.request_id`. La API presupone tablas de tareas existentes en producción, pero el esquema versionado está incompleto.
- Decisión técnica recomendada: implementar la primera versión sin migración, creando la tarea con `app_id` de la mejora y `description` contextual tipo "Creada desde mejora #ID: título". Esto evita tocar base de datos y cumple el flujo principal.
- Si el usuario quiere trazabilidad fuerte bidireccional más adelante, planificar una migración separada para `tasks.source_request_id` con FK a `requests(id)`, índice y actualización de GET para exponer la relación. No ejecutar esa migración sin confirmación explícita.

Actualización Executor (2026-06-23, CTM.2):
- Añadida `normalizeRequestTitleForTask(title)` en `assets/js/task-parser.js`, expuesta en `window` junto al parser existente.
- La función no usa IA ni dependencias: elimina tags iniciales (`[BUG]`, `[Puri]`), prefijos técnicos (`Add:`, `fix:`, `feat(auth):`, `Actualizar:`, `UI -`, `hotfix |`) y limpia espacios/separadores sin inventar contenido.
- Verificación ejecutada: `node --check assets/js/task-parser.js`, tabla de 10 casos entrada/salida vía Node y `git diff --check`, todo correcto.

Actualización Executor (2026-06-23, CTM.3 + CTM.4):
- Añadido botón "Crear tarea" en acciones de mejora tanto en tabla como en cards (`assets/js/main.js`), usando `iconoir-task-list` y estilos específicos en `assets/css/styles.css`.
- Añadido modal compacto en `index.php` con contexto de la mejora, título editable normalizado y fecha requerida. El foco entra directamente en la fecha para mantener el flujo rápido.
- Conectado `submitTaskFromRequest()` a `api/tasks.php` sin migración: envía `title`, `due_date`, `app_id` y `description` con contexto "Creada desde mejora #ID".
- Estados cubiertos: validación inline, loading/deshabilitado, error inline, toast de éxito y refresco del widget "Qué toca" si está disponible.
- Versionado: `styles.css?v=3.7`, `task-parser.js?v=3`, `main.js?v=3.7`, PWA `sw.js` cache v11.
- Verificación Executor: `node --check assets/js/main.js`, `node --check assets/js/task-parser.js`, `php -l index.php` y `git diff --check`, todo correcto.
- Pendiente: verificación manual en dashboard autenticado para confirmar que la tarea aparece en `tasks.php` con la fecha elegida y la app de la mejora.

## High-level Task Breakdown (Visor de archivos in-app)

Planner (2026-06-25): los adjuntos (mejoras, tareas, archivos de app) se enlazan directos a `/uploads/...` con `target="_blank"`. En la PWA `display: standalone` eso expulsa el archivo a una ventana suelta del navegador y lo descarga con el nombre físico aleatorio (`uniqid()_time.ext`). Objetivo: visor in-app + nombres correctos, sin salir de la PWA.

- VF.1 Endpoint seguro `api/file.php?type=request|task|appfile&id=&download=0|1`. Verifica permiso por tipo (request: login; task: dueño/compartida/share; appfile: `can_access_app`). Sirve con `Content-Type`, `Content-Disposition: inline|attachment; filename*=UTF-8''<original>`, `Content-Length`, `X-Content-Type-Options: nosniff`. Criterio: PDF/imagen se ven inline; resto descarga con nombre real; sin permiso → 403/404.
- VF.2 Visor compartido `assets/js/file-viewer.js` (global `openFileViewer(type,id,name,mime)`). Imagen/PDF → modal overlay in-app (header con nombre, botones Descargar/Abrir/Cerrar, body img/iframe). Resto → descarga directa con nombre correcto. Cargado en `index.php` y `tasks.php`.
- VF.3 CSS `.fv-*` en `styles.css` (tokens existentes, sin inline, responsive + Escape para cerrar).
- VF.4 Reescribir 3 puntos de render para usar `openFileViewer` en lugar de `<a target="_blank">`: `main.js:2146` (mejoras), `main.js:2879` (app-files), `tasks.js:577` (tareas).
- VF.5 Versionar assets (`?v=`) y bump cache `sw.js`. Verificar `php -l`, `node --check`, y manual en preview.

## Project Status Board (Visor de archivos in-app)
- [x] VF.1 Endpoint `api/file.php`
- [x] VF.2 Visor `file-viewer.js`
- [x] VF.3 CSS `.fv-*`
- [x] VF.4 Render points → openFileViewer
- [x] VF.5 Versionado + verificación

## Executor's Feedback or Assistance Requests (Visor de archivos in-app)
Actualización Executor (2026-06-25):
- Creado `api/file.php` (sirve con `Content-Disposition` + nombre real, confina ruta dentro de `/uploads/`, permisos por tipo: request=login, task=dueño/compartida/share, appfile=`can_access_app`).
- Creado `assets/js/file-viewer.js`: overlay in-app para imagen/PDF, descarga directa para el resto. Delegación de eventos con `.fv-trigger` + `data-*` y lectura del nombre desde el DOM (sin problemas de escapado).
- CSS `.fv-*` en `styles.css` (z-index 1100 para quedar sobre el modal de edición de mejoras; responsive full-screen en móvil).
- Reescritos los 3 enlaces `target="_blank"`: mejoras (`main.js`), app-files (`main.js`), tareas (`tasks.js`).
- Versionado: `styles.css?v=3.8`, `main.js?v=3.8`, `tasks.js?v=5`, `file-viewer.js?v=1`, cache SW `prisma-v12`.
- Verificación: `php -l`, `node --check` y `git diff --check` OK. En preview: endpoint sin sesión redirige a login (permiso OK); overlay abre/cierra (Escape + backdrop), título con comillas/`&` correcto, z-index 1100, body se limpia al cerrar.
- Pendiente verificación manual: David, en sesión autenticada, abrir un adjunto de imagen/PDF (se ve in-app) y uno de Word/Excel/ZIP (se descarga con nombre real, sin ventana en blanco).

## Lessons (Visor de archivos in-app)
- En PWA `display: standalone`, `target="_blank"` expulsa el recurso a una ventana suelta del navegador. Para previsualizar sin salir de la app, servir el archivo desde un endpoint propio y mostrarlo en un overlay (img/iframe).
- El SW manda `/api/` a network-only, así que el endpoint de archivos no se cachea (correcto para permisos).

## Background and Motivation (colaboración de usuarios en peticiones)

El usuario informa de que el rol `user` puede votar peticiones, pero en la vista habitual no puede entrar en su detalle, abrir documentos ni comentar. Solicita revisar el funcionamiento, definir una mejora y proceder en modo Executor.

Objetivo funcional: separar claramente **ver y colaborar** de **administrar/editar**. Un usuario con acceso a la app debe poder abrir una petición, leer su información y comentarios, descargar/ver sus adjuntos y publicar comentarios, sin obtener permisos para cambiar título, descripción, estado, prioridad, asignaciones, checklist ni datos del solicitante.

Restricción de seguridad: la apertura del detalle no debe desplegarse sin verificar primero que todos los endpoints asociados validan el acceso a la petición/app; la auditoría inicial ha detectado rutas autenticadas que actualmente operan por ID sin comprobar el scope multiempresa.

## Key Challenges and Analysis (colaboración de usuarios en peticiones)

### Diagnóstico funcional
- La card y la tabla solo ofrecen `openEditRequestModal()` dentro del bloque `canEdit`. Para el rol `user`, votar es prácticamente la única acción disponible aunque la petición pertenezca a una app que puede ver.
- El detalle actual mezcla en un mismo modal tres capacidades distintas: **consultar**, **colaborar** y **administrar**. El nombre, estructura y handlers están diseñados como edición; no existe un modo explícito de solo lectura.
- Comentarios y adjuntos ya están renderizados dentro de ese modal. Por tanto, el camino más simple es introducir una apertura de detalle para todos los usuarios con acceso y hacer que cada bloque respete capacidades, sin duplicar toda la experiencia en un segundo modal.

### Modelo de permisos recomendado
- **Puede ver la petición**: usuario autenticado con `can_access_app(request.app_id)`.
- **Puede colaborar**: quien puede ver la petición puede leer comentarios, publicar comentarios y abrir/descargar adjuntos. Puede editar/eliminar únicamente sus propios comentarios. En esta primera versión no puede subir ni eliminar adjuntos, modificar checklist ni crear tareas desde la petición salvo que ya tenga permiso de edición.
- **Puede administrar la petición**: se mantiene `can_edit_requests()` y, además, debe verificarse el acceso concreto a la app de la petición. Permite cambiar título, descripción, prioridad, dificultad, estado, asignaciones, checklist y adjuntos.
- **Puede eliminar**: se mantiene `can_delete_requests()`, siempre combinado con acceso a la app concreta.
- Los permisos deben decidirse en backend. Ocultar controles en JS es una mejora de UX, no una barrera de seguridad.

### Hallazgos de seguridad que bloquean la UI colaborativa
- `api/requests.php` sí limita el GET a apps accesibles para no-superadmin, incluso al filtrar por ID. Sin embargo, devuelve `r.*`, lo que incluye `requester_name` y `requester_email`; esos datos no deben llegar al rol `user` en el detalle colaborativo.
- `api/comments.php` valida login, pero GET y POST aceptan `request_id` sin comprobar que la petición pertenece a una app accesible. PUT y DELETE comprueban autoría/rol, pero tampoco el scope de la petición. Además, las menciones buscan usuarios globalmente; se debe limitar a usuarios que compartan acceso relevante para no filtrar identidades entre empresas ni crear notificaciones cruzadas.
- `api/attachments.php` lista adjuntos solo por `request_id`; DELETE valida rol/subidor, pero no acceso a la app de la petición.
- `api/file.php?type=request` sirve cualquier adjunto por ID a cualquier usuario autenticado. El comentario del código confirma que replica el comportamiento inseguro de `attachments.php`.
- `api/upload.php` solo comprueba que la petición existe. Un usuario autenticado puede intentar subir un archivo a una petición de otra empresa mediante un ID conocido y tampoco se exige actualmente permiso de edición.
- `api/assignments.php` GET opera por `request_id` sin scope. Aunque la UI de solo lectura no necesita consultar este endpoint porque `requests.php` ya aporta asignaciones, debe cerrarse para evitar enumeración directa.
- Antes de ampliar la colaboración conviene revisar también `api/votes.php`, notificaciones con `request_id` y cualquier deep-link `?request=` para que todos reutilicen la misma regla de acceso.

### Decisiones de alcance para la primera entrega
- No crear roles nuevos ni migraciones de base de datos: la capacidad se deriva de acceso a app + rol actual.
- No permitir que `user` cambie campos, asignaciones, checklist o adjuntos en esta primera versión.
- Sí permitir lectura del detalle, comentarios propios y visualización/descarga segura de documentos.
- Presentar un encabezado neutral como **Detalle de petición**. Para usuarios con edición, el mismo componente habilita controles de administración; para `user`, muestra valores de solo lectura y oculta datos del solicitante y acciones mutables.
- Mantener comentarios internos en esta fase. Si en el futuro participa un solicitante externo, habrá que separar comentarios públicos de notas internas antes de abrir ese portal.

### Riesgos y verificaciones esenciales
- **IDOR multiempresa**: probar con dos empresas y IDs conocidos que ningún endpoint devuelve, modifica o sirve información cruzada.
- **PII**: comprobar en la respuesta HTTP, no solo en el DOM, que `requester_email`/`requester_name` no llegan a roles sin permiso.
- **Menciones**: evitar que autocomplete o parsing permita descubrir/notificar usuarios ajenos al scope de la petición.
- **Regresión de administradores/programadores**: el nuevo modo no debe romper edición, checklist, subida de adjuntos, asignación ni acciones existentes.
- **PWA**: al modificar JS/CSS, actualizar versiones `?v=` y la caché de `sw.js`.

## High-level Task Breakdown (colaboración de usuarios en peticiones)

### FASE CUP.1 — Guardia de acceso reutilizable y contrato de capacidades
- [x] **CUP.1.1** Crear un helper backend que resuelva una petición por ID y compruebe `can_access_app(app_id)` antes de devolverla o actuar sobre ella. **VALIDADO por el usuario al autorizar CUP.2.**
  - **Criterio verificable**: petición inexistente o de app ajena produce 404/403 de forma consistente; petición accesible continúa.
- [x] **CUP.1.2** Definir helpers/capacidades separadas para `view`, `comment`, `edit` y `delete`, sin depender del estado visual del frontend. **VALIDADO por el usuario al autorizar CUP.2.**
  - **Criterio verificable**: una matriz de roles demuestra que `user` puede ver/comentar, pero no editar; `programador` conserva CRU; admin/superadmin conservan sus permisos.
- [x] **CUP.1.3** Añadir pruebas o harness HTTP con dos empresas, al menos una app por empresa y usuarios con scopes distintos. **VALIDADO por el usuario al autorizar CUP.2.**
  - **Criterio verificable**: los casos permitidos devuelven 2xx y todos los accesos cruzados por ID devuelven 403/404.

### FASE CUP.2 — Cierre de endpoints del detalle (bloqueante antes de UI)
- [x] **CUP.2.1** Aplicar la guardia a GET/POST/PUT/DELETE de `api/comments.php`. **ACEPTADO para validación conjunta con CUP.3 por indicación del usuario.**
  - **Criterio verificable**: no se pueden leer ni escribir comentarios de una petición fuera del scope; un usuario puede modificar/eliminar solo su comentario dentro del scope.
- [x] **CUP.2.2** Limitar menciones y autocomplete a usuarios compatibles con la empresa/app de la petición. **ACEPTADO para validación conjunta con CUP.3 por indicación del usuario.**
  - **Criterio verificable**: un usuario de Empresa A no aparece ni recibe una mención originada en una petición exclusiva de Empresa B.
- [x] **CUP.2.3** Aplicar la guardia a `api/attachments.php`, `api/file.php?type=request` y `api/upload.php`; exigir permiso de edición para subir/eliminar en esta primera versión. **ACEPTADO para validación conjunta con CUP.3 por indicación del usuario.**
  - **Criterio verificable**: un archivo de otra empresa no se lista ni se sirve conociendo su ID; `user` puede abrir/descargar un archivo accesible pero no subirlo ni eliminarlo.
- [x] **CUP.2.4** Aplicar la guardia al GET/POST de asignaciones y auditar votos, notificaciones y deep-links relacionados con peticiones. **ACEPTADO para validación conjunta con CUP.3 por indicación del usuario.**
  - **Criterio verificable**: ningún endpoint asociado al detalle acepta un `request_id` ajeno; votar sigue funcionando solo en peticiones visibles.
- [x] **CUP.2.5** Dejar de exponer `r.*` indiscriminadamente y construir una respuesta segura según capacidades, excluyendo datos del solicitante para `user`. **ACEPTADO para validación conjunta con CUP.3 por indicación del usuario.**
  - **Criterio verificable**: la respuesta JSON del detalle para `user` no contiene email/nombre privado del solicitante; perfiles autorizados conservan los campos necesarios.

### FASE CUP.3 — Detalle de petición en modo lectura/colaboración
- [ ] **CUP.3.1** Sustituir la acción exclusiva “Editar” por una acción neutral “Ver detalle” disponible en card y tabla para todo usuario con acceso. **IMPLEMENTADO, pendiente de prueba visual del usuario.**
  - **Criterio verificable**: un `user` abre una petición desde ambas vistas en un clic; el voto sigue funcionando sin abrir el detalle accidentalmente.
- [ ] **CUP.3.2** Adaptar el modal existente a dos modos explícitos: lectura/colaboración y edición, usando capacidades recibidas o derivadas de forma fiable. **IMPLEMENTADO, pendiente de prueba visual del usuario.**
  - **Criterio verificable**: `user` ve título, descripción, app, estado, prioridad, dificultad, responsables, actividad y checklist sin controles mutables; programador/admin conservan edición.
- [ ] **CUP.3.3** Ocultar para `user` solicitante, subida/eliminación de adjuntos, edición de checklist, asignaciones, crear tarea y submit de edición. **IMPLEMENTADO, pendiente de prueba visual del usuario.**
  - **Criterio verificable**: inspección visual y de teclado no muestra ni permite activar controles administrativos; llamadas manuales a API siguen bloqueadas por CUP.2.
- [ ] **CUP.3.4** Mantener los adjuntos accesibles mediante el visor in-app ya existente. **IMPLEMENTADO, pendiente de prueba con archivos reales del usuario.**
  - **Criterio verificable**: imagen/PDF se abre dentro de Prisma y otros formatos se descargan con su nombre real; archivo ajeno devuelve 403/404.
- [ ] **CUP.3.5** Permitir que `user` gestione subtareas dentro de una mejora visible mediante una capacidad `checklist` independiente de editar la petición. **IMPLEMENTADO, pendiente de prueba visual del usuario.**
  - **Criterio verificable**: `user` puede crear, completar, renombrar y eliminar subtareas compartidas; sigue sin poder editar título, descripción, solicitante, responsables, adjuntos ni otros metadatos de la mejora.
  - **Requisito actualizado por el usuario**: las restricciones de checklist descritas en CUP.3.2 y CUP.3.3 quedan sustituidas por esta excepción colaborativa explícita.
- [ ] **CUP.3.6** Simplificar la Vista Global de `user` y priorizar sus mejoras asignadas y actividad. **IMPLEMENTADO, pendiente de prueba visual del usuario.**
  - **Criterio verificable**: filtros visibles por defecto, acceso textual a `Ver detalle`, métricas personales y estado vacío compacto; los controles flotantes de gestión se conservan para roles distintos de `user`.

### FASE CUP.4 — Comentarios para usuarios
- [ ] **CUP.4.1** Habilitar lectura y publicación de comentarios en el modo colaborativo, con error inline, loading y prevención de doble envío.
  - **Criterio verificable**: `user` publica un comentario y aparece sin recargar; un fallo de red no duplica ni pierde silenciosamente el texto.
- [ ] **CUP.4.2** Mantener edición/eliminación solo de comentarios propios y privilegio admin donde ya aplique.
  - **Criterio verificable**: `user A` no ve ni ejecuta acciones sobre el comentario de `user B`; sí puede operar sobre el suyo.
- [ ] **CUP.4.3** Verificar notificaciones de comentario/mención dentro del scope permitido.
  - **Criterio verificable**: asignados y mencionados válidos reciben una sola notificación; usuarios ajenos no reciben ninguna.
- [ ] **CUP.4.4** Notificar internamente cuando una mejora cambia a completada al creador interno y a quienes realizaron las asignaciones vigentes. **IMPLEMENTADO, pendiente de prueba visual del usuario.**
  - **Criterio verificable**: se excluye a quien completa, cada destinatario recibe un único aviso y repetir un guardado sin transición de estado no crea duplicados.

### FASE CUP.5 — QA, regresión y entrega
- [ ] **CUP.5.1** Ejecutar lint/syntax checks de todos los PHP/JS tocados y `git diff --check`.
  - **Criterio verificable**: todos los comandos terminan sin error.
- [ ] **CUP.5.2** Ejecutar la matriz manual con `user`, `programador`, `admin` y, si existe el caso, usuario con permiso específico de una sola app.
  - **Criterio verificable**: cada rol obtiene exactamente las capacidades documentadas y no hay acceso entre empresas.
- [ ] **CUP.5.3** Versionar assets y caché PWA, y documentar archivos/endpoints modificados.
  - **Criterio verificable**: un refresh/despliegue carga la nueva UI sin necesitar limpiar caché manualmente.

## Project Status Board (colaboración de usuarios en peticiones)
- [x] **CUP.1** Guardia reutilizable + matriz multiempresa
- [x] **CUP.2** Endpoints del detalle asegurados (bloqueante)
- [ ] **CUP.3** Detalle accesible en modo lectura/colaboración
- [ ] **CUP.4** Comentarios habilitados para `user`
- [ ] **CUP.5** QA de roles, regresión y PWA

## Executor's Feedback or Assistance Requests (colaboración de usuarios en peticiones)
- El usuario ha solicitado modo Executor, pero según la convención del proyecto se debe ejecutar **un solo bloque del Project Status Board cada vez**. El siguiente y único bloque autorizado recomendado es **CUP.1**. Tras completarlo, registrar pruebas y pedir validación antes de marcarlo como verificado o empezar CUP.2.
- **No implementar todavía la apertura del detalle para `user`**. Primero deben completarse CUP.1 y CUP.2; de lo contrario la UI haría explotables accesos por ID ya observados.
- No hace falta migración de base de datos para la primera versión. Si durante la implementación aparece una necesidad de schema, documentarla y pedir confirmación antes de ejecutarla.
- Empezar TDD con casos de autorización multiempresa. La prueba crítica no es solo que el rol correcto pueda entrar, sino que un usuario autenticado no pueda cambiar el ID para leer/comentar/descargar sobre otra empresa.
- Mantener CSS en `assets/css/styles.css`, sin inline nuevo. Reutilizar el visor `file-viewer.js` y el modal existente para minimizar superficie y regresiones.
- Añadir salida de diagnóstico útil en el entorno de prueba, pero no devolver trazas SQL, rutas físicas ni datos sensibles en errores de producción.
- Si el servidor muestra vulnerabilidades de dependencias en terminal, ejecutar `npm audit` antes de continuar y registrar el resultado en Lessons.
- Al terminar cada bloque, actualizar esta sección y el Project Status Board; el Executor no debe anunciar la finalización total, sino solicitar revisión del Planner/usuario.

Actualización Executor (2026-07-13 — reconocimiento previo a CUP.1):
- Auditoría frontend y backend completada sin modificar código de producto. La causa visible está confirmada: en cards, el voto se renderiza para todos, pero el único acceso a `openEditRequestModal()` está dentro del bloque `canEdit`; en tabla la fila sí abre el modal, creando una inconsistencia.
- El backend ya permite técnicamente leer/escribir comentarios y listar/servir adjuntos con sesión, pero varias rutas no validan que el `request_id` pertenezca a una app accesible. Abrir la UI ahora ampliaría un riesgo IDOR multiempresa.
- Detectada también una inconsistencia entre `get_user_apps()` y `can_access_app()`: si existen permisos explícitos pero todos tienen `can_view=0`, el primero puede aplicar el fallback de empresa y devolver todas sus apps.
- Siguiente bloque recomendado y no iniciado: **CUP.1**, con TDD/harness de dos empresas antes de cambiar helpers.
- Bloqueo de proceso: las instrucciones del proyecto exigen solicitar `@web` antes de desarrollar. No hay una API externa nueva en esta fase; la consulta se limitaría a documentación vigente de seguridad/autorización aplicable a PHP y se documentaría la referencia pertinente antes de tocar código.

Actualización Executor (2026-07-13 — CUP.1 implementado, pendiente de verificación):
- Añadido en `includes/auth.php` el contrato puro `request_capabilities_for_role()` y capacidades separadas `view`, `comment`, `edit`, `delete`. Todas quedan anuladas fuera del scope de la app, incluso para `admin`; `superadmin` conserva acceso porque `can_access_app()` ya lo resuelve para apps existentes.
- Añadidos `get_request_access_context()`, `get_request_capabilities()`, `can_view_request()`, `can_comment_request()`, `can_edit_request()`, `can_delete_request()` y `require_request_capability()`. La guardia resuelve primero la petición padre y su `app_id`/`company_id`.
- Creado `tests/request-access-capabilities.php`. Usa SQLite en memoria; no lee ni modifica la base real. Simula dos empresas/apps y comprueba que conocer el ID de la otra empresa no concede ninguna capacidad.
- Verificación Executor: 17 casos `PASS`, `php -l` correcto en helper y test, y `git diff --check` sin errores.
- No se ha aplicado todavía la guardia a endpoints ni se ha cambiado la UI. Eso corresponde a CUP.2 y CUP.3 respectivamente.
- Pendiente de confirmación del usuario: ejecutar `php tests/request-access-capabilities.php` y confirmar que finaliza con `Authorization matrix completed successfully.` antes de marcar CUP.1 como completado y comenzar CUP.2.

Actualización Executor (2026-07-13 — CUP.2 implementado, pendiente de verificación):
- Aplicada `require_request_capability()` a comentarios, adjuntos, visor/descarga, subida, asignaciones, votos y CRUD de peticiones. Lectura/comentario requiere scope visible; subida, borrado de adjuntos y mutaciones requieren edición; eliminar petición requiere capacidad delete.
- Las menciones y su autocomplete se filtran por la app concreta. La lista global de usuarios queda restringida a roles con edición. También se filtran notificaciones a asignados que conserven acceso.
- Asignaciones rechaza IDs de usuarios ajenos a la empresa/app. Notificaciones antiguas se filtran por acceso vigente y el contador usa el mismo scope. Los deep-links vuelven a pasar por el GET protegido de la petición; no se encontró bypass.
- Corregido `get_user_apps()`: tener permisos explícitos con todos los `can_view=0` ya no activa por error el fallback a todas las apps de la empresa.
- La respuesta de `api/requests.php` incluye capacidades y aplica `sanitize_request_for_capabilities()` antes del JSON: para `user` omite `requester_name` y `requester_email`, y no filtra el nombre externo mediante aliases de creador. Editores conservan esos campos.
- Añadido `tests/request-endpoint-guards.php` y ampliada la matriz aislada. Resultado Executor: 24 comprobaciones de capacidades/scope/PII y 9 endpoints/contratos pasan; `php -l`, `node --check` y `git diff --check` correctos.
- Versionado: `main.js?v=3.9`; caché PWA `prisma-v13`. No hay migraciones ni escrituras sobre la base de datos.
- Pendiente de verificación del usuario: ejecutar `php tests/request-access-capabilities.php` y `php tests/request-endpoint-guards.php`. Tras confirmación, marcar CUP.2 y comenzar CUP.3; la UI todavía no se ha abierto a `user`.

Actualización Executor (2026-07-13 — CUP.3 implementado, pendiente de prueba visual conjunta):
- El usuario no dispone de SSH y pidió completar CUP.3 para validar CUP.2+CUP.3 directamente desde la app. CUP.2 queda aceptado para esa validación conjunta.
- Cards y tabla muestran una acción neutral `Ver detalle` para todo usuario con capacidad `view`; el título de la card también abre la petición. Los gestores conservan crear tarea, cambios rápidos y eliminación según sus capacidades.
- El modal compartido usa `configureRequestModalMode(request)` con las capacidades devueltas por backend. Para `user` muestra `Detalle de petición` + `Solo lectura`, título/descripcion readonly y oculta Guardar, solicitante, subida/borrado de adjuntos, alta/edición de checklist y controles administrativos.
- Checklist permanece legible con checks deshabilitados; adjuntos permanecen abribles en el visor in-app; comentarios siguen visibles y publicables mediante el flujo existente. Para programador/admin el mismo modal conserva edición.
- Añadidos estilos específicos en `assets/css/styles.css`, sin CSS inline nuevo. La revisión visual detectó que una regla tardía restauraba dos columnas en móvil; se añadió un override final a una columna, `100dvh` y sin overflow horizontal.
- Añadido `tests/request-detail-ui.php`. Resultado Executor: contrato UI 11/11, matriz permisos/PII 24/24, guardias 9/9, todos los `php -l`, `node --check` y `git diff --check` correctos.
- Versionado: `styles.css?v=3.9`, `main.js?v=4.0`, caché PWA `prisma-v14`. No hay migraciones ni cambios sobre la base de datos.
- Pendiente usuario: probar con rol `user` card/tabla, modo solo lectura, visor de documento y publicación de comentario; después probar con programador/admin que la edición sigue disponible. No marcar CUP.3 hasta recibir esa confirmación.

Actualización Executor (2026-07-13 — CUP.3.5 subtareas colaborativas implementadas, pendiente de prueba visual):
- Añadida la capacidad backend independiente `checklist`. Todo rol con acceso visible a la mejora puede gestionar su checklist sin recibir permiso para editar o eliminar la petición.
- `api/request-checklist.php` valida el scope de la petición padre en GET/POST/PUT/DELETE, también cuando la operación parte del ID de una subtarea. Título obligatorio y máximo de 500 caracteres. No hay migración ni escritura de estructura sobre la base de datos.
- El modal muestra la checklist como área colaborativa: `user` puede crear, completar, renombrar y eliminar subtareas compartidas, mientras los campos administrativos siguen en lectura. Se sustituyó la etiqueta confusa `Solo lectura` por `Colaboración`.
- El alta de subtarea incluye error inline, estado `Añadiendo…`, bloqueo de doble envío y restauración del foco. Todos los estilos nuevos están en `assets/css/styles.css`.
- TDD/validación Executor: matriz de capacidades 24/24, contratos de guardia 10/10 y contrato UI 12/12; `php -l`, `node --check` para JS y service worker, y `git diff --check` finalizan correctamente.
- Versionado: `styles.css?v=4.0`, `main.js?v=4.1`, caché PWA `prisma-v15`. No se necesita SSH para la comprobación funcional desde la app.
- Pendiente usuario: con rol `user`, abrir una mejora y probar alta, completar, renombrar y eliminar una subtarea; confirmar después que Guardar, subida de archivos y metadatos administrativos siguen sin aparecer. CUP.3 permanece sin marcar hasta esa validación.

Actualización Executor (2026-07-13 — CUP.4.4 notificación de mejora completada implementada, pendiente de prueba visual):
- Al producirse una transición real a `completed`, se crea una notificación interna para el creador de la mejora y para el último usuario que asignó a cada persona que continúa asignada. El actor que completa queda excluido y los destinatarios se deduplican.
- No se ha añadido ninguna columna ni migración: el asignador vigente se resuelve a partir del historial de notificaciones de asignación ya existente, cruzado con `request_assignments` actuales.
- El email existente al solicitante externo se mantiene sin cambios funcionales y sigue siendo independiente de estos avisos internos.
- La bandeja incorpora el filtro `Completadas` y un icono específico. Versionado: `sidebar.js?v=1.1` y caché PWA `prisma-v16`.
- TDD/validación Executor: nueva matriz aislada de destinatarios y payload 4/4, contratos de endpoints/UI, matriz de capacidades, `php -l`, `node --check` y `git diff --check` correctos.
- Pendiente usuario: asignar una mejora desde la cuenta A, completarla desde otra cuenta con permiso de edición y comprobar que A recibe un único aviso que abre el detalle. CUP.4 no se marca completo hasta esa validación.

Actualización Executor (2026-07-13 — CUP.3.6 optimización UX de Vista Global para `user`, pendiente de prueba visual):
- Los filtros quedan abiertos permanentemente y se elimina el toggle `Más filtros`; `Mis asignadas` permanece visible. El filtro `Completadas` revela directamente las tarjetas completadas.
- El bloque `Qué toca` se contrae a un mensaje `Todo al día` cuando sus tres contadores están a cero. Cuando hay tareas conserva el desglose por vencidas, hoy y próximos siete días.
- Para `user`, la búsqueda deja de prometer datos del solicitante y el resumen muestra `Asignadas a mí`, `Con actividad nueva`, `En curso` y `Completadas recientes` (últimos 30 días). La actividad nueva deriva de notificaciones no leídas ya autorizadas.
- Las cards no reinterpretan la descripción: textos como `Adjunto PDF` siguen siendo descripción. Se aclaran `Creada hace…`, `Creada por…`, `Responsable/Equipo` y se muestra un botón textual `Ver detalle` para perfiles sin edición.
- El CTA `Ver detalle` gana altura táctil de 34px, padding horizontal, separación respecto al voto, hover sobrio y foco visible de teclado.
- La terminología visible se unifica en `En curso` y `Completadas`. La tarea rápida flotante se oculta solo para `user`; programador/admin/superadmin la conservan. La instalación PWA de `user` se mueve al menú de perfil, mientras los demás roles conservan el botón flotante.
- TDD/validación Executor: contrato UX 26/26, contratos previos de detalle/endpoints, matriz de capacidades y notificaciones, `php -l`, `node --check` y `git diff --check` correctos.
- Versionado: `styles.css?v=4.2`, `main.js?v=4.2`, `sidebar.js?v=1.2`, `pwa.js?v=1.1`, caché PWA `prisma-v18`. Sin migraciones ni cambios de permisos.
- Pendiente usuario: recargar con rol `user` y validar densidad, filtros, `Mis asignadas`, apertura de detalle y ausencia de overlays; después comprobar con admin/superadmin que la tarea rápida y el instalador flotante continúan visibles. CUP.3 permanece sin marcar hasta esa validación.

## Lessons (colaboración de usuarios en peticiones)
- Autenticación no equivale a autorización: todo endpoint que acepte `request_id` o un ID de recurso hijo debe resolver la petición padre y validar su `app_id`.
- El acceso colaborativo debe modelarse como capacidad independiente de editar; reutilizar `can_edit_requests()` para decidir si se puede abrir un detalle bloquea usuarios legítimos y empuja a mezclar controles sensibles con contenido de lectura.
- Ocultar PII en el DOM no basta. Si un campo no está autorizado, debe omitirse en la respuesta del backend.
- El usuario autorizó explícitamente continuar esta mejora sin consulta `@web`; para este flujo interno de permisos no es necesario bloquear el Executor por documentación externa.
- Una checklist de equipo no debe reutilizar la capacidad general `edit`: una capacidad propia permite colaborar en subtareas sin ampliar privilegios sobre la mejora ni sus adjuntos.
- Mientras `request_assignments` no almacene `assigned_by`, el último evento de asignación de cada usuario aún asignado es la fuente más precisa para avisar al asignador sin introducir una migración.
- En una vista colaborativa, las métricas deben responder al trabajo del usuario (asignación y actividad no leída) y no a necesidades de gestión como `Sin asignar`; mantener variantes por rol evita empobrecer la vista administrativa.

---

## Análisis UX Notificaciones (Planner, 13 Julio 2026)

Revisión completa de la funcionalidad de notificaciones (panel inbox, badge, API, generación). Hallazgos documentados en conversación con el usuario. Puntos clave: panel sin ancho móvil (400px fijos), sin accesibilidad (divs clicables, sin Escape/focus), mensajes sin contexto (no incluyen snippet de comentario), el creador de una petición no recibe notificación de comentarios, sin agrupación temporal, badge sin tope 99+, filtro "Completadas" reutiliza color de comentarios, sin push/email interno. Pendiente de que el usuario priorice qué implementar.

### Executor: Rediseño notificaciones (13 Julio 2026) — COMPLETADO
- Backend: notificaciones de cambio de estado (tipo `status_change`, avisa a creador + asignadores), comentarios notifican también al creador, mensajes con snippet del comentario («…», 80 chars).
- Frontend: panel responsive (min(400px,100vw)), role=dialog + Escape + foco, agrupación Hoy/Ayer/Últimos 7 días/Anteriores, dot de no leída clicable (marcar leída individual), botón "Marcar todo" con toast, contador en chip No leídas, badge 99+, fechas absolutas >7d con tooltip, empty states por filtro, sin re-render si el usuario está leyendo (scroll), iconos con paleta de marca (completada verde, estado gris), reduced-motion.
- Filtro "Completadas" renombrado a "Estado" (completion + status_change).
- Versiones: styles.css?v=4.3 en todas las páginas, sidebar.js?v=1.3, sw.js prisma-v19.
- Tests: suite completa OK (nuevos asserts para status_change y snippet; contratos de guards actualizados).
- Verificado en navegador (desktop + móvil 375px) con datos seed en BD local.

#### Lessons
- El CLI mysql local necesita --default-character-set=utf8mb4 para insertar seeds con tildes/«» correctamente.
- Los tokens --text-muted y --bg-hover no existen en styles.css; usar --text-secondary/--text-light/--bg-secondary.
- tests/request-endpoint-guards.php contiene contratos de strings literales sobre sidebar.php/sidebar.js: actualizar al renombrar.
- Password local de admin (solo BD dev) establecida a devtest123 para poder verificar en navegador.

### Executor: Iteración 2 filtros + toggle no leída (13 Julio 2026) — COMPLETADO
- Filtros del inbox rediseñados: segmented control (Todas | No leídas con contador pill) + select compacto de tipo ("Todo tipo"). Sustituye los 6 chips de igual peso.
- Decisión de producto: NO se añade estado "resuelta"; en su lugar, toggle leída/no leída por item (patrón GitHub/Gmail): dot azul = pendiente; items leídos muestran dot hueco al hover para re-marcar como no leída. API PUT acepta {id, is_read: 0|1}.
- Versiones: styles.css?v=4.4, sidebar.js?v=1.4, sw prisma-v20. Suite de tests OK (contrato guards actualizado a value="status" + data-read-filter).

### Executor: Iteración 3 flujo de retorno + select (13 Julio 2026) — COMPLETADO
- Al abrir una mejora desde una notificación, el panel se reabre automáticamente cuando se cierra el modal (MutationObserver sobre la clase 'active' de #edit-request-modal, con timeout de seguridad de 5s si el modal nunca llega a abrirse). Permite revisar notificaciones en cadena sin reabrir el panel a mano.
- Select de tipos con afordance: pill con borde + chevron (appearance:none + icono iconoir-nav-arrow-down en wrapper).
- Versiones: styles.css?v=4.5, sidebar.js?v=1.5, sw prisma-v21. Tests OK.

### Executor: Capability update_status para rol user (13 Julio 2026) — COMPLETADO
- Nueva capability `update_status` en auth.php: true para todos los roles con acceso a la app (incluido user). `edit` sigue reservado a programador/admin/superadmin.
- api/requests.php PUT: si el payload solo trae id+status exige `update_status`; cualquier otro campo exige `edit` (verificado: user puede cambiar estado, 403 en prioridad y en mixto estado+prioridad).
- main.js: botones rápidos de estado en tarjeta y dropdown de estado en tabla gateados por update_status; dificultad/prioridad siguen con edit. Modal sin cambios (la sección estado está gateada por has_role('programador') en PHP).
- Tests actualizados (matriz de capacidades + contrato de guards). Versiones: main.js?v=4.3, sw prisma-v22.
- Usuario local de prueba: testuser / devtest123 (rol user).

## Modal de petición: scroll horizontal + dedup UX (14 Julio 2026) — COMPLETADO

### Background and Motivation
Al abrir una petición desde una notificación, el modal (`#edit-request-modal`) tenía scroll horizontal en escritorio y mostraba información repetida (comentarios duplicados en timeline, resumen redundante).

### Diagnóstico (verificado con harness preview-request-modal.html)
- Scroll horizontal: `.modal-body-grid` usaba `1fr 280px`; sin `minmax(0, 1fr)` el min-content de la columna (nombres de archivo largos con `white-space: nowrap`, URLs) ensanchaba el grid más que el modal (1023px en 855px).
- `.request-attachment-item` como hijo flex de `.attachments-grid` (flex-wrap) crecía más que su contenedor por el mismo motivo.
- Comentarios renderizados DOS veces: en `renderActivityTimeline` (timeline) y en `renderComments` (lista).
- Resumen lateral duplicaba Estado/Prioridad/Dificultad (selects editables arriba) y contadores (badges junto a cada sección).
- "Último toque"/"Responsable principal" duplicaban el último comentario y la sección Asignados.
- Píldora de ID (`#edit-request-id-display`) nunca se rellenaba: salía como píldora vacía junto al título.

### Cambios
- styles.css: `minmax(0, 1fr)` en `.modal-body-grid` (x2 definiciones), `min-width: 0` en columnas, `width:100%; min-width:0` en `.request-attachment-item`, `.modal-request-id:empty { display:none }`. Eliminado CSS muerto de timeline/activity-overview.
- index.php: eliminados bloque "Último toque/Responsable principal" y timeline. Resumen reducido a Creada por / Creada (fecha · antigüedad) / Última actividad; filas Estado/Prioridad/Dificultad solo para roles sin `has_role('programador')` (para ellos el resumen era la única vista de esos datos).
- main.js: eliminada `renderActivityTimeline`; `updateEditRequestSummary` simplificada; `renderComments` refresca el resumen; se rellena `#edit-request-id-display` con `#<id>`.
- Versiones: styles.css v4.6 (todas las páginas), main.js v4.4, sw.js prisma-v23.
- Nuevo harness: `preview-request-modal.html` (mock de /api/requests, comments, checklist, attachments, users-list). No subir a producción.

### Lessons
- El checklist del modal usa `item.title` (no `content`) en la API mock.
- `javascript_tool` justo tras `navigate` puede medir durante la animación `modal-pop`; repetir la medición.

### Executor: Pulido visual del modal de petición (14 Julio 2026) — COMPLETADO
Pase de jerarquía/ritmo sobre v2.4 (bloque "v2.5" al final de styles.css), verificado en preview-request-modal.html:
- Un solo nivel de uppercase en la columna lateral (títulos de sección); labels de campo a sentence case.
- Resumen como metadatos: filas de una línea, valores fs-13 medium (no bold grande), fecha compacta ("2 jul 2026 · hace 12 días").
- Dropzone de adjuntos compacta en una fila (clase .file-upload-area--compact, inline styles retirados de index.php).
- Lista de adjuntos sin caja gris contenedora (el borde lo lleva cada item); tamaño de archivo en una línea.
- Composer de comentarios: textarea 44px (el min-height 140px de .modal-column-main textarea lo pisaba) + botón de envío como icono 38px anclado abajo.
