#!/usr/bin/env python3
"""EXAMIS perf: sign in synthetic identities and write the ZAP plans.

Run as root while the perf session runs, after `submission-flow.js` has left
some submitted applications for the staff pages:

    python3 zap-prepare.py

Identities (all synthetic, on examis_perf only):
- a guest on each portal (fresh session, for the public pages and forms);
- student A (number 2900) with a draft, student B (2901) with a draft, used
  for one cross-account check before the scan;
- perf.admin.1@qe.localhost, an administrator this script adds through
  artisan after SyntheticTarget::assert(), with the synthetic staff password.

ZAP cannot discover Inertia pages by crawling (they are JSON in a script
tag), so the plans seed every page and the main forms explicitly. The
session cookies go into the plans (mode 600, qe) and into redact.txt (root),
which zap-run.sh uses to scrub the reports. Nothing secret is printed.
"""
import json
import os
import pathlib
import pwd
import re
import subprocess
import sys
import tempfile
import urllib.parse

PERF = pathlib.Path('/srv/examis-perf')
SESSION = PERF / 'qe/zap/session'
S = 'https://students.perf.localhost:8443'
T = 'https://staff.perf.localhost:8443'
SR = r'https://students\.perf\.localhost:8443'
TR = r'https://staff\.perf\.localhost:8443'
STUDENT_PATHS = r'(applications|dashboard|notifications|student/card|portal|apply/specialization)\b'
STAFF_PATHS = r'(admin|settings|user)\b'
RESOLVE = [
    '--resolve', 'students.perf.localhost:8443:127.0.0.1',
    '--resolve', 'staff.perf.localhost:8443:127.0.0.1',
]
WORK = pathlib.Path(tempfile.mkdtemp(prefix='zap-prepare-'))
os.chmod(WORK, 0o700)


def fail(message):
    print(f'[zap-prepare] ERROR: {message}', file=sys.stderr)
    sys.exit(1)


def log(message):
    print(f'[zap-prepare] {message}')


def page(body):
    match = re.search(r'<script data-page="app" type="application/json">([\s\S]*?)</script>', body)
    return json.loads(match.group(1)) if match else None


class Client:
    """curl with its own cookie jar; bodies go through files, never argv."""

    def __init__(self, name):
        self.jar = WORK / f'{name}.jar'
        self.jar.touch(mode=0o600)

    def cookies(self, host):
        found = {}
        for line in self.jar.read_text().splitlines():
            if line.startswith('#HttpOnly_'):
                line = line[len('#HttpOnly_'):]
            elif line.startswith('#') or not line.strip():
                continue
            fields = line.split('\t')
            if len(fields) == 7 and fields[0].lstrip('.') == host:
                found[fields[5]] = fields[6]
        return found

    def cookie_header(self, host):
        return '; '.join(f'{k}={v}' for k, v in self.cookies(host).items())

    def xsrf(self, host):
        return urllib.parse.unquote(self.cookies(host).get('XSRF-TOKEN', ''))

    def request(self, method, url, form=None, follow=True):
        host = urllib.parse.urlsplit(url).hostname
        out = WORK / 'body'
        args = ['curl', '-sS', '-k', *RESOLVE, '-b', str(self.jar), '-c', str(self.jar),
                '-o', str(out), '-w', '%{http_code} %{url_effective}', '-H', 'Accept: text/html']
        if method != 'GET':
            headers = WORK / 'headers'
            headers.write_text(f'X-XSRF-TOKEN: {self.xsrf(host)}\n')
            os.chmod(headers, 0o600)
            args += ['-H', f'@{headers}']
        if form is not None:
            data = WORK / 'data'
            data.write_text(urllib.parse.urlencode(form))
            os.chmod(data, 0o600)
            args += ['--data-binary', f'@{data}', '-H', 'Content-Type: application/x-www-form-urlencoded']
        if method not in ('GET', 'POST'):
            args += ['-X', method]
            follow = False
        if follow:
            args.append('-L')
        result = subprocess.run([*args, url], capture_output=True, text=True, check=False)
        if result.returncode != 0:
            fail(f'{method} {urllib.parse.urlsplit(url).path}: curl exit {result.returncode}')
        status, effective = result.stdout.split(' ', 1)
        return int(status), effective, out.read_text(errors='replace')


def student(n):
    client = Client(f'student-{n}')
    public_id = f'00000000-0000-4000-8000-{n:012d}'
    code = f'PERF{n:06d}'
    client.request('GET', f'{S}/s/{public_id}')
    _, _, body = client.request('POST', f'{S}/student/verify', {'access_code': f'{code[:5]}-{code[5:]}'})
    draft = page(body)
    if draft and draft['component'] == 'apply/specialization':
        branch = draft['props']['branches'][0]
        _, url, body = client.request('POST', f'{S}/apply/specialization', {
            'branch_code': branch['code'],
            'category_code': branch['specializations'][0]['code'],
        })
        draft = page(body)
    else:
        url = draft['url'] if draft else ''
    match = re.search(r'/applications/([0-9a-f-]{36})/edit', url)
    if not draft or draft['component'] != 'applicant/applications/edit' or not match:
        fail(f'student {n}: no draft')
    log(f'student {n}: signed in, draft {match.group(1)}')
    return client, public_id, match.group(1), draft['props']['application']['lock_version']


def staff(email, password):
    client = Client(email.split('@')[0])
    client.request('GET', f'{T}/staff/login')
    _, url, _ = client.request('POST', f'{T}/login', {'email': email, 'password': password})
    if not url.endswith('/admin/home'):
        fail(f'{email}: sign-in did not reach /admin/home')
    log(f'{email}: signed in')
    return client


def guest(origin, path):
    client = Client('guest-' + urllib.parse.urlsplit(origin).hostname.split('.')[0])
    status, _, _ = client.request('GET', origin + path)
    if status != 200 or not client.xsrf(urllib.parse.urlsplit(origin).hostname):
        fail(f'guest on {origin}: no session')
    return client


def sql(query):
    result = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-p', '5433', '-d', 'examis_perf', '-qAtX', '-c', query],
                            capture_output=True, text=True, check=True)
    return result.stdout.strip()


def secret(name):
    for line in pathlib.Path('/etc/examis-perf/secrets').read_text().splitlines():
        if line.startswith(f'{name}='):
            return line.split('=', 1)[1]
    fail(f'{name} missing from /etc/examis-perf/secrets')


def add_administrator(password):
    code = (
        r'Database\Seeders\Support\SyntheticTarget::assert();'
        r'$u = App\Models\User::query()->firstOrNew(["email" => "perf.admin.1@qe.localhost"]);'
        r'$u->forceFill(["username" => "perf.admin.1", "name" => "perf admin 1",'
        r' "password" => Illuminate\Support\Facades\Hash::make(getenv("QE_STAFF_PASSWORD")),'
        r' "account_type" => App\Enums\AccountType::Staff, "locale" => "ar"])->save();'
        r'$u->roleAssignments()->firstOrCreate(["role" => App\Enums\Role::Administrator]);'
        r'echo "administrator ready", PHP_EOL;'
    )
    result = subprocess.run(
        ['runuser', '-u', 'examisperf', '--', 'env', f'HOME={PERF}', f'QE_STAFF_PASSWORD={password}',
         'bash', '-c', 'set -a; . "$1"; set +a; cd "$2"; exec php artisan tinker --execute "$3"', '_',
         str(PERF / '.db-owner'), str(PERF / 'app'), code],
        capture_output=True, text=True, check=False)
    if 'administrator ready' not in result.stdout:
        fail('could not add the perf administrator (guard refused or artisan failed)')
    log('perf.admin.1@qe.localhost: administrator on examis_perf')


def get(url):
    return {'url': url, 'method': 'GET'}


def form(url, fields, method='POST'):
    return {'url': url, 'method': method, 'data': urllib.parse.urlencode(fields),
            'headers': ['Content-Type:application/x-www-form-urlencoded']}


def main():
    if os.geteuid() != 0:
        fail('run as root')
    if subprocess.run(['systemctl', 'is-active', '--quiet', 'examis-perf.target']).returncode != 0:
        fail('the perf session is not running')
    submitted = sql("SELECT uuid || ' ' || verification_token || ' ' || exam_period_id FROM applications"
                    " WHERE status = 'submitted' ORDER BY id LIMIT 1")
    if not submitted:
        fail('no submitted application: run submission-flow.js first')
    sub_uuid, sub_token, period_id = submitted.split(' ')
    document = sql(f"SELECT d.uuid FROM application_documents d JOIN applications a ON a.id = d.application_id"
                   f" WHERE a.uuid = '{sub_uuid}' LIMIT 1")
    slug = sql("SELECT slug FROM content_pages WHERE is_published ORDER BY id LIMIT 1")

    password = secret('QE_STAFF_PASSWORD')
    add_administrator(password)

    a, a_public, a_uuid, a_lock = student(2900)
    b, _, b_uuid, _ = student(2901)
    admin = staff('perf.admin.1@qe.localhost', password)
    student_guest = guest(S, '/apply/terms')
    staff_guest = guest(T, '/staff/login')

    # One cross-account check before the scan: A asks for B's draft.
    for path in (f'/applications/{b_uuid}/edit', f'/applications/{b_uuid}'):
        status, _, _ = a.request('GET', S + path, follow=False)
        log(f'student A -> student B {path.replace(b_uuid, "<B>")}: {status}')
        if status != 404:
            fail('cross-account check: expected 404')

    fixture = json.loads((PERF / 'app/tests/load/fixtures/application.json').read_text())
    step1 = {'lock_version': a_lock, 'step': 1, **fixture['steps']['1']}

    sh, th = 'students.perf.localhost', 'staff.perf.localhost'
    rules = []
    for name, url, client, host in (
        ('student A', f'{SR}/{STUDENT_PATHS}.*', a, sh),
        ('student guest', f'{SR}/(?!{STUDENT_PATHS}).*', student_guest, sh),
        ('administrator', f'{TR}/{STAFF_PATHS}.*', admin, th),
        ('staff guest', f'{TR}/(?!{STAFF_PATHS}).*', staff_guest, th),
    ):
        for header, value in (('Cookie', client.cookie_header(host)), ('X-XSRF-TOKEN', client.xsrf(host))):
            rules.append({'description': f'{name} {header}', 'url': url, 'matchType': 'req_header',
                          'matchString': header, 'matchRegex': False, 'replacementString': value,
                          'tokenProcessing': False})

    student_requests = [
        get(f'{S}/'), get(f'{S}/apply/terms'), get(f'{S}/register'), get(f'{S}/track'),
        get(f'{S}/pages/{slug}'), get(f'{S}/verify/{sub_token}'), get(f'{S}/s/{a_public}'),
        get(f'{S}/student/verify'), get(f'{S}/up'),
        form(f'{S}/track', {'reference_number': 'ZAP-0000'}),
        form(f'{S}/register', {'phone': '07700000000'}),
        get(f'{S}/portal'), get(f'{S}/dashboard'), get(f'{S}/notifications'), get(f'{S}/student/card'),
        get(f'{S}/applications/{a_uuid}'), get(f'{S}/applications/{a_uuid}/edit'),
        get(f'{S}/applications/{a_uuid}/receipt'), get(f'{S}/applications/{a_uuid}/printed'),
        # ASCII escapes: ZAP does not send the body as UTF-8 otherwise.
        {'url': f'{S}/applications/{a_uuid}', 'method': 'PUT', 'data': json.dumps(step1),
         'headers': ['Content-Type:application/json', 'Accept:application/json']},
        form(f'{S}/notifications/read', {}),
    ]
    staff_requests = [
        get(f'{T}/'), get(f'{T}/staff/login'), get(f'{T}/staff/invitation'),
        form(f'{T}/login', {'email': 'zap.nobody@qe.localhost', 'password': 'not-a-password'}),
        get(f'{T}/admin/home'), get(f'{T}/admin'),
        get(f'{T}/admin/applications?status=submitted&period={period_id}&assigned=me&q=perf&per_page=20'),
        get(f'{T}/admin/applications/{sub_uuid}'), get(f'{T}/admin/applications/{sub_uuid}/receipt'),
        *([get(f'{T}/admin/documents/{document}')] if document else []),
        get(f'{T}/admin/content'), get(f'{T}/admin/content/{slug}/edit'),
        get(f'{T}/admin/periods'), get(f'{T}/admin/periods/create'), get(f'{T}/admin/periods/{period_id}/edit'),
        get(f'{T}/admin/reports'), get(f'{T}/admin/reports/{period_id}/export'),
        get(f'{T}/admin/student-export?from=2026-01-01&to=2026-12-31&per_page=20'),
        get(f'{T}/admin/users?role=reviewer&state=active&q=perf&per_page=20'),
        get(f'{T}/settings/profile'), get(f'{T}/settings/appearance'),
        form(f'{T}/admin/message-templates', {'title': 'zap', 'status': 'accepted', 'body': 'zap'}),
        form(f'{T}/admin/content/{slug}', {'title_ar': 'zap', 'title_en': 'zap', 'body_ar': 'zap',
                                            'body_en': 'zap', 'is_published': '1'}, method='PUT'),
    ]

    contexts = [
        {'name': 'students', 'urls': [f'{S}/'], 'includePaths': [f'{SR}.*'],
         'excludePaths': [f'{SR}/student/logout.*', f'{SR}/applications/[^/]+/(finish|withdraw|submit).*',
                          f'{SR}/applications/{b_uuid}.*']},
        {'name': 'staff', 'urls': [f'{T}/'], 'includePaths': [f'{TR}.*'],
         'excludePaths': [f'{TR}/logout.*', f'{TR}/admin/users/[0-9]+/.*', f'{TR}/admin/students/.*',
                          f'{TR}/settings/password.*', f'{TR}/user/confirm-password.*', f'{TR}/staff/invitation/.*']},
    ]
    common = [
        {'type': 'replacer', 'parameters': {'deleteAllRules': True}, 'rules': rules},
        {'type': 'requestor', 'requests': student_requests},
        {'type': 'requestor', 'requests': staff_requests},
    ]
    reports = [
        {'type': 'passiveScan-wait', 'parameters': {'maxDuration': 15}},
        {'type': 'report', 'parameters': {'template': 'traditional-html', 'reportDir': '__OUT__',
                                          'reportFile': 'zap-report', 'reportTitle': 'EXAMIS perf __KIND__ scan'}},
        {'type': 'report', 'parameters': {'template': 'traditional-json', 'reportDir': '__OUT__',
                                          'reportFile': 'zap-report'}},
    ]
    env = {'contexts': contexts, 'parameters': {'failOnError': True, 'progressToStdout': True}}
    spiders = [{'type': 'spider', 'parameters': {'context': c['name'], 'maxDuration': 3, 'maxDepth': 5}}
               for c in contexts]
    scans = [{'type': 'activeScan', 'parameters': {'context': c['name'], 'maxRuleDurationInMins': 3,
                                                   'maxScanDurationInMins': 35, 'threadPerHost': 2}}
             for c in contexts]
    plans = {
        'passive': {'env': env, 'jobs': [*common, *spiders, *reports]},
        'active': {'env': env, 'jobs': [*common, *spiders, *scans, *reports]},
    }
    qe = pwd.getpwnam('qe')
    for kind, plan in plans.items():
        target = SESSION / f'plan-{kind}.yaml'
        target.write_text(json.dumps(plan, ensure_ascii=False, indent=1).replace('__KIND__', kind))
        os.chown(target, qe.pw_uid, qe.pw_gid)
        os.chmod(target, 0o600)

    # Whole headers and each cookie value, longest first, for zap-run.sh.
    values = set()
    for rule in rules:
        values.add(rule['replacementString'])
        values.update(part.split('=', 1)[1] for part in rule['replacementString'].split('; ') if '=' in part)
    redact = pathlib.Path('/root/examis-perf-deploy/zap-redact.txt')
    redact.write_text('\n'.join(sorted((v for v in values if len(v) >= 16), key=len, reverse=True)) + '\n')
    os.chmod(redact, 0o600)
    log(f'plans written: {SESSION}/plan-passive.yaml, plan-active.yaml'
        f' ({len(student_requests)} + {len(staff_requests)} seed requests)')
    subprocess.run(['rm', '-rf', str(WORK)], check=False)


if __name__ == '__main__':
    main()
