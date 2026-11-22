/*
 * The loader for an embeddable widget.
 *
 * An owner pastes one of two snippets into their own website. The
 * iframe snippet is a plain iframe and needs nothing from this file.
 * The script snippet loads this file, and this file puts a plain
 * iframe in the place of the script tag, so the two snippets end up
 * showing the same thing.
 *
 * The file is deliberately small and has no dependencies. It is served
 * straight from the public directory, so it does not go through the
 * build, and it must keep working when the application is upgraded.
 *
 * Three rules shape what it does:
 *
 *  - the widget id is checked before it is used, so a script tag that
 *    was tampered with cannot turn into a path of somebody's making;
 *  - the application origin comes from the src of this script, never
 *    from the page that embeds it, so the frame always points at the
 *    application that served the loader;
 *  - nothing is read from the host page, nothing is written to the
 *    global object, and no markup is built out of a string.
 */

const PUBLIC_ID_PATTERN = /^[A-Za-z0-9]{32}$/;

const LOADER_PATH = '/embed.js';

const HEIGHT = 600;

function originFrom(script) {
    const src = script.getAttribute('src');

    if (!src) {
        return null;
    }

    try {
        const url = new URL(src, window.location.href);

        return url.origin === 'null' ? null : url.origin;
    } catch (error) {
        return null;
    }
}

function loadsThisLoader(script) {
    const src = script.getAttribute('src');

    if (!src) {
        return false;
    }

    try {
        const url = new URL(src, window.location.href);

        return url.pathname === LOADER_PATH;
    } catch (error) {
        return false;
    }
}

function publicIdFrom(script) {
    const value = script.getAttribute('data-widget');

    if (!value || !PUBLIC_ID_PATTERN.test(value)) {
        return null;
    }

    return value;
}

function replaceScriptWithFrame(script, publicId, origin) {
    const frame = document.createElement('iframe');

    frame.setAttribute('src', origin + '/widget/' + publicId);
    frame.setAttribute('width', '100%');
    frame.setAttribute('height', String(HEIGHT));
    frame.setAttribute('frameborder', '0');
    frame.setAttribute('loading', 'lazy');
    frame.setAttribute('title', 'Testimonials');
    frame.setAttribute('style', 'border:0;display:block;max-width:100%;');

    script.replaceWith(frame);
}

function setUp(script) {
    if (!script || script.getAttribute('data-klw-embed-ready') === 'true') {
        return;
    }

    if (!loadsThisLoader(script)) {
        return;
    }

    const publicId = publicIdFrom(script);

    if (publicId === null) {
        return;
    }

    const origin = originFrom(script);

    if (origin === null) {
        return;
    }

    script.setAttribute('data-klw-embed-ready', 'true');

    replaceScriptWithFrame(script, publicId, origin);
}

function setUpAll() {
    const scripts = document.querySelectorAll('script[src][data-widget]');

    scripts.forEach(setUp);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setUpAll, { once: true });
} else {
    setUpAll();
}
