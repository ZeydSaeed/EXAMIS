// Soak (needs approval per run): steady QE_SOAK_VUS (default 50, at most
// QE_MAX_VUS) for QE_SOAK_DURATION (default 1h), plus one fresh submission
// a minute, to reveal memory growth in PHP-FPM and workers, connection
// leaks, queue growth and autovacuum effects. Sample the monitoring
// dashboards (09-monitoring-and-observability.md) during the run.
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e QE_HEAVY_APPROVAL=<id> -e QE_SOAK_VUS=50 -e QE_MAX_VUS=55 ... tests/load/soak.js
import exec from 'k6/execution';
import { assertVus } from './lib/guard.js';
import { baseOptions, preflight, target, thresholdsFor } from './lib/common.js';
import { requireHeavyApproval } from './lib/heavy.js';
import {
    browsePublicPages,
    fillingStudent,
    submissionJourney,
} from './lib/journeys.js';

requireHeavyApproval(target, 'soak.js');

const duration = __ENV.QE_SOAK_DURATION || '1h';
const vus = Number(__ENV.QE_SOAK_VUS || 50);
const publicVus = Math.max(1, Math.round(vus * 0.6));
const fillingVus = Math.max(1, vus - publicVus);
const studentOffset = Number(__ENV.QE_STUDENT_OFFSET || 0);
const submitOffset = Number(__ENV.QE_SUBMIT_OFFSET || 1000);

assertVus(target, publicVus + fillingVus + 3, 'soak');

export const options = baseOptions({
    noCookiesReset: true,
    scenarios: {
        public: {
            executor: 'constant-vus',
            vus: publicVus,
            duration,
            exec: 'publicScenario',
        },
        filling: {
            executor: 'constant-vus',
            vus: fillingVus,
            duration,
            exec: 'fillingScenario',
        },
        submissions: {
            executor: 'constant-arrival-rate',
            rate: 1,
            timeUnit: '1m',
            duration,
            preAllocatedVUs: 1,
            maxVUs: 3,
            exec: 'submitScenario',
        },
    },
    thresholds: thresholdsFor('load'),
});

export function setup() {
    preflight();
}

export function publicScenario() {
    browsePublicPages();
}

export function fillingScenario() {
    fillingStudent(studentOffset + __VU);
}

export function submitScenario() {
    submissionJourney(submitOffset + exec.scenario.iterationInTest + 1);
}
