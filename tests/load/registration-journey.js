// Student entry by mobile number (ADR-0010), through the real edge.
//   journey: one student goes start page -> terms -> register -> specialization.
//            Registration is limited to 3 a minute and 30 a day per IP, so it
//            runs once; it proves the path, not its capacity.
//   browse:  anonymous visitors on the public pages, ramping to 50.
// k6 run -e BASE_URL=https://students-staging.mahani-iq.com tests/load/registration-journey.js
import { check, group, sleep } from 'k6';
import { get, send, thresholds } from './lib/common.js';

export const options = {
    scenarios: {
        journey: {
            executor: 'per-vu-iterations',
            vus: 1,
            iterations: 1,
            exec: 'registerScenario',
            maxDuration: '1m',
        },
        browse: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 50 },
                { duration: '3m', target: 50 },
                { duration: '30s', target: 0 },
            ],
            exec: 'browseScenario',
            startTime: '10s',
        },
    },
    thresholds,
};

export function registerScenario() {
    group('register by mobile number', () => {
        // The button to the terms is rendered in the browser, not in the HTML.
        check(get('/', 'GET /'), { 'start page 200': (r) => r.status === 200 });

        check(get('/apply/terms', 'GET /apply/terms'), {
            'terms 200': (r) => r.status === 200,
        });

        // Without accepting the terms, registration sends the student back.
        const early = get('/register', 'GET /register');
        check(early, {
            'register needs the terms first': (r) => r.url.endsWith('/apply/terms'),
        });

        const accepted = send('POST', '/apply/terms', { accept: '1' }, 'POST /apply/terms');
        check(accepted, {
            'terms accepted -> register': (r) => r.status === 200 && r.url.endsWith('/register'),
        });

        const phone = `0770${String(Date.now()).slice(-7)}`;
        const registered = send('POST', '/register', { phone }, 'POST /register');
        check(registered, {
            'registered -> specialization': (r) =>
                r.status === 200 && r.url.endsWith('/apply/specialization'),
        });

        check(get('/dashboard', 'GET /dashboard'), {
            'signed in': (r) => r.status === 200 && !r.url.endsWith('/apply/terms'),
        });

        send('POST', '/student/logout', {}, 'POST /student/logout');
    });
}

export function browseScenario() {
    check(get('/', 'GET /'), { 'start 200': (r) => r.status === 200 });
    sleep(1 + Math.random() * 2);
    check(get('/apply/terms', 'GET /apply/terms'), { 'terms 200': (r) => r.status === 200 });
    sleep(1 + Math.random() * 2);
    check(get('/portal', 'GET /portal'), { 'portal 200': (r) => r.status === 200 });
    sleep(1 + Math.random() * 2);
    check(get('/track', 'GET /track'), { 'track 200': (r) => r.status === 200 });
    sleep(2 + Math.random() * 3);
}
