// Stress: push past the 1000-VU target (up to 2000) to find the breaking
// point and observe how the system degrades (errors vs. latency, recovery).
// Thresholds are reported, not used to abort: the goal is to find the limit.
import { thresholds } from './lib/common.js';
import { applicantReads, browsePublicPages } from './lib/journeys.js';

export const options = {
    scenarios: {
        public: {
            executor: 'ramping-vus',
            stages: [
                { duration: '3m', target: 600 },
                { duration: '3m', target: 1200 },
                { duration: '3m', target: 1500 },
                { duration: '3m', target: 0 },
            ],
            exec: 'publicScenario',
        },
        reads: {
            executor: 'ramping-vus',
            stages: [
                { duration: '3m', target: 300 },
                { duration: '3m', target: 600 },
                { duration: '3m', target: 500 },
                { duration: '3m', target: 0 },
            ],
            exec: 'readScenario',
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
