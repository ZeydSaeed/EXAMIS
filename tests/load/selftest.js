// Self-test of the suite itself: no HTTP request is sent (threshold
// http_reqs count==0). It proves that the guard refuses every unauthorised
// target and that the step checks fail, with a reason, on each way a run
// could otherwise report a false success: a sign-in that did not happen, an
// expired session, an error page, a validation error, a save that was not
// applied, a refused permission, a failed submission, a fake PDF. Each case
// also has a positive control, so a check that fails everything is caught.
//
// k6 run -e QE_ENV=local -e QE_RUN_ID=selftest-local -e STUDENTS_URL=http://students.localhost:9 \
//        -e STAFF_URL=http://staff.localhost:9 tests/load/selftest.js
import { check } from 'k6';
import { evaluatePage, evaluateStatus } from './lib/common.js';
import { assertVus, validateTarget } from './lib/guard.js';
import { requireHeavyApproval } from './lib/heavy.js';
import {
    evaluatePdf,
    evaluatePrinted,
    photoExpectation,
    savedStepExpectation,
} from './lib/student.js';

export const options = {
    scenarios: {
        selftest: { executor: 'per-vu-iterations', vus: 1, iterations: 1 },
    },
    thresholds: { checks: ['rate==1'], http_reqs: ['count==0'] },
};

const valid = {
    QE_ENV: 'perf',
    QE_RUN_ID: 'selftest-run',
    STUDENTS_URL: 'https://students.perf.localhost:8443',
    STAFF_URL: 'https://staff.perf.localhost:8443',
    QE_MAX_VUS: '100',
};

const refusedTargets = {
    'production students portal': {
        STUDENTS_URL: 'https://students.mahani-iq.com',
    },
    'public staging portal': {
        STUDENTS_URL: 'https://students-staging.mahani-iq.com',
    },
    'staging server address': { STUDENTS_URL: 'https://178.105.246.187' },
    'private network address': { STAFF_URL: 'http://10.0.0.5' },
    'localhost as a subdomain': {
        STUDENTS_URL: 'https://localhost.mahani-iq.com',
    },
    'localhost inside another domain': {
        STUDENTS_URL: 'https://students.localhost.mahani-iq.com',
    },
    'credentials in the URL': {
        STUDENTS_URL: 'https://students.localhost@mahani-iq.com',
    },
    'a path in the URL': {
        STUDENTS_URL: 'https://students.perf.localhost:8443/admin',
    },
    'one host for both portals': {
        STAFF_URL: 'https://students.perf.localhost:8443',
    },
    'missing staff URL': { STAFF_URL: undefined },
    'production environment': { QE_ENV: 'production' },
    'staging environment': { QE_ENV: 'staging' },
    'missing environment': { QE_ENV: undefined },
    'missing run id': { QE_RUN_ID: undefined },
    'run id with spaces': { QE_RUN_ID: 'run id' },
    'old BASE_URL variable': { BASE_URL: 'http://127.0.0.1:8000' },
    'old approval bypass': { I_HAVE_WRITTEN_APPROVAL: 'yes' },
    'VU ceiling above 200': { QE_MAX_VUS: '500' },
    'VU ceiling zero': { QE_MAX_VUS: '0' },
    'VU ceiling not a number': { QE_MAX_VUS: 'many' },
};

const html = (
    component,
    props = {},
    url = 'https://students.perf.localhost:8443/x',
) => ({
    status: 200,
    url,
    headers: {},
    body: `<html><script data-page="app" type="application/json">${JSON.stringify({ component, props: { errors: {}, ...props } })}</script></html>`,
});

const draft = (lockVersion, step, form, extra = {}) =>
    html('applicant/applications/edit', {
        step,
        application: {
            uuid: 'u',
            lock_version: lockVersion,
            form,
            documents: [],
            ...extra,
        },
        missing: { documents: [] },
    });

const stepOne = {
    student_name: 'سجاد',
    father_name: 'حيدر',
    grandfather_name: 'كاظم',
    great_grandfather_name: 'جواد',
    mother_first_name: 'زهراء',
    mother_father_name: 'مهدي',
    mother_grandfather_name: 'كريم',
    birth_date: '2006-05-20',
    birth_place_code: 'KRB',
    gender: 'male',
};

function pdfResponse(bytes, type = 'application/pdf') {
    return {
        status: 200,
        url: 'x',
        headers: { 'Content-Type': type },
        body: bytes.buffer,
    };
}

function bytesOf(text, size) {
    const bytes = new Uint8Array(size);
    for (let i = 0; i < text.length; i++) bytes[i] = text.charCodeAt(i);

    return bytes;
}

function expectRefused(name, fn) {
    let refused = false;

    try {
        fn();
    } catch {
        refused = true;
    }

    check(null, { [`refused: ${name}`]: () => refused });
}

export default function () {
    // Guard: one positive control, then every refusal.
    check(validateTarget(valid), {
        'guard accepts the loopback perf portals': (r) => r.ok,
    });

    for (const [name, change] of Object.entries(refusedTargets)) {
        const result = validateTarget({ ...valid, ...change });
        check(result, {
            [`guard refuses: ${name}`]: (r) => !r.ok && r.errors.length > 0,
        });
    }

    const target = validateTarget(valid);
    expectRefused('more VUs than QE_MAX_VUS', () =>
        assertVus(target, 101, 'selftest'),
    );
    expectRefused('a heavy test without the approval for this run', () =>
        requireHeavyApproval(target, 'selftest'),
    );

    // Page checks: no false success.
    const signIn = {
        components: [
            'apply/specialization',
            'applicant/applications/edit',
            'applicant/applications/printed',
        ],
    };
    check(null, {
        'sign-in passes on the specialization page': () =>
            evaluatePage(html('apply/specialization'), signIn).ok,
        'sign-in fails when sent back to the code page': () =>
            !evaluatePage(html('auth/student-code'), signIn).ok,
        'a step fails when the session expired (terms page)': () =>
            !evaluatePage(html('apply/terms'), {
                component: 'applicant/applications/edit',
            }).ok,
        'a step fails on HTTP 500': () =>
            !evaluatePage(
                { ...html('start'), status: 500 },
                { component: 'start' },
            ).ok,
        'a step fails on HTTP 419 (CSRF)': () =>
            !evaluatePage(
                { ...html('start'), status: 419 },
                { component: 'start' },
            ).ok,
        'a step fails on HTTP 429': () =>
            !evaluatePage(
                { ...html('start'), status: 429 },
                { component: 'start' },
            ).ok,
        'a step fails on a page without Inertia data': () =>
            !evaluatePage(
                {
                    status: 200,
                    url: 'x',
                    headers: {},
                    body: '<h1>Server Error</h1>',
                },
                { component: 'start' },
            ).ok,
        'a step fails on a transport error': () =>
            !evaluatePage(
                { status: 0, error: 'dial tcp: refused', url: 'x', body: '' },
                { component: 'start' },
            ).ok,
        'a step fails when the form came back with errors': () =>
            !evaluatePage(
                html('applicant/applications/edit', {
                    errors: { student_name: 'required' },
                }),
                { component: 'applicant/applications/edit' },
            ).ok,
    });

    const state = { n: 7, uuid: 'u', lockVersion: 3 };
    const saved = savedStepExpectation(state, 1);
    check(null, {
        'a saved step passes with the next step, a new version and the answers':
            () => evaluatePage(draft(4, 2, stepOne), saved).ok,
        'a save fails when the lock version did not move (not applied)': () =>
            !evaluatePage(draft(3, 2, stepOne), saved).ok,
        'a save fails when the form stayed on the same step': () =>
            !evaluatePage(draft(4, 1, stepOne), saved).ok,
        'a save fails when an answer reads back different (lost data)': () =>
            !evaluatePage(draft(4, 2, { ...stepOne, student_name: '' }), saved)
                .ok,
    });

    const photo = photoExpectation();
    check(null, {
        'an upload passes with a verified photo and nothing missing': () =>
            evaluatePage(
                draft(5, 4, stepOne, {
                    documents: [
                        { document_type: 'personal_photo', status: 'verified' },
                    ],
                }),
                photo,
            ).ok,
        'an upload fails when the photo is not listed': () =>
            !evaluatePage(draft(5, 4, stepOne), photo).ok,
        'an upload fails when the photo is still pending or rejected': () =>
            !evaluatePage(
                draft(5, 4, stepOne, {
                    documents: [
                        { document_type: 'personal_photo', status: 'rejected' },
                    ],
                }),
                photo,
            ).ok,
    });

    check(null, {
        "a submission passes on this student's printed page": () =>
            evaluatePrinted(
                html('applicant/applications/printed', {
                    studentNumber: 900007,
                }),
                state,
            ).ok,
        "a submission fails on another student's printed page": () =>
            !evaluatePrinted(
                html('applicant/applications/printed', {
                    studentNumber: 900008,
                }),
                state,
            ).ok,
        'a submission fails when the form came back instead': () =>
            !evaluatePrinted(
                html('applicant/applications/edit', {
                    errors: { declaration: 'accepted' },
                }),
                state,
            ).ok,
    });

    check(null, {
        'a refusal passes on the expected 404': () =>
            evaluateStatus({ status: 404 }, 404).ok,
        'a refusal fails when the page was served (IDOR leak)': () =>
            !evaluateStatus({ status: 200 }, 404).ok,
        'a refusal fails on 403 where 404 is required (existence leak)': () =>
            !evaluateStatus({ status: 403 }, 404).ok,
        'a permission check fails when the page was served': () =>
            !evaluateStatus({ status: 200 }, 403).ok,
    });

    check(null, {
        'the form passes as a real PDF': () =>
            evaluatePdf(pdfResponse(bytesOf('%PDF-1.7', 4096))).ok,
        'the form fails as an HTML page with status 200': () =>
            !evaluatePdf(pdfResponse(bytesOf('<html>', 4096), 'text/html')).ok,
        'the form fails when the body is not a PDF': () =>
            !evaluatePdf(pdfResponse(bytesOf('<html>', 4096))).ok,
        'the form fails when the PDF is empty': () =>
            !evaluatePdf(pdfResponse(bytesOf('%PDF', 10))).ok,
        'the form fails on HTTP 403': () =>
            !evaluatePdf({ status: 403, headers: {}, body: new ArrayBuffer(0) })
                .ok,
    });
}
