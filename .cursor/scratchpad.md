# Scratchpad: Generación de enlaces específicos por aplicación

## Background and Motivation
El usuario desea poder generar un enlace de acceso directo para cada aplicación específica, en lugar de tener solo un enlace general por empresa. Esto facilitará que los usuarios externos o del equipo accedan directamente al contexto de una aplicación concreta.

## Key Challenges and Analysis
- **Estructura de la URL**: La aplicación ya soporta el parámetro `app_id` en `index.php`.
- **UI/UX**: Es necesario integrar de forma intuitiva la opción de "Copiar enlace" en las interfaces de gestión de aplicaciones.
- **Consistencia**: El enlace debe ser absoluto para que pueda ser compartido externamente.

## High-level Task Breakdown
1. **Investigación**: Localizar dónde se muestra o genera el enlace de la empresa actualmente.
2. **Lógica de Generación**: Crear una utilidad en JS para construir la URL base + el parámetro de la app.
3. **Interfaz Admin**: Añadir botón de copia en la tabla de aplicaciones de `admin.php`.
4. **Interfaz Usuario**: Añadir botón de copia en las tarjetas de aplicaciones de `manage-apps.php`.
5. **Validación**: Asegurar que el enlace redirige correctamente y mantiene la sesión/permisos si es necesario.

## Project Status Board
- [x] Investigar generación de enlace actual <!-- id: 0 -->
- [x] Implementar función para generar URL de app <!-- id: 1 -->
- [x] Añadir botón "Copiar enlace" en `admin.php` <!-- id: 2 -->
- [x] Añadir botón "Copiar enlace" en `manage-apps.php` <!-- id: 3 -->
- [x] Pruebas de funcionamiento <!-- id: 4 -->

## Executor's Feedback or Assistance Requests
- Procediendo con la investigación del enlace actual.

## Lessons
- (N/A)

