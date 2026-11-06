/*
 * Target guard for every k6 script. It runs in the init stage, before k6
 * sends a single request, and refuses unless every rule holds. Allow-list
 * only: the portals must be loopback names on this machine, which the
 * internet cannot route to, so production and the public staging portals
 * (mahani-iq.com) can never be the target, whatever the environment says.
 *
 *   QE_ENV        local | perf
 *   QE_RUN_ID     run identifier, 6-64 of [A-Za-z0-9._-]; tags every request
 *   STUDENTS_URL  e.g. https://students.perf.localhost:8443
 *   STAFF_URL     e.g. https://staff.perf.localhost:8443
 *   QE_MAX_VUS    ceiling the script may use, at most HARD_MAX_VUS
 *
 * k6 resolves the *.localhost names to 127.0.0.1 itself (options.hosts), so
 * no DNS answer can send the traffic elsewhere.
 */

export const ENVIRONMENTS = ['local', 'perf'];
export const HARD_MAX_VUS = 200;

const URL_PATTERN = /^(https?):\/\/([a-z0-9.-]+)(?::([0-9]{1,5}))?\/?$/;
const RUN_ID_PATTERN = /^[A-Za-z0-9._-]{6,64}$/;

function isLoopbackHost(host) {
    return (
        host === 'localhost' ||
        host === '127.0.0.1' ||
        host.endsWith('.localhost')
    );
}

function parseOrigin(name, value, errors) {
    if (!value) {
        errors.push(`${name} is not set`);

        return null;
    }

    const match = URL_PATTERN.exec(String(value).toLowerCase());

    if (!match) {
        errors.push(
            `${name} [${value}] must be scheme://host[:port] with nothing else`,
        );

        return null;
    }

    const [, scheme, host, port] = match;

    if (!isLoopbackHost(host)) {
        errors.push(
            `${name} host [${host}] is not a loopback name on this machine`,
        );

        return null;
    }

    return { origin: `${scheme}://${host}${port ? `:${port}` : ''}`, host };
}

/**
 * Pure check, so tests/load/selftest.js can prove each refusal offline.
 *
 * @returns {{ok: boolean, errors: string[], students: object|null, staff: object|null, runId: string, maxVus: number}}
 */
export function validateTarget(env) {
    const errors = [];

    if (!ENVIRONMENTS.includes(env.QE_ENV)) {
        errors.push(
            `QE_ENV [${env.QE_ENV || ''}] must be one of ${ENVIRONMENTS.join(', ')}`,
        );
    }

    if (!RUN_ID_PATTERN.test(env.QE_RUN_ID || '')) {
        errors.push('QE_RUN_ID must be 6-64 characters of A-Z a-z 0-9 . _ -');
    }

    if (
        env.I_HAVE_WRITTEN_APPROVAL !== undefined ||
        env.BASE_URL !== undefined
    ) {
        errors.push(
            'BASE_URL and I_HAVE_WRITTEN_APPROVAL belong to the old scripts and are refused',
        );
    }

    const students = parseOrigin('STUDENTS_URL', env.STUDENTS_URL, errors);
    const staff = parseOrigin('STAFF_URL', env.STAFF_URL, errors);

    if (students && staff && students.host === staff.host) {
        errors.push(
            'STUDENTS_URL and STAFF_URL must use different host names (one per portal)',
        );
    }

    const maxVus = Number(env.QE_MAX_VUS || 5);

    if (!Number.isInteger(maxVus) || maxVus < 1 || maxVus > HARD_MAX_VUS) {
        errors.push(`QE_MAX_VUS must be an integer from 1 to ${HARD_MAX_VUS}`);
    }

    return {
        ok: errors.length === 0,
        errors,
        students,
        staff,
        runId: env.QE_RUN_ID || '',
        maxVus,
    };
}

/** Throws in the init stage, so k6 exits before any request. */
export function enforceTarget(env) {
    const target = validateTarget(env);

    if (!target.ok) {
        throw new Error(
            `QE guard refused the run: ${target.errors.join('; ')}`,
        );
    }

    return target;
}

/** k6 `hosts` option: pin every *.localhost portal name to 127.0.0.1. */
export function hostsOption(target) {
    const hosts = {};

    for (const portal of [target.students, target.staff]) {
        if (portal.host.endsWith('.localhost')) {
            hosts[portal.host] = '127.0.0.1';
        }
    }

    return hosts;
}

/** Refuses a VU count above the run's ceiling instead of quietly lowering it. */
export function assertVus(target, requested, label) {
    if (requested > target.maxVus) {
        throw new Error(
            `QE guard refused ${label}: ${requested} VUs is above QE_MAX_VUS=${target.maxVus}`,
        );
    }

    return requested;
}
