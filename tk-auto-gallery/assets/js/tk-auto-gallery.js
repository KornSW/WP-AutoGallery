(function () {
    'use strict';

    /**
     * Initializes all galleries that have not been initialized yet.
     *
     * @returns {void}
     */
    function initializeGalleries() {
        var galleries = document.querySelectorAll('[data-tk-auto-gallery]:not([data-tk-auto-gallery-ready])');
        var index = 0;

        for (index = 0; index < galleries.length; index++) {
            galleries[index].setAttribute('data-tk-auto-gallery-ready', '1');
            window.TkLightbox.bind(galleries[index]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeGalleries);
    } else {
        initializeGalleries();
    }
}());
