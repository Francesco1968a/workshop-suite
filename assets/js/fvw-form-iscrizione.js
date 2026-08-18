document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('fvw-registration-form');
    if (!form) return;

    const submitBtn = document.getElementById('fvw-submit-btn');
    const msgContainer = document.getElementById('fvw-form-message');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('span');
        const originalText = btnText ? btnText.textContent : '';
        if (btnText) btnText.textContent = FVW_Form_Vars.i18n.sending;

        msgContainer.style.display = 'none';
        msgContainer.className = 'fvw-form-response';

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(FVW_Form_Vars.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': FVW_Form_Vars.nonce
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            msgContainer.style.display = 'block';
            if (response.ok && result.success) {
                msgContainer.classList.add('success');
                msgContainer.textContent = result.message || FVW_Form_Vars.i18n.success;
                form.reset();
            } else {
                msgContainer.classList.add('error');
                msgContainer.textContent = result.message || FVW_Form_Vars.i18n.error;
            }
        } catch (err) {
            msgContainer.style.display = 'block';
            msgContainer.classList.add('error');
            msgContainer.textContent = FVW_Form_Vars.i18n.error;
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    });
});
