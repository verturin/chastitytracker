/* Chastity cage lightbox — vanilla JS, no dependencies */
(function() {
    'use strict';

    var overlay, imgEl, counterEl, currentSet = [], currentIdx = 0;

    function ensureOverlay() {
        if (overlay) { return; }
        overlay = document.createElement('div');
        overlay.className = 'chastity-lightbox-overlay';
        overlay.innerHTML =
            '<div class="chastity-lightbox-content">' +
                '<button class="chastity-lightbox-close" type="button" aria-label="Fermer">×</button>' +
                '<button class="chastity-lightbox-prev" type="button" aria-label="Précédent">‹</button>' +
                '<img alt="" />' +
                '<button class="chastity-lightbox-next" type="button" aria-label="Suivant">›</button>' +
                '<div class="chastity-lightbox-counter"></div>' +
            '</div>';
        document.body.appendChild(overlay);
        imgEl = overlay.querySelector('img');
        counterEl = overlay.querySelector('.chastity-lightbox-counter');

        overlay.querySelector('.chastity-lightbox-close').addEventListener('click', close);
        overlay.querySelector('.chastity-lightbox-prev').addEventListener('click', function(e) { e.stopPropagation(); prev(); });
        overlay.querySelector('.chastity-lightbox-next').addEventListener('click', function(e) { e.stopPropagation(); next(); });
        overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function(e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowLeft') prev();
            else if (e.key === 'ArrowRight') next();
        });
    }

    function open(urls, idx) {
        ensureOverlay();
        currentSet = urls;
        currentIdx = idx || 0;
        update();
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function update() {
        if (!currentSet.length) return;
        imgEl.src = currentSet[currentIdx];
        counterEl.textContent = (currentIdx + 1) + ' / ' + currentSet.length;
        var prevBtn = overlay.querySelector('.chastity-lightbox-prev');
        var nextBtn = overlay.querySelector('.chastity-lightbox-next');
        prevBtn.style.display = currentSet.length > 1 ? '' : 'none';
        nextBtn.style.display = currentSet.length > 1 ? '' : 'none';
    }

    function prev() {
        if (!currentSet.length) return;
        currentIdx = (currentIdx - 1 + currentSet.length) % currentSet.length;
        update();
    }

    function next() {
        if (!currentSet.length) return;
        currentIdx = (currentIdx + 1) % currentSet.length;
        update();
    }

    // Attach to all .chastity-cage-card-photo elements with data-photos attribute
    function init() {
        var photos = document.querySelectorAll('.chastity-cage-card-photo[data-photos]');
        photos.forEach(function(el) {
            el.addEventListener('click', function() {
                try {
                    var urls = JSON.parse(el.getAttribute('data-photos'));
                    if (urls && urls.length) { open(urls, 0); }
                } catch (e) {}
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
