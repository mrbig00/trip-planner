/**
 * Google Analytics (GA4), gated behind cookie consent.
 *
 * Nothing here ever loads gtag.js, sets a GA cookie, or sends an event
 * until the visitor actively accepts analytics cookies via the banner
 * below — that's the whole point. The choice is remembered in
 * localStorage (not a cookie — no need for one just to remember that
 * someone declined cookies) so the banner only asks once per browser.
 *
 * The measurement ID itself is injected by the server as a <meta> tag
 * (see resources/views/partials/google-analytics.blade.php) since this is
 * a plain bundled module with no access to Laravel's config().
 *
 * It's always safe to call trackEvent() regardless of consent status — page
 * views on Livewire's wire:navigate transitions, and custom events
 * dispatched from Livewire/Volt components via the TracksAnalyticsEvents
 * trait, both go through it. Anything that fires before consent is granted
 * is dropped, not queued: an action taken before the visitor accepted must
 * never reach Google, even after they do accept.
 */

const CONSENT_STORAGE_KEY = 'ga-consent';

/** @type {'pending' | 'granted' | 'denied'} */
let consentStatus = 'pending';
let gaLoaded = false;

function readMeasurementId() {
    return document.querySelector('meta[name="ga-measurement-id"]')?.content || null;
}

function readPrivacyPolicyUrl() {
    return document.querySelector('meta[name="privacy-policy-url"]')?.content || null;
}

function readStoredConsent() {
    try {
        const raw = localStorage.getItem(CONSENT_STORAGE_KEY);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeStoredConsent(status) {
    try {
        localStorage.setItem(CONSENT_STORAGE_KEY, JSON.stringify({ status, at: new Date().toISOString() }));
    } catch {
        // Private browsing, storage disabled, quota exceeded, etc. — the
        // banner will just be shown again next visit, which is safe.
    }
}

export function trackEvent(name, params = {}) {
    // Nothing is buffered for later: an event that happens before consent is
    // granted must never reach Google, including once consent does arrive —
    // so anything that fires while the banner is still pending is dropped,
    // not queued.
    if (consentStatus !== 'granted') {
        return;
    }

    window.gtag('event', name, params);
}

function trackPageView() {
    trackEvent('page_view', {
        page_location: window.location.href,
        page_path: window.location.pathname + window.location.search,
        page_title: document.title,
    });
}

function loadGoogleAnalytics(measurementId) {
    if (gaLoaded) {
        // gtag.js is already on the page from an earlier "granted" (this is
        // a re-accept after a decline, in the same session) — restore the
        // Consent Mode signal that revokeGoogleAnalytics() turned off, but
        // don't re-inject the script or re-run the one-time setup below.
        window.gtag('consent', 'update', { analytics_storage: 'granted' });
        trackPageView();

        return;
    }

    gaLoaded = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () {
        window.dataLayer.push(arguments);
    };
    window.gtag('consent', 'update', { analytics_storage: 'granted' });
    window.gtag('js', new Date());
    // Page views are sent manually (trackPageView, below) rather than
    // automatically, so the one for the current page only fires once
    // consent is granted and gtag.js loads — which may be a while after the
    // real page load, if the visitor takes time to decide.
    window.gtag('config', measurementId, { send_page_view: false });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);

    trackPageView();
}

/**
 * Build the list of `domain` attribute values a GA cookie could plausibly
 * have been set with, so it can actually be deleted: gtag.js's default
 * `cookie_domain: 'auto'` sets `_ga`/`_ga_*` on the parent registrable
 * domain with a leading dot (e.g. `.example.com`) whenever the site is
 * served from a subdomain (e.g. `app.example.com`), not just the exact
 * hostname. This walks up the label chain trying each parent domain, both
 * bare and dotted — a reasonable heuristic without a public-suffix-list
 * dependency; it doesn't special-case multi-part suffixes like `co.uk`, but
 * those aren't in play for this app's own domain.
 */
function cookieDomainVariants(hostname) {
    const labels = hostname.split('.');
    const variants = [];

    for (let i = 0; i < labels.length - 1; i++) {
        const domain = labels.slice(i).join('.');
        variants.push(domain, `.${domain}`);
    }

    return variants.length ? variants : [hostname];
}

/**
 * Undo loadGoogleAnalytics() when consent is withdrawn after having been
 * granted: tell gtag.js to stop collecting (Google's Consent Mode signal —
 * the library is already loaded and would otherwise keep measuring
 * engagement on its own even without our trackEvent calls), and delete the
 * cookies it already set so nothing lingers in the browser.
 */
function revokeGoogleAnalytics(measurementId) {
    if (typeof window.gtag === 'function') {
        window.gtag('consent', 'update', { analytics_storage: 'denied' });
    }

    const idSuffix = measurementId.replace(/^G-/, '');
    const domains = cookieDomainVariants(window.location.hostname);

    for (const name of ['_ga', `_ga_${idSuffix}`]) {
        document.cookie = `${name}=; Max-Age=0; path=/`;
        for (const domain of domains) {
            document.cookie = `${name}=; Max-Age=0; path=/; domain=${domain}`;
        }
    }
}

document.addEventListener('livewire:navigated', () => {
    if (gaLoaded) {
        trackPageView();
    }

    // wire:navigate morphs the document between pages, and our banner/reopen
    // button are plain client-injected DOM nodes with no counterpart in the
    // server-rendered HTML — the morph can drop them. Re-assert whichever one
    // belongs on screen; both are no-ops if already present.
    ensureConsentUi();
});

document.addEventListener('livewire:init', () => {
    Livewire.on('analytics-event', ({ name, params }) => trackEvent(name, params));
});

// Exposed so the queued-events flush in the google-analytics partial (for
// events raised outside of a Livewire request, e.g. login/registration)
// can reach it without importing a module from an inline script.
window.trackEvent = trackEvent;

// ---------------------------------------------------------------------
// Consent banner
// ---------------------------------------------------------------------

function injectStyles() {
    if (document.getElementById('ga-consent-styles')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'ga-consent-styles';
    style.textContent = `
        .ga-consent-banner {
            position: fixed;
            z-index: 2147483000;
            left: 50%;
            bottom: 1rem;
            transform: translateX(-50%);
            width: min(40rem, calc(100vw - 2rem));
            background: #18181b;
            color: #e4e4e7;
            border: 1px solid #3f3f46;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            padding: 1.25rem;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        .ga-consent-banner p {
            margin: 0;
            flex: 1 1 20rem;
            color: #e4e4e7;
        }
        .ga-consent-banner a {
            color: #93c5fd;
            text-decoration: underline;
        }
        .ga-consent-banner a:hover {
            color: #bfdbfe;
        }
        .ga-consent-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
            margin-left: auto;
        }
        .ga-consent-actions button {
            font: inherit;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .ga-consent-actions button:focus-visible {
            outline: 2px solid #3987e5;
            outline-offset: 2px;
        }
        .ga-consent-accept {
            background: #3987e5;
            color: #fff;
        }
        .ga-consent-accept:hover {
            background: #2f74c9;
        }
        .ga-consent-reject {
            background: transparent;
            border-color: #52525b;
            color: #e4e4e7;
        }
        .ga-consent-reject:hover {
            background: #27272a;
        }
        .ga-consent-reopen {
            position: fixed;
            z-index: 2147483000;
            left: 1rem;
            bottom: 1rem;
            background: #18181b;
            color: #a1a1aa;
            border: 1px solid #3f3f46;
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
            font: 12px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }
        .ga-consent-reopen:hover {
            color: #e4e4e7;
        }
        @media (max-width: 30rem) {
            .ga-consent-actions {
                width: 100%;
                margin-left: 0;
            }
            .ga-consent-actions button {
                flex: 1;
            }
        }
    `;
    document.head.appendChild(style);
}

function removeElement(id) {
    document.getElementById(id)?.remove();
}

function showReopenButton() {
    removeElement('ga-consent-reopen');

    const button = document.createElement('button');
    button.id = 'ga-consent-reopen';
    button.type = 'button';
    button.className = 'ga-consent-reopen';
    button.textContent = 'Cookie settings';
    button.setAttribute('aria-label', 'Change cookie preferences');
    button.addEventListener('click', () => showConsentBanner(readMeasurementId(), { reopened: true }));

    document.body.appendChild(button);
}

function decide(status, measurementId) {
    consentStatus = status;
    writeStoredConsent(status);
    removeElement('ga-consent-banner');
    showReopenButton();

    if (status === 'granted') {
        loadGoogleAnalytics(measurementId);
    } else {
        revokeGoogleAnalytics(measurementId);
    }
}

function showConsentBanner(measurementId, { reopened = false } = {}) {
    removeElement('ga-consent-banner');
    injectStyles();

    const banner = document.createElement('div');
    banner.id = 'ga-consent-banner';
    banner.className = 'ga-consent-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-live', 'polite');
    banner.setAttribute('aria-label', 'Cookie consent');

    const text = document.createElement('p');
    const message = reopened
        ? 'Update your cookie preferences: allow Google Analytics to help us understand how Trip Planner is used?'
        : 'We use Google Analytics to understand how Trip Planner is used. No analytics cookies are set unless you accept.';
    text.appendChild(document.createTextNode(message + ' '));

    const privacyPolicyUrl = readPrivacyPolicyUrl();
    if (privacyPolicyUrl) {
        const link = document.createElement('a');
        link.href = privacyPolicyUrl;
        link.textContent = 'Privacy policy';
        text.appendChild(link);
    }

    banner.appendChild(text);

    const actions = document.createElement('div');
    actions.className = 'ga-consent-actions';

    const reject = document.createElement('button');
    reject.type = 'button';
    reject.className = 'ga-consent-reject';
    reject.textContent = 'Decline';
    reject.addEventListener('click', () => decide('denied', measurementId));

    const accept = document.createElement('button');
    accept.type = 'button';
    accept.className = 'ga-consent-accept';
    accept.textContent = 'Accept';
    accept.addEventListener('click', () => decide('granted', measurementId));

    actions.append(reject, accept);
    banner.appendChild(actions);

    removeElement('ga-consent-reopen');
    document.body.appendChild(banner);
}

function ensureConsentUi() {
    const measurementId = readMeasurementId();

    if (!measurementId) {
        return;
    }

    if (consentStatus === 'pending') {
        if (!document.getElementById('ga-consent-banner')) {
            showConsentBanner(measurementId);
        }
    } else if (!document.getElementById('ga-consent-reopen')) {
        injectStyles();
        showReopenButton();
    }
}

function initConsent() {
    const measurementId = readMeasurementId();

    if (!measurementId) {
        return; // Analytics isn't configured server-side — nothing to ask about.
    }

    const stored = readStoredConsent();

    if (stored?.status === 'granted') {
        consentStatus = 'granted';
        injectStyles();
        showReopenButton();
        loadGoogleAnalytics(measurementId);
    } else if (stored?.status === 'denied') {
        consentStatus = 'denied';
        injectStyles();
        showReopenButton();
        // Defense in depth: clean up any GA cookie that might still be
        // sitting in the browser from before this consent gate existed.
        revokeGoogleAnalytics(measurementId);
    } else {
        showConsentBanner(measurementId);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConsent);
} else {
    initConsent();
}
