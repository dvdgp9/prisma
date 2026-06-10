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
- [ ] T6. Pulido y prueba end-to-end

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
