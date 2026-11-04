# EXAMIS staging

Files for one staging server: Hetzner CAX21 (ARM64, 4 vCPU, 8 GB) or any
Ubuntu 24.04 host. Nothing here runs by itself. Every step that buys, creates,
connects or deploys is run by the owner, after approval.

**Staging only.** Production is separate and on hold. The database is
`examis_staging` with synthetic data (factories, `DemoSeeder`). Both scripts
refuse any other database name, including `sis`.

## What is in this folder

| File | Runs where | Does |
|---|---|---|
| `package.ps1` | this PC | `npm run build` + `git archive HEAD` into `storage/releases/examis-<sha>.tgz` |
| `provision.sh` | server, as root, once | packages, firewall, SSH hardening, swap, PostgreSQL roles and `examis_staging`, Redis, PHP-FPM, Nginx, `.env`, systemd units |
| `release.sh` | server, as `examis`, per release | unpack, Composer, migrate (as schema owner), caches, switch `current`, restart workers, health check |
| `env.staging.example` | template | staging `.env`; secrets filled in by `provision.sh` |
| `nginx/`, `php/`, `postgresql/`, `systemd/` | templates | installed by `provision.sh` |

## Layout on the server

```
/srv/examis/
  current -> releases/<timestamp>     Nginx root: current/public
  releases/<timestamp>/               last 5 kept
  shared/.env                         640 examis:www-data
  shared/.db-owner                    600, migration credentials
  shared/storage/                     documents, logs, cache (survives releases)
  shared/fonts/                       times.ttf, timesbd.ttf (optional)
  deploy/release.sh
/root/examis-staging-secrets.txt      generated passwords (root only)
```

Two PostgreSQL roles: `examis_owner` owns the schema and runs migrations;
`examis_app` (used by the site and workers) may only read and write rows,
with `lock_timeout 5s`, `statement_timeout 30s`, `idle_in_transaction 60s`.
Cache and queue use Redis and fall back to the database; sessions stay in
the database (staff deactivation deletes session rows).

## Order of steps (each needs approval)

1. **Host names (decision).** The portals choose pages by host name, so
   staging needs two names, for example `students.<ip>.sslip.io` and
   `staff.<ip>.sslip.io` (a public wildcard DNS service), or two records on
   the future domain. Without HTTPS keep `SECURE_COOKIES=false`.
2. **Create the server** (owner): Ubuntu 24.04, existing SSH key.
3. **Copy this folder and provision** (owner or with approval):

   ```bash
   scp -r deploy/staging root@<IP>:/root/examis-staging
   ssh root@<IP> "STUDENTS_HOST=... STAFF_HOST=... bash /root/examis-staging/provision.sh"
   ```

4. **Fonts (optional):** copy `times.ttf` and `timesbd.ttf` (the Windows
   versions; the Ubuntu `ttf-mscorefonts` Times New Roman has no Arabic) to
   `/srv/examis/shared/fonts`, owned by `examis`.
5. **Build and upload a release:**

   ```powershell
   powershell -ExecutionPolicy Bypass -File deploy\staging\package.ps1
   scp storage\releases\examis-<sha>.tgz examis@<IP>:/tmp/
   ssh examis@<IP> bash /srv/examis/deploy/release.sh /tmp/examis-<sha>.tgz --seed
   ```

   Use `--seed` on the first release only.
6. **Check:** `/up` answers 200 without a password; everything else asks for
   user `staging` and the `BASIC_AUTH_PASSWORD` from the secrets file. Demo
   accounts use the password `password` (see `DemoSeeder`).
7. **Test:** sign-in on both portals, a student journey with a phone photo,
   the printed form (fonts, barcode), Excel export, then k6 from another
   machine (`tests/load`, `docs/testing/PERFORMANCE.md`). For k6, raise
   `EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR` temporarily and restore it after.

## Rollback

```bash
ln -sfn /srv/examis/releases/<previous> /srv/examis/current
sudo systemctl reload php8.4-fpm
sudo systemctl restart examis-queue.service examis-queue-fallback.service
```

Migrations are additive and are not rolled back (see
`docs/database/SCHEMA.md`).

## Not covered yet

HTTPS (needs the host name decision), off-site backups (`scripts/ops/backup.sh`
to a storage target), monitoring, and production.
