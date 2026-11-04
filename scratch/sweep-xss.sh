#!/usr/bin/env bash
source F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh
M=/f/Xamp/mysql/bin/mysql.exe
DB() { "$M" -h 127.0.0.1 -P 3307 -u root -D dexxoxxbd -N -B -e "$1"; }
login admin admin@dexxoxxbd.test 'Admin@2026'
login buyer customer@dexxoxxbd.test 'Customer@2026'
head -c 120 "$TMP/login-admin.json"; echo

echo "-- XSS name + description --"
req admin POST "/api/products.php" '{"name":"<script>alert(1)</script> Xss Widget","price":20,"description":"<img src=x onerror=alert(1)> hello\n\n\n\n world","image_url":"https://cdn.example.com/a.png"}'
XID=$(DB "SELECT id FROM products ORDER BY id DESC LIMIT 1")
DB "SELECT CONCAT('name=[',name,'] slug=',slug,' img=',IFNULL(image_url,'NULL')) FROM products WHERE id=$XID"
DB "SELECT CONCAT('desc=[',REPLACE(description,CHAR(10),'|'),']') FROM products WHERE id=$XID"

echo "-- bidi / zero-width stripping --"
req admin PUT "/api/products.php?id=$XID" '{"name":"A‮B​C Widget","price":20}' >/dev/null
DB "SELECT CONCAT('hex=',HEX(name)) FROM products WHERE id=$XID"

echo "-- inactive visibility --"
req admin PUT "/api/products.php?id=$XID" '{"status":"inactive"}' >/dev/null
get "-" "/api/products.php?id=$XID" | head -2
get buyer "/api/products.php?id=$XID" | "$PHP" scratch/fmt.php product.name
get admin "/api/products.php?id=$XID" | "$PHP" scratch/fmt.php product.name
get "-" "/api/products.php?per_page=60" | "$PHP" -r '$d=json_decode(stream_get_contents(STDIN),true);echo in_array("Xss Widget",array_column($d["products"],"name"),true)?"BAD guest sees inactive\n":"OK guest list hides inactive\n";'

echo "-- slug reuse after delete + FK-safe delete of referenced product --"
REQID=$(DB "SELECT product_id FROM order_items WHERE product_id IS NOT NULL ORDER BY product_id LIMIT 1")
echo "seeded product with order history: $REQID"
req admin PUT "/api/products.php?id=$REQID" '{"status":"inactive"}' >/dev/null
get "-" "/api/products.php?id=$REQID" | head -2
req admin PUT "/api/products.php?id=$REQID" '{"status":"active"}' >/dev/null
echo "restored: $(DB "SELECT status FROM products WHERE id=$REQID")"

echo "-- cleanup sweep rows --"
req admin DELETE "/api/products.php?id=$XID"
for p in $(DB "SELECT id FROM products WHERE name LIKE 'Sweep%' OR name LIKE 'Robert)%' OR name='Ab'"); do req admin DELETE "/api/products.php?id=$p" >/dev/null; done
for p in $(DB "SELECT id FROM plans WHERE name LIKE 'Sweep%'"); do req admin DELETE "/api/plans.php?id=$p" >/dev/null; done
DB "SELECT CONCAT('products=',(SELECT COUNT(*) FROM products),' plans=',(SELECT COUNT(*) FROM plans),' leftover_products=[',IFNULL((SELECT GROUP_CONCAT(id) FROM products WHERE name LIKE 'Sweep%' OR name LIKE 'Robert)%' OR name='Ab'),''),'] leftover_plans=[',IFNULL((SELECT GROUP_CONCAT(id) FROM plans WHERE name LIKE 'Sweep%'),''),']')"
