source "F:/Xamp/htdocs/DexxoxxBD/scratch/lib.sh"
login nusrat nusrat@dexxoxxbd.test 'Customer@2026'
echo "login body: $(cat "$TMP/login-nusrat.json" | head -c 200)"
get nusrat '/api/cart.php' | head -c 160; echo
get nusrat '/api/orders.php' | head -c 120; echo
