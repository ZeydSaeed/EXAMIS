// Normal load, staged: 5 → 10 → 20 → 50 → 100 → 150 → 200 VUs, cut so
// that the stage plus 3 helper VUs (1 staff, up to 2 submitting) fits in
// QE_MAX_VUS. Stops at the first stage that breaks an SLO (abortOnFail).
// Each stage: 55% public visitors, 45% students filling the form (accounts
// QE_STUDENT_OFFSET+VU, never submitted). Alongside: one supervisor working
// the queue, and fresh submissions at QE_SUBMIT_PER_MIN (accounts
// QE_SUBMIT_OFFSET+1…, consumed).
//
// k6 run -e QE_ENV=perf -e QE_RUN_ID=<id> -e QE_MAX_VUS=53 ... tests/load/normal-load.js
import exec from 'k6/execution';
import { assertVus } from './lib/guard.js';
import { baseOptions, preflight, target, thresholdsFor } from './lib/common.js';
import {
    browsePublicPages,
    fillingStudent,
    submissionJourney,
    supervisorJourney,
} from './lib/journeys.js';

const HELPERS = 3;
const ramp = __ENV.STAGE_RAMP || '1m';
const hold = __ENV.STAGE_HOLD || '3m';
const studentOffset = Number(__ENV.QE_STUDENT_OFFSET || 0);
const submitOffset = Number(__ENV.QE_SUBMIT_OFFSET || 1000);
const submitPerMinute = Number(__ENV.QE_SUBMIT_PER_MIN || 2);

const levels = [5, 10, 20, 50, 100, 150, 200].filter(
    (vus) => vus + HELPERS <= target.maxVus,
);

if (levels.length === 0) {
    throw new Error(
        `QE guard refused normal-load: QE_MAX_VUS must be at least ${5 + HELPERS}`,
    );
}

assertVus(target, levels[levels.length - 1] + HELPERS, 'normal-load peak');

const filling = (vus) => Math.max(1, Math.round(vus * 0.45));
const stagesFor = (count) =>
    levels.flatMap((vus) => [
        { duration: ramp, target: count(vus) },
        { duration: hold, target: count(vus) },
    ]);
const totalMinutes = levels.length * (parseInt(ramp, 10) + parseInt(hold, 10));

export const options = baseOptions({
    noCookiesReset: true,
    scenarios: {
        public: {
            executor: 'ramping-vus',
            stages: stagesFor((vus) => vus - filling(vus)),
            exec: 'publicScenario',
            gracefulRampDown: '30s',
        },
        filling: {
            executor: 'ramping-vus',
            stages: stagesFor(filling),
            exec: 'fillingScenario',
            gracefulRampDown: '30s',
        },
        staff: {
            executor: 'constant-vus',
            vus: 1,
            duration: `${totalMinutes}m`,
            exec: 'staffScenario',
        },
        submissions: {
            executor: 'constant-arrival-rate',
            rate: submitPerMinute,
            timeUnit: '1m',
            duration: `${totalMinutes}m`,
            preAllocatedVUs: 1,
            maxVUs: 2,
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

export function staffScenario() {
    supervisorJourney(1, { review: false });
}

export function submitScenario() {
    submissionJourney(submitOffset + exec.scenario.iterationInTest + 1);
}
