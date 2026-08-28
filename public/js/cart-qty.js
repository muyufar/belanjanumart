(function () {
    document.querySelectorAll('[data-qty-stepper]').forEach(function (wrap) {
        var form = wrap.closest('form');
        var input = wrap.querySelector('input[name="qty"]');
        if (!form || !input) return;

        var minus = wrap.querySelector('[data-qty-minus]');
        var plus = wrap.querySelector('[data-qty-plus]');
        var max = parseInt(input.getAttribute('max'), 10) || 99;

        if (minus) {
            minus.addEventListener('click', function () {
                var v = parseInt(input.value, 10) || 1;
                input.value = String(Math.max(0, v - 1));
                form.requestSubmit();
            });
        }

        if (plus) {
            plus.addEventListener('click', function () {
                var v = parseInt(input.value, 10) || 0;
                input.value = String(Math.min(max, v + 1));
                form.requestSubmit();
            });
        }

        input.addEventListener('change', function () {
            form.requestSubmit();
        });
    });
})();
