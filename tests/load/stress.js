// Stress (needs approval per run): steps of a quarter of QE_MAX_VUS up to
// QE_MAX_VUS, to find where latency or errors break and how the system
// recovers. SLOs are reported, not used to stop; it aborts only when errors
// pass 5%. Never above QE_MAX_VUS (at most 200 on the shared server).
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e QE_HEAVY_APPROVAL=<id> -e QE_MAX_VUS=200 ... tests/load/stress.js
import { baseOptions, preflight, target } from './lib/common.js';
import { requireHeavyApproval } from './lib/heavy.js';
import { browsePublicPages, fillingStudent } from './lib/journeys.js';

requireHeavyApproval(target, 'stress.js');

const offset = Number(__ENV.QE_STUDENT_OFFSET || 0);
const quarter = Math.max(1, Math.round(target.maxVus / 4));
const half = (vus) => Math.max(1, Math.round(vus / 2));

const stages = [1, 2, 3, 4].flatMap((k) => [
    { duration: '2m', target: half(quarter * k) },
    { duration: '3m', target: half(quarter * k) },
]);
stages.push({ duration: '2m', target: 0 });

export const options = baseOptions({
    noCookiesReset: true,
    scenarios: {
        public: { executor: 'ramping-vus', stages, exec: 'publicScenario' },
        filling: { executor: 'ramping-vus', stages, exec: 'fillingScenario' },
    },
    thresholds: {
        http_req_failed: [
            {
                threshold: 'rate<0.05',
                abortOnFail: true,
                delayAbortEval: '30s',
            },
        ],
        qe_step_ok: ['rate>0.95'],
        'http_req_duration{type:read}': ['p(95)<1000'],
        'http_req_duration{type:write}': ['p(95)<2000'],
    },
});

export function setup() {
    preflight();
}

export function publicScenario() {
    browsePublicPages();
}

export function fillingScenario() {
    fillingStudent(offset + __VU);
}
