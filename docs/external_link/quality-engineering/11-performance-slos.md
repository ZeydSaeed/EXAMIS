# Performance SLOs (initial targets, not current results)

These are starting targets. A baseline may adjust them with a written reason in `17-execution-log.md`; they are never loosened to make a run pass. Nothing below has been measured on `0f0de40`.

| Metric | Target | k6 threshold |
| --- | --- | --- |
| Read p95 | < 1 s | `http_req_duration{type:read}` p(95)<1000 |
| Write p95 (save, specialization, submit) | < 2 s | `{type:write}` p(95)<2000 |
| Upload p95 (photo ≤ 2 MB) | < 2 s | `{type:upload}` p(95)<2000 |
| PDF p95 | < 3 s | `{type:pdf}` p(95)<3000 |
| Unexpected errors | < 1% | `http_req_failed` rate<0.01 (abort) |
| Steps proven | > 99% under load; 100% in functional runs | `qe_step_ok` |
| Duplicate submissions / references | 0 | integrity SQL |
| Unauthorized successful requests | 0 | refusal probes (exact 403/404) |
| Lost updates | 0 | read-back after every save; stale saves refused |
| Data integrity violations | 0 | integrity SQL, `examis:verify-integrity` |
| Memory during soak | no unbounded growth of PHP-FPM, worker or PostgreSQL RSS | Prometheus trend over the run |

Note: `POST /submit` also renders the form PDF (`ReceiptRenderer::warm`), so it is measured under `write` but may need its own target after the baseline.

Security and data integrity are not traded for speed: a change that weakens validation, permissions, transactions or constraints is refused even if it improves a number.
