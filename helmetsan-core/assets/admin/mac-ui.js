(function () {
    if (!document.body.classList.contains('helmetsan-admin')) {
        return;
    }

    document.querySelectorAll('.notice.is-dismissible').forEach(function (el) {
        el.style.borderRadius = '12px';
    });
})();

