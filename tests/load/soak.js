// Soak: moderate steady load for a long period (default 2 h) to reveal memory
// growth in PHP-FPM/workers, connection leaks, growing queues and autovacuum
// effects. Sample `docs/operations/OPERATIONS.md` metrics every 10 minutes.
//
// k6 run -e BASE_URL=... -e SOAK_DURATION=4h tests/load/soak.js
import { thresholds } from './lib/common.js';
import {
    applicantReads,
    browsePublicPages,
    submissionJourney,
} from './lib/journeys.js';

const duration = __ENV.SOAK_DURATION || '2h';

export const options = {
    scenarios: {
        public: {
            executor: 'constant-vus',
            vus: 150,
            duration,
            exec: 'publicScenario',
        },
        reads: {
            executor: 'constant-vus',
            vus: 80,
            duration,
            exec: 'readScenario',
        },
        submissions: {
            executor: 'constant-arrival-rate',
            rate: 2,
            timeUnit: '1m',
            duration,
            preAllocatedVUs: 20,
            maxVUs: 50,
            exec: 'submitScenario',
        },
    },
    thresholds,
};

export function publicScenario() {
    browsePublicPages();
}

export function readScenario() {
    applicantReads();
}

export function submitScenario() {
    submissionJourney();
}
