/*
 * Shared helpers for the external_link k6 suite.
 *
 * A step passes only when the response proves the operation happened: the
 * expected Inertia page, no validation errors, and the data the step wrote
 * read back. HTTP 200 alone proves nothing here, because Laravel answers a
 * failed form or an expired session with a redirect that ends in a 200 page.
 * Each failed step records why (qe_step_failures, console) and stops the
 * journey, so later steps cannot report success on top of it.
 *
 * Read tests/load/README.md before running anything.
 */
import http from 'k6/http';
import { check } from 'k6';
import { Counter, Rate } from 'k6/metrics';
import { enforceTarget, hostsOption } from './guard.js';

export const target = enforceTarget(__ENV);

export const STUDENTS = target.students.origin;
export const STAFF = target.staff.origin;

export const stepOk = new Rate('qe_step_ok');
export const stepFailures = new Counter('qe_step_failures');
export const journeyComplete = new Rate('qe_journey_complete');

/** Options every script starts from: run tag, pinned hosts, guarded target. */
export function baseOptions(extra) {
    return {
        hosts: hostsOption(target),
        // Loopback portals use a self-signed certificate; the guard already
        // limited the target to this machine.
        insecureSkipTLSVerify: true,
        tags: { qe_run: target.runId, qe_env: __ENV.QE_ENV },
        summaryTrendStats: [
            'avg',
            'min',
            'med',
            'p(90)',
            'p(95)',
            'p(99)',
            'max',
        ],
        ...extra,
    };
}

/**
 * functional: every step must pass. load: the initial SLOs
 * (docs/external_link/quality-engineering/11-performance-slos.md), which a
 * baseline may revise but never loosen to make a run pass.
 */
export function thresholdsFor(kind) {
    if (kind === 'functional') {
        return {
            qe_step_ok: ['rate==1'],
            checks: ['rate==1'],
            http_req_failed: ['rate==0'],
        };
    }

    return {
        qe_step_ok: [
            {
                threshold: 'rate>0.99',
                abortOnFail: true,
                delayAbortEval: '30s',
            },
        ],
        http_req_failed: [
            {
                threshold: 'rate<0.01',
                abortOnFail: true,
                delayAbortEval: '30s',
            },
        ],
        'http_req_duration{type:read}': ['p(95)<1000'],
        'http_req_duration{type:write}': ['p(95)<2000'],
        'http_req_duration{type:upload}': ['p(95)<2000'],
        'http_req_duration{type:pdf}': ['p(95)<3000'],
    };
}

/** Reads the Inertia page object from a full HTML response. */
export function inertiaPage(response) {
    const body =
        response && typeof response.body === 'string' ? response.body : '';
    const match =
        /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/.exec(
            body,
        );

    if (!match) {
        return null;
    }

    try {
        return JSON.parse(match[1]);
    } catch {
        return null;
    }
}

function pathOf(url) {
    const match = /^[a-z]+:\/\/[^/]+(\/[^?#]*)?/i.exec(String(url || ''));

    return match && match[1] ? match[1] : '/';
}

/**
 * Checks a response against what the step must produce.
 *
 * expect: {component | components, path (RegExp), props(page) -> string|null}
 * Returns {ok, reason, page}. Pure, so selftest.js can feed it fakes.
 */
export function evaluatePage(response, expect) {
    if (!response) {
        return { ok: false, reason: 'no response', page: null };
    }

    if (response.error) {
        return {
            ok: false,
            reason: `transport error: ${response.error}`,
            page: null,
        };
    }

    if (response.status !== 200) {
        return { ok: false, reason: `HTTP ${response.status}`, page: null };
    }

    const page = inertiaPage(response);

    if (!page) {
        return {
            ok: false,
            reason: `no Inertia page at ${pathOf(response.url)}`,
            page: null,
        };
    }

    const wanted = expect.components || [expect.component];

    if (!wanted.includes(page.component)) {
        return {
            ok: false,
            reason: `landed on ${page.component} (${pathOf(response.url)}), expected ${wanted.join(' or ')}`,
            page,
        };
    }

    const errors =
        page.props && page.props.errors ? Object.keys(page.props.errors) : [];

    if (errors.length > 0) {
        return {
            ok: false,
            reason: `validation errors: ${errors.join(', ')}`,
            page,
        };
    }

    if (expect.path && !expect.path.test(pathOf(response.url))) {
        return {
            ok: false,
            reason: `unexpected path ${pathOf(response.url)}`,
            page,
        };
    }

    const problem = expect.props ? expect.props(page) : null;

    if (problem) {
        return { ok: false, reason: problem, page };
    }

    return { ok: true, reason: '', page };
}

/** Checks a response that must be refused with one exact status. */
export function evaluateStatus(response, status) {
    if (!response || response.error) {
        return {
            ok: false,
            reason: `transport error: ${response ? response.error : 'none'}`,
        };
    }

    return response.status === status
        ? { ok: true, reason: '' }
        : { ok: false, reason: `HTTP ${response.status}, expected ${status}` };
}

/** Records a step result; returns true when it passed. */
export function record(step, result, response) {
    check(result, { [step]: (r) => r.ok }, { step });
    stepOk.add(result.ok, { step });

    if (!result.ok) {
        stepFailures.add(1, { step });
        console.error(
            JSON.stringify({
                qe_run: target.runId,
                step,
                reason: result.reason,
                vu: __VU,
                iteration: __ITER,
                status: response ? response.status : null,
                url: response ? pathOf(response.url) : null,
                request_id:
                    response && response.headers
                        ? response.headers['X-Request-Id'] || null
                        : null,
            }),
        );
    }

    return result.ok;
}

/**
 * Every VU runs on 127.0.0.1, so per-address rate limits (10 QR sign-ins a
 * minute) would throttle the whole run. Only the loopback perf vhost may
 * turn this header into the client address (Nginx realip, owner approval);
 * anywhere else it is ignored and the run fails with clear 429s instead.
 */
export function clientIp(vu) {
    return `10.77.${Math.floor(vu / 250)}.${(vu % 250) + 1}`;
}

/**
 * A new person on their own device: no cookies from an earlier journey of
 * this VU. Without it, a student still on the printed page (who can reach
 * nothing else, ADR-0010) or a remembered staff redirect would leak into
 * the next journey and fail it for the wrong reason.
 */
export function freshDevice() {
    const jar = http.cookieJar();
    jar.clear(STUDENTS);
    jar.clear(STAFF);
}

function baseHeaders() {
    return { Accept: 'text/html', 'X-QE-Client-IP': clientIp(__VU) };
}

function xsrfHeaders(origin) {
    const cookies = http.cookieJar().cookiesForURL(origin);
    const token = cookies['XSRF-TOKEN']
        ? decodeURIComponent(cookies['XSRF-TOKEN'][0])
        : '';

    return { ...baseHeaders(), 'X-XSRF-TOKEN': token };
}

/** The headers a write needs; for http.batch, which bypasses send(). */
export function writeHeaders(origin) {
    return xsrfHeaders(origin);
}

/** GET; type is read (default) or pdf. */
export function get(origin, path, name, params = {}) {
    return http.get(`${origin}${path}`, {
        ...params,
        headers: { ...baseHeaders(), ...params.headers },
        tags: { name, type: 'read', ...params.tags },
    });
}

/** Form write with the CSRF header; type is write (default) or upload. */
export function send(origin, method, path, body, name, params = {}) {
    return http.request(method, `${origin}${path}`, body, {
        ...params,
        headers: { ...xsrfHeaders(origin), ...params.headers },
        tags: { name, type: 'write', ...params.tags },
        redirects: 10,
    });
}

/** Confirms the target is this application's two portals before any load. */
export function preflight() {
    const students = get(STUDENTS, '/apply/terms', 'preflight students');
    const staff = get(STAFF, '/staff/login', 'preflight staff');
    const studentsOk = evaluatePage(students, { component: 'apply/terms' });
    const staffOk = evaluatePage(staff, { component: 'auth/staff-login' });

    for (const [result, response, label] of [
        [studentsOk, students, 'students'],
        [staffOk, staff, 'staff'],
    ]) {
        if (!result.ok || !response.headers['X-Request-Id']) {
            throw new Error(
                `QE preflight: the ${label} portal is not external_link (${result.reason || 'no X-Request-Id header'})`,
            );
        }
    }
}
