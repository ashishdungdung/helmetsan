(function () {
    'use strict';

    var toggle = document.querySelector('.hs-mega-menu-toggle');
    var panel = document.getElementById('hs-mega-menu-panel');
    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        panel.hidden = expanded;
    });
})();
