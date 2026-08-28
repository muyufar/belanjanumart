(function () {
    document.querySelectorAll('[data-catalog-filters]').forEach(function (form) {
        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                if (window.matchMedia('(min-width: 1024px)').matches) {
                    form.requestSubmit();
                }
            });
        });
    });
})();
