if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('✅ Service Worker registrado:', registration.scope);
        
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showUpdateNotification();
            }
          });
        });
      })
      .catch(error => {
        console.log('❌ Error al registrar Service Worker:', error);
      });
  });

  let refreshing = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (!refreshing) {
      refreshing = true;
      window.location.reload();
    }
  });
}

function showUpdateNotification() {
  const notification = document.createElement('div');
  notification.className = 'pwa-update-notification';
  notification.innerHTML = `
    <div class="pwa-update-content">
      <p><strong>Nueva versión disponible</strong></p>
      <p>Actualiza para obtener las últimas mejoras</p>
      <button onclick="updateApp()" class="btn btn-primary btn-sm">Actualizar</button>
      <button onclick="dismissUpdate(this)" class="btn btn-secondary btn-sm">Después</button>
    </div>
  `;
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.classList.add('show');
  }, 100);
}

function updateApp() {
  navigator.serviceWorker.getRegistration().then(registration => {
    if (registration && registration.waiting) {
      registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
  });
}

function dismissUpdate(btn) {
  const notification = btn.closest('.pwa-update-notification');
  notification.classList.remove('show');
  setTimeout(() => {
    notification.remove();
  }, 300);
}

let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  
  showInstallButton();
});

function showInstallButton() {
  const installBtn = document.createElement('button');
  installBtn.className = 'pwa-install-button';
  installBtn.innerHTML = '<i class="iconoir-download"></i> Instalar App';
  installBtn.onclick = promptInstall;
  
  document.body.appendChild(installBtn);
}

async function promptInstall() {
  if (!deferredPrompt) return;
  
  deferredPrompt.prompt();
  
  const { outcome } = await deferredPrompt.userChoice;
  
  console.log(`Resultado instalación: ${outcome}`);
  
  deferredPrompt = null;
  
  const installBtn = document.querySelector('.pwa-install-button');
  if (installBtn) {
    installBtn.remove();
  }
}

window.addEventListener('appinstalled', () => {
  console.log('✅ PWA instalada con éxito');
  deferredPrompt = null;
});
