(function () {
    var root = document.getElementById('quickView');
    if (!root) return;

    var titleEl = document.getElementById('quickViewTitle');
    var kodeEl = document.getElementById('quickViewKode');
    var stockEl = document.getElementById('quickViewStock');
    var pricesEl = document.getElementById('quickViewPrices');
    var imgEl = document.getElementById('quickViewImg');
    var placeholderEl = document.getElementById('quickViewPlaceholder');
    var barangIdEl = document.getElementById('quickViewBarangId');
    var qtyEl = document.getElementById('quickViewQty');
    var submitEl = document.getElementById('quickViewSubmit');
    var detailEl = document.getElementById('quickViewDetail');
    var formEl = document.getElementById('quickViewForm');
    var lastFocus = null;

    function formatRp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function openModal(data) {
        lastFocus = document.activeElement;
        titleEl.textContent = data.name || '';
        kodeEl.textContent = data.kode ? 'Kode barang · ' + data.kode : '';
        stockEl.textContent = data.inStock
            ? 'Stok tersedia · ' + Math.max(0, data.stock || 0)
            : 'Stok habis';
        stockEl.className = 'quick-view__stock' + (data.inStock ? '' : ' product-stock--empty');

        var priceHtml = '';
        if (data.priceOriginal && data.priceOriginal > data.price) {
            priceHtml = '<p class="price price--sale" style="font-size:1.35rem;margin:0">' + formatRp(data.price) + '</p>'
                + '<p class="price-old">' + formatRp(data.priceOriginal) + '</p>';
        } else if (data.hasDiscount && data.priceOriginal) {
            priceHtml = '<p class="price price--sale" style="font-size:1.35rem;margin:0">' + formatRp(data.price) + '</p>'
                + '<p class="price-old">' + formatRp(data.priceOriginal)
                + (data.discountLabel ? ' (' + data.discountLabel + ')' : '') + '</p>';
        } else {
            priceHtml = '<p class="price" style="font-size:1.35rem;margin:0">' + formatRp(data.price) + '</p>';
        }
        pricesEl.innerHTML = priceHtml;

        if (data.image) {
            imgEl.src = data.image;
            imgEl.alt = data.name || '';
            imgEl.hidden = false;
            placeholderEl.hidden = true;
            imgEl.onerror = function () {
                imgEl.hidden = true;
                placeholderEl.hidden = false;
                placeholderEl.textContent = (data.name || '?').slice(0, 2).toUpperCase();
            };
        } else {
            imgEl.hidden = true;
            placeholderEl.hidden = false;
            placeholderEl.textContent = (data.name || '?').slice(0, 2).toUpperCase();
        }

        barangIdEl.value = data.id || '';
        detailEl.href = data.url || '#';
        var maxQty = data.inStock ? Math.min(99, Math.max(1, data.stock || 1)) : 1;
        qtyEl.max = maxQty;
        qtyEl.value = 1;
        qtyEl.disabled = !data.inStock;
        submitEl.disabled = !data.inStock;
        formEl.style.display = data.inStock ? '' : 'none';

        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('quick-view-open');
        root.querySelector('.quick-view__close').focus();
    }

    function closeModal() {
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('quick-view-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-quick-view]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        try {
            openModal(JSON.parse(btn.getAttribute('data-quick-view')));
        } catch (err) {
            // ignore invalid payload
        }
    });

    root.querySelectorAll('[data-quick-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !root.hidden) closeModal();
    });
})();
