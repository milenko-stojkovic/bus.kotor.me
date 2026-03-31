<script>
    (function () {
        function initToggle(wrapper) {
            const input = wrapper.querySelector('input');
            const btn = wrapper.querySelector('[data-pw-toggle]');
            if (!input || !btn) return;

            const showText = btn.getAttribute('data-show-text') || 'Show';
            const hideText = btn.getAttribute('data-hide-text') || 'Hide';

            const eye = btn.querySelector('[data-eye]');
            const eyeOff = btn.querySelector('[data-eye-off]');

            function sync() {
                const isHidden = input.type === 'password';
                // No visible text: toggle icons + aria-label for accessibility.
                btn.setAttribute('aria-label', isHidden ? showText : hideText);
                btn.setAttribute('aria-pressed', String(!isHidden));
                if (eye) eye.classList.toggle('hidden', !isHidden);
                if (eyeOff) eyeOff.classList.toggle('hidden', isHidden);
            }

            btn.addEventListener('click', function () {
                input.type = (input.type === 'password') ? 'text' : 'password';
                sync();
            });

            sync();
        }

        document.querySelectorAll('[data-pw-wrapper]').forEach(initToggle);
    })();
</script>

