# ADR-0006: Database-backed drivers locally, Redis in production

- Status: Accepted (2026-10-14)

Redis is not available on the development workstation. Cache, queue, and session use the `database` driver locally and in CI. Production should use Redis for cache, queue, and locks (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`). Sessions may stay in the database.

No code depends on a specific driver. Correctness-critical serialization uses database row locks, never cache locks.

Queues are separated by workload: `default` (notifications) and `documents` (scanning). Each worker pool can be sized independently.
