// Smoke: 2 VUs for 1 minute. Proves the scripts and the target work before any real load.
// k6 run -e BASE_URL=http://staging.local tests/load/smoke.js
import { thresholds } from './lib/common.js';
import {
    applicantReads,
    browsePublicPages,
    submissionJourney,
} from './lib/journeys.js';

export const options = {
    scenarios: {
        public: {
            executor: 'constant-vus',
            vus: 1,
            duration: '1m',
            exec: 'publicScenario',
        },
        applicant: {
            executor: 'per-vu-iterations',
            vus: 1,
            iterations: 1,
            exec: 'submitScenario',
            maxDuration: '1m',
        },
    },
    thresholds,
};

export function publicScenario() {
    browsePublicPages();
}

export function submitScenario() {
    submissionJourney();
    applicantReads();
}
