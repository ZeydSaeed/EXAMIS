# EXAMIS production

Production runs on the former staging server (`examis-staging-1`, owner's decision of 2026-11-08). The setup, the release script (`../staging/release.sh`) and the backups are the same as for staging. The differences are listed here.

|                      | Staging                                             | Production                                            |
| -------------------- | --------------------------------------------------- | ----------------------------------------------------- |
| Hosts                | `students-staging.` / `staff-staging.mahani-iq.com` | `students.` / `staff.mahani-iq.com`                   |
| `APP_ENV` / database | `staging` / `examis_staging`                        | `production` / `examis_production`                    |
| Staff host           | staging password first                              | email and password only                               |
| Seeding              | reference data and demo accounts                    | reference data only                                   |
| Search engines       | `noindex`                                           | indexed                                               |
| Perf copy and ZAP    | session only                                        | off (unit files kept in `/root/examis-perf-disabled`) |

## Conversion (once)

1. Build the package on the PC with `deploy\staging\package.ps1`. Copy it and the `production`, `staging` and `backup` folders of `deploy/` to `/root/examis-production-kit/` on the server.
2. On the server, run `bash production/convert-from-staging.sh convert /tmp/examis-<sha>.tgz "<admin name>" <admin email>`.
3. In Cloudflare DNS, add proxied A records for `students` and `staff` that point to the server, and remove the two `-staging` records.
4. The administrator opens the link in `/root/examis-admin-activation.txt` once and sets their own password and authenticator. Staff accounts are then added from the staff screen.
5. When production answers through Cloudflare, run `bash production/convert-from-staging.sh drop-staging`.

## Releases

Work happens on a branch; a pull request into `main` runs CI (lint, Larastan, Pest on SQLite and PostgreSQL, Playwright, audits, Gitleaks). Once it is merged:

```powershell
git checkout main; git pull
powershell -ExecutionPolicy Bypass -File deploy\staging\package.ps1                 # builds storage\releases\examis-<sha>.tgz
powershell -NoProfile -ExecutionPolicy Bypass -File deploy\production\release-from-pc.ps1 <sha>
```

The script refuses a commit that is not on `main`, installs the package with `release.sh`, fails if `release.sh` fails, and tags the commit `release-<UTC date-time>` on GitHub. `-FromBranch` exists for emergencies only (a pushed commit not merged yet); merge it to `main` afterwards. After a release with migrations, run `deploy\replica\migrate.ps1`.

Passwords follow the application's rules: at least 12 characters with upper and lower case letters, numbers and symbols, and in production not a known leaked password.
