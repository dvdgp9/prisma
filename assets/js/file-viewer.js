/**
 * In-app file viewer (shared by mejoras, tareas y archivos de app).
 *
 * Images and PDFs open in an overlay inside the PWA; everything else downloads
 * with its real filename. This replaces <a target="_blank"> links that, in the
 * standalone PWA, popped files into a loose browser window and downloaded them
 * with a random on-disk name.
 *
 * Public API: window.openFileViewer(type, id, name, mimeType)
 *   type: 'request' | 'task' | 'appfile'
 */
(function () {
  'use strict';

  function fileUrl(type, id, download) {
    const base = '/api/file.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id);
    return download ? base + '&download=1' : base;
  }

  function isImage(mime) {
    return typeof mime === 'string' && mime.indexOf('image/') === 0;
  }

  function isPdf(mime) {
    return mime === 'application/pdf';
  }

  function triggerDownload(type, id) {
    const a = document.createElement('a');
    a.href = fileUrl(type, id, true);
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  let overlay = null;
  let keyHandler = null;

  function closeViewer() {
    if (!overlay) return;
    overlay.classList.remove('active');
    // Clear the body so iframes/images stop loading/holding memory.
    const body = overlay.querySelector('.fv-body');
    if (body) body.innerHTML = '';
    if (keyHandler) {
      document.removeEventListener('keydown', keyHandler);
      keyHandler = null;
    }
  }

  function ensureOverlay() {
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'fv-overlay';
    overlay.innerHTML = [
      '<div class="fv-dialog" role="dialog" aria-modal="true">',
      '  <div class="fv-header">',
      '    <span class="fv-title" title=""></span>',
      '    <div class="fv-actions">',
      '      <button type="button" class="fv-btn fv-download" title="Descargar">',
      '        <i class="iconoir-download"></i><span>Descargar</span>',
      '      </button>',
      '      <button type="button" class="fv-btn fv-close" title="Cerrar" aria-label="Cerrar">',
      '        <i class="iconoir-xmark"></i>',
      '      </button>',
      '    </div>',
      '  </div>',
      '  <div class="fv-body"></div>',
      '</div>'
    ].join('');

    // Close when clicking the backdrop (but not the dialog itself).
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeViewer();
    });
    overlay.querySelector('.fv-close').addEventListener('click', closeViewer);

    document.body.appendChild(overlay);
    return overlay;
  }

  window.openFileViewer = function (type, id, name, mime) {
    const previewable = isImage(mime) || isPdf(mime);

    // Non-previewable types: download directly, no overlay, no blank window.
    if (!previewable) {
      triggerDownload(type, id);
      return;
    }

    ensureOverlay();

    const titleEl = overlay.querySelector('.fv-title');
    titleEl.textContent = name || 'Archivo';
    titleEl.setAttribute('title', name || '');

    overlay.querySelector('.fv-download').onclick = function () {
      triggerDownload(type, id);
    };

    const body = overlay.querySelector('.fv-body');
    const url = fileUrl(type, id, false);

    if (isImage(mime)) {
      body.innerHTML = '<img class="fv-img" alt="">';
      body.querySelector('.fv-img').src = url;
    } else {
      body.innerHTML = '<iframe class="fv-frame" title="Vista previa"></iframe>';
      body.querySelector('.fv-frame').src = url;
    }

    overlay.classList.add('active');

    keyHandler = function (e) {
      if (e.key === 'Escape') closeViewer();
    };
    document.addEventListener('keydown', keyHandler);
  };

  // Allow closing programmatically if ever needed.
  window.closeFileViewer = closeViewer;

  // Delegated handler: any element with .fv-trigger opens the viewer.
  // We read the display name from a child .fv-name (or the element's own text)
  // so filenames with quotes/apostrophes need no attribute escaping.
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest ? e.target.closest('.fv-trigger') : null;
    if (!trigger) return;
    e.preventDefault();
    const nameEl = trigger.querySelector('.fv-name');
    const name = ((nameEl ? nameEl.textContent : trigger.textContent) || 'Archivo').trim();
    window.openFileViewer(trigger.dataset.type, trigger.dataset.id, name, trigger.dataset.mime);
  });
})();
