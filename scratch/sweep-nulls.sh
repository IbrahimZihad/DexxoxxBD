#!/usr/bin/env bash
# Test-only regression for explicit-null "clear this field" semantics. Deleted after the run.
source "F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh"

# read the JSON body curl left in resp.txt, stripped of the status prefix
J() { "$PHP" -r '$s=file_get_contents($argv[1]); $a=strpos($s,"{"); $b=strrpos($s,"}"); echo $a===false||$b===false?"{}":substr($s,$a,$b-$a+1);' "$1"; }
ID() { J "$1" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["id"] ?? "";'; }

login admin admin@dexxoxxbd.test 'Admin@2026'
login buyer customer@dexxoxxbd.test 'Customer@2026'

echo '=== 1. product create (temp probe) ==='
req admin POST '/api/products.php' '{"name":"Null Semantics Probe","description":"original text","price":150,"duration_days":30,"stock":5,"category_id":1,"image_url":"https://example.com/a.png","compare_at_price":200,"is_featured":true}' >/dev/null
PID=$(ID "$TMP/resp.txt")
echo "product_id=$PID"
J "$TMP/resp.txt"; echo

echo '=== 2. PUT: null on fields with no empty state, empty string on clearable ones ==='
req admin PUT "/api/products.php?id=$PID" '{"name":null,"price":null,"description":"","image_url":"","compare_at_price":null,"category_id":null,"stock":null,"duration_days":null,"is_featured":false,"status":null}'

echo '=== 3. row after (expect name/price/duration/stock kept; description/image/compare/category cleared; is_featured 0) ==='
Q "SELECT id,name,price,description,image_url,compare_at_price,category_id,stock,duration_days,is_featured,status FROM products WHERE id=$PID\G"

echo '=== 4. register with empty phone (phone must store NULL, account must be created) ==='
rm -f "$TMP/probe.jar"
PT=$(curl -s -c "$TMP/probe.jar" "$BASE/api/auth.php?action=token" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["csrf_token"] ?? "";')
CSRF_CACHE[probe]="$PT"
req probe POST '/api/auth.php?action=register' '{"name":"Phone Blank Probe","email":"phoneblank@example.test","password":"Probe#2026ok","phone":""}' >/dev/null
J "$TMP/resp.txt" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); echo "created id=",$j["user"]["id"] ?? "NONE"," phone=",var_export($j["user"]["phone"] ?? "ABSENT",true),PHP_EOL;'
TARGET=$(Q "SELECT id FROM users WHERE email='phoneblank@example.test'")
echo "user_row: $(Q "SELECT id,email,phone,status FROM users WHERE email='phoneblank@example.test'")"

echo '=== 5. admin user update with empty role/status -> must 422 (no silent clear) ==='
req admin PUT "/api/users.php?id=$TARGET" '{"role":"","status":""}' | J /dev/stdin | "$PHP" -r '$s=stream_get_contents(STDIN);echo trim($s),PHP_EOL;'
req admin PUT "/api/admin_users.php?id=$TARGET" '{"role":"","status":""}'
echo '--- row unchanged:'
Q "SELECT id,role,status FROM users WHERE id=$TARGET"

echo '=== 6. cart add: quantity null / "" / omitted -> all mean 1 ==='
for BODY in 'null' '""' '1'; do
  req buyer POST '/api/cart.php' "{\"type\":\"product\",\"id\":$PID,\"quantity\":$BODY}" >/dev/null
  echo -n "  quantity=$BODY -> "
  J "$TMP/resp.txt" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); $out=[]; foreach (($j["items"] ?? []) as $i) { $out[]=($i["name"]??"?")." x".($i["quantity"]??"?"); } echo implode(" | ",$out) ?: "ERROR ".json_encode($j["error"] ?? $j),PHP_EOL;'
done
echo '--- omitted:'
req buyer POST '/api/cart.php' "{\"type\":\"product\",\"id\":$PID}" >/dev/null
J "$TMP/resp.txt" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); $out=[]; foreach (($j["items"] ?? []) as $i) { $out[]=($i["name"]??"?")." x".($i["quantity"]??"?"); } echo "  -> ",implode(" | ",$out) ?: json_encode($j),PHP_EOL;'

echo '=== 7. cart update with quantity null -> 422 (required rule still wins) ==='
CARTID=$(Q "SELECT id FROM cart_items WHERE product_id=$PID ORDER BY id DESC LIMIT 1")
req buyer PUT "/api/cart.php?id=$CARTID" '{"quantity":null}'

echo '=== 8. plan create with empty badge/description/features (expect NULL badge, "" desc, [] features) ==='
req admin POST '/api/plans.php' '{"name":"Empty Badge Probe","price":111,"duration_days":30,"badge":"","description":"","features":[]}' >/dev/null
PLID=$(ID "$TMP/resp.txt")
echo "plan_id=$PLID"
Q "SELECT id,name,badge,description,features,duration_days,status FROM plans WHERE id=$PLID\G"

echo '=== cleanup ==='
req admin DELETE "/api/products.php?id=$PID"
req admin DELETE "/api/plans.php?id=$PLID"
Q "DELETE FROM users WHERE email='phoneblank@example.test'; DELETE FROM cart_items WHERE user_id=(SELECT id FROM users WHERE email='customer@dexxoxxbd.test') AND product_id IN ($PID); DELETE FROM rate_limits;"
echo "products=$(Q 'SELECT COUNT(*) FROM products') plans=$(Q 'SELECT COUNT(*) FROM plans') probe_users=$(Q "SELECT COUNT(*) FROM users WHERE email='phoneblank@example.test'") cart=$(Q 'SELECT COUNT(*) FROM cart_items')"
echo 'done'
