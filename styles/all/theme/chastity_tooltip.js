/**
 * Chastity Tracker — Tooltip mobile-friendly
 * Affiche un popup au tap/click sur les cellules calendrier avec data-tooltip,
 * et au survol sur desktop. Compatible mobile, tablette et desktop.
 */
(function() {
    'use strict';

    var bubble = null;
    var currentTarget = null;

    function createBubble() {
        if (bubble) return bubble;
        bubble = document.createElement('div');
        bubble.className = 'chastity-tt-bubble';
        bubble.style.cssText = [
            'position:absolute',
            'z-index:9999',
            'background:#2E4057',
            'color:#fff',
            'padding:8px 12px',
            'border-radius:6px',
            'font-size:13px',
            'line-height:1.5',
            'max-width:280px',
            'box-shadow:0 4px 12px rgba(0,0,0,0.25)',
            'pointer-events:none',
            'white-space:pre-line',
            'display:none'
        ].join(';');
        document.body.appendChild(bubble);
        return bubble;
    }

    function showBubble(target) {
        var text = target.getAttribute('data-tooltip');
        if (!text) return;
        var b = createBubble();
        b.textContent = text;
        b.style.display = 'block';
        var rect = target.getBoundingClientRect();
        var bRect = b.getBoundingClientRect();
        var top = window.scrollY + rect.bottom + 6;
        var left = window.scrollX + rect.left + (rect.width / 2) - (bRect.width / 2);
        // Garder dans l'écran
        if (left < 8) left = 8;
        var maxLeft = document.documentElement.clientWidth - bRect.width - 8;
        if (left > maxLeft) left = maxLeft;
        b.style.top = top + 'px';
        b.style.left = left + 'px';
        currentTarget = target;
    }

    function hideBubble() {
        if (bubble) bubble.style.display = 'none';
        currentTarget = null;
    }

    // Délégation : un seul handler global pour toute la page
    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-tooltip]');
        if (target) {
            if (currentTarget === target) {
                hideBubble();
            } else {
                showBubble(target);
            }
            e.stopPropagation();
        } else if (currentTarget) {
            hideBubble();
        }
    });

    // Hover desktop : on garde le comportement title-like
    document.addEventListener('mouseover', function(e) {
        var target = e.target.closest('[data-tooltip]');
        if (target && !('ontouchstart' in window)) {
            showBubble(target);
        }
    });
    document.addEventListener('mouseout', function(e) {
        var target = e.target.closest('[data-tooltip]');
        if (target && !('ontouchstart' in window)) {
            hideBubble();
        }
    });

    // Fermer sur scroll/resize
    window.addEventListener('scroll', hideBubble, true);
    window.addEventListener('resize', hideBubble);
})();
