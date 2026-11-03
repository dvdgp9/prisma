<?php
// Shared authenticated shell. No data queries or provider calls on page load.
if (empty($_SESSION['assistant_csrf'])) $_SESSION['assistant_csrf'] = bin2hex(random_bytes(32));
$assistant_attr = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<link rel="stylesheet" href="/assets/css/assistant.css?v=6">
<section id="prisma-assistant" class="prisma-assistant" role="complementary" aria-labelledby="assistant-heading" hidden
    data-user-id="<?php echo (int) $user['id']; ?>" data-company-id="<?php echo (int) $user['company_id']; ?>"
    data-role="<?php echo $assistant_attr($user['role']); ?>" data-csrf="<?php echo $assistant_attr($_SESSION['assistant_csrf']); ?>">
    <header class="assistant-header">
        <div class="assistant-brand"><i class="iconoir-sparks" aria-hidden="true"></i><h2 id="assistant-heading">Asistente</h2></div>
        <div class="assistant-header-tools">
            <button type="button" data-assistant-action="history" aria-pressed="false" aria-controls="assistant-history"><i class="iconoir-clock-rotate-right" aria-hidden="true"></i><span>Historial</span></button>
            <button type="button" data-assistant-action="new" aria-label="Nueva conversación" title="Nueva conversación"><i class="iconoir-plus" aria-hidden="true"></i></button>
            <button type="button" data-assistant-action="close" aria-label="Cerrar asistente" title="Cerrar (Esc)"><i class="iconoir-xmark" aria-hidden="true"></i></button>
        </div>
    </header>
    <div id="assistant-history" class="assistant-history" hidden>
        <div class="assistant-subheading">
            <button type="button" class="assistant-back" data-assistant-action="chat"><i class="iconoir-arrow-left" aria-hidden="true"></i>Volver</button>
            <h3>Tus conversaciones</h3>
            <div class="assistant-history-search">
                <i class="iconoir-search" aria-hidden="true"></i>
                <label for="assistant-history-filter" class="assistant-sr-only">Buscar en tus conversaciones</label>
                <input type="search" id="assistant-history-filter" placeholder="Buscar por nombre" autocomplete="off" maxlength="200">
            </div>
        </div>
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
        <div id="assistant-messages" class="assistant-messages" tabindex="-1" role="log" aria-label="Mensajes de la conversación" aria-live="polite"></div>
        <button type="button" id="assistant-latest" class="assistant-latest" hidden>Último mensaje <i class="iconoir-arrow-down" aria-hidden="true"></i></button>
    </div>
    <div id="assistant-error" class="assistant-error" role="alert" hidden></div>
    <fieldset class="assistant-scope" id="assistant-context">
        <legend>Consultar en</legend>
        <div id="assistant-scope-options" class="assistant-scope-options" aria-describedby="assistant-scope-help"></div>
        <p id="assistant-scope-help"></p>
    </fieldset>
    <form id="assistant-composer" class="assistant-composer">
        <div class="assistant-compose-box">
            <div class="assistant-compose-heading">
                <label for="assistant-input" id="assistant-input-label">Tu pregunta</label>
                <a href="/ai-inbox.php#desde-asistente" class="assistant-compose-link" data-assistant-create id="assistant-notes-create" hidden>Crear tareas y mejoras<i class="iconoir-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <textarea id="assistant-input" rows="2" maxlength="10000" placeholder="Pregunta sobre tu trabajo…" aria-describedby="assistant-save-state"></textarea>
            <div class="assistant-composer-footer">
                <div class="assistant-composer-tools">
                    <button type="button" class="assistant-chip" id="assistant-scope-toggle" aria-expanded="false" aria-controls="assistant-context" hidden><span class="assistant-sr-only">Consultar en: </span><span id="assistant-scope-chip">Mi trabajo</span><i class="iconoir-nav-arrow-down" aria-hidden="true"></i></button>
                    <button type="button" class="assistant-chip" id="assistant-text-mode"><i class="iconoir-align-left" aria-hidden="true"></i>Ordenar texto</button>
                    <button type="button" class="assistant-chip" id="assistant-cancel-notes" hidden>Cancelar</button>
                </div>
                <button type="submit" id="assistant-send" aria-label="Enviar pregunta"><span>Enviar</span><i class="iconoir-arrow-up" aria-hidden="true"></i></button>
            </div>
        </div>
        <p class="assistant-hint" id="assistant-save-state"></p>
    </form>
</section>
<script src="/assets/js/assistant-format.js?v=1" defer></script>
<script src="/assets/js/assistant.js?v=6" defer></script>
