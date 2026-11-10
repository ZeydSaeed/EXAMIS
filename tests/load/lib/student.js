/*
 * Student steps, as the application works today (ADR-0009, ADR-0010):
 * one-time accounts that enter by QR link (/s/{public_id}) plus access code,
 * choose a specialization, fill three steps, upload the personal photo,
 * submit, and land on the printed page. Accounts come from PerfSeeder; the
 * formulas below must match database/seeders/PerfSeeder.php.
 *
 * Every function returns the journey state, or null after a failed step
 * (already recorded with its reason).
 */
import http from 'k6/http';
import {
    STUDENTS,
    evaluatePage,
    evaluateStatus,
    get,
    record,
    send,
    writeHeaders,
} from './common.js';

const FIXTURE = JSON.parse(open('../fixtures/application.json'));
const PHOTO = open(`../fixtures/${FIXTURE.photo.file}`, 'b');

const pad = (value, length) => String(value).padStart(length, '0');

export const publicIdFor = (n) => `00000000-0000-4000-8000-${pad(n, 12)}`;
export const studentNumberFor = (n) => 900000 + n;
export const phoneFor = (n) => `0000${pad(n, 8)}`;
export function accessCodeFor(n) {
    const code = `PERF${pad(n, 6)}`;

    return `${code.slice(0, 5)}-${code.slice(5)}`;
}

const LANDINGS = [
    'apply/specialization',
    'applicant/applications/edit',
    'applicant/applications/printed',
];

/** QR link, then the access code. A wrong account or expired code fails here. */
export function signIn(n) {
    const qr = get(STUDENTS, `/s/${publicIdFor(n)}`, 'GET /s/:public_id');

    if (
        !record(
            'student: QR opens the code page',
            evaluatePage(qr, { component: 'auth/student-code' }),
            qr,
        )
    ) {
        return null;
    }

    const verified = send(
        STUDENTS,
        'POST',
        '/student/verify',
        { access_code: accessCodeFor(n) },
        'POST /student/verify',
    );
    const result = evaluatePage(verified, { components: LANDINGS });

    if (!record('student: access code signs in', result, verified)) {
        return null;
    }

    return { n, page: result.page, uuid: null, lockVersion: null, saved: {} };
}

/** From the landing page to an open draft with the specialization chosen. */
export function ensureDraft(state) {
    if (state.page.component === 'applicant/applications/printed') {
        record(
            'student: account still open',
            {
                ok: false,
                reason: 'account already submitted; rebuild the test data or move QE_STUDENT_OFFSET',
            },
            null,
        );

        return null;
    }

    if (state.page.component === 'applicant/applications/edit') {
        return withDraft(state, state.page);
    }

    const branch = (state.page.props.branches || [])[0];

    if (!branch || !branch.specializations || !branch.specializations[0]) {
        record(
            'student: an open period offers a specialization',
            {
                ok: false,
                reason: 'no open period or no eligible specialization',
            },
            null,
        );

        return null;
    }

    const choice = {
        branch_code: branch.code,
        category_code: branch.specializations[0].code,
    };
    const response = send(
        STUDENTS,
        'POST',
        '/apply/specialization',
        choice,
        'POST /apply/specialization',
    );
    const result = evaluatePage(response, {
        component: 'applicant/applications/edit',
        props: (page) =>
            page.props.application.status !== 'draft'
                ? `status ${page.props.application.status}, expected draft`
                : page.props.application.form.branch_code !== choice.branch_code
                  ? 'branch not stored'
                  : null,
    });

    return record('student: specialization starts a draft', result, response)
        ? withDraft(state, result.page)
        : null;
}

function withDraft(state, page) {
    return {
        ...state,
        page,
        uuid: page.props.application.uuid,
        lockVersion: page.props.application.lock_version,
    };
}

function storedMismatch(form, answers) {
    for (const [field, value] of Object.entries(answers)) {
        if (String(form[field] ?? '') !== value) {
            return `field ${field} reads back as [${form[field]}], sent [${value}]`;
        }
    }

    return null;
}

/**
 * Saves one step. exact: a fresh draft must move to exactly the next step;
 * a draft saved again (read/write loops) may already be further on.
 * changes: values that differ from the last save. The application skips a
 * save that changes nothing (no new lock version), so a repeated save must
 * change something to be a real write that can be checked.
 */
export function saveStep(state, step, exact = true, changes = {}) {
    const answers = { ...FIXTURE.steps[String(step)], ...changes };
    const response = send(
        STUDENTS,
        'PUT',
        `/applications/${state.uuid}`,
        {
            ...answers,
            lock_version: String(state.lockVersion),
            step: String(step),
        },
        'PUT /applications/:uuid',
    );
    const result = evaluatePage(
        response,
        savedStepExpectation(state, step, exact, answers),
    );

    return record(`student: step ${step} saved`, result, response)
        ? {
              ...withDraft(state, result.page),
              saved: { ...state.saved, ...answers },
          }
        : null;
}

/** What a saved step must show: the next step, a new lock version, the answers read back. */
export function savedStepExpectation(
    state,
    step,
    exact = true,
    answers = FIXTURE.steps[String(step)],
) {
    return {
        component: 'applicant/applications/edit',
        props: (page) => {
            const application = page.props.application;

            if (application.lock_version !== state.lockVersion + 1) {
                return `lock_version ${application.lock_version}, expected ${state.lockVersion + 1} (save not applied)`;
            }

            if (
                exact ? page.props.step !== step + 1 : page.props.step <= step
            ) {
                return `form is on step ${page.props.step} after saving step ${step}`;
            }

            return storedMismatch(application.form, answers);
        },
    };
}

export function fillForm(state) {
    let current = state;

    for (const step of [1, 2, 3]) {
        current = current && saveStep(current, step);
    }

    return current;
}

export function uploadPhoto(state) {
    const response = send(
        STUDENTS,
        'POST',
        `/applications/${state.uuid}/documents`,
        {
            document_type: FIXTURE.photo.document_type,
            file: http.file(PHOTO, FIXTURE.photo.file, FIXTURE.photo.mime),
        },
        'POST /applications/:uuid/documents',
        { tags: { type: 'upload' } },
    );
    const result = evaluatePage(response, photoExpectation());

    return record('student: photo uploaded and verified', result, response)
        ? withDraft(state, result.page)
        : null;
}

/** The photo must be listed, verified, and no required document still missing. */
export function photoExpectation() {
    return {
        component: 'applicant/applications/edit',
        props: (page) => {
            const photo = (page.props.application.documents || []).find(
                (d) => d.document_type === FIXTURE.photo.document_type,
            );

            if (!photo) {
                return 'photo not listed after upload';
            }

            if (photo.status !== 'verified') {
                return `photo status ${photo.status}`;
            }

            return page.props.missing.documents.length > 0
                ? `still missing: ${page.props.missing.documents.join(', ')}`
                : null;
        },
    };
}

/** Submits; the reference number is checked in the database afterwards (post-run-integrity.sql). */
export function submit(state, label = 'student: submitted') {
    const response = send(
        STUDENTS,
        'POST',
        `/applications/${state.uuid}/submit`,
        { declaration: '1' },
        'POST /applications/:uuid/submit',
    );

    return record(label, evaluatePrinted(response, state), response)
        ? state
        : null;
}

/** Two submissions at the same moment (double click, retry race): both must end on the printed page. */
export function submitTwiceAtOnce(state) {
    const request = {
        method: 'POST',
        url: `${STUDENTS}/applications/${state.uuid}/submit`,
        body: { declaration: '1' },
        params: {
            headers: writeHeaders(STUDENTS),
            tags: { name: 'POST /applications/:uuid/submit', type: 'write' },
            redirects: 10,
        },
    };
    const [first, second] = http.batch([request, request]);
    const firstOk = record(
        'student: racing submit 1 answered',
        evaluatePrinted(first, state),
        first,
    );
    const secondOk = record(
        'student: racing submit 2 answered',
        evaluatePrinted(second, state),
        second,
    );

    return firstOk && secondOk ? state : null;
}

/** The printed page of this student, nobody else's. */
export function evaluatePrinted(response, state) {
    return evaluatePage(response, {
        component: 'applicant/applications/printed',
        props: (page) =>
            page.props.studentNumber !== studentNumberFor(state.n)
                ? `printed page shows student ${page.props.studentNumber}, expected ${studentNumberFor(state.n)}`
                : null,
    });
}

export function downloadForm(state) {
    const response = get(
        STUDENTS,
        `/applications/${state.uuid}/receipt`,
        'GET /applications/:uuid/receipt',
        {
            responseType: 'binary',
            tags: { type: 'pdf' },
        },
    );

    return record(
        'student: form PDF downloaded',
        evaluatePdf(response),
        response,
    )
        ? state
        : null;
}

/** 200 is not enough: the body must be a real PDF. */
export function evaluatePdf(response) {
    const result = evaluateStatus(response, 200);

    if (!result.ok) {
        return result;
    }

    const type = String(response.headers['Content-Type'] || '');
    const bytes = new Uint8Array(response.body || new ArrayBuffer(0));
    const magic = String.fromCharCode(...bytes.slice(0, 4));

    if (!type.includes('pdf')) {
        return { ok: false, reason: `Content-Type ${type}` };
    }

    return magic !== '%PDF' || bytes.length < 1000
        ? { ok: false, reason: `not a PDF (${bytes.length} bytes)` }
        : result;
}

/** Reads the draft back: every value this VU saved must still be there. */
export function readDraft(state) {
    const response = get(
        STUDENTS,
        `/applications/${state.uuid}/edit`,
        'GET /applications/:uuid/edit',
    );
    const result = evaluatePage(response, {
        component: 'applicant/applications/edit',
        props: (page) =>
            storedMismatch(page.props.application.form, state.saved),
    });

    return record('student: draft reads back intact', result, response)
        ? withDraft(state, result.page)
        : null;
}

/** Signed out: a protected page must send the visitor to the terms page, never show data. */
export function probeSignedOut() {
    const jar = http.cookieJar();
    jar.clear(STUDENTS);
    const response = get(STUDENTS, '/dashboard', 'GET /dashboard (signed out)');

    return record(
        'student: signed-out visitor is sent to the terms',
        evaluatePage(response, { component: 'apply/terms' }),
        response,
    );
}

/** A student session must not open the staff portal. */
export function probeStaffPortal(staffOrigin) {
    const response = get(
        staffOrigin,
        '/admin/applications',
        'GET /admin/applications (as student)',
    );

    return record(
        'student: staff portal stays closed',
        evaluatePage(response, { component: 'auth/staff-login' }),
        response,
    );
}

/** Another student's application answers 404, not 403 and never its data (IDOR). */
export function probeForeignApplication(foreignUuid) {
    const response = get(
        STUDENTS,
        `/applications/${foreignUuid}/edit`,
        'GET /applications/:uuid/edit (foreign)',
        {
            responseCallback: http.expectedStatuses(404),
            redirects: 0,
        },
    );

    return record(
        "student: another student's application is 404",
        evaluateStatus(response, 404),
        response,
    );
}

export function signOut() {
    const response = send(
        STUDENTS,
        'POST',
        '/student/logout',
        {},
        'POST /student/logout',
    );

    return record(
        'student: signed out',
        evaluatePage(response, { component: 'start' }),
        response,
    );
}

/** New student by mobile number (registration is limited to 3 a minute per address). */
export function register() {
    const terms = get(STUDENTS, '/apply/terms', 'GET /apply/terms');

    if (
        !record(
            'register: terms page',
            evaluatePage(terms, { component: 'apply/terms' }),
            terms,
        )
    ) {
        return false;
    }

    const accepted = send(
        STUDENTS,
        'POST',
        '/apply/terms',
        { accept: '1' },
        'POST /apply/terms',
    );

    if (
        !record(
            'register: terms accepted',
            evaluatePage(accepted, { component: 'auth/register' }),
            accepted,
        )
    ) {
        return false;
    }

    const phone = `0000${String(Date.now()).slice(-8)}`;
    const registered = send(
        STUDENTS,
        'POST',
        '/register',
        { phone },
        'POST /register',
    );

    return record(
        'register: account created and signed in',
        evaluatePage(registered, { component: 'apply/specialization' }),
        registered,
    );
}
