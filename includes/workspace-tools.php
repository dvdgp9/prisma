<?php // Shared workspace utilities: actions open panels; sidebar links navigate. ?>
<div class="workspace-tools" role="group" aria-label="Herramientas del espacio de trabajo">
    <button type="button" class="workspace-notifications" id="inbox-nav-btn" onclick="toggleInbox()"
        aria-label="Notificaciones" aria-controls="inbox-panel" aria-expanded="false" title="Notificaciones">
        <i class="iconoir-bell" aria-hidden="true"></i>
        <span class="workspace-notification-count" id="inbox-count" hidden></span>
    </button>
    <button type="button" class="assistant-launcher" id="assistant-toggle" data-assistant-toggle
        aria-label="Preguntar a Prisma" aria-controls="prisma-assistant" aria-expanded="false"
        aria-keyshortcuts="Control+J Meta+J" title="Abrir el asistente en un panel lateral (Ctrl/Cmd + J)">
        <i class="iconoir-sparks" aria-hidden="true"></i>
        <span class="assistant-launcher-label">Preguntar a Prisma</span>
        <span class="assistant-launcher-short" aria-hidden="true">Asistente</span>
        <kbd data-assistant-shortcut aria-hidden="true">Ctrl J</kbd>
        <svg class="assistant-launcher-panel" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="14" height="12" rx="2"/><path d="M12 4v12"/></svg>
    </button>
</div>
