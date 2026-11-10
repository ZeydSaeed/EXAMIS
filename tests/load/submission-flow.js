// Concurrent submissions (functional under concurrency): QE_VUS students
// (accounts QE_STUDENT_OFFSET+VU) each run the whole journey once and send
// their submission twice at the same moment. Every step must pass. Then the
// database decides: run tests/load/sql/post-run-integrity.sql, every count
// must be 0 (no duplicate reference, one submission event per application).
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e QE_VUS=20 -e QE_MAX_VUS=20 ... tests/load/submission-flow.js
import { assertVus } from './lib/guard.js';
import { baseOptions, preflight, target, thresholdsFor } from './lib/common.js';
import { submissionJourney } from './lib/journeys.js';

const vus = assertVus(target, Number(__ENV.QE_VUS || 5), 'submission-flow');
const offset = Number(__ENV.QE_STUDENT_OFFSET || 0);

export const options = baseOptions({
    scenarios: {
        submit: {
            executor: 'per-vu-iterations',
            vus,
            iterations: 1,
            maxDuration: '15m',
        },
    },
    thresholds: thresholdsFor('functional'),
});

export function setup() {
    preflight();
}

export default function () {
    submissionJourney(offset + __VU, { racing: true });
}
