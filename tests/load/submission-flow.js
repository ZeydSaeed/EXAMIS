// Submission flow under concurrency: every VU runs the full write path once
// with its own account (start → save → upload → double submit → receipt).
// Afterwards verify integrity in the database:
//
//   SELECT count(*), count(DISTINCT reference_number) FROM applications WHERE reference_number IS NOT NULL;
//   -- both numbers must be equal; and no user may have two applications in a period:
//   SELECT user_id, exam_period_id, count(*) FROM applications GROUP BY 1, 2 HAVING count(*) > 1;
//
// k6 run -e BASE_URL=... -e VUS=200 tests/load/submission-flow.js
import { thresholds } from './lib/common.js';
import { submissionJourney } from './lib/journeys.js';

const vus = Number(__ENV.VUS || 100);

export const options = {
    scenarios: {
        submit: {
            executor: 'per-vu-iterations',
            vus,
            iterations: 1,
            maxDuration: '15m',
            exec: 'submit',
        },
    },
    thresholds,
};

export function submit() {
    submissionJourney();
}
