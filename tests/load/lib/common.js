/*
 * Shared helpers for EXAMIS k6 scripts.
 *
 * NEVER point these scripts at production. Use a local or staging stack
 * seeded with `php artisan db:seed --class=LoadTestSeeder`.
 *
 *   BASE_URL      target (default http://127.0.0.1:8000)
 *   USER_OFFSET   first loadtest account number for this run (default 1)
 */
import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const USER_OFFSET = Number(__ENV.USER_OFFSET || 1);

if (
    /examis\.(gov|edu)|production/i.test(BASE_URL) &&
    __ENV.I_HAVE_WRITTEN_APPROVAL !== 'yes'
) {
    fail(`Refusing to load-test ${BASE_URL} without explicit approval.`);
}

/** Baseline thresholds from the acceptance criteria (docs/testing/PERFORMANCE.md). */
export const thresholds = {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{type:read}': ['p(95)<1000'],
    'http_req_duration{type:write}': ['p(95)<2000'],
    checks: ['rate>0.99'],
};

/** Each VU uses its own synthetic account so data never overlaps. */
export function usernameFor(vu) {
    return `loadtest.${String(USER_OFFSET + vu - 1).padStart(4, '0')}`;
}

function xsrfHeader(jar) {
    const cookies = jar.cookiesForURL(BASE_URL);
    const token = cookies['XSRF-TOKEN']
        ? decodeURIComponent(cookies['XSRF-TOKEN'][0])
        : '';

    return {
        'X-XSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html, application/json',
    };
}

export function get(path, name) {
    return http.get(`${BASE_URL}${path}`, {
        tags: { type: 'read', name: name || path },
    });
}

/** Form-style write with the CSRF header taken from the XSRF cookie. */
export function send(method, path, body, name) {
    const jar = http.cookieJar();
    const params = {
        headers: xsrfHeader(jar),
        tags: { type: 'write', name: name || path },
        redirects: 5,
    };

    return http.request(method, `${BASE_URL}${path}`, body, params);
}

export function login(username) {
    get('/login', 'GET /login');
    const response = send(
        'POST',
        '/login',
        { username, password: 'password' },
        'POST /login',
    );

    check(response, {
        'logged in': (r) => r.status === 200 && !r.url.includes('/login'),
    });

    return response;
}

export function logout() {
    send('POST', '/logout', {}, 'POST /logout');
}

/** Reads the Inertia page object embedded in a full HTML response. */
export function inertiaPage(response) {
    const match =
        /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/.exec(
            response.body || '',
        ) || /data-page="([^"]+)"/.exec(response.body || '');

    if (!match) {
        return null;
    }

    const raw = match[1].includes('&quot;')
        ? match[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')
        : match[1];

    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
}
