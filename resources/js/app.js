/**
 * The live character counter for the testimonial edit form.
 *
 * A textarea opts in with data-character-counter="<name>", and the element
 * that shows the count carries data-character-counter-output="<name>". The
 * two are matched by that name.
 *
 * The maximum is read from data-character-counter-max so the number lives in
 * one place. Laravel's max rule stays the authority on what is accepted; this
 * only keeps the field from growing past the limit in the first place.
 */

/**
 * The number of Unicode code points in a string.
 *
 * Array.from() splits by code point, which is the unit PHP's mb_strlen()
 * counts, so this agrees with the server side max rule. The native length
 * counts UTF-16 code units and would disagree on anything outside the
 * Basic Multilingual Plane, such as an emoji.
 */
function countCharacters(value) {
    return Array.from(value).length;
}

/**
 * Cut a string down to the given number of code points.
 *
 * The string is split into code points first, so a surrogate pair is never
 * cut in half.
 */
function trimToCharacters(value, max) {
    const characters = Array.from(value);

    return characters.length > max ? characters.slice(0, max).join('') : value;
}

/**
 * Find the output element that belongs to this field.
 *
 * The search is limited to the form the field belongs to, so a field can
 * never write its count into the output of a counter in another form. The
 * name is compared as an attribute value rather than used to build a
 * selector, so no name can change what is matched.
 */
function findOutput(field, name) {
    const scope = field.closest('form') ?? document;

    return Array.from(scope.querySelectorAll('[data-character-counter-output]'))
        .find((element) => element.dataset.characterCounterOutput === name) ?? null;
}

/**
 * Set up the counter for a single textarea.
 */
function setUpCounter(field) {
    // The marker keeps the setup idempotent. Running it twice on the same
    // element would otherwise attach the listener a second time and make
    // every keystroke run the handler twice.
    if (field.dataset.characterCounterReady === 'true') {
        return;
    }

    const name = field.dataset.characterCounter;

    if (!name) {
        return;
    }

    const output = findOutput(field, name);

    if (!output) {
        return;
    }

    const max = Number.parseInt(field.dataset.characterCounterMax ?? '', 10) || 2000;

    field.dataset.characterCounterReady = 'true';

    const update = () => {
        // The value is trimmed first, so the count that is reported is
        // always the count that is really in the field.
        const trimmed = trimToCharacters(field.value, max);

        if (trimmed !== field.value) {
            field.value = trimmed;
        }

        output.textContent = `${countCharacters(field.value)} of ${max} characters used`;
    };

    field.addEventListener('input', update);

    // Start from whatever the field already holds, which is the stored
    // testimonial the first time and the old input after a validation
    // error.
    update();
}

/**
 * Set up every counter on the page.
 */
function setUpCharacterCounters() {
    document.querySelectorAll('[data-character-counter]').forEach(setUpCounter);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setUpCharacterCounters, { once: true });
} else {
    setUpCharacterCounters();
}
