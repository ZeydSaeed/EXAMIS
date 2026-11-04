# EXAMIS staging

Files for one staging server: Hetzner CAX21 (ARM64, 4 vCPU, 8 GB) or any
Ubuntu 24.04 host, behind Cloudflare. Nothing here runs by itself. Every
step that buys, creates, connects or deploys is run by the owner, after
approval.

**Staging only.** Production is separate and on hold. The database is
`examis_staging` with synthetic data (factories, `DemoSeeder`). Both scripts
refuse any other database name, including `sis`. The real separation is that
this server holds no production credentials and has no route to the
production database: keep the staging IP out of the production firewall.

## Host names

| Portal | Staging | Production (later) |
|---|---|---|
| Students | `students-staging.mahani-iq.com` | `students.mahani-iq.com` |
| Staff | `staff-staging.mahani-iq.com` | `staff.mahani-iq.com` |

Cloudflare's free certificate covers one level below the domain
(`*.mahani-iq.com`), which is why staging uses `-staging` and not
`students.staging.…`.

## What is in this folder

| File | Runs where | Does |
|---|---|---|
| `package.ps1` | this PC | `npm run build` + `git archive HEAD` into `storage/releases/examis-<sha>.tgz` |
| `provision.sh` | server, as root, once | packages, SSH hardening, swap, PostgreSQL roles and `examis_staging`, Redis, PHP-FPM, Nginx (HTTPS), firewall, `.env`, systemd units |
| `cloudflare-ips.sh` | server, root, weekly | Cloudflare ranges for the real client IP in Nginx and for the firewall |
| `release.sh` | server, as `examis`, per release | unpack, Composer, migrate (as schema owner), caches, switch `current`, restart workers, health check |
| `env.staging.example` | template | staging `.env`; secrets filled in on the server |
| `nginx/`, `php/`, `postgresql/`, `systemd/`, `logrotate/` | templates | installed by `provision.sh` |

## Security model

- **Edge:** Cloudflare proxy, SSL mode **Full (strict)** with a Cloudflare
  Origin Certificate on Nginx. Ports 80/443 accept Cloudflare's ranges only
  (ufw, refreshed weekly); any other host name is dropped.
- **Client IP:** Nginx takes `CF-Connecting-IP` from Cloudflare connections
  only, so Laravel's rate limits and audit rows see the student, not
  Cloudflare.
- **Access:** every page asks for the staging password (user `staging`),
  including `/up` from outside. `/up` is open only to the server itself.
  Demo accounts use the password `password` and stay behind it.
- **SSH:** keys only (`00-examis.conf`, checked with `sshd -T`).
- **Secrets:** generated on the server into
  `/root/examis-staging-secrets.txt` (root, 600). None are in the repo or
  sent anywhere. `.env` is 600 for `examis`.
- **Database:** `examis_owner` owns the schema and runs migrations;
  `examis_app` (site, workers) may only read and write rows, with
  `lock_timeout 5s`, `statement_timeout 30s`, `idle_in_transaction 60s`.
  Only these two roles may connect. Passwords are set with statement logging
  off.
- **Redis:** local only, password, 256 MB, never evicts queued jobs. Cache
  and queue fall back to the database if Redis is down; sessions stay in
  the database (staff deactivation deletes session rows).

**Accepted for staging, to fix before production:** PHP runs as the same
user that owns the code and can read the migration credentials. Production
needs a separate PHP user with read-only code.

## Order of steps (each needs approval)

1. **Domain** (owner): register `mahani-iq.com` at Cloudflare Registrar.
2. **Server** (owner): Hetzner CAX21, Ubuntu 24.04, existing SSH key, name
   `examis-staging-1`. Optionally a Hetzner Cloud Firewall with the same
   rules as ufw.
3. **Cloudflare** (owner, in the dashboard):
   - DNS: `A` records `students-staging` and `staff-staging` → server IPv4,
     proxied (orange cloud).
   - SSL/TLS: mode **Full (strict)**; Edge Certificates: Always Use HTTPS,
     minimum TLS 1.2.
   - SSL/TLS → Origin Server → Create Certificate for `*.mahani-iq.com` and
     `mahani-iq.com`, RSA, 15 years. Save the certificate and key straight
     onto the server (next step); never send them by chat or email.
   - Security: keep the free managed rules; add one rate-limiting rule for
     `/staff/login`. Leave Bot Fight Mode off (it can block students' phones).
4. **Provision** (owner, or with approval):

   ```bash
   scp -r deploy/staging root@<IP>:/root/examis-staging
   ssh root@<IP>
   install -d -m 755 /etc/ssl/examis
   nano /etc/ssl/examis/origin.pem    # paste the certificate
   nano /etc/ssl/examis/origin.key    # paste the private key
   STUDENTS_HOST=students-staging.mahani-iq.com \
   STAFF_HOST=staff-staging.mahani-iq.com \
   bash /root/examis-staging/provision.sh
   ```

   The script stops if the key does not match the certificate or the
   certificate does not cover both names. After it runs, SSH still works;
   web ports answer Cloudflare only.
5. **Fonts (optional):** copy `times.ttf` and `timesbd.ttf` (the Windows
   versions; Ubuntu's `ttf-mscorefonts` Times New Roman has no Arabic) to
   `/srv/examis/shared/fonts`, owned by `examis`.
6. **Release:**

   ```powershell
   powershell -ExecutionPolicy Bypass -File deploy\staging\package.ps1
   scp storage\releases\examis-<sha>.tgz examis@<IP>:/tmp/
   ssh examis@<IP> bash /srv/examis/deploy/release.sh /tmp/examis-<sha>.tgz --seed
   ```

   `--seed` adds the synthetic data only when the database has no users.
7. **Test:** both portals through Cloudflare, a student journey with a phone
   photo, the printed form (fonts, barcode), Excel export, then k6 from
   another machine (`tests/load`, `docs/testing/PERFORMANCE.md`). Raise
   `EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR` for the k6 run only.

## Rollback

```bash
ln -sfn /srv/examis/releases/<previous> /srv/examis/current
sudo systemctl reload php8.4-fpm
sudo systemctl restart examis-queue.service examis-queue-fallback.service
```

Migrations are additive and are not rolled back (see
`docs/database/SCHEMA.md`).

## Not covered yet

Off-site backups (`scripts/ops/backup.sh` to a storage target), uptime
monitoring, and production.
