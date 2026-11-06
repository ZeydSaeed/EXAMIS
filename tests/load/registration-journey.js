// Registration by mobile number (ADR-0010), once, while anonymous visitors
// browse the public pages (QE_BROWSE_VUS, default 5). Registration is
// limited to 3 a minute and 30 a day per address in the application, so
// this proves the path, not its capacity.
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e QE_MAX_VUS=10 ... tests/load/registration-journey.js
import { assertVus } from './lib/guard.js';
import { baseOptions, preflight, target, thresholdsFor } from './lib/common.js';
import { browsePublicPages } from './lib/journeys.js';
import { register } from './lib/student.js';

const browse = assertVus(
    target,
    Number(__ENV.QE_BROWSE_VUS || 5),
    'registration-journey browse',
);
assertVus(target, browse + 1, 'registration-journey total');

export const options = baseOptions({
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
                { duration: '1m', target: browse },
                { duration: '3m', target: browse },
                { duration: '30s', target: 0 },
            ],
            exec: 'browseScenario',
            startTime: '10s',
        },
    },
    thresholds: thresholdsFor('load'),
});

export function setup() {
    preflight();
}

export function registerScenario() {
    register();
}

export function browseScenario() {
    browsePublicPages();
}
