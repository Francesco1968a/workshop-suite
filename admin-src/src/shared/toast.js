// Minimal success-toast, framework-free — avoids pulling in a UI library
// (e.g. Element Plus's ElMessage) just for a one-line notification.
// Shared across bundles that only need this, not full Element Plus.

let styleInjected = false;

function injectStyle() {
  if (styleInjected) return;
  styleInjected = true;
  const style = document.createElement('style');
  style.textContent = `
    .ws-toast {
      position: fixed;
      top: 46px;
      left: 50%;
      transform: translateX(-50%);
      background: #1d2327;
      color: #fff;
      padding: 10px 20px;
      border-radius: 4px;
      font-size: 13px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      z-index: 100000;
      opacity: 0;
      transition: opacity 0.2s ease;
      pointer-events: none;
    }
    .ws-toast.ws-toast--visible {
      opacity: 1;
    }
  `;
  document.head.appendChild(style);
}

export function showToast(message, duration = 2200) {
  injectStyle();
  const el = document.createElement('div');
  el.className = 'ws-toast';
  el.textContent = message;
  document.body.appendChild(el);
  requestAnimationFrame(() => el.classList.add('ws-toast--visible'));
  setTimeout(() => {
    el.classList.remove('ws-toast--visible');
    setTimeout(() => el.remove(), 250);
  }, duration);
}
