/*
 * TK Lightbox Viewer 1.0.0
 * License: MIT
 * Lightweight image and HTML5 video lightbox for TK Auto Gallery.
 */
(function () {
    'use strict';

    var currentItems = [];
    var currentIndex = 0;
    var overlay = null;
    var content = null;
    var caption = null;

    /**
     * Creates the overlay once and wires global controls.
     *
     * @returns {void}
     */
    function ensureOverlay() {
        if (overlay !== null) {
            return;
        }

        overlay = document.createElement('div');
        overlay.className = 'tk-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = '<button class="tk-lightbox__close" type="button" aria-label="Close">×</button><button class="tk-lightbox__prev" type="button" aria-label="Previous">‹</button><div class="tk-lightbox__content"></div><button class="tk-lightbox__next" type="button" aria-label="Next">›</button><div class="tk-lightbox__caption"></div>';
        document.body.appendChild(overlay);

        content = overlay.querySelector('.tk-lightbox__content');
        caption = overlay.querySelector('.tk-lightbox__caption');

        overlay.querySelector('.tk-lightbox__close').addEventListener('click', close);
        overlay.querySelector('.tk-lightbox__prev').addEventListener('click', showPrevious);
        overlay.querySelector('.tk-lightbox__next').addEventListener('click', showNext);
        overlay.addEventListener('click', handleOverlayClick);
        document.addEventListener('keydown', handleKeyDown);
    }

    /**
     * Handles clicks on the overlay background.
     *
     * @param {MouseEvent} event Click event.
     * @returns {void}
     */
    function handleOverlayClick(event) {
        if (event.target === overlay) {
            close();
        }
    }

    /**
     * Handles keyboard navigation.
     *
     * @param {KeyboardEvent} event Keyboard event.
     * @returns {void}
     */
    function handleKeyDown(event) {
        if (overlay === null || overlay.classList.contains('tk-lightbox--open') === false) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        }

        if (event.key === 'ArrowLeft') {
            showPrevious();
        }

        if (event.key === 'ArrowRight') {
            showNext();
        }
    }

    /**
     * Opens the lightbox at a specific item index.
     *
     * @param {number} index Item index.
     * @returns {void}
     */
    function open(index) {
        ensureOverlay();
        currentIndex = index;
        document.documentElement.classList.add('tk-lightbox-lock');
        overlay.classList.add('tk-lightbox--open');
        renderCurrentItem();
    }

    /**
     * Closes the lightbox and clears the active media.
     *
     * @returns {void}
     */
    function close() {
        if (overlay === null) {
            return;
        }

        overlay.classList.remove('tk-lightbox--open');
        document.documentElement.classList.remove('tk-lightbox-lock');
        content.innerHTML = '';
    }

    /**
     * Shows the previous item.
     *
     * @returns {void}
     */
    function showPrevious() {
        if (currentItems.length === 0) {
            return;
        }

        currentIndex = currentIndex - 1;
        if (currentIndex < 0) {
            currentIndex = currentItems.length - 1;
        }

        renderCurrentItem();
    }

    /**
     * Shows the next item.
     *
     * @returns {void}
     */
    function showNext() {
        if (currentItems.length === 0) {
            return;
        }

        currentIndex = currentIndex + 1;
        if (currentIndex >= currentItems.length) {
            currentIndex = 0;
        }

        renderCurrentItem();
    }

    /**
     * Renders the currently selected item.
     *
     * @returns {void}
     */
    function renderCurrentItem() {
        var item = currentItems[currentIndex];
        var media = null;

        content.innerHTML = '';
        caption.textContent = item.title;

        if (item.type === 'video') {
            media = document.createElement('video');
            media.controls = true;
            media.autoplay = true;
            media.playsInline = true;
            media.src = item.href;
            if (item.poster !== '') {
                media.poster = item.poster;
            }
        } else {
            media = document.createElement('img');
            media.src = item.href;
            media.alt = item.title;
        }

        media.className = 'tk-lightbox__media';
        content.appendChild(media);
    }

    /**
     * Binds a gallery element to the lightbox viewer.
     *
     * @param {Element} gallery Gallery element.
     * @returns {void}
     */
    function bind(gallery) {
        var links = gallery.querySelectorAll('[data-tk-lightbox-item]');
        var index = 0;

        for (index = 0; index < links.length; index++) {
            links[index].addEventListener('click', handleItemClick);
        }
    }

    /**
     * Handles gallery item clicks.
     *
     * @param {MouseEvent} event Click event.
     * @returns {void}
     */
    function handleItemClick(event) {
        var link = event.currentTarget;
        var gallery = link.closest('[data-tk-auto-gallery]');
        var links = gallery.querySelectorAll('[data-tk-lightbox-item]');
        var index = 0;

        event.preventDefault();
        currentItems = [];

        for (index = 0; index < links.length; index++) {
            currentItems.push({
                href: links[index].getAttribute('href'),
                type: links[index].getAttribute('data-type'),
                title: links[index].getAttribute('data-title') || '',
                poster: links[index].getAttribute('data-poster') || ''
            });

            if (links[index] === link) {
                currentIndex = index;
            }
        }

        open(currentIndex);
    }

    window.TkLightbox = {
        bind: bind
    };
}());
