/*
 * The behaviour of an embeddable widget.
 *
 * A widget is shown on somebody else's website, so this script must not
 * touch anything outside its own widget and must not put anything on
 * the global object.
 *
 * The script is loaded with defer, so the DOM is ready when it runs.
 */

/**
 * The number of milliseconds between two automatic advances.
 */
const CAROUSEL_INTERVAL = 4000;

/**
 * The gap between two cards, in pixels.
 *
 * The gap is read from the rendered card rather than hard coded, so the
 * carousel always advances by exactly one card and the gap between the
 * cards, whatever the stylesheet says it is.
 */
function gapBetween(card) {
    const style = window.getComputedStyle(card);

    return Number.parseFloat(style.marginRight) || 0;
}

/**
 * Move the track forward by one card.
 *
 * The card width is measured rather than assumed, so the carousel works
 * with any card width and with a host page that changes the font size.
 * The gap between the cards is read from the same card, so the advance
 * lands exactly on the start of the next card.
 *
 * The scroll is applied to the track, which is the element that
 * actually scrolls.
 */
function advance(track) {
    const card = track.querySelector('.klw-card');

    if (!card) {
        return;
    }

    const distance = card.getBoundingClientRect().width + gapBetween(card);

    track.scrollBy({ left: distance, behavior: 'smooth' });
}

/**
 * Move the track back to its first card.
 *
 * The loop is driven by the scroll position rather than by an index,
 * so the carousel stays correct when the visitor scrolls it by hand.
 */
function rewind(track) {
    if (track.scrollLeft <= 1) {
        return;
    }

    track.scrollTo({ left: 0, behavior: 'smooth' });
}

/**
 * Whether the track is scrolled to its end.
 *
 * The measurements are taken from the track, because the track is what
 * scrolls. Measuring the widget root would report the width of the
 * whole widget rather than the width of the visible part of the row.
 */
function atEnd(track) {
    return track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
}

/**
 * Start the automatic movement of a track.
 *
 * The timer is only started when the visitor has not asked for reduced
 * motion, and it is stopped as soon as the visitor interacts with the
 * carousel. A moving carousel is a distraction for some people and a
 * problem for others, so the animation setting and the visitor's own
 * preference both decide.
 */
function startAnimation(track) {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced) {
        return;
    }

    const timer = window.setInterval(() => {
        if (atEnd(track)) {
            rewind(track);
        } else {
            advance(track);
        }
    }, CAROUSEL_INTERVAL);

    track.addEventListener(
        'pointerdown',
        () => {
            window.clearInterval(timer);
        },
        { once: true }
    );
}

/**
 * Set up every carousel on the page.
 *
 * The track carries data-klw-track, so it is found directly rather
 * than by searching for a descendant of a widget root. The root is
 * read only for the animation setting.
 */
function setUpCarousels() {
    document.querySelectorAll('[data-klw-track]').forEach((track) => {
        if (track.dataset.klwReady === 'true') {
            return;
        }

        const widget = track.closest('.klw-widget');

        if (!widget) {
            return;
        }

        track.dataset.klwReady = 'true';

        if (widget.dataset.klwAnimation === 'true') {
            startAnimation(track);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setUpCarousels, { once: true });
} else {
    setUpCarousels();
}
