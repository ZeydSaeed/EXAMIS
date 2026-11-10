/*
 * Staff steps on the staff portal (ADR-0011, ADR-0012): email and password
 * through Fortify's POST /login, then the review queue. Accounts come from
 * PerfSeeder (perf.reviewer.<i>@qe.localhost, perf.supervisor.<i>@...)
 * with the password the operator set in QE_STAFF_PASSWORD; it is read from
 * the environment and never written to a report.
 */
import http from 'k6/http';
import {
    STAFF,
    evaluatePage,
    evaluateStatus,
    get,
    record,
    send,
} from './common.js';

export const staffEmail = (role, i) => `perf.${role}.${i}@qe.localhost`;

export function signIn(email) {
    const password = __ENV.QE_STAFF_PASSWORD || '';

    if (password === '') {
        record(
            'staff: password provided',
            { ok: false, reason: 'QE_STAFF_PASSWORD is not set' },
            null,
        );

        return false;
    }

    const form = get(STAFF, '/staff/login', 'GET /staff/login');

    if (
        !record(
            'staff: sign-in page',
            evaluatePage(form, { component: 'auth/staff-login' }),
            form,
        )
    ) {
        return false;
    }

    const response = send(
        STAFF,
        'POST',
        '/login',
        { email, password },
        'POST /login',
    );

    return record(
        'staff: signed in',
        evaluatePage(response, { component: 'admin/home' }),
        response,
    );
}

/** The queue filtered by status; returns the listed application ids. */
export function queue(status) {
    const response = get(
        STAFF,
        `/admin/applications?status=${status}`,
        'GET /admin/applications',
    );
    const result = evaluatePage(response, {
        component: 'admin/applications/index',
        props: (page) =>
            page.props.applications &&
            Array.isArray(page.props.applications.data)
                ? null
                : 'no application list',
    });

    return record('staff: queue listed', result, response)
        ? result.page.props.applications.data.map((a) => a.uuid)
        : null;
}

export function search(term) {
    const response = get(
        STAFF,
        `/admin/applications?q=${encodeURIComponent(term)}`,
        'GET /admin/applications?q',
    );
    const result = evaluatePage(response, {
        components: ['admin/applications/index', 'admin/applications/show'],
    });

    return record('staff: search answered', result, response);
}

export function open(uuid) {
    const response = get(
        STAFF,
        `/admin/applications/${uuid}`,
        'GET /admin/applications/:uuid',
    );
    const result = evaluatePage(response, {
        component: 'admin/applications/show',
        props: (page) =>
            page.props.application.uuid !== uuid
                ? 'another application opened'
                : null,
    });

    return record('staff: application opened', result, response)
        ? result.page
        : null;
}

/** Starts the review; the application must come back under review. */
export function startReview(uuid, label = 'staff: review started') {
    const response = send(
        STAFF,
        'POST',
        `/admin/applications/${uuid}/transition`,
        { target: 'under_review' },
        'POST /admin/applications/:uuid/transition',
    );
    const result = evaluatePage(response, {
        component: 'admin/applications/show',
        props: (page) =>
            page.props.application.status !== 'under_review'
                ? `status ${page.props.application.status}, expected under_review`
                : null,
    });

    return record(label, result, response);
}

/** A reviewer has no users.manage permission: the staff page must answer 403. */
export function probeReviewerLimits() {
    const response = get(
        STAFF,
        '/admin/users',
        'GET /admin/users (as reviewer)',
        {
            responseCallback: http.expectedStatuses(403),
        },
    );

    return record(
        'staff: reviewer cannot manage staff',
        evaluateStatus(response, 403),
        response,
    );
}

export function signOut() {
    const response = send(STAFF, 'POST', '/logout', {}, 'POST /logout (staff)');

    return record(
        'staff: signed out',
        evaluatePage(response, { component: 'auth/staff-login' }),
        response,
    );
}
