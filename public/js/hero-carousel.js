(function () {
    var carousels = document.querySelectorAll('[data-hero-carousel]');
    if (!carousels.length) return;

    carousels.forEach(function (root) {
        var track = root.querySelector('.hero-carousel__track');
        var slides = root.querySelectorAll('[data-hero-slide]');
        var dots = root.querySelectorAll('[data-hero-dot]');
        var prev = root.querySelector('[data-hero-prev]');
        var next = root.querySelector('[data-hero-next]');
        var index = 0;
        var timer = null;
        var total = slides.length;

        if (total <= 1) return;

        function goTo(i) {
            index = (i + total) % total;
            var slide = slides[index];
            if (!slide || !track) return;

            track.scrollTo({
                left: slide.offsetLeft - track.offsetLeft,
                behavior: 'smooth',
            });

            dots.forEach(function (dot, di) {
                var active = di === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function startAuto() {
            stopAuto();
            if (window.matchMedia('(min-width: 1024px)').matches) {
                timer = window.setInterval(function () {
                    goTo(index + 1);
                }, 6000);
            }
        }

        function stopAuto() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        if (prev) prev.addEventListener('click', function () { goTo(index - 1); startAuto(); });
        if (next) next.addEventListener('click', function () { goTo(index + 1); startAuto(); });

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                goTo(parseInt(dot.getAttribute('data-hero-dot'), 10) || 0);
                startAuto();
            });
        });

        track.addEventListener('scroll', function () {
            window.clearTimeout(track._heroScrollT);
            track._heroScrollT = window.setTimeout(function () {
                var closest = 0;
                var minDist = Infinity;
                slides.forEach(function (slide, si) {
                    var dist = Math.abs(track.scrollLeft - (slide.offsetLeft - track.offsetLeft));
                    if (dist < minDist) {
                        minDist = dist;
                        closest = si;
                    }
                });
                if (closest !== index) {
                    index = closest;
                    dots.forEach(function (dot, di) {
                        var active = di === index;
                        dot.classList.toggle('is-active', active);
                        dot.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                }
            }, 80);
        }, { passive: true });

        root.addEventListener('mouseenter', stopAuto);
        root.addEventListener('mouseleave', startAuto);

        if (window.matchMedia) {
            window.matchMedia('(min-width: 1024px)').addEventListener('change', startAuto);
        }

        startAuto();
    });
})();
