(function () {
    function createPopup() {
        if (!document.querySelector('link[data-app-popup-style]')) {
            var style = document.createElement('link');
            style.rel = 'stylesheet';
            style.href = 'popup.css';
            style.dataset.appPopupStyle = 'true';
            document.head.appendChild(style);
        }
        let overlay = document.getElementById('appPopup');
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.id = 'appPopup';
        overlay.className = 'app-popup-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <section class="app-popup" role="alertdialog" aria-modal="true" aria-labelledby="appPopupTitle" aria-describedby="appPopupMessage">
                <div class="app-popup-icon" aria-hidden="true"></div>
                <p class="app-popup-kicker" id="appPopupTitle">Notification</p>
                <p class="app-popup-message" id="appPopupMessage"></p>
                <div class="app-popup-actions">
                    <button type="button" class="app-popup-secondary" data-popup-cancel>Cancel</button>
                    <button type="button" class="app-popup-primary" data-popup-ok>OK</button>
                </div>
            </section>`;
        document.body.appendChild(overlay);
        return overlay;
    }

    function closePopup(overlay, result) {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        window.dispatchEvent(new CustomEvent('appPopupClosed'));
        if (overlay._resolve) {
            overlay._resolve(result);
            overlay._resolve = null;
        }
    }

    function openPopup(message, options) {
        const overlay = createPopup();
        const popup = overlay.querySelector('.app-popup');
        const icon = overlay.querySelector('.app-popup-icon');
        const kicker = overlay.querySelector('.app-popup-kicker');
        const messageElement = overlay.querySelector('.app-popup-message');
        const cancelButton = overlay.querySelector('[data-popup-cancel]');
        const okButton = overlay.querySelector('[data-popup-ok]');
        const settings = Object.assign({ type: 'info', title: 'Notification', confirm: false }, options);

        popup.dataset.type = settings.type;
        icon.textContent = settings.type === 'success' ? '✓' : (settings.type === 'error' || settings.type === 'warning') ? '!' : '?';
        kicker.textContent = settings.title;
        messageElement.textContent = message;
        cancelButton.hidden = !settings.confirm;
        cancelButton.textContent = settings.cancelLabel || 'Cancel';
        okButton.textContent = settings.confirm ? (settings.confirmLabel || 'Confirm') : 'OK';
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');

        return new Promise(function (resolve) {
            overlay._resolve = resolve;
            okButton.onclick = function () { closePopup(overlay, true); };
            cancelButton.onclick = function () { closePopup(overlay, false); };
            overlay.onclick = function (event) {
                if (event.target === overlay && settings.confirm) {
                    closePopup(overlay, false);
                }
            };
            popup.querySelector('[data-popup-ok]').focus();
        });
    }

    window.AppPopup = {
        alert: function (message, options) {
            const settings = Object.assign({ type: 'info', title: 'Notification' }, options);
            return openPopup(message, settings);
        },
        show: function (message, type, redirect) {
            var container = document.getElementById('appToastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'appToastContainer';
                container.className = 'app-toast-container';
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'false');
                document.body.appendChild(container);
            }
            var toast = document.createElement('div');
            toast.className = 'app-toast app-toast-' + (type || 'info');
            toast.setAttribute('role', 'status');
            var content = document.createElement('span');
            content.textContent = message;
            var close = document.createElement('button');
            close.type = 'button'; close.setAttribute('aria-label', 'Dismiss'); close.textContent = '×';
            toast.append(content, close); container.appendChild(toast);
            var finish = function () { toast.remove(); window.dispatchEvent(new CustomEvent('appPopupClosed')); if (redirect) window.location.href = redirect; };
            var timer = window.setTimeout(finish, 3200);
            close.onclick = function () { window.clearTimeout(timer); finish(); };
            return Promise.resolve(true);
        },
        confirm: function (message, options) {
            var container = document.getElementById('appToastContainer');
            if (!container) { container = document.createElement('div'); container.id = 'appToastContainer'; container.className = 'app-toast-container'; container.setAttribute('aria-live', 'polite'); container.setAttribute('aria-atomic', 'false'); document.body.appendChild(container); }
            var toast = document.createElement('div'); toast.className = 'app-toast app-toast-warning'; toast.setAttribute('role', 'alert');
            var text = document.createElement('span'); text.textContent = message;
            var cancel = document.createElement('button'); cancel.type = 'button'; cancel.textContent = (options && options.cancelLabel) || 'Cancel';
            var ok = document.createElement('button'); ok.type = 'button'; ok.textContent = (options && options.confirmLabel) || 'Confirm';
            toast.append(text, cancel, ok); container.appendChild(toast);
            return new Promise(function (resolve) { cancel.onclick = function () { toast.remove(); resolve(false); }; ok.onclick = function () { toast.remove(); resolve(true); }; });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === 'true') {
                    form.dataset.confirmed = 'false';
                    return;
                }
                event.preventDefault();
                AppPopup.confirm(form.dataset.confirm).then(function (confirmed) {
                    if (confirmed) {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                });
            });
        });
    });
}());
