// node res.js <resources.csv> <bucketMinutes> — averages per time bucket.
const fs = require('fs');
const [file, bucketMin] = process.argv.slice(2);
const lines = fs.readFileSync(file, 'utf8').trim().split('\n');
const h = lines[0].split(',');
const rows = lines.slice(1).map((l) => {
    const v = l.split(',');
    const o = {};
    h.forEach((k, i) => (o[k] = k === 'time' ? Date.parse(v[i]) : v[i] === '' ? null : Number(v[i])));
    return o;
});
const t0 = rows[0].time;
const units = ['k6', 'perf_fpm', 'perf_pg', 'perf_redis', 'perf_queue', 'nginx', 'staging_fpm', 'staging_pg'];
const b = Number(bucketMin) * 60000;
const groups = new Map();
for (let i = 1; i < rows.length; i++) {
    const r = rows[i], p = rows[i - 1];
    const key = Math.floor((r.time - t0) / b);
    if (!groups.has(key)) groups.set(key, []);
    const dt = r.time - p.time;
    const cores = {};
    for (const u of units) {
        const a = r[u + '_cpu_ms'], c = p[u + '_cpu_ms'];
        cores[u] = a != null && c != null && a >= c ? (a - c) / dt : null;
    }
    groups.get(key).push({ r, cores });
}
const avg = (xs) => { const f = xs.filter((x) => x != null); return f.length ? f.reduce((s, x) => s + x, 0) / f.length : NaN; };
const max = (xs) => Math.max(...xs.filter((x) => x != null));
console.log(['min', 'cpu%', 'max_cpu%', 'steal', 'iow', 'load1', 'memAvail', 'swap', 'fpmBusyMax', ...units.map((u) => u + '_cores'), 'fpm_mb', 'pg_mb', 'queue_mb', 'redis_mb', 'k6_mb'].join('\t'));
for (const [k, g] of [...groups].sort((a, b) => a[0] - b[0])) {
    const R = g.map((x) => x.r);
    console.log([
        k * bucketMin,
        avg(R.map((r) => r.cpu_user_pct + r.cpu_system_pct)).toFixed(1),
        max(R.map((r) => r.cpu_user_pct + r.cpu_system_pct)).toFixed(1),
        avg(R.map((r) => r.cpu_steal_pct)).toFixed(1),
        avg(R.map((r) => r.cpu_iowait_pct)).toFixed(1),
        max(R.map((r) => r.load1)).toFixed(2),
        Math.min(...R.map((r) => r.mem_available_mb)),
        max(R.map((r) => r.swap_used_mb)),
        max(R.map((r) => r.perf_fpm_active)),
        ...units.map((u) => avg(g.map((x) => x.cores[u])).toFixed(2)),
        max(R.map((r) => r.perf_fpm_mem_mb)),
        max(R.map((r) => r.perf_pg_mem_mb)),
        max(R.map((r) => r.perf_queue_mem_mb)),
        max(R.map((r) => r.perf_redis_mem_mb)),
        max(R.map((r) => r.k6_mem_mb)),
    ].join('\t'));
}
