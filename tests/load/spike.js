// Spike (needs approval per run): 5 VUs, then QE_MAX_VUS within 30 s (a
// result or deadline announcement), hold 2 min, drop, watch the recovery.
// Mix: two thirds public visitors, one third students filling the form.
// Aborts when errors pass 5% (the server is not being protected any more).
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e QE_HEAVY_APPROVAL=<id> -e QE_MAX_VUS=100 ... tests/load/spike.js
import { baseOptions, preflight, target, thresholdsFor } from './lib/common.js';
import { requireHeavyApproval } from './lib/heavy.js';
import { browsePublicPages, fillingStudent } from './lib/journeys.js';

requireHeavyApproval(target, 'spike.js');

const offset = Number(__ENV.QE_STUDENT_OFFSET || 0);

export const options = baseOptions({
    noCookiesReset: true,
    scenarios: {
        surge: {
            executor: 'ramping-vus',
            startVUs: 5,
            stages: [
                { duration: '1m', target: 5 },
                { duration: '30s', target: target.maxVus },
                { duration: '2m', target: target.maxVus },
                { duration: '30s', target: 5 },
                { duration: '3m', target: 5 },
            ],
            exec: 'mixed',
        },
    },
    thresholds: {
        ...thresholdsFor('load'),
        http_req_failed: [
            {
                threshold: 'rate<0.05',
                abortOnFail: true,
                delayAbortEval: '20s',
            },
        ],
    },
});

export function setup() {
    preflight();
}

export function mixed() {
    if (__VU % 3 === 0) {
        fillingStudent(offset + __VU);
    } else {
        browsePublicPages();
    }
}
