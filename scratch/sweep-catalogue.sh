#!/usr/bin/env bash
# Live verification: admin order filters, product CRUD, plan CRUD.
source F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh
login admin admin@dexxoxxbd.test 'Admin@2026'
login buyer customer@dexxoxxbd.test 'Customer@2026'

echo "=== C. ADMIN ORDER LIST ==="
Q "SELECT status, COUNT(*) FROM orders GROUP BY status"
for s in "" pending paid failed cancelled expired bogus; do
  q=$([ -n "$s" ] && echo "&status=$s" || echo "")
  get admin "/api/admin_orders.php?per_page=3$q" | "$PHP" scratch/fmt.php meta | tr '\n' ' '
  echo " <- status='${s:-all}'"
done
echo "-- search --"
get admin "/api/admin_orders.php?q=DXB-" | "$PHP" scratch/fmt.php meta | tr '\n' ' '; echo " <- q=DXB-"
get admin "/api/admin_orders.php?q=customer@dexxoxxbd.test" | "$PHP" scratch/fmt.php meta | tr '\n' ' '; echo " <- q=email"
get admin "/api/admin_orders.php?q=Ibrahim" | "$PHP" scratch/fmt.php meta | tr '\n' ' '; echo " <- q=name"
get admin "/api/admin_orders.php?q=%25" | "$PHP" scratch/fmt.php meta | tr '\n' ' '; echo " <- q=% (wildcard must be escaped)"
get admin "/api/admin_orders.php?q=nope-nope" | "$PHP" scratch/fmt.php meta | tr '\n' ' '; echo " <- q=nope"
echo "-- paging --"
get admin "/api/admin_orders.php?per_page=2&page=1" | "$PHP" scratch/fmt.php | tr '\n' ' '; echo
get admin "/api/admin_orders.php?per_page=2&page=99" | "$PHP" scratch/fmt.php | tr '\n' ' '; echo " <- page beyond last"
echo "-- buyer cannot list all orders --"
get buyer "/api/admin_orders.php"

echo
echo "=== D. PRODUCT CRUD ==="
echo "-- 1. create minimal --"
req admin POST "/api/products.php" '{"name":"Sweep Widget One","price":499}'
PID1=$(Q "SELECT id FROM products WHERE slug='sweep-widget-one'")
echo "pid1=$PID1  row: $(Q "SELECT CONCAT(name,' | price=',price,' | stock=',stock,' | dur=',duration_days,' | status=',status,' | feat=',is_featured) FROM products WHERE id=$PID1")"

echo "-- 2. missing price / bad price --"
req admin POST "/api/products.php" '{"name":"Sweep No Price"}'
req admin POST "/api/products.php" '{"name":"Sweep Zero","price":0}'
req admin POST "/api/products.php" '{"name":"Sweep Neg","price":-5}'
req admin POST "/api/products.php" '{"name":"Sweep Text","price":"abc"}'

echo "-- 3. price rounding + long name (validator length fix) --"
req admin POST "/api/products.php" '{"name":"Sweep Round","price":"12.3456"}'
echo "stored price: $(Q "SELECT price FROM products WHERE slug='sweep-round'")"
LONG=$(printf 'a%.0s' {1..201})
req admin POST "/api/products.php" "{\"name\":\"$LONG\",\"price\":10}" | head -4
req admin POST "/api/products.php" '{"name":"Ab","price":10}' >/dev/null
req admin DELETE "/api/products.php?id=$(Q "SELECT id FROM products WHERE name='Ab'")" >/dev/null

echo "-- 4. XSS + SQL-shaped name, evil image URLs --"
req admin POST "/api/products.php" '{"name":"<script>alert(1)</scr"ipt>Xss Widget","price":20,"image_url":"javascript:alert(document.cookie)","description":"<img src=x onerror=alert(1)> hello\n\n\n world"}'
XID=$(Q "SELECT id FROM products WHERE slug LIKE 'xss-widget%' OR slug LIKE 'alert%' ORDER BY id DESC LIMIT 1")
echo "xss row: $(Q "SELECT CONCAT('[',name,'] img=',IFNULL(image_url,'NULL'),' desc=[',description,']') FROM products WHERE id=$XID")"
req admin PUT "/api/products.php?id=$XID" '{"image_url":"http://example.com/plain.png"}' >/dev/null
echo "http url kept? $(Q "SELECT IFNULL(image_url,'NULL') FROM products WHERE id=$XID")"
req admin PUT "/api/products.php?id=$XID" '{"image_url":"ftp://example.com/x.png"}' >/dev/null
echo "ftp url -> $(Q "SELECT IFNULL(image_url,'NULL') FROM products WHERE id=$XID")"
req admin PUT "/api/products.php?id=$XID" '{"image_url":"data:text/html,<script>alert(1)</script>"}' >/dev/null
echo "data url -> $(Q "SELECT IFNULL(image_url,'NULL') FROM products WHERE id=$XID")"
SQLY="Robert'); DROP TABLE products;--"
req admin POST "/api/products.php" "{\"name\":\"$SQLY\",\"price\":15}" >/dev/null
echo "injection-shaped name stored literally: $(Q "SELECT name FROM products WHERE name LIKE 'Robert)%'")"
echo "products table still present: $(Q "SHOW TABLES LIKE 'products'")"

echo "-- 5. compare_at_price below price --"
req admin PUT "/api/products.php?id=$PID1" '{"price":500,"compare_at_price":400}'
req admin PUT "/api/products.php?id=$PID1" '{"price":500,"compare_at_price":900}' >/dev/null
echo "stored: $(Q "SELECT CONCAT(price,'/',IFNULL(compare_at_price,'NULL')) FROM products WHERE id=$PID1")"

echo "-- 6. unknown category --"
req admin PUT "/api/products.php?id=$PID1" '{"category_id":99999}'
CID=$(Q "SELECT id FROM categories ORDER BY id LIMIT 1")
req admin PUT "/api/products.php?id=$PID1" "{\"category_id\":$CID}" >/dev/null
echo "category set to $CID -> $(Q "SELECT category_id FROM products WHERE id=$PID1")"

echo "-- 7. duplicate name -> unique slug --"
req admin POST "/api/products.php" '{"name":"Sweep Widget One","price":100}'

echo "-- 8. partial PUT (stock only) --"
BEFORE=$(Q "SELECT CONCAT(name,'|',price) FROM products WHERE id=$PID1")
req admin PUT "/api/products.php?id=$PID1" '{"stock":7}' >/dev/null
echo "before=$BEFORE after=$(Q "SELECT CONCAT(name,'|',price,'|stock=',stock) FROM products WHERE id=$PID1")"

echo "-- 9. inactive hides from guests, visible to admin --"
req admin PUT "/api/products.php?id=$PID1" '{"status":"inactive"}' >/dev/null
get "-" "/api/products.php?id=$PID1"
get "-" "/api/products.php?per_page=100" | "$PHP" -r '$d=json_decode(stream_get_contents(STDIN),true);echo in_array("Sweep Widget One",array_column($d["products"],"name"),true)?"GUEST SEES INACTIVE PRODUCT (BAD)\n":"guest list excludes inactive: OK\n";'
get admin "/api/products.php?id=$PID1" | "$PHP" scratch/fmt.php product.name
req buyer PUT "/api/products.php?id=$PID1" '{"status":"active"}'
req admin PUT "/api/products.php?id=$PID1" '{"status":"active"}' >/dev/null

echo "-- 10. delete own products, seeded ones untouched --"
for p in $PID1 $XID $(Q "SELECT id FROM products WHERE slug LIKE 'sweep-widget-one%' OR slug='sweep-round' OR name LIKE 'Robert)%'"); do
  req admin DELETE "/api/products.php?id=$p" >/dev/null
done
get "-" "/api/products.php?id=$PID1"
echo "remaining sweep rows: [$(Q "SELECT GROUP_CONCAT(id) FROM products WHERE name LIKE 'Sweep%' OR name LIKE 'Robert)%'")]  total products: $(Q "SELECT COUNT(*) FROM products")"

echo
echo "=== E. PLAN CRUD ==="
req admin POST "/api/plans.php" '{"name":"Sweep Plan","price":1500,"duration_days":90,"features":["a","b"],"badge":"Popular","sort_order":5}'
PLANID=$(Q "SELECT id FROM plans WHERE slug='sweep-plan'")
echo "plan row: $(Q "SELECT CONCAT(name,'|',price,'|',duration_days,'|',IFNULL(badge,'-'),'|sort=',sort_order,'|features=',features) FROM plans WHERE id=$PLANID")"
req admin POST "/api/plans.php" '{"name":"Sweep Bad","price":100}'
BIG=$(printf '"x",' {1..13}); BIG="[${BIG%?}]"
req admin POST "/api/plans.php" "{\"name\":\"Sweep Many Feats\",\"price\":100,\"duration_days\":30,\"features\":$BIG}"
LONGBADGE=$(printf 'b%.0s' {1..41})
req admin POST "/api/plans.php" "{\"name\":\"Sweep Long Badge\",\"price\":100,\"duration_days\":30,\"badge\":\"$LONGBADGE\"}"
req admin PUT "/api/plans.php?id=$PLANID" '{"price":1800,"status":"inactive"}' >/dev/null
echo "after PUT: $(Q "SELECT CONCAT(price,'|',status) FROM plans WHERE id=$PLANID")"
get "-" "/api/plans.php?per_page=50" | "$PHP" -r '$d=json_decode(stream_get_contents(STDIN),true);echo in_array("Sweep Plan",array_column($d["plans"],"name"),true)?"GUEST SEES INACTIVE PLAN (BAD)\n":"guest plan list excludes inactive: OK\n";'
get admin "/api/plans.php?id=$PLANID" | "$PHP" scratch/fmt.php plan.name
req admin DELETE "/api/plans.php?id=$PLANID" >/dev/null
echo "remaining sweep plans: [$(Q "SELECT GROUP_CONCAT(id) FROM plans WHERE name LIKE 'Sweep%'")]  total plans: $(Q "SELECT COUNT(*) FROM plans")"

echo
echo "=== state check ==="
Q "SELECT (SELECT COUNT(*) FROM products) products, (SELECT COUNT(*) FROM plans) plans, (SELECT COUNT(*) FROM users) users, (SELECT COUNT(*) FROM orders) orders"
