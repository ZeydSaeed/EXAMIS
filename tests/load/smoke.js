// Smoke (functional, 1 VU): proves the target and every journey before any
// load. Every step must pass (thresholds: rate==1). Uses students
// QE_STUDENT_OFFSET+1 (submitted), +2 and +3 (drafts).
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e STUDENTS_URL=... -e STAFF_URL=... \
//        -e QE_STAFF_PASSWORD=... --summary-export=<results>/<id>-smoke.json tests/load/smoke.js
import { baseOptions, preflight, thresholdsFor } from './lib/common.js';
import {
    browsePublicPages,
    securityProbes,
    submissionJourney,
    supervisorJourney,
} from './lib/journeys.js';

const offset = Number(__ENV.QE_STUDENT_OFFSET || 0);

export const options = baseOptions({
    scenarios: {
        smoke: {
            executor: 'per-vu-iterations',
            vus: 1,
            iterations: 1,
            maxDuration: '5m',
        },
    },
    thresholds: thresholdsFor('functional'),
});

export function setup() {
    preflight();
}

export default function () {
    browsePublicPages();
    submissionJourney(offset + 1);
    securityProbes(offset + 2, offset + 3);
    supervisorJourney(1);
}
