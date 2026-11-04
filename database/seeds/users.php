<?php
declare(strict_types=1);

/**
 * Demo accounts. Passwords are documented in README and must be changed before
 * any real deployment; the seeder hashes them with the same bcrypt cost the app uses.
 */
return [
    ['name' => 'DexxoxxBD Admin', 'email' => 'admin@dexxoxxbd.test', 'phone' => '01711000000', 'password' => 'Admin@2026', 'role' => 'admin'],
    ['name' => 'Ibrahim Zihad', 'email' => 'customer@dexxoxxbd.test', 'phone' => '01811000000', 'password' => 'Customer@2026', 'role' => 'customer'],
    ['name' => 'Nusrat Jahan', 'email' => 'nusrat@dexxoxxbd.test', 'phone' => '01911000000', 'password' => 'Customer@2026', 'role' => 'customer'],
    ['name' => 'Tanvir Ahmed', 'email' => 'tanvir@dexxoxxbd.test', 'phone' => null, 'password' => 'Customer@2026', 'role' => 'customer'],
];
