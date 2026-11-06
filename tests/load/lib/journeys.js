/*
 * Whole journeys built from the student and staff steps. Each returns true
 * only when every step passed, and feeds qe_journey_complete.
 */
import { sleep } from 'k6';
import {
    STAFF,
    STUDENTS,
    evaluatePage,
    get,
    journeyComplete,
    record,
} from './common.js';
import * as staff from './staff.js';
import * as student from './student.js';

const think = (min, max) => sleep(min + Math.random() * (max - min));

/** Anonymous visitor on the public pages. */
export function browsePublicPages() {
    const pages = [
        ['/', 'start', 'GET /'],
        ['/apply/terms', 'apply/terms', 'GET /apply/terms'],
        ['/portal', 'welcome', 'GET /portal'],
        ['/track', 'public/track', 'GET /track'],
    ];
    let ok = true;

    for (const [path, component, name] of pages) {
        const response = get(STUDENTS, path, name);
        ok =
            record(
                `public: ${path}`,
                evaluatePage(response, { component }),
                response,
            ) && ok;
        think(1, 3);
    }

    journeyComplete.add(ok, { journey: 'public' });

    return ok;
}

/** One account from QR link to printed form. racing: submit twice at the same moment. */
export function submissionJourney(n, { racing = false } = {}) {
    let state = student.signIn(n);
    state = state && student.ensureDraft(state);
    state = state && student.fillForm(state);
    state = state && student.uploadPhoto(state);
    state =
        state &&
        (racing ? student.submitTwiceAtOnce(state) : student.submit(state));
    // A retry after the answer arrived must land on the same printed page.
    state =
        state &&
        student.submit(state, 'student: retry after submit is harmless');
    state = state && student.downloadForm(state);

    journeyComplete.add(!!state, { journey: 'submission' });

    return !!state;
}

/**
 * A student filling the form for a while: signs in once per VU (cookies are
 * kept between iterations), then reads the draft and saves a step again.
 * The account is never submitted, so it can loop for any duration.
 */
const signedIn = {};

export function fillingStudent(n) {
    if (!signedIn[n]) {
        let state = student.signIn(n);
        state = state && student.ensureDraft(state);
        // A draft left by an earlier run is already past the form steps.
        state =
            state &&
            (state.page.props.step > 1 ? state : student.fillForm(state));

        if (!state) {
            journeyComplete.add(false, { journey: 'filling' });

            return false;
        }

        signedIn[n] = state;
    }

    think(5, 15);
    let state = student.readDraft(signedIn[n]);
    think(5, 15);
    state =
        state &&
        student.saveStep(state, 2, false, {
            // Unique per save, also against a draft left by an earlier run.
            house_number: String(Date.now() % 1e9),
        });

    if (!state) {
        delete signedIn[n];
    } else {
        signedIn[n] = state;
    }

    journeyComplete.add(!!state, { journey: 'filling' });

    return !!state;
}

/** A supervisor working the queue: list, search, open, take one into review. */
export function supervisorJourney(i, { review = true } = {}) {
    if (!staff.signIn(staff.staffEmail('supervisor', i))) {
        journeyComplete.add(false, { journey: 'staff' });

        return false;
    }

    let ok = true;
    const submitted = staff.queue('submitted');
    ok = submitted !== null && ok;
    ok = staff.search('900') && ok;

    if (submitted && submitted.length > 0) {
        ok = staff.open(submitted[0]) !== null && ok;
        ok = (review ? staff.startReview(submitted[0]) : true) && ok;
    }

    ok = staff.signOut() && ok;
    journeyComplete.add(ok, { journey: 'staff' });

    return ok;
}

/** Refusals that must hold: signed out, IDOR, staff portal, reviewer limits. */
export function securityProbes(n, otherN) {
    let first = student.signIn(n);
    first = first && student.ensureDraft(first);
    let ok = !!first && student.signOut();

    let second = ok && student.signIn(otherN);
    ok = !!second && student.probeForeignApplication(first.uuid) && ok;
    ok = student.probeStaffPortal(STAFF) && ok;
    ok = student.probeSignedOut() && ok;

    ok =
        staff.signIn(staff.staffEmail('reviewer', 1)) &&
        staff.probeReviewerLimits() &&
        staff.signOut() &&
        ok;

    journeyComplete.add(ok, { journey: 'security' });

    return ok;
}
