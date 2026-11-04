source "F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh"
login admin admin@dexxoxxbd.test 'Admin@2026'
T=$(Q "SELECT id FROM users WHERE email='tanvir@dexxoxxbd.test'")
echo "target user id=$T before: $(Q "SELECT id,email,role,status FROM users WHERE id=$T")"
echo '--- PUT {"role":"","status":""} (expect 422, row untouched)'
req admin PUT "/api/users.php?id=$T" '{"role":"","status":""}'
echo '--- PUT {"role":null} (expect 422 as well, not a silent demotion)'
req admin PUT "/api/users.php?id=$T" '{"role":null}'
echo '--- PUT {"status":"active","role":"customer"} on an already-customer (expect 200)'
req admin PUT "/api/users.php?id=$T" '{"status":"active","role":"customer"}'
echo "after: $(Q "SELECT id,email,role,status FROM users WHERE id=$T")"
echo '=== cleanup leftover probe rows via API ==='
req admin DELETE '/api/products.php?id=22'
req admin DELETE '/api/plans.php?id=7'
req admin DELETE '/api/plans.php?id=8'
echo "products=$(Q 'SELECT COUNT(*) FROM products') plans=$(Q 'SELECT COUNT(*) FROM plans') users=$(Q 'SELECT COUNT(*) FROM users')"
Q "DELETE FROM rate_limits;"
