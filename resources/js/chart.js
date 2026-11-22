/*
 * The submissions chart on the dashboard overview.
 *
 * The chart is drawn by Chart.js. The numbers are written by the server
 * into a script block next to the canvas, so the chart and the page can
 * never disagree about what was counted.
 *
 * The script follows the same rules as app.js:
 *
 *  - nothing is read from the host page and nothing is written to the
 *    global object;
 *  - the setup is idempotent, so running it twice does not draw two
 *    charts on top of each other;
 *  - the script is loaded with defer, so the DOM is ready when it runs.
 *
 * The colours are read from the design tokens of the application, so the
 * chart looks like the rest of the dashboard and follows the theme
 * rather than carrying colours of its own.
 */

import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';

// Only the pieces the dashboard chart uses are registered, so the
// bundle does not carry the whole library.
Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    Legend,
);

/**
 * The colour the line of the chart is drawn in.
 *
 * The token is an oklch value in the stylesheet, and a canvas cannot
 * read that. The token is therefore resolved in the browser: a throw
 * away element is given the token as its colour, and the colour the
 * browser actually resolved it to is read back. What comes back is an
 * rgb value that the canvas understands, whatever form the token was
 * written in.
 *
 * The element is removed again straight away, so nothing is left on the
 * page.
 */
function lineColour() {
    const styles = window.getComputedStyle(document.documentElement);

    const token = styles.getPropertyValue('--color-brand-500').trim();

    if (! token) {
        return '#7c3aed';
    }

    const probe = document.createElement('span');

    probe.style.color = `var(--color-brand-500)`;
    probe.style.display = 'none';

    document.documentElement.appendChild(probe);

    const resolved = window.getComputedStyle(probe).color;

    probe.remove();

    return resolved || '#7c3aed';
}

/**
 * The colour the area under the line is filled with.
 *
 * The fill is the brand colour with an alpha channel, so the area under
 * the line is genuinely translucent and whatever is behind the chart
 * shows through it. An rgba value is understood by every browser that
 * can draw a canvas, so the fill is a real translucent colour rather
 * than a colour mixed by the browser.
 *
 * The resolved colour is read twice: once as a six digit hex value,
 * which is converted directly, and once as an rgb value, whose channels
 * are parsed. The violet is used only when the colour cannot be read
 * at all.
 */
function fillColour() {
    const line = lineColour();

    // A six digit hex colour is converted to rgba, so the fill keeps
    // the exact colour of the line and only differs in how solid it is.
    const hex = /^#([0-9a-fA-F]{6})$/.exec(line);

    if (hex) {
        const value = hex[1];

        const red = parseInt(value.slice(0, 2), 16);
        const green = parseInt(value.slice(2, 4), 16);
        const blue = parseInt(value.slice(4, 6), 16);

        return `rgba(${red}, ${green}, ${blue}, 0.12)`;
    }

    // The browser resolves the token to an rgb value, whose channels are
    // read here so the fill is the colour of the line.
    const rgb = /^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i.exec(line);

    if (rgb) {
        return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, 0.12)`;
    }

    return 'rgba(124, 58, 237, 0.12)';
}

/**
 * The colour the text of the axes and the tooltip is drawn in.
 */
function inkColour() {
    const styles = window.getComputedStyle(document.documentElement);

    return styles.getPropertyValue('--color-ink-muted').trim() || '#6b7280';
}

/**
 * The colour the grid lines are drawn in.
 */
function borderColour() {
    const styles = window.getComputedStyle(document.documentElement);

    return styles.getPropertyValue('--color-border-subtle').trim() || '#e5e7eb';
}

/**
 * Read the series the server wrote next to the canvas.
 *
 * The data is in a script block of type application/json, so it is not
 * executed as code and it is read exactly as it was written. A block
 * that is missing or that does not hold a readable series returns null,
 * and the chart is simply not drawn.
 */
function readSeries() {
    const block = document.querySelector('[data-submission-chart-data]');

    if (!block) {
        return null;
    }

    try {
        const parsed = JSON.parse(block.textContent);

        if (!parsed || !Array.isArray(parsed.labels) || !Array.isArray(parsed.counts)) {
            return null;
        }

        if (parsed.labels.length === 0 || parsed.labels.length !== parsed.counts.length) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

/**
 * Draw the chart of one canvas.
 */
function draw(canvas) {
    if (canvas.dataset.klwChartReady === 'true') {
        return;
    }

    const series = readSeries();

    if (series === null) {
        return;
    }

    canvas.dataset.klwChartReady = 'true';

    const line = lineColour();

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: series.labels,
            datasets: [
                {
                    label: 'Submissions',
                    data: series.counts,
                    borderColor: line,
                    backgroundColor: fillColour(),
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: line,
                    pointHoverBorderColor: line,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // The chart redraws itself when the viewport changes, so it
            // fills the card it sits in whatever width that card has.
            resizeDelay: 100,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    displayColors: false,
                    backgroundColor: inkColour(),
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    border: {
                        display: false,
                    },
                    ticks: {
                        color: inkColour(),
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: borderColour(),
                    },
                    border: {
                        display: false,
                    },
                    ticks: {
                        color: inkColour(),
                        precision: 0,
                        maxTicksLimit: 5,
                    },
                },
            },
        },
    });
}

/**
 * Draw every chart on the page.
 */
function setUpCharts() {
    document.querySelectorAll('canvas[data-submission-chart]').forEach(draw);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setUpCharts, { once: true });
} else {
    setUpCharts();
}
