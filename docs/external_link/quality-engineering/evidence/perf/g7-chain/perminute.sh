
# Per-minute request count, 5xx/429 and p95/max of upstream time from the perf access log.
# Prints nothing but aggregates (no IPs, no paths).
set -euo pipefail
from="$1"; to="$2"
cat /var/log/nginx/examis-perf.access.log \
| awk -v from="$from" -v to="$to" '
{ t=substr($1,1,16); if (t < from || t > to) next;
  st=$7+0;for(i=1;i<=NF;i++) if ($i ~ /^urt=/) { u=substr($i,5); }
  if (u=="-" || u=="") next;
  n[t]++; if (st>=500) e5[t]++; if (st==429) e4[t]++;
  print t, u > "/tmp/qe-urt.txt" }
END { for (k in n) printf "%s %d %d %d\n", k, n[k], e5[k]+0, e4[k]+0 }' | sort > /tmp/qe-count.txt
sort -k1,1 -k2,2n /tmp/qe-urt.txt | awk '
{ if ($1!=cur) { if (cur!="") out(); cur=$1; c=0 } v[++c]=$2 }
function out() { i=int(c*0.95); if (i<1) i=1; printf "%s p95=%.0fms max=%.0fms\n", cur, v[i]*1000, v[c]*1000 }
END { out() }' > /tmp/qe-p95.txt
join /tmp/qe-count.txt /tmp/qe-p95.txt | awk '{printf "%s req=%s 5xx=%s 429=%s %s %s\n",$1,$2,$3,$4,$5,$6}'
rm -f /tmp/qe-urt.txt /tmp/qe-count.txt /tmp/qe-p95.txt
