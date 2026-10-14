// Normal load, staged ramp: 25 → 100 → 250 → 500 → 1000 VUs.
// Traffic mix approximates a submission peak: 60% public browsing,
// 30% signed-in reads, 10% full submissions. Stop at the first stage that
// breaches a threshold (abortOnFail) and record results in docs/testing/PERFORMANCE.md.
//
// k6 run -e BASE_URL=... -e MAX_VUS=250 [-e STAGE_RAMP=2m -e STAGE_HOLD=5m] tests/load/normal-load.js
import { thresholds } from './lib/common.js';
import {
    applicantReads,
    browsePublicPages,
    submissionJourney,
} from './lib/journeys.js';

const maxVus = Number(__ENV.MAX_VUS || 1000);
const ramp = __ENV.STAGE_RAMP || '2m';
const hold = __ENV.STAGE_HOLD || '5m';
const stages = [25, 100, 250, 500, 1000]
    .filter((target) => target <= maxVus)
    .flatMap((target) => [
        { duration: ramp, target },
        { duration: hold, target },
    ]);

const share = (fraction) =>
    stages.map((stage) => ({
        ...stage,
        target: Math.max(1, Math.round(stage.target * fraction)),
    }));

export const options = {
    scenarios: {
        public: {
            executor: 'ramping-vus',
            stages: share(0.6),
            exec: 'publicScenario',
            gracefulRampDown: '30s',
        },
        reads: {
            executor: 'ramping-vus',
            stages: share(0.3),
            exec: 'readScenario',
            gracefulRampDown: '30s',
        },
        submissions: {
            executor: 'ramping-vus',
            stages: share(0.1),
            exec: 'submitScenario',
            gracefulRampDown: '60s',
        },
    },
    thresholds: Object.fromEntries(
        Object.entries(thresholds).map(([key, value]) => [
            key,
            value.map((threshold) => ({
                threshold,
                abortOnFail: true,
                delayAbortEval: '1m',
            })),
        ]),
    ),
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
