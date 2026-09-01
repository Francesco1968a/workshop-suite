document.addEventListener('DOMContentLoaded', function () {
    // querySelectorAll, not getElementById: a page can embed [ws_form_iscrizione]
    // more than once (e.g. one inline instance plus one per "Richiedi info"
    // modal for each evento date) — getElementById('ws-registration-form')
    // only ever wires up the first one it finds in the DOM, since IDs must be
    // unique; every other instance was left with no submit handler at all,
    // silently falling back to a native GET form submission (page reload,
    // no data sent, no error shown) instead of the REST API call below.
    document.querySelectorAll('form.ws-form').forEach(function (form) {
        const submitBtn = form.querySelector('.ws-btn-submit');
        const msgContainer = form.querySelector('.ws-form-response');
        if (!submitBtn || !msgContainer) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            submitBtn.disabled = true;
            const btnText = submitBtn.querySelector('span');
            const originalText = btnText ? btnText.textContent : '';
            if (btnText) btnText.textContent = WSMA_Form_Vars.i18n.sending;

            msgContainer.style.display = 'none';
            msgContainer.className = 'ws-form-response';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            data.pagina_provenienza = window.location.href;

            try {
                const response = await fetch(WSMA_Form_Vars.restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': WSMA_Form_Vars.nonce
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                msgContainer.style.display = 'block';
                if (response.ok && result.success) {
                    msgContainer.classList.add('success');
                    msgContainer.textContent = result.message || WSMA_Form_Vars.i18n.success;
                    form.reset();
                } else {
                    msgContainer.classList.add('error');
                    msgContainer.textContent = result.message || WSMA_Form_Vars.i18n.error;
                }
            } catch (err) {
                msgContainer.style.display = 'block';
                msgContainer.classList.add('error');
                msgContainer.textContent = WSMA_Form_Vars.i18n.error;
            } finally {
                submitBtn.disabled = false;
                if (btnText) btnText.textContent = originalText;
            }
        });
    });
});
