// Spike: sudden surge (e.g. the moment results or a deadline is announced):
// 50 → 1000 VUs within 30 s, hold, then drop. Watch error rate, queue depth
// and how fast latency recovers after the spike.
import { thresholds } from './lib/common.js';
import { applicantReads, browsePublicPages } from './lib/journeys.js';

export const options = {
    scenarios: {
        surge: {
            executor: 'ramping-vus',
            startVUs: 50,
            stages: [
                { duration: '1m', target: 50 },
                { duration: '30s', target: 1000 },
                { duration: '3m', target: 1000 },
                { duration: '30s', target: 50 },
                { duration: '3m', target: 50 },
            ],
            exec: 'mixed',
        },
    },
    thresholds,
};

export function mixed() {
    if (__VU % 3 === 0) {
        applicantReads();
    } else {
        browsePublicPages();
    }
}
