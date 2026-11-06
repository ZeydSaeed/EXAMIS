# Execution log

One line per action that ran. Times +0300. No secrets.

| When | Commit | Environment | Action | Result | Evidence |
| --- | --- | --- | --- | --- | --- |
| 2026-11-06 17:40 | `0f0de40` | workstation, read-only Git | baseline check: HEAD, branches, origin, tags, status, deployment log | baseline `0f0de40`; another session active (HEAD moved twice during the check) | `01-approved-version-baseline.md` |
| 2026-11-06 17:50 | `0f0de40` | workstation, `php artisan route:list` (no database) | route inventory | 89 routes (incl. framework and Inertia DevTools) | `03-student-and-staff-flows.md` |
| 2026-11-06 18:07:03 | `0f0de40` + QE changes | workstation, Pest, SQLite `:memory:` | `tests/Feature/QualityEngineering` | 34 passed, 355 assertions | `evidence/pest-quality-engineering.txt` |
| 2026-11-06 18:07:09 | same | same | full Pest suite | 537 tests: 528 passed, 9 skipped, 0 failed (≈ 62 s) | `evidence/pest-full-suite.txt` |
| 2026-11-06 18:08:12 | same | workstation, k6 2.2.0, offline | `selftest.js` | 51/51 checks, 0 HTTP requests | `evidence/k6-selftest.txt` |
| 2026-11-06 18:08 | same | workstation, `k6 inspect` (init only) | guard on all 7 scripts | production and staging hosts refused (exit 107); heavy scripts refused without approval; VU ceiling 500 refused | `evidence/k6-guard-inspect.txt` |
| 2026-11-06 18:05 | same | workstation | Pint, Larastan (new seeders), `vp check` (tests/load) | pass, 0 errors | console |

Not run (at 18:08): anything on the server, any k6 request over the network, Playwright, ZAP, Semgrep, PostgreSQL test run.

## 2026-11-06, after the owner's approval ("موافق": D1, D2, D3, D4, D9)

| When | Commit | Environment | Action | Result | Evidence |
| --- | --- | --- | --- | --- | --- |
| 21:47 | `0f0de40` + QE changes | workstation | QE Pest re-run; k6 self-test (and without settings) | 34/34 passed; 51/51 checks, 0 HTTP; without settings the guard refuses with exit 107 | console |
| 21:48 | `b463e83` | workstation, Git | committed this phase by explicit paths on `feature/staff-portal-auth`; `perf/phase-a-hardening` fast-forwarded to match (as the release log keeps them equal) | not pushed | `git log` |
| 21:49 | — | `examis-staging-1`, SSH as root, read-only script | G1 inspection: CPU, RAM, disk, services, FPM, PostgreSQL settings, database names, tools | idle host; no `sis` database; no QE tools; 17/50 PostgreSQL connections | `evidence/g1-server-inspection.txt` |
| 21:50 | `b463e83` | workstation, PostgreSQL `examis_testing` | marker `external_link:synthetic` set on `examis_testing` (D4); full Pest on PostgreSQL | 537 passed, 2,293 assertions, 0 skipped | `evidence/pest-full-suite-pgsql.txt` |
| 21:55 | same | same | `--testsuite=Concurrency` | 4 passed, 7 assertions | `evidence/pest-concurrency-pgsql.txt` |
| 21:56 | same | workstation | documents updated (D9): THREAT-MODEL T1/T19, ARCHITECTURE §2, STATUS-REPORT banner, `.ai` E2E note; CI `e2e` job gets a PostgreSQL service | CI not run | this commit |

## 2026-11-06, gates G2 to G4 (owner approved: "اسمح بتشغيل سكربتات perf على الخادم")

Times are UTC as recorded on the server (+0300 locally). Environment for every run: `examis-staging-1`, 4 shared vCPU ARM64, 7.7 GB. The perf copy runs app code `0f0de40` (release `20261106142707`), PHP-FPM static 16, its own PostgreSQL 16 cluster (shared_buffers 512 MB), Redis and queue worker. k6 2.2.0 runs on the same host, capped at 1 vCPU and 1.5 GB. **Results describe the application plus the generator on one shared host, not the application's capacity alone.**

| When (UTC) | Commit | Action | Result | Evidence |
| --- | --- | --- | --- | --- |
| 20:06 | `28e4d4c` | `provision-perf.sh`: cluster 16/perf on 127.0.0.1:5433, `examis_perf` marked synthetic, Redis 6380, FPM master, vhost 127.0.0.1:8443, k6 (checksum OK) | exit 0; staging `/up` 200, staging services active, new ports on loopback only | `evidence/perf/provision.log` |
| 20:07 | `28e4d4c` | first `release-perf.sh` | stopped: Redis could not read its config (`/etc/examis-perf` 750); fixed to 711 | journal |
| 20:08 | `28e4d4c` | `release-perf.sh` again: SyntheticTarget guard, migrate, PerfSeeder 1,000 students + 7 staff | 2 s; `/up` 200 | `evidence/perf/release.log` |
| 20:09 | `28e4d4c` | negative check on the server: smoke, submission-flow, normal-load against the public staging names | all refused, exit 107; 0 requests in the staging access log. Before that, k6 could not open the scripts (`/srv/examis-perf` 750 → 751) | console |
| 20:10 | `28e4d4c` | `smoke.js` (run `2026-11-06-smoke-01`) | **FAIL** 17/20 steps, 0% HTTP errors: three people shared one cookie jar (test defect QE-001, see below) | `evidence/perf/2026-11-06-smoke-01/` |
| 20:13 | `b2f0774` | test fix `freshDevice()`, perf rebuilt (1,000 students) | — | `evidence/perf/release.log` |
| 20:13:56 | `b2f0774` | `smoke.js` (`2026-11-06-smoke-02`) | **PASS** 34/34 steps, 4/4 journeys, 0/63 HTTP errors, p95 127 ms; integrity 8/8 zero, `examis:verify-integrity` passed | `evidence/perf/2026-11-06-smoke-02/` |
| 20:14:42 | `b2f0774` | `submission-flow.js` QE_VUS=5, offset 100, racing double submit (`2026-11-06-submit-05`) | **PASS** 55/55 steps, 5/5 journeys, 0/112 HTTP errors, p50 37 ms, p95 1.29 s (submit); integrity 8/8 zero | `evidence/perf/2026-11-06-submit-05/` |
| 20:16 | — | perf session stopped (`examis-perf.target`) | perf units inactive; staging active; 7.0 GB available | console |

## 2026-11-06/07, gate G5 and the QE-002 fix (owner approved items 1, 2, 3)

| When (UTC) | Commit | Action | Result | Evidence |
| --- | --- | --- | --- | --- |
| 20:35 | `e16ff52` | QE-002 fix: regression test first (fails: lands on the other student's printed page), then `EnsureAccountIsActive` | test passes; full Pest 538/538 on PostgreSQL, 529 + 9 skipped on SQLite | console |
| 20:40 | `e16ff52` | `git push` of both branches | **blocked by the Claude Code permission classifier**; not pushed | — |
| 20:54 | `b2f0774` | perf rebuilt with 2,000 students; `submission-flow.js` 20 VUs without `QE_MAX_VUS` | refused by the guard before any request (ceiling defaults to 5) | `evidence/perf/2026-11-06-submit-20/` |
| 20:55:35 | `b2f0774` | `submission-flow.js` QE_VUS=20, QE_MAX_VUS=20, offset 200 (`2026-11-06-submit-20b`) | **PASS** 220/220 steps, 20/20 journeys, 0/442 HTTP errors, integrity 0; p50 180 ms, **p95 3.41 s, max 6.5 s** (20 PDF renders at once, host CPU ~70%) | `evidence/perf/2026-11-06-submit-20b/` |
| 20:56:21 | `b2f0774` | `normal-load.js` 5→10→20→50 (`2026-11-06-normal-50`) | aborted after 45 s by its own threshold: staff sign-in 429 (test defect QE-004); other requests p95 25 ms | `evidence/perf/2026-11-06-normal-50/` |
| 21:00:32 | `1bf5468` | QE-004 fixed; perf rebuilt; `normal-load.js` 5→10→20→50 VUs (+1 staff, 2 submissions/min), 16.5 min (`2026-11-06-normal-50b`) | **PASS** 6,506/6,506 steps, 1,769/1,769 journeys, 0/7,437 HTTP errors, 7.6 req/s; p95 40 ms (read 37, write 45, upload 61, pdf 40); submit avg 0.28 s, max 0.78 s; integrity 0 | `evidence/perf/2026-11-06-normal-50b/` |
| 21:18 | — | perf session stopped | perf inactive, staging active | console |

Per-stage resources (`normal-50b/resources.csv`, averages; vCPU = share of one core):

| Stage | Host CPU user | Steal | Min. available RAM | perf FPM | perf PostgreSQL | k6 | FPM workers busy (max) |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 5 VUs | 3.1% | 0 | 6,675 MB | 0.07 | 0.01 | 0.03 | 2 / 16 |
| 10 VUs | 4.1% | 0 | 6,670 MB | 0.09 | 0.02 | 0.05 | 2 / 16 |
| 20 VUs | 6.4% | 0 | 6,647 MB | 0.15 | 0.03 | 0.09 | 2 / 16 |
| 50 VUs | 12.4% | 0 | 6,631 MB | 0.31 | 0.06 | ~0.1 | 3 / 16 |

The generator stayed far below its 1 vCPU cap, so the run is valid. The staging services used no measurable CPU.
