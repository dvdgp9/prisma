<?php
// Shared authenticated shell. No data queries or provider calls on page load.
if (empty($_SESSION['assistant_csrf'])) $_SESSION['assistant_csrf'] = bin2hex(random_bytes(32));
$assistant_attr = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<link rel="stylesheet" href="/assets/css/assistant.css?v=4">
<section id="prisma-assistant" class="prisma-assistant" role="complementary" aria-labelledby="assistant-heading" hidden
    data-user-id="<?php echo (int) $user['id']; ?>" data-company-id="<?php echo (int) $user['company_id']; ?>"
    data-role="<?php echo $assistant_attr($user['role']); ?>" data-csrf="<?php echo $assistant_attr($_SESSION['assistant_csrf']); ?>">
    <header class="assistant-header">
        <div class="assistant-brand"><i class="iconoir-sparks" aria-hidden="true"></i><h2 id="assistant-heading">Asistente</h2></div>
        <button type="button" class="assistant-close" data-assistant-action="close" aria-label="Cerrar asistente" title="Cerrar (Esc)"><i class="iconoir-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="assistant-navigation" role="group" aria-label="Vistas del asistente">
        <button type="button" data-assistant-action="chat" aria-pressed="true" aria-controls="assistant-chat">Conversación</button>
        <button type="button" data-assistant-action="history" aria-pressed="false" aria-controls="assistant-history"><i class="iconoir-clock-rotate-right" aria-hidden="true"></i>Historial</button>
        <button type="button" class="assistant-new" data-assistant-action="new"><i class="iconoir-plus" aria-hidden="true"></i>Nueva</button>
    </div>
    <div id="assistant-history" class="assistant-history" hidden>
        <div class="assistant-subheading"><h3>Tus conversaciones</h3><p>Retoma una consulta anterior.</p></div>
        <div id="assistant-history-list" aria-label="Conversaciones guardadas"></div>
        <button type="button" class="assistant-text-button" id="assistant-history-more" hidden>Cargar más conversaciones</button>
    </div>
    <div id="assistant-chat" class="assistant-chat">
        <div class="assistant-conversation-bar" id="assistant-conversation-bar" hidden>
            <span id="assistant-conversation-title"></span>
            <button type="button" data-assistant-action="manage" aria-label="Gestionar conversación"><i class="iconoir-more-horiz" aria-hidden="true"></i></button>
        </div>
        <form id="assistant-manage" class="assistant-manage" hidden>
            <label for="assistant-rename">Nombre de la conversación</label>
            <input id="assistant-rename" maxlength="200" required autocomplete="off">
            <div><button type="submit">Guardar nombre</button><button type="button" data-assistant-action="cancel-manage">Cancelar</button><button type="button" data-assistant-action="delete">Eliminar conversación</button></div>
            <p id="assistant-delete-confirm" hidden>Se borrará esta conversación y sus mensajes. <button type="button" data-assistant-action="confirm-delete">Eliminar definitivamente</button></p>
        </form>
        <div id="assistant-messages" class="assistant-messages" role="log" aria-label="Mensajes de la conversación" aria-live="polite"></div>
        <button type="button" id="assistant-latest" class="assistant-latest" hidden>Último mensaje <i class="iconoir-arrow-down" aria-hidden="true"></i></button>
    </div>
    <div class="assistant-context" id="assistant-context">
        <button type="button" class="assistant-context-toggle" id="assistant-context-toggle" aria-expanded="false" aria-controls="assistant-context-controls" title="Cambiar qué consulta el siguiente mensaje">
            <i class="iconoir-page" id="assistant-context-icon" aria-hidden="true"></i>
            <span class="assistant-context-summary"><span id="assistant-context-label">Mi trabajo</span><span id="assistant-context-mode">Sigue esta pantalla</span></span>
            <i class="iconoir-nav-arrow-down" aria-hidden="true"></i>
        </button>
        <div id="assistant-context-controls" class="assistant-context-controls" hidden>
            <label for="assistant-context-select">Consultar sobre</label>
            <select id="assistant-context-select" aria-describedby="assistant-context-caption"></select>
            <button type="button" id="assistant-pin" aria-pressed="false"><i class="iconoir-pin" aria-hidden="true"></i><span>Fijar contexto</span></button>
            <p id="assistant-context-caption">El contexto cambia con la pantalla que estás viendo.</p>
        </div>
    </div>
    <div id="assistant-error" class="assistant-error" role="alert" hidden></div>
    <form id="assistant-composer" class="assistant-composer">
        <label class="assistant-sr-only" for="assistant-input">Mensaje para el asistente</label>
        <textarea id="assistant-input" rows="2" maxlength="10000" placeholder="Pregunta o comparte tus apuntes…"></textarea>
        <div class="assistant-composer-footer"><span id="assistant-save-state">Ctrl + Enter para enviar</span><button type="submit" id="assistant-send" aria-label="Enviar mensaje"><span>Enviar</span><i class="iconoir-arrow-up" aria-hidden="true"></i></button></div>
    </form>
    <p class="assistant-footnote" id="assistant-footnote">La IA puede equivocarse. Revisa sus respuestas.</p>
</section>
<script src="/assets/js/assistant.js?v=4" defer></script>
