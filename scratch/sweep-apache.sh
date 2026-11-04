#!/usr/bin/env bash
# Test-only: verify the same API surface through real Apache (mod_php), where .htaccess CSP applies. Deleted after the run.
source "F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh"
BASE="http://localhost/DexxoxxBD/public"
export BASE

J() { "$PHP" -r '$s=file_get_contents($argv[1]); $a=strpos($s,"{"); $b=strrpos($s,"}"); echo $a===false||$b===false?"{}":substr($s,$a,$b-$a+1);' "$1"; }
ID() { J "$1" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["id"] ?? "";'; }

echo '=== 1. JSON login through Apache (was 422 before the header fix) ==='
login admin admin@dexxoxxbd.test 'Admin@2026'
J "$TMP/login-admin.json" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); echo isset($j["user"]) ? "OK id=".$j["user"]["id"]." role=".$j["user"]["role"] : "FAIL ".json_encode($j), PHP_EOL;'

echo '=== 2. PUT with no CSRF header -> 419 ==='
jar="$TMP/admin.jar"
curl -s -o "$TMP/resp.txt" -w '%{http_code}' -b "$jar" -c "$jar" -X PUT "$BASE/api/plans.php?id=1" -H 'Content-Type: application/json' --data-binary '{"price":9}'
echo " <- $(J "$TMP/resp.txt" | head -c 120)"

echo '=== 3. plan create / update-clear-badge / delete through Apache ==='
req admin POST '/api/plans.php' '{"name":"Apache Probe Plan","price":222,"duration_days":30,"badge":"Probe","features":["one","two"]}' >/dev/null
PLID=$(ID "$TMP/resp.txt"); echo "created id=$PLID $(J "$TMP/resp.txt")"
req admin PUT "/api/plans.php?id=$PLID" '{"badge":null,"features":[],"description":""}' >/dev/null
Q "SELECT id,name,badge,description,features FROM plans WHERE id=$PLID\G"
req admin DELETE "/api/plans.php?id=$PLID" >/dev/null
echo "plans left=$(Q 'SELECT COUNT(*) FROM plans')"

echo '=== 4. product create + null-guard PUT through Apache ==='
req admin POST '/api/products.php' '{"name":"Apache Probe Product","description":"d","price":99,"duration_days":30,"stock":3,"category_id":1}' >/dev/null
PID=$(ID "$TMP/resp.txt"); echo "created id=$PID"
req admin PUT "/api/products.php?id=$PID" '{"description":"","price":null,"name":null}' >/dev/null
Q "SELECT id,name,price,description,image_url,is_featured FROM products WHERE id=$PID\G"
req admin DELETE "/api/products.php?id=$PID" >/dev/null
echo "products left=$(Q 'SELECT COUNT(*) FROM products')"

echo '=== 5. buyer: cart add + read back (JSON body must parse under Apache) ==='
login buyer customer@dexxoxxbd.test 'Customer@2026'
req buyer POST '/api/cart.php' '{"type":"product","id":1,"quantity":2}' >/dev/null
J "$TMP/resp.txt" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN),true); foreach (($j["items"] ?? []) as $i) { echo "  ",($i["name"]??"?")," x",($i["quantity"]??"?"),PHP_EOL; } echo "  total=",($j["total"]??"?"),PHP_EOL;'
req buyer DELETE '/api/cart.php?id=99999' >/dev/null
CARTID=$(Q "SELECT id FROM cart_items WHERE user_id=(SELECT id FROM users WHERE email='customer@dexxoxxbd.test') LIMIT 1")
if [ -n "$CARTID" ]; then req buyer DELETE "/api/cart.php?id=$CARTID" >/dev/null; fi
echo "cart rows left=$(Q 'SELECT COUNT(*) FROM cart_items')"

echo '=== 6. bad JSON must 422/400, never a 500 ==='
jar="$TMP/admin.jar"
curl -s -o "$TMP/resp.txt" -w '%{http_code}' -b "$jar" -c "$jar" -X POST "$BASE/api/plans.php" -H 'Content-Type: application/json' -H "X-CSRF-Token: ${CSRF_CACHE[admin]}" --data-binary '{not json'
echo " <- $(head -c 160 "$TMP/resp.txt")"

echo '=== 7. oversized body guard (128 KB cap) ==='
"$PHP" -r 'file_put_contents("F:/Xamp/htdocs/DexxoxxBD/scratch/tmp/big.json", json_encode(["name"=>str_repeat("A",200000),"price"=>5,"duration_days"=>30]));'
curl -s -o "$TMP/resp.txt" -w '%{http_code}' -b "$TMP/admin.jar" -c "$TMP/admin.jar" -X POST "$BASE/api/plans.php" -H 'Content-Type: application/json' -H "X-CSRF-Token: ${CSRF_CACHE[admin]}" --data-binary "@$TMP/big.json"
echo " <- $(head -c 160 "$TMP/resp.txt")"

echo '=== 8. security headers on a page and on the API (Apache only) ==='
curl -s -D - -o /dev/null "$BASE/index.html" | grep -icE 'content-security-policy'
curl -s -D - -o /dev/null "$BASE/api/products.php" | grep -iE 'content-security-policy|cache-control'

echo '=== cleanup ==='
rm -f "$TMP/big.json"
Q "DELETE FROM rate_limits;"
echo "products=$(Q 'SELECT COUNT(*) FROM products') plans=$(Q 'SELECT COUNT(*) FROM plans') users=$(Q 'SELECT COUNT(*) FROM users') cart=$(Q 'SELECT COUNT(*) FROM cart_items')"
