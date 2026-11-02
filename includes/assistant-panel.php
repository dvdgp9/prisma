<?php
// Shared authenticated shell. No data queries or provider calls on page load.
if (empty($_SESSION['assistant_csrf'])) $_SESSION['assistant_csrf'] = bin2hex(random_bytes(32));
$assistant_attr = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<link rel="stylesheet" href="/assets/css/assistant.css?v=3">
<section id="prisma-assistant" class="prisma-assistant" role="complementary" aria-labelledby="assistant-heading" hidden
    data-user-id="<?php echo (int) $user['id']; ?>" data-company-id="<?php echo (int) $user['company_id']; ?>"
    data-role="<?php echo $assistant_attr($user['role']); ?>" data-csrf="<?php echo $assistant_attr($_SESSION['assistant_csrf']); ?>">
    <header class="assistant-header">
        <div class="assistant-brand"><i class="iconoir-sparks" aria-hidden="true"></i><h2 id="assistant-heading">Asistente</h2></div>
        <div class="assistant-tools">
            <button type="button" data-assistant-action="history" aria-label="Historial de conversaciones" title="Historial" aria-expanded="false"><i class="iconoir-clock-rotate-right" aria-hidden="true"></i></button>
            <button type="button" data-assistant-action="new" aria-label="Nueva conversación" title="Nueva conversación"><i class="iconoir-plus" aria-hidden="true"></i></button>
            <button type="button" data-assistant-action="close" aria-label="Cerrar asistente" title="Cerrar (Esc)"><i class="iconoir-xmark" aria-hidden="true"></i></button>
        </div>
    </header>
    <div class="assistant-context">
        <div class="assistant-context-top"><span>CONTEXTO</span><button type="button" id="assistant-pin" aria-pressed="false"><i class="iconoir-pin" aria-hidden="true"></i><span>Fijar</span></button></div>
        <select id="assistant-context-select" aria-label="Contexto del siguiente mensaje"></select>
        <p id="assistant-context-caption">Sigue la pantalla que estás viendo.</p>
    </div>
    <div id="assistant-history" class="assistant-history" hidden>
        <div class="assistant-subheading"><h3>Conversaciones</h3><button type="button" data-assistant-action="history">Volver al chat</button></div>
        <div id="assistant-history-list"></div>
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
            <div><button type="submit">Guardar nombre</button><button type="button" data-assistant-action="delete">Eliminar conversación</button></div>
            <p id="assistant-delete-confirm" hidden>Se borrará esta conversación y sus mensajes. <button type="button" data-assistant-action="confirm-delete">Eliminar definitivamente</button></p>
        </form>
        <div id="assistant-messages" class="assistant-messages" role="log" aria-label="Mensajes de la conversación" aria-live="polite"></div>
    </div>
    <div id="assistant-error" class="assistant-error" role="alert" hidden></div>
    <form id="assistant-composer" class="assistant-composer">
        <label class="assistant-sr-only" for="assistant-input">Mensaje para el asistente</label>
        <textarea id="assistant-input" rows="3" maxlength="10000" placeholder="Comparte una idea o pega tus apuntes…"></textarea>
        <div class="assistant-composer-footer"><span id="assistant-save-state">Tu borrador se conserva al navegar.</span><button type="submit" id="assistant-send" aria-label="Enviar mensaje"><i class="iconoir-arrow-up" aria-hidden="true"></i></button></div>
    </form>
    <p class="assistant-footnote">La IA puede equivocarse. Revisa sus respuestas.</p>
</section>
<script src="/assets/js/assistant.js?v=3" defer></script>
