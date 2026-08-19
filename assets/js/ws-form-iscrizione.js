document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('ws-registration-form');
    if (!form) return;

    const submitBtn = document.getElementById('ws-submit-btn');
    const msgContainer = document.getElementById('ws-form-message');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('span');
        const originalText = btnText ? btnText.textContent : '';
        if (btnText) btnText.textContent = WS_Form_Vars.i18n.sending;

        msgContainer.style.display = 'none';
        msgContainer.className = 'ws-form-response';

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(WS_Form_Vars.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': WS_Form_Vars.nonce
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            msgContainer.style.display = 'block';
            if (response.ok && result.success) {
                msgContainer.classList.add('success');
                msgContainer.textContent = result.message || WS_Form_Vars.i18n.success;
                form.reset();
            } else {
                msgContainer.classList.add('error');
                msgContainer.textContent = result.message || WS_Form_Vars.i18n.error;
            }
        } catch (err) {
            msgContainer.style.display = 'block';
            msgContainer.classList.add('error');
            msgContainer.textContent = WS_Form_Vars.i18n.error;
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    });
});
