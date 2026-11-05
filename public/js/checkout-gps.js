(function () {
    var form = document.getElementById('checkout-form');
    if (!form) return;

    var btn = document.getElementById('checkout-gps-btn');
    var statusEl = document.getElementById('checkout-gps-status');
    var latInput = document.getElementById('customer_lat');
    var lngInput = document.getElementById('customer_lng');
    var accInput = document.getElementById('gps_accuracy');
    var validInput = document.getElementById('gps_valid');
    var coordsEl = document.getElementById('checkout-gps-coords');
    var mapLink = document.getElementById('checkout-gps-map');

    if (!btn || !latInput || !lngInput || !validInput) return;

    var maxAccuracyM = 500;

    function setStatus(type, message) {
        if (!statusEl) return;
        statusEl.className = 'checkout-gps__status checkout-gps__status--' + type;
        statusEl.textContent = message;
    }

    function applyCoords(lat, lng, accuracy) {
        latInput.value = String(lat);
        lngInput.value = String(lng);
        if (accInput) accInput.value = accuracy != null ? String(Math.round(accuracy)) : '';
        validInput.value = '1';

        if (coordsEl) {
            coordsEl.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6) +
                (accuracy != null ? ' (±' + Math.round(accuracy) + ' m)' : '');
        }
        if (mapLink) {
            mapLink.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng);
            mapLink.hidden = false;
        }

        setStatus('ok', 'Lokasi GPS tercatat. Pastikan titik sesuai alamat pengiriman.');
        btn.textContent = 'Ambil ulang lokasi';
    }

    function clearCoords() {
        latInput.value = '';
        lngInput.value = '';
        if (accInput) accInput.value = '';
        validInput.value = '0';
        if (coordsEl) coordsEl.textContent = '';
        if (mapLink) mapLink.hidden = true;
    }

    function geoError(err) {
        clearCoords();
        var msg = 'Gagal mengambil lokasi.';
        if (err && err.code === 1) msg = 'Izin lokasi ditolak. Aktifkan GPS & izin browser untuk situs ini.';
        else if (err && err.code === 2) msg = 'Lokasi tidak tersedia. Pastikan GPS aktif.';
        else if (err && err.code === 3) msg = 'Waktu habis. Coba lagi di area dengan sinyal lebih baik.';
        setStatus('err', msg);
        btn.disabled = false;
    }

    btn.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setStatus('err', 'Browser tidak mendukung GPS. Gunakan Chrome/Safari terbaru di HP.');
            return;
        }

        btn.disabled = true;
        setStatus('wait', 'Mencari lokasi… izinkan akses jika diminta.');

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var acc = pos.coords.accuracy;

                if (acc > maxAccuracyM) {
                    clearCoords();
                    setStatus('err', 'Akurasi GPS lemah (±' + Math.round(acc) + ' m). Pindah ke area terbuka lalu ambil ulang.');
                    btn.disabled = false;
                    return;
                }

                applyCoords(lat, lng, acc);
                btn.disabled = false;
            },
            geoError,
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    });

    form.addEventListener('submit', function (e) {
        if (validInput.value !== '1' || !latInput.value || !lngInput.value) {
            e.preventDefault();
            setStatus('err', 'Ambil lokasi GPS terlebih dahulu agar alamat pengiriman valid.');
            btn.focus();
        }
    });

    if (validInput.value === '1' && latInput.value && lngInput.value) {
        var lat = parseFloat(latInput.value);
        var lng = parseFloat(lngInput.value);
        var acc = accInput && accInput.value ? parseFloat(accInput.value) : null;
        if (!isNaN(lat) && !isNaN(lng)) {
            applyCoords(lat, lng, acc);
        }
    }
})();
