# 📱 Configuración PWA - Prisma

## ✅ Archivos Creados

La aplicación ha sido convertida en PWA (Progressive Web App) con estrategia **online-first**.

### Archivos principales:
- `/manifest.json` - Configuración de la PWA
- `/sw.js` - Service Worker con cache online-first
- `/assets/js/pwa.js` - Script de registro y notificaciones
- `/includes/pwa-head.php` - Meta tags reutilizables

## 🎨 Iconos Requeridos

### Ubicación de los iconos:
**Carpeta:** `/assets/icons/`

### Nombres y tamaños requeridos:

| Archivo | Tamaño | Uso |
|---------|--------|-----|
| `icon-72x72.png` | 72×72 px | Dispositivos pequeños |
| `icon-96x96.png` | 96×96 px | Escritorio, shortcuts |
| `icon-128x128.png` | 128×128 px | Chrome Web Store |
| `icon-144x144.png` | 144×144 px | Windows Tiles |
| `icon-152x152.png` | 152×152 px | iPad |
| `icon-192x192.png` | 192×192 px | Android (estándar) |
| `icon-384x384.png` | 384×384 px | Android alta resolución |
| `icon-512x512.png` | 512×512 px | Splash screens, maskable |

### Screenshots (Opcionales):
- `screenshot-mobile.png` - 540×720 px - Vista móvil
- `screenshot-desktop.png` - 1280×720 px - Vista escritorio

## 🎯 Recomendaciones para los iconos

### Diseño:
1. **Fondo sólido:** Usar colores del tema Prisma (#42a0d1, #ed6f66, #f9af51)
2. **Logo centrado:** Con padding de al menos 10% del tamaño total
3. **Formato:** PNG con transparencia (excepto si es maskable)
4. **Maskable icons:** Para 192×192 y 512×512, el contenido importante debe estar en el 80% central (safe zone)

### Generación rápida:
Puedes usar herramientas como:
- **PWA Asset Generator:** https://www.pwabuilder.com/imageGenerator
- **Favicon.io:** https://favicon.io/
- **RealFaviconGenerator:** https://realfavicongenerator.net/

O puedes crear una imagen de 512×512 px y redimensionarla con:

```bash
# Instalar ImageMagick si no lo tienes
brew install imagemagick

# Generar todos los tamaños desde un archivo base
cd /Users/dvdgp/Documents/Codeapps/prisma/assets/icons
convert base-icon-512.png -resize 72x72 icon-72x72.png
convert base-icon-512.png -resize 96x96 icon-96x96.png
convert base-icon-512.png -resize 128x128 icon-128x128.png
convert base-icon-512.png -resize 144x144 icon-144x144.png
convert base-icon-512.png -resize 152x152 icon-152x152.png
convert base-icon-512.png -resize 192x192 icon-192x192.png
convert base-icon-512.png -resize 384x384 icon-384x384.png
```

## 🚀 Características Implementadas

### ✅ Online-First Strategy
- Las peticiones van primero a la red
- Si falla, se sirve desde caché
- APIs siempre van directo a la red (no se cachean)
- Archivos subidos se cachean para acceso rápido

### ✅ Instalación
- Botón flotante de instalación automático
- Compatible con iOS (Add to Home Screen)
- Compatible con Android (Install App)
- Compatible con Desktop (Chrome, Edge, Opera)

### ✅ Actualizaciones
- Notificación automática de nueva versión
- Actualización sin recargar manualmente
- Cache inteligente de assets

### ✅ Shortcuts (Atajos)
- **Nueva Mejora:** Acceso directo desde el icono
- **Mis Tareas:** Vista rápida de tareas

## 🔧 Configuración del Manifest

El archivo `/manifest.json` contiene:
- **Name:** "Prisma - Gestor de Proyectos"
- **Short Name:** "Prisma"
- **Theme Color:** #6366f1 (Azul Prisma)
- **Display:** standalone (app nativa)
- **Start URL:** /index.php

Puedes modificar estos valores en `/manifest.json`.

## 📱 Testing

### En Local:
1. Servir la app con HTTPS (Service Workers requieren HTTPS)
2. Abrir Chrome DevTools → Application → Service Workers
3. Verificar que el SW se registra correctamente

### En Producción:
1. **Android Chrome:**
   - Abre la app
   - Menú → "Agregar a pantalla de inicio"
   
2. **iOS Safari:**
   - Abre la app
   - Botón compartir → "Agregar a pantalla de inicio"
   
3. **Desktop Chrome:**
   - Ícono de instalación en la barra de direcciones
   - O botón flotante que aparece automáticamente

## 🔍 Verificación

Para verificar que la PWA está configurada correctamente:

1. **Lighthouse:** Chrome DevTools → Lighthouse → PWA
2. **PWA Builder:** https://www.pwabuilder.com/
3. **Web.dev:** https://web.dev/measure/

## ⚙️ Service Worker Cache

### Archivos Pre-cacheados:
- `/` y `/index.php`
- `/tasks.php`
- `/changelog.php`
- `/assets/css/styles.css`
- Fuentes de Google Fonts
- Iconos de Iconoir

### Rutas especiales:
- **API (`/api/`):** Network only (no cache)
- **Uploads (`/uploads/`):** Cache first
- **Resto:** Network first con fallback a cache

## 🎨 Colores del Tema

Los colores usados en el manifest y theme-color:
- **Primary:** #6366f1 (Indigo/Azul)
- **Background:** #ffffff (Blanco)
- **Prisma Blue:** #42a0d1
- **Prisma Red:** #ed6f66
- **Prisma Yellow:** #f9af51

---

**Última actualización:** 28 Enero 2026
**Versión PWA:** 1.0.0
