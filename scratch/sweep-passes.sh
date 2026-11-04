#!/usr/bin/env bash
# Live verification: catalogue CRUD, pass buckets + auto-expire, admin order filters.
source F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh
login admin admin@dexxoxxbd.test 'Admin@2026'
login buyer customer@dexxoxxbd.test 'Customer@2026'
echo "== logged in: $(cat "$TMP/login-admin.json" | "$PHP" scratch/fmt.php ok 2>/dev/null | tr -d '\n')"

echo
echo "=== A. ADMIN PASS BUCKETS ==="
for s in "" active expiring expired revoked nonsense; do
  q=$([ -n "$s" ] && echo "&status=$s" || echo "")
  get admin "/api/admin_passes.php?per_page=5$q" | "$PHP" scratch/fmt.php meta | tr '\n' ' '
  echo "  <- status='${s:-all}'"
done

echo
echo "-- cross-check buckets against SQL --"
Q "SELECT status, COUNT(*) FROM user_subscriptions GROUP BY status"
echo "expiring (active, ends<+7d): $(Q "SELECT COUNT(*) FROM user_subscriptions WHERE status='active' AND ends_at < DATE_ADD(CURDATE(), INTERVAL 7 DAY)")"

echo
echo "=== B. auto_expire_passes TOGGLE ==="
PASSID=$(Q "SELECT id FROM user_subscriptions WHERE status='active' ORDER BY id LIMIT 1")
ORIG=$(Q "SELECT CONCAT(starts_at,'|',ends_at) FROM user_subscriptions WHERE id=$PASSID")
echo "pass $PASSID original window: $ORIG"

Q "UPDATE user_subscriptions SET starts_at=DATE_SUB(CURDATE(),INTERVAL 40 DAY), ends_at=DATE_SUB(CURDATE(),INTERVAL 30 DAY) WHERE id=$PASSID"
echo "forced window into the past: $(Q "SELECT CONCAT(status,' starts=',starts_at,' ends=',ends_at) FROM user_subscriptions WHERE id=$PASSID")"

req admin PUT "/api/admin_settings.php" '{"auto_expire_passes":"0"}' | tail -2
get admin "/api/admin_passes.php?status=expired" >/dev/null
echo "with sweep gate OFF -> $(Q "SELECT status FROM user_subscriptions WHERE id=$PASSID")"

req admin PUT "/api/admin_settings.php" '{"auto_expire_passes":"1"}' | tail -2
get admin "/api/admin_passes.php?status=expired" >/dev/null
echo "with sweep gate ON  -> $(Q "SELECT status FROM user_subscriptions WHERE id=$PASSID")"

# restore
Q "UPDATE user_subscriptions SET starts_at=SUBSTRING_INDEX('$ORIG','|',1), ends_at=SUBSTRING_INDEX('$ORIG','|',-1), status='active' WHERE id=$PASSID"
echo "restored -> $(Q "SELECT CONCAT(status,' starts=',starts_at,' ends=',ends_at) FROM user_subscriptions WHERE id=$PASSID")"

echo
echo "-- bin/sweep.php honours the same gate --"
req admin PUT "/api/admin_settings.php" '{"auto_expire_passes":"0"}' >/dev/null
Q "UPDATE user_subscriptions SET starts_at=DATE_SUB(CURDATE(),INTERVAL 40 DAY), ends_at=DATE_SUB(CURDATE(),INTERVAL 30 DAY) WHERE id=$PASSID"
"$PHP" bin/sweep.php 2>&1 | tail -3
echo "gate OFF, sweep.php -> $(Q "SELECT status FROM user_subscriptions WHERE id=$PASSID")"
req admin PUT "/api/admin_settings.php" '{"auto_expire_passes":"1"}' >/dev/null
"$PHP" bin/sweep.php 2>&1 | tail -3
echo "gate ON,  sweep.php -> $(Q "SELECT status FROM user_subscriptions WHERE id=$PASSID")"
Q "UPDATE user_subscriptions SET starts_at=SUBSTRING_INDEX('$ORIG','|',1), ends_at=SUBSTRING_INDEX('$ORIG','|',-1), status='active' WHERE id=$PASSID"
echo "restored -> $(Q "SELECT CONCAT(status,' starts=',starts_at,' ends=',ends_at) FROM user_subscriptions WHERE id=$PASSID")"
