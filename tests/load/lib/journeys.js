import { check, group, sleep } from 'k6';
import http from 'k6/http';
import {
    BASE_URL,
    get,
    inertiaPage,
    login,
    logout,
    send,
    usernameFor,
} from './common.js';

const PDF = open('../fixtures/document.pdf', 'b');
const PNG = open('../fixtures/photo.png', 'b');

/** Anonymous visitor: home, instructions, a content page, tracking form. */
export function browsePublicPages() {
    group('public pages', () => {
        check(get('/', 'GET /'), { 'home 200': (r) => r.status === 200 });
        sleep(1);
        check(get('/pages/instructions', 'GET /pages/:slug'), {
            'page 200': (r) => r.status === 200,
        });
        sleep(1);
        check(get('/track', 'GET /track'), {
            'track 200': (r) => r.status === 200,
        });
        check(get('/manifest.webmanifest', 'GET manifest'), {
            'manifest 200': (r) => r.status === 200,
        });
    });
}

/** Signed-in applicant reading their dashboard and notifications. */
export function applicantReads() {
    const username = usernameFor(__VU);
    login(username);

    group('applicant reads', () => {
        check(get('/dashboard', 'GET /dashboard'), {
            'dashboard 200': (r) => r.status === 200,
        });
        sleep(2);
        check(get('/notifications', 'GET /notifications'), {
            'notifications 200': (r) => r.status === 200,
        });
    });

    logout();
}

/**
 * The full write path: start (idempotent), save draft, upload documents,
 * submit twice (the second must return the same reference), read the
 * receipt. Each VU owns one account, so it can only ever create one
 * application per period; re-running the script is safe.
 */
export function submissionJourney() {
    const username = usernameFor(__VU);
    login(username);

    const applicationUrl = group('start application', () => {
        const dashboard = get('/dashboard', 'GET /dashboard');
        const page = inertiaPage(dashboard);
        const period = page?.props?.openPeriods?.[0];
        const existing = page?.props?.applications?.[0];

        if (existing) {
            return `/applications/${existing.uuid}`;
        }

        if (!period) {
            return '';
        }

        const started = send(
            'POST',
            '/applications',
            { exam_period_id: String(period.id) },
            'POST /applications',
        );
        check(started, {
            'draft opened': (r) =>
                r.status === 200 &&
                /\/applications\/[0-9a-f-]+\/edit/.test(r.url),
        });
        return started.url.replace(BASE_URL, '').replace('/edit', '');
    });

    if (!applicationUrl) {
        logout();

        return;
    }

    const edit = get(`${applicationUrl}/edit`, 'GET /applications/:id/edit');
    const editPage = inertiaPage(edit);

    if (editPage?.props?.application?.status === 'draft') {
        group('save draft', () => {
            const saved = send(
                'PUT',
                applicationUrl,
                {
                    lock_version: String(
                        editPage.props.application.lock_version,
                    ),
                    full_name_ar: 'محمد علي حسين الربيعي',
                    mother_name_ar: 'فاطمة جاسم محمد',
                    birth_date: '2006-05-20',
                    gender: 'male',
                    national_id_number: `1999${String(__VU).padStart(8, '0')}`,
                    phone: `0770${String(__VU).padStart(7, '0')}`,
                    governorate_code: 'BGD',
                    school_name: 'إعدادية النجاح للبنين',
                    branch_code: 'SCI',
                    category_code: 'EXTERNAL',
                },
                'PUT /applications/:id',
            );
            check(saved, { 'draft saved': (r) => r.status === 200 });
        });

        group('upload documents', () => {
            for (const [type, file] of [
                ['personal_photo', http.file(PNG, 'photo.png', 'image/png')],
                ['national_id', http.file(PDF, 'id.pdf', 'application/pdf')],
                [
                    'school_certificate',
                    http.file(PDF, 'school.pdf', 'application/pdf'),
                ],
            ]) {
                const uploaded = send(
                    'POST',
                    `${applicationUrl}/documents`,
                    { document_type: type, file },
                    'POST /applications/:id/documents',
                );
                check(uploaded, { 'document stored': (r) => r.status === 200 });
                sleep(1);
            }
        });

        group('submit (twice, must be idempotent)', () => {
            const first = send(
                'POST',
                `${applicationUrl}/submit`,
                { declaration: '1' },
                'POST /applications/:id/submit',
            );
            const second = send(
                'POST',
                `${applicationUrl}/submit`,
                { declaration: '1' },
                'POST /applications/:id/submit',
            );
            const firstReference =
                inertiaPage(first)?.props?.application?.reference_number;
            const secondReference =
                inertiaPage(second)?.props?.application?.reference_number;

            check(first, {
                submitted: (r) => r.status === 200 && !!firstReference,
            });
            check(second, {
                'resubmit returns the same reference': () =>
                    firstReference === secondReference,
            });
        });
    }

    group('status and receipt', () => {
        check(get(applicationUrl, 'GET /applications/:id'), {
            'status 200': (r) => r.status === 200,
        });
        const receipt = get(
            `${applicationUrl}/receipt`,
            'GET /applications/:id/receipt',
        );
        check(receipt, {
            'receipt pdf': (r) =>
                r.status === 200 &&
                String(r.headers['Content-Type']).includes('pdf'),
        });
    });

    logout();
}
