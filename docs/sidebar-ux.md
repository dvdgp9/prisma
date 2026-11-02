# Navegación lateral de Prisma

Revisión del 2 Nov 2026 con `design-taste-frontend`. El objetivo es que la barra explique dónde puedes ir, cómo se agrupan las aplicaciones y dónde está la gestión. Se conserva el stack PHP/JS/CSS, Geist, Iconoir y la paleta existente; no se añaden dependencias.

## Estructura

- **Espacio de trabajo:** Vista global, Tareas, Mejoras y Notas, en el orden anterior.
- **Aplicaciones:** búsqueda por aplicación o empresa, sin distinguir tildes; nombre de empresa como enlace a sus mejoras y botón independiente para plegar sus aplicaciones. Los grupos recuerdan su plegado durante la sesión de la pestaña, por usuario/empresa. Buscar expande coincidencias y limpiar restaura el plegado. Los enlaces reales permiten abrir destinos en otra pestaña.
- **Gestión:** Por aprobar, Release Planner, Changelog y administración con texto visible y respetando los permisos existentes. Los usuarios sin administración ven «Más en Prisma» con Changelog.
- **Cuenta:** permanece disponible al pie. El desplegable se comparte entre páginas; «Mi perfil» utiliza el modal existente o navega a Inicio para abrirlo cuando la página actual no dispone de él.

Una sola región desplazable evita el scroll anidado entre aplicaciones y navegación. Con pocos elementos, gestión ocupa el espacio inferior. En pantallas bajas o con listas largas, todo el menú sigue siendo alcanzable, mientras marca y cuenta permanecen visibles. Las empresas y las apps se dibujan con un único renderer en todas las páginas. El destino activo expone `aria-current`.

## Móvil y estados

La cabecera móvil abre un panel superpuesto con fondo oscurecido, sin desplazar el contenido. Objetivos táctiles de 44 px, foco contenido, fondo principal inerte, Escape y cierre al elegir un destino o pulsar fuera. Los atajos de la pantalla no abren editores detrás del menú; Ctrl/Cmd+J cierra el menú antes de abrir el asistente. El movimiento se limita a transform y respeta movimiento reducido.

La carga de aplicaciones tiene un esqueleto, estado vacío y error con reintento. La búsqueda muestra resultados o explica que no hay coincidencias. Reintentar lleva el foco al buscador. El contador de aprobaciones se comparte entre páginas y actualiza `hidden` al recibir datos.

## Verificación y publicación

43 scripts existentes, sintaxis PHP/JS y diff. Navegador con datos ficticios: escritorio 1440×900 y 1280×720; móvil 375×812; aplicación activa, navegación entre páginas, perfil desde Notas, plegado/búsqueda/restauración, búsqueda sin tildes por empresa, cero resultados, error/reintento y lista vacía. Dos empresas y doce aplicaciones con nombres largos verifican scroll y ausencia de desbordamiento horizontal. Cuenta de usuario sin administración conserva solo sus accesos. Tab circular, Escape, cierre al navegar y transición al asistente con atajo verificados. Sin nuevos errores de consola en la pasada final; el error inducido de red se comprobó por separado.

Capturas con datos ficticios fuera del repositorio: `prisma-sidebar-desktop.jpg` y `prisma-sidebar-mobile.jpg`.

Assets: `sidebar.css?v=1`, `sidebar.js?v=3`, `main.js?v=5.7`, `pending-approvals.js?v=2`. Caché PWA `prisma-v51` / `prisma-runtime-v39`. No requiere migraciones ni cambios de permisos o registros de negocio. El despliegue usa copia privada previa del código, fast-forward y comprobación HTTP de las ocho páginas autenticadas y assets.
