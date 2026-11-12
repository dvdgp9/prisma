# Creación rápida de mejoras

En Mejoras y en las vistas de aplicación y empresa hay una caja sobre los filtros.
La aplicación actual se selecciona por defecto; si no hay contexto, se elige con
`@nombre` o con el selector. Un nombre ambiguo requiere escribir más letras.

```text
Exportar informes @prisma +mar mañana prioridad alta dificultad baja
Conservar los filtros al descargar el informe.
Solicitante: Marta Gil
Email: marta@empresa.es
- [ ] Añadir el botón de descarga
- [ ] Comprobar permisos
```

- Primera línea: título, `@app` o `@"Nombre completo"`, prioridad y dificultad.
- Personas: `+nombre`, `+apellido` o `para Nombre`. Se admiten varias; la primera es responsable.
- Fechas: las mismas de tareas (`hoy`, `mañana`, `viernes`, `en 3 días`, `15/07`, etc.).
- Descripción: líneas siguientes o después de ` :: ` (también ` -- `).
- Checklist: una línea `- [ ] Texto` por paso.
- Solicitante: líneas `Solicitante: Nombre` y `Email: correo@ejemplo.com`.

Los chips muestran lo detectado. Quitarlos conserva esos fragmentos como texto.
La descripción no se analiza para fechas, aplicaciones o personas. El rol usuario
conserva fechas y personas como texto, de acuerdo con sus permisos de planificación.

Enter crea desde una sola línea. Mayús+Enter añade una línea. En textos con varias
líneas, Enter continúa escribiendo y Ctrl/Cmd+Enter crea. Más opciones traslada
el borrador al modal para revisarlo o adjuntar archivos; cancelarlo conserva la
entrada rápida. Mientras se guarda, se bloquean los envíos repetidos. Si falla la
creación se conserva el texto. Si falla un extra, se avisa de que la mejora ya está
creada y de qué parte no se pudo guardar.

Pruebas: `node --test tests/request-quick-add.test.js` y
`php tests/request-create-modal.php`.
