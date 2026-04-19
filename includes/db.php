<?php
/**
 * db.php - Kết nối MySQL bằng mysqli
 * PHP 7.2 compatible
 */

define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'shopee_product');
define('DB_CHARSET',  'utf8mb4');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // Trả về null để product.php dùng sample data
    $conn = null;
} else {
    mysqli_set_charset($conn, DB_CHARSET);
}
