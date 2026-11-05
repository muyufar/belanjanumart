(function () {
    var form = document.getElementById('checkout-form');
    if (!form) return;

    var radios = form.querySelectorAll('input[name="checkout_beneficiary"]');
    var wrap = document.getElementById('checkout-member-wrap');
    var select = document.getElementById('beneficiary_user_id');
    var nameInput = form.querySelector('input[name="name"]');
    var phoneInput = form.querySelector('input[name="phone"]');
    var addressInput = form.querySelector('textarea[name="address"]');

    if (!radios.length || !wrap || !select) return;

    function selfDefaults() {
        /* biarkan nilai user isi manual untuk diri sendiri */
    }

    function applyMember() {
        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return;
        if (nameInput) nameInput.value = opt.getAttribute('data-name') || '';
        if (phoneInput) phoneInput.value = opt.getAttribute('data-phone') || '';
        if (addressInput) addressInput.value = opt.getAttribute('data-address') || '';
    }

    function sync() {
        var mode = form.querySelector('input[name="checkout_beneficiary"]:checked');
        var isMember = mode && mode.value === 'member';
        wrap.style.display = isMember ? '' : 'none';
        if (isMember) {
            applyMember();
        }
    }

    radios.forEach(function (r) {
        r.addEventListener('change', sync);
    });
    select.addEventListener('change', applyMember);
    sync();
})();
