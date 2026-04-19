<?php
/**
 * product.php - Trang chi tiết sản phẩm kiểu Shopee
 * PHP 7.2 | mysqli | Vanilla JS | Pure CSS
 */

session_start();
require_once __DIR__ . '/includes/db.php';

// ============================================================
//  1. VALIDATE PRODUCT ID
// ============================================================
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
if ($product_id <= 0) {
    $product_id = 1;
}

// ============================================================
//  2. ADD TO CART (xử lý POST trước khi output HTML)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_to_cart') {
        $variant_id   = isset($_POST['variant_id'])   ? (int)$_POST['variant_id']   : 0;
        $variant_name = isset($_POST['variant_name'])  ? trim($_POST['variant_name']) : '';
        $price        = isset($_POST['price'])          ? (int)$_POST['price']         : 0;
        $qty          = isset($_POST['quantity'])       ? (int)$_POST['quantity']      : 1;
        $stock        = isset($_POST['stock'])          ? (int)$_POST['stock']         : 0;

        if ($variant_id > 0 && $qty >= 1 && $qty <= $stock && $stock > 0) {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = array();
            }
            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['variant_id'] === $variant_id) {
                    $item['quantity'] = min($stock, $item['quantity'] + $qty);
                    $found = true;
                    break;
                }
            }
            unset($item);
            if (!$found) {
                $_SESSION['cart'][] = array(
                    'variant_id'   => $variant_id,
                    'variant_name' => $variant_name,
                    'price'        => $price,
                    'quantity'     => $qty,
                );
            }
            $_SESSION['toast'] = 'Đã thêm ' . $qty . ' sản phẩm vào giỏ hàng!';
        } else {
            $_SESSION['toast'] = 'Vui lòng chọn đúng phân loại và số lượng.';
        }
        header('Location: product.php?id=' . $product_id);
        exit;
    }

    if ($_POST['action'] === 'remove_cart') {
        $variant_id = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        if (isset($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $k => $item) {
                if ($item['variant_id'] === $variant_id) {
                    unset($_SESSION['cart'][$k]);
                    break;
                }
            }
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
        header('Location: product.php?id=' . $product_id);
        exit;
    }
}

// ============================================================
//  3. LẤY DỮ LIỆU (DB hoặc sample data)
// ============================================================

$product  = null;
$images   = array();
$variants = array();

if ($conn !== null) {
    // ── Lấy sản phẩm ──────────────────────────────────────
    $stmt = mysqli_prepare($conn, 'SELECT id, name, description, rating, review_count, sold FROM products WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($product) {
        $pid = (int)$product['id'];

        // ── Lấy ảnh ──────────────────────────────────────
        $stmt2 = mysqli_prepare($conn, 'SELECT id, image, color FROM product_images WHERE product_id = ? ORDER BY sort_order ASC');
        mysqli_stmt_bind_param($stmt2, 'i', $pid);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        while ($row = mysqli_fetch_assoc($res2)) {
            $images[] = $row;
        }
        mysqli_stmt_close($stmt2);

        // ── Lấy biến thể ─────────────────────────────────
        $stmt3 = mysqli_prepare($conn, 'SELECT id, name, color, size, price, original_price, stock FROM product_variants WHERE product_id = ? ORDER BY color, size');
        mysqli_stmt_bind_param($stmt3, 'i', $pid);
        mysqli_stmt_execute($stmt3);
        $res3 = mysqli_stmt_get_result($stmt3);
        while ($row = mysqli_fetch_assoc($res3)) {
            $variants[] = $row;
        }
        mysqli_stmt_close($stmt3);
    }

    mysqli_close($conn);
}

// ── Fallback: sample data nếu không có DB ─────────────────
if ($product === null) {
    $product = array(
        'id'           => 1,
        'name'         => 'Áo Thun Nam Cao Cấp Premium Cotton - Nhiều Size và Màu Sắc - Chất Liệu Co Giãn 4 Chiều Thời Trang Hàn Quốc',
        'description'  => 'Áo thun nam cao cấp được làm từ vải cotton 100% nguyên chất, mềm mại và thoáng mát. Chất liệu co giãn 4 chiều giúp thoải mái trong mọi hoạt động. Thiết kế hiện đại, phù hợp nhiều dịp mặc từ đi làm đến dạo phố.',
        'rating'       => 4.8,
        'review_count' => 2341,
        'sold'         => 15892,
    );
    $images = array(
        array('id'=>1,'image'=>'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&q=80','color'=>'Trắng'),
        array('id'=>2,'image'=>'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=600&q=80','color'=>'Đen'),
        array('id'=>3,'image'=>'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=600&q=80','color'=>'Xanh Navy'),
        array('id'=>4,'image'=>'https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=600&q=80','color'=>null),
        array('id'=>5,'image'=>'https://images.unsplash.com/photo-1503341504253-dff4815485f1?w=600&q=80','color'=>null),
    );
    $variants = array(
        array('id'=>1, 'name'=>'Trắng - S',       'color'=>'Trắng',     'size'=>'S',   'price'=>185000,'original_price'=>259000,'stock'=>42),
        array('id'=>2, 'name'=>'Trắng - M',       'color'=>'Trắng',     'size'=>'M',   'price'=>185000,'original_price'=>259000,'stock'=>67),
        array('id'=>3, 'name'=>'Trắng - L',       'color'=>'Trắng',     'size'=>'L',   'price'=>195000,'original_price'=>269000,'stock'=>28),
        array('id'=>4, 'name'=>'Trắng - XL',      'color'=>'Trắng',     'size'=>'XL',  'price'=>205000,'original_price'=>279000,'stock'=>15),
        array('id'=>5, 'name'=>'Trắng - XXL',     'color'=>'Trắng',     'size'=>'XXL', 'price'=>215000,'original_price'=>289000,'stock'=>0),
        array('id'=>6, 'name'=>'Đen - S',         'color'=>'Đen',       'size'=>'S',   'price'=>185000,'original_price'=>259000,'stock'=>38),
        array('id'=>7, 'name'=>'Đen - M',         'color'=>'Đen',       'size'=>'M',   'price'=>185000,'original_price'=>259000,'stock'=>54),
        array('id'=>8, 'name'=>'Đen - L',         'color'=>'Đen',       'size'=>'L',   'price'=>195000,'original_price'=>269000,'stock'=>19),
        array('id'=>9, 'name'=>'Đen - XL',        'color'=>'Đen',       'size'=>'XL',  'price'=>205000,'original_price'=>279000,'stock'=>7),
        array('id'=>10,'name'=>'Đen - XXL',       'color'=>'Đen',       'size'=>'XXL', 'price'=>215000,'original_price'=>289000,'stock'=>12),
        array('id'=>11,'name'=>'Xanh Navy - S',   'color'=>'Xanh Navy', 'size'=>'S',   'price'=>195000,'original_price'=>279000,'stock'=>22),
        array('id'=>12,'name'=>'Xanh Navy - M',   'color'=>'Xanh Navy', 'size'=>'M',   'price'=>195000,'original_price'=>279000,'stock'=>31),
        array('id'=>13,'name'=>'Xanh Navy - L',   'color'=>'Xanh Navy', 'size'=>'L',   'price'=>205000,'original_price'=>289000,'stock'=>0),
        array('id'=>14,'name'=>'Xanh Navy - XL',  'color'=>'Xanh Navy', 'size'=>'XL',  'price'=>215000,'original_price'=>299000,'stock'=>11),
        array('id'=>15,'name'=>'Xanh Navy - XXL', 'color'=>'Xanh Navy', 'size'=>'XXL', 'price'=>225000,'original_price'=>309000,'stock'=>5),
    );
}

// ── Tính các giá trị hiển thị ─────────────────────────────
// Danh sách màu sắc (unique, theo thứ tự xuất hiện)
$colors = array();
$color_image_map = array(); // màu => index ảnh
foreach ($variants as $v) {
    $c = $v['color'];
    if (!in_array($c, $colors)) {
        $colors[] = $c;
    }
}
foreach ($images as $idx => $img) {
    if ($img['color'] !== null && !isset($color_image_map[$img['color']])) {
        $color_image_map[$img['color']] = $idx;
    }
}

// Danh sách kích thước (unique)
$sizes = array();
foreach ($variants as $v) {
    if (!in_array($v['size'], $sizes)) {
        $sizes[] = $v['size'];
    }
}

// Giá hiển thị mặc định (biến thể đầu tiên còn hàng)
$default_price    = 0;
$default_original = 0;
foreach ($variants as $v) {
    if ($v['stock'] > 0) {
        $default_price    = $v['price'];
        $default_original = $v['original_price'];
        break;
    }
}
if ($default_price === 0 && count($variants) > 0) {
    $default_price    = $variants[0]['price'];
    $default_original = $variants[0]['original_price'];
}

$discount_pct = ($default_original > 0)
    ? round(($default_original - $default_price) / $default_original * 100)
    : 0;

// Tổng số hàng trong giỏ
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$total_cart_items = 0;
foreach ($cart as $item) {
    $total_cart_items += $item['quantity'];
}
$cart_total = 0;
foreach ($cart as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}

// Toast
$toast_msg = '';
if (isset($_SESSION['toast'])) {
    $toast_msg = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

// ── Helpers ───────────────────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function format_price($price) {
    return number_format((float)$price, 0, ',', '.') . '₫';
}

// JSON cho JavaScript (escaped cho inline script)
$js_variants        = json_encode($variants,        JSON_UNESCAPED_UNICODE);
$js_color_image_map = json_encode($color_image_map, JSON_UNESCAPED_UNICODE);
$js_images          = json_encode(array_column($images, 'image'), JSON_UNESCAPED_UNICODE);

?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($product['name']); ?> - Shopee</title>
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #222; font-size: 14px; }
a { text-decoration: none; color: inherit; }
button { cursor: pointer; font-family: inherit; }

/* ── Variables ── */
:root { --orange: #ee4d2d; --orange-light: #fff8f7; --orange-hover: #d94226; }

/* ── Header ── */
.header {
    background: linear-gradient(to right, #f53d2d, #ff6633);
    padding: 12px 0;
}
.header-inner {
    max-width: 1200px; margin: 0 auto;
    padding: 0 20px;
    display: flex; align-items: center; gap: 16px;
}
.logo {
    color: #fff; font-size: 28px; font-weight: 800;
    font-style: italic; letter-spacing: -1px; flex-shrink: 0;
}
.logo-sep {
    border-left: 1px solid rgba(255,255,255,.4);
    padding-left: 16px; color: #fff; font-size: 18px;
}
.search-wrap { flex: 1; max-width: 520px; display: flex; }
.search-wrap input {
    flex: 1; padding: 9px 16px; border: none;
    border-radius: 4px 0 0 4px; font-size: 14px; outline: none;
}
.search-wrap button {
    background: #fb5533; border: none;
    border-radius: 0 4px 4px 0; padding: 9px 20px;
    color: #fff; font-size: 16px;
}
.cart-wrap { position: relative; }
.cart-wrap .cart-icon { font-size: 28px; color: #fff; }
.cart-badge {
    position: absolute; top: -8px; right: -8px;
    background: #fff; color: var(--orange);
    font-size: 11px; font-weight: 700;
    width: 18px; height: 18px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}

/* ── Breadcrumb ── */
.breadcrumb {
    max-width: 1200px; margin: 0 auto;
    padding: 12px 20px; font-size: 13px; color: #555;
    display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
}
.breadcrumb a { color: var(--orange); }
.breadcrumb span { color: #bbb; }

/* ── Main container ── */
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px 24px; }

/* ── Card ── */
.card {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
}

/* ── Product layout ── */
.product-wrap { display: flex; gap: 0; padding: 20px; flex-wrap: wrap; }

/* ── Left: images ── */
.img-col { width: 420px; flex-shrink: 0; }
.main-img {
    width: 100%; aspect-ratio: 1/1;
    border: 1px solid #f0f0f0; border-radius: 4px;
    overflow: hidden; margin-bottom: 12px;
}
.main-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.thumb-list { display: flex; gap: 8px; flex-wrap: wrap; }
.thumb-list img {
    width: 60px; height: 60px; object-fit: cover;
    border: 2px solid transparent; border-radius: 3px;
    cursor: pointer; transition: border-color .15s;
}
.thumb-list img:hover,
.thumb-list img.active { border-color: var(--orange); }
.share-row {
    margin-top: 16px; display: flex; align-items: center;
    gap: 10px; font-size: 13px; color: #555;
}
.share-icon {
    width: 30px; height: 30px; background: #f5f5f5;
    border-radius: 50%; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 16px;
    border: none;
}

/* ── Right: info ── */
.info-col { flex: 1; min-width: 280px; padding-left: 24px; }
.discount-badge {
    background: var(--orange); color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 2px 6px; border-radius: 2px;
    text-transform: uppercase; letter-spacing: .5px;
    flex-shrink: 0;
}
.product-title-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; }
.product-title { font-size: 17px; font-weight: 500; line-height: 1.4; color: #222; }

/* Rating */
.rating-row { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; }
.stars { color: var(--orange); font-size: 14px; letter-spacing: 1px; }
.rating-val { color: var(--orange); font-weight: 600; font-size: 14px; border-bottom: 1px solid var(--orange); }
.stat-sep { border-left: 1px solid #e0e0e0; padding-left: 16px; color: #555; }
.stat-sep strong { color: #222; }

/* Price */
.price-box {
    background: #fafafa; border-radius: 4px;
    padding: 14px 20px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 12px;
}
.price-current { font-size: 28px; font-weight: 500; color: var(--orange); }
.price-original { text-decoration: line-through; color: #999; font-size: 14px; }
.price-badge {
    background: var(--orange); color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 2px 6px; border-radius: 2px;
}

/* Divider */
.divider { border: none; border-top: 1px solid #f0f0f0; margin: 16px 0; }

/* Info rows */
.info-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; font-size: 13px; }
.info-label { color: #999; min-width: 110px; flex-shrink: 0; padding-top: 2px; }

/* Variant buttons */
.variant-row { display: flex; align-items: flex-start; gap: 0; margin-bottom: 14px; }
.variant-label { color: #999; font-size: 13px; min-width: 110px; flex-shrink: 0; padding-top: 6px; }
.variant-group { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; }
.variant-btn {
    position: relative; display: inline-flex; align-items: center;
    justify-content: center; padding: 6px 14px;
    border: 1px solid #d0d5dd; border-radius: 4px;
    font-size: 13px; cursor: pointer; background: #fff;
    transition: border-color .15s, color .15s;
    user-select: none;
}
.variant-btn:hover { border-color: var(--orange); color: var(--orange); }
.variant-btn.selected {
    border-color: var(--orange); color: var(--orange); background: var(--orange-light);
}
.variant-btn.selected::after {
    content: ''; position: absolute; bottom: -1px; right: -1px;
    width: 18px; height: 18px; background: var(--orange);
    clip-path: polygon(100% 0, 100% 100%, 0 100%);
    border-bottom-right-radius: 4px;
}
.variant-btn.selected::before {
    content: '✓'; position: absolute; bottom: -1px; right: 1px;
    font-size: 9px; color: #fff; z-index: 1; line-height: 1;
}
.variant-btn.disabled { opacity: .35; cursor: not-allowed; text-decoration: line-through; }
.size-header { display: flex; justify-content: flex-end; margin-bottom: 6px; }
.size-guide-btn {
    background: none; border: none; color: var(--orange);
    font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px;
}

/* Quantity */
.qty-wrap { display: flex; align-items: center; }
.qty-btn {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: 1px solid #d0d5dd;
    background: #fff; font-size: 18px;
    transition: background .15s; user-select: none;
}
.qty-btn:disabled { opacity: .4; cursor: not-allowed; }
.qty-btn:not(:disabled):hover { background: #f5f5f5; }
.qty-btn.dec { border-radius: 4px 0 0 4px; }
.qty-btn.inc { border-radius: 0 4px 4px 0; }
.qty-input {
    width: 52px; height: 32px; text-align: center;
    border: 1px solid #d0d5dd; border-left: none; border-right: none;
    font-size: 14px; outline: none;
    -moz-appearance: textfield;
}
.qty-input::-webkit-inner-spin-button,
.qty-input::-webkit-outer-spin-button { -webkit-appearance: none; }
.qty-stock { margin-left: 12px; color: #999; font-size: 13px; }

/* Action buttons */
.action-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.btn-cart {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 28px; border: 1px solid var(--orange);
    background: var(--orange-light); color: var(--orange);
    border-radius: 4px; font-size: 15px; font-weight: 500;
    transition: background .15s;
}
.btn-cart:hover { background: #ffeae6; }
.btn-buy {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 28px; background: var(--orange);
    color: #fff; border: 1px solid var(--orange);
    border-radius: 4px; font-size: 15px; font-weight: 500;
    transition: background .15s;
}
.btn-buy:hover { background: var(--orange-hover); }

/* Guarantees */
.guarantee-row { border: 1px solid #f0f0f0; border-radius: 4px; padding: 12px 16px; }
.guarantee-row ul { list-style: none; display: flex; gap: 20px; flex-wrap: wrap; }
.guarantee-row li { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #555; }

/* Stock hint */
.stock-hint { margin-top: 8px; font-size: 12px; color: #777; }
.stock-hint.out { color: var(--orange); }

/* Toast */
.toast {
    position: fixed; top: 24px; right: 24px;
    background: #333; color: #fff;
    padding: 12px 20px; border-radius: 6px;
    font-size: 14px; z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
    animation: slideIn .25s ease;
}
@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to   { transform: translateX(0);     opacity: 1; }
}

/* Shop card */
.shop-card {
    display: flex; align-items: center;
    gap: 20px; flex-wrap: wrap; padding: 20px;
}
.shop-avatar {
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg,#f53d2d,#f63);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: #fff; font-weight: 700;
    flex-shrink: 0;
}
.shop-stats { display: flex; gap: 24px; flex-wrap: wrap; flex: 1; }
.shop-stat { text-align: center; }
.shop-stat strong { display: block; color: var(--orange); font-size: 15px; }
.shop-stat span { color: #999; font-size: 12px; }
.shop-btns { display: flex; gap: 10px; }
.btn-outline {
    padding: 8px 16px; border: 1px solid var(--orange);
    background: #fff; color: var(--orange);
    border-radius: 4px; font-size: 13px;
}
.btn-follow {
    padding: 8px 16px; border: 1px solid #d0d5dd;
    background: #fff; color: #555;
    border-radius: 4px; font-size: 13px;
}

/* Detail table */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px 40px;
}
.detail-grid .detail-row { display: flex; gap: 8px; }
.detail-grid .detail-label { color: #999; min-width: 100px; }

/* Section title */
.section-title {
    font-size: 18px; font-weight: 500; color: #222;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}

/* Cart summary */
.cart-item-row {
    display: flex; justify-content: space-between;
    align-items: center; padding: 10px 14px;
    background: #fafafa; border-radius: 4px;
    border: 1px solid #f0f0f0; font-size: 14px;
    margin-bottom: 10px; gap: 8px;
}
.cart-item-name { color: #333; flex: 1; }
.cart-item-qty { color: #999; }
.cart-item-price { color: var(--orange); font-weight: 600; white-space: nowrap; }
.cart-item-del {
    background: none; border: none; color: #999;
    font-size: 18px; line-height: 1; cursor: pointer;
}
.cart-item-del:hover { color: var(--orange); }
.cart-total-row {
    display: flex; justify-content: flex-end;
    align-items: center; gap: 16px;
    padding-top: 10px; border-top: 1px solid #f0f0f0; margin-top: 4px;
}
.cart-total-label { font-size: 15px; color: #555; }
.cart-total-price { font-size: 22px; font-weight: 600; color: var(--orange); }

/* Size guide modal */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 10000;
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: #fff; border-radius: 8px;
    padding: 28px; width: 480px; max-width: 95vw;
}
.modal-header {
    display: flex; justify-content: space-between;
    align-items: center; margin-bottom: 18px;
}
.modal-header h3 { font-size: 17px; font-weight: 600; }
.modal-close {
    background: none; border: none; font-size: 22px;
    cursor: pointer; color: #666; line-height: 1;
}
.size-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.size-table th {
    background: var(--orange); color: #fff;
    padding: 9px 12px; text-align: center; font-weight: 600;
}
.size-table td { padding: 9px 12px; text-align: center; }
.size-table tr:nth-child(even) td { background: #fafafa; }
.size-table .size-cell { font-weight: 700; color: var(--orange); }
.modal-note { margin-top: 14px; font-size: 12px; color: #999; }

/* Responsive */
@media (max-width: 768px) {
    .product-wrap { flex-direction: column; }
    .img-col { width: 100%; }
    .info-col { padding-left: 0; padding-top: 16px; }
    .action-row { flex-direction: column; }
    .btn-cart, .btn-buy { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<!-- ── HEADER ─────────────────────────────────────────── -->
<header class="header">
  <div class="header-inner">
    <div class="logo">shopee</div>
    <div class="logo-sep">Chi Tiết Sản Phẩm</div>
    <div class="search-wrap">
      <input type="text" placeholder="Tìm kiếm sản phẩm..." value="Áo thun nam">
      <button>🔍</button>
    </div>
    <div class="cart-wrap">
      <span class="cart-icon">🛒</span>
      <?php if ($total_cart_items > 0): ?>
        <div class="cart-badge"><?php echo $total_cart_items; ?></div>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ── BREADCRUMB ─────────────────────────────────────── -->
<div class="breadcrumb">
  <a href="#">Shopee</a><span>›</span>
  <a href="#">Thời Trang Nam</a><span>›</span>
  <a href="#">Áo Thun</a><span>›</span>
  <span><?php echo e(mb_substr($product['name'], 0, 30, 'UTF-8')); ?>...</span>
</div>

<!-- ── MAIN ───────────────────────────────────────────── -->
<div class="container">

  <!-- Product card -->
  <div class="card product-wrap">

    <!-- LEFT: Images -->
    <div class="img-col">
      <div class="main-img">
        <img id="main-image"
             src="<?php echo e($images[0]['image']); ?>"
             alt="<?php echo e($product['name']); ?>">
      </div>
      <div class="thumb-list">
        <?php foreach ($images as $idx => $img): ?>
          <img src="<?php echo e($img['image']); ?>"
               alt="Ảnh <?php echo $idx + 1; ?>"
               class="<?php echo $idx === 0 ? 'active' : ''; ?>"
               onclick="changeMainImage(<?php echo $idx; ?>, this)">
        <?php endforeach; ?>
      </div>
      <div class="share-row">
        <span>Chia sẻ:</span>
        <button class="share-icon">📘</button>
        <button class="share-icon">💬</button>
        <button class="share-icon">📌</button>
        <button class="share-icon">🐦</button>
        <span style="margin-left:auto;color:var(--orange);cursor:pointer">❤️ Yêu thích</span>
      </div>
    </div>

    <!-- RIGHT: Info -->
    <div class="info-col">

      <!-- Title -->
      <div class="product-title-row">
        <span class="discount-badge" id="discount-badge">-<?php echo $discount_pct; ?>%</span>
        <h1 class="product-title"><?php echo e($product['name']); ?></h1>
      </div>

      <!-- Rating -->
      <div class="rating-row">
        <span class="rating-val"><?php echo $product['rating']; ?></span>
        <span class="stars">
          <?php
          $r = (float)$product['rating'];
          for ($s = 1; $s <= 5; $s++) {
              echo $s <= floor($r) ? '★' : ($s === ceil($r) && fmod($r, 1) > 0 ? '½' : '☆');
          }
          ?>
        </span>
        <div class="stat-sep"><strong><?php echo number_format($product['review_count']); ?></strong> Đánh giá</div>
        <div class="stat-sep"><strong><?php echo number_format($product['sold']); ?></strong> Đã bán</div>
      </div>

      <!-- Price -->
      <div class="price-box">
        <span class="price-current" id="price-current"><?php echo format_price($default_price); ?></span>
        <span class="price-original" id="price-original"><?php echo format_price($default_original); ?></span>
        <span class="price-badge" id="price-badge">-<?php echo $discount_pct; ?>% GIẢM</span>
      </div>

      <hr class="divider">

      <!-- Shipping -->
      <div class="info-row">
        <span class="info-label">Vận chuyển</span>
        <span>🚚 Miễn phí vận chuyển <span style="color:var(--orange)">đến TP.HCM</span></span>
      </div>
      <div class="info-row" style="margin-bottom:16px">
        <span class="info-label"></span>
        <span>Nhận hàng vào <strong>15&nbsp;–&nbsp;16 Tháng 4</strong></span>
      </div>

      <hr class="divider">

      <!-- Màu sắc -->
      <div class="variant-row">
        <span class="variant-label">Màu sắc</span>
        <div class="variant-group" id="color-group">
          <?php foreach ($colors as $color): ?>
            <?php
            $available = false;
            foreach ($variants as $v) {
                if ($v['color'] === $color && $v['stock'] > 0) { $available = true; break; }
            }
            ?>
            <button class="variant-btn<?php echo $available ? '' : ' disabled'; ?>"
                    data-color="<?php echo e($color); ?>"
                    <?php echo $available ? '' : 'disabled'; ?>
                    onclick="selectColor(this, '<?php echo e($color); ?>')">
              <?php echo e($color); ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Kích thước -->
      <div class="variant-row" style="align-items:flex-start">
        <span class="variant-label" style="padding-top:30px">Kích thước</span>
        <div style="flex:1">
          <div class="size-header">
            <button class="size-guide-btn" onclick="document.getElementById('size-modal').classList.add('open')">
              📏 Hướng dẫn chọn size
            </button>
          </div>
          <div class="variant-group" id="size-group">
            <?php foreach ($sizes as $size): ?>
              <button class="variant-btn"
                      data-size="<?php echo e($size); ?>"
                      onclick="selectSize(this, '<?php echo e($size); ?>')">
                <?php echo e($size); ?>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="stock-hint" id="stock-hint">Vui lòng chọn màu sắc trước</div>
        </div>
      </div>

      <hr class="divider">

      <!-- Số lượng -->
      <div class="info-row" style="align-items:center;margin-bottom:20px">
        <span class="info-label">Số lượng</span>
        <div class="qty-wrap">
          <button class="qty-btn dec" id="btn-dec" onclick="changeQty(-1)" disabled>−</button>
          <input type="number" class="qty-input" id="qty-input" value="1" min="1" max="1" oninput="onQtyInput(this)">
          <button class="qty-btn inc" id="btn-inc" onclick="changeQty(1)" disabled>+</button>
          <span class="qty-stock" id="qty-stock"></span>
        </div>
      </div>

      <!-- Nút thêm giỏ + mua ngay -->
      <form method="POST" action="product.php?id=<?php echo $product_id; ?>" id="cart-form">
        <input type="hidden" name="action"       value="add_to_cart">
        <input type="hidden" name="variant_id"   id="f-variant-id"   value="0">
        <input type="hidden" name="variant_name" id="f-variant-name" value="">
        <input type="hidden" name="price"        id="f-price"        value="0">
        <input type="hidden" name="stock"        id="f-stock"        value="0">
        <input type="hidden" name="quantity"     id="f-quantity"     value="1">

        <div class="action-row">
          <button type="submit" class="btn-cart" onclick="return validateCart(event, false)">
            🛒 Thêm Vào Giỏ Hàng
          </button>
          <button type="button" class="btn-buy" onclick="validateCart(event, true)">
            Mua Ngay
          </button>
        </div>
      </form>

      <!-- Cam kết -->
      <div class="guarantee-row">
        <ul>
          <li>🛡️ Chính Hãng 100%</li>
          <li>↩️ Hoàn Tiền 15 Ngày</li>
          <li>🚀 Giao Hàng Nhanh</li>
        </ul>
      </div>

    </div><!-- /.info-col -->
  </div><!-- /.product-wrap -->

  <!-- Shop info -->
  <div class="card" style="margin-top:12px">
    <div class="shop-card">
      <div class="shop-avatar">F</div>
      <div>
        <div style="font-weight:600;font-size:15px;margin-bottom:4px">Fashion Hub Official</div>
        <div style="font-size:12px;color:#999">📍 TP. Hồ Chí Minh</div>
      </div>
      <div class="shop-stats">
        <div class="shop-stat"><strong>4.9/5</strong><span>Đánh giá</span></div>
        <div class="shop-stat"><strong>128.500</strong><span>Người theo dõi</span></div>
        <div class="shop-stat"><strong>99%</strong><span>Tỉ lệ phản hồi</span></div>
      </div>
      <div class="shop-btns">
        <button class="btn-outline">Xem Shop</button>
        <button class="btn-follow">+ Theo dõi</button>
      </div>
    </div>
  </div>

  <!-- Chi tiết sản phẩm -->
  <div class="card" style="margin-top:12px;padding:20px">
    <h2 class="section-title">Chi Tiết Sản Phẩm</h2>
    <div class="detail-grid">
      <div class="detail-row"><span class="detail-label">Danh mục</span><span>Áo Thun Nam</span></div>
      <div class="detail-row"><span class="detail-label">Chất liệu</span><span>Cotton 100%</span></div>
      <div class="detail-row"><span class="detail-label">Xuất xứ</span><span>Việt Nam</span></div>
      <div class="detail-row"><span class="detail-label">Gửi từ</span><span>TP. Hồ Chí Minh</span></div>
      <div class="detail-row"><span class="detail-label">Kho hàng</span><span>287 sản phẩm</span></div>
      <div class="detail-row"><span class="detail-label">Đã bán</span><span><?php echo number_format($product['sold']); ?></span></div>
    </div>
  </div>

  <!-- Mô tả sản phẩm -->
  <div class="card" style="margin-top:12px;padding:20px">
    <h2 class="section-title">Mô Tả Sản Phẩm</h2>
    <p style="line-height:1.8;color:#333;margin-bottom:16px"><?php echo nl2br(e($product['description'])); ?></p>
    <ul style="padding-left:20px">
      <li style="margin-bottom:6px">Cotton 100% cao cấp</li>
      <li style="margin-bottom:6px">Co giãn 4 chiều</li>
      <li style="margin-bottom:6px">Không ra màu, không co rút</li>
      <li style="margin-bottom:6px">Đường may chắc chắn</li>
      <li style="margin-bottom:6px">Phù hợp đi làm và dạo phố</li>
    </ul>
  </div>

  <!-- Giỏ hàng -->
  <?php if (!empty($cart)): ?>
  <div class="card" style="margin-top:12px;padding:20px">
    <h2 class="section-title">🛒 Giỏ Hàng (<?php echo $total_cart_items; ?> sản phẩm)</h2>
    <?php foreach ($cart as $item): ?>
      <div class="cart-item-row">
        <span class="cart-item-name">
          <?php echo e(mb_substr($product['name'], 0, 28, 'UTF-8')); ?>... (<?php echo e($item['variant_name']); ?>)
        </span>
        <span class="cart-item-qty">× <?php echo (int)$item['quantity']; ?></span>
        <span class="cart-item-price"><?php echo format_price($item['price'] * $item['quantity']); ?></span>
        <form method="POST" action="product.php?id=<?php echo $product_id; ?>" style="margin:0">
          <input type="hidden" name="action"     value="remove_cart">
          <input type="hidden" name="variant_id" value="<?php echo (int)$item['variant_id']; ?>">
          <button type="submit" class="cart-item-del" title="Xóa">×</button>
        </form>
      </div>
    <?php endforeach; ?>
    <div class="cart-total-row">
      <span class="cart-total-label">Tổng cộng:</span>
      <span class="cart-total-price"><?php echo format_price($cart_total); ?></span>
      <a href="checkout.php" class="btn-buy" style="padding:10px 24px;text-decoration:none">Thanh Toán</a>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.container -->

<!-- ── SIZE GUIDE MODAL ───────────────────────────────── -->
<div class="modal-overlay" id="size-modal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Bảng Hướng Dẫn Kích Thước</h3>
      <button class="modal-close" onclick="document.getElementById('size-modal').classList.remove('open')">×</button>
    </div>
    <table class="size-table">
      <thead>
        <tr><th>Cỡ</th><th>Chiều cao (cm)</th><th>Cân nặng (kg)</th><th>Vòng ngực (cm)</th><th>Vòng eo (cm)</th></tr>
      </thead>
      <tbody>
        <?php
        $size_guide = array(
            array('S',   '155–160', '48–55',  '82–86',  '66–70'),
            array('M',   '160–165', '55–62',  '86–92',  '70–76'),
            array('L',   '165–170', '62–70',  '92–98',  '76–82'),
            array('XL',  '170–175', '70–80',  '98–104', '82–88'),
            array('XXL', '175–180', '80–90',  '104–110','88–94'),
        );
        foreach ($size_guide as $row):
        ?>
          <tr>
            <td class="size-cell"><?php echo e($row[0]); ?></td>
            <td><?php echo e($row[1]); ?></td>
            <td><?php echo e($row[2]); ?></td>
            <td><?php echo e($row[3]); ?></td>
            <td><?php echo e($row[4]); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="modal-note">* Số liệu mang tính tham khảo. Nên chọn size lớn hơn nếu số đo nằm ở giữa hai size.</p>
  </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────── -->
<?php if ($toast_msg !== ''): ?>
  <div class="toast" id="toast"><?php echo e($toast_msg); ?></div>
  <script>setTimeout(function(){ var t=document.getElementById('toast'); if(t) t.remove(); }, 2800);</script>
<?php endif; ?>

<!-- ── JAVASCRIPT ─────────────────────────────────────── -->
<script>
// Dữ liệu biến thể từ PHP
var VARIANTS        = <?php echo $js_variants; ?>;
var COLOR_IMG_MAP   = <?php echo $js_color_image_map; ?>;
var IMAGES          = <?php echo $js_images; ?>;

var selectedColor = null;
var selectedSize  = null;

// ── Chuyển ảnh chính ──────────────────────────────────
function changeMainImage(idx, el) {
    document.getElementById('main-image').src = IMAGES[idx];
    var thumbs = document.querySelectorAll('.thumb-list img');
    for (var i = 0; i < thumbs.length; i++) {
        thumbs[i].classList.remove('active');
    }
    if (el) el.classList.add('active');
}

// ── Chọn màu sắc ─────────────────────────────────────
function selectColor(btn, color) {
    selectedColor = color;
    selectedSize  = null;

    // Cập nhật UI màu
    var colorBtns = document.querySelectorAll('#color-group .variant-btn');
    for (var i = 0; i < colorBtns.length; i++) {
        colorBtns[i].classList.remove('selected');
    }
    btn.classList.add('selected');

    // Đổi ảnh theo màu
    if (COLOR_IMG_MAP.hasOwnProperty(color)) {
        changeMainImage(COLOR_IMG_MAP[color], document.querySelectorAll('.thumb-list img')[COLOR_IMG_MAP[color]]);
    }

    // Cập nhật size buttons
    refreshSizeButtons();

    // Reset size selection
    var sizeBtns = document.querySelectorAll('#size-group .variant-btn');
    for (var j = 0; j < sizeBtns.length; j++) {
        sizeBtns[j].classList.remove('selected');
    }

    // Cập nhật giá với màu được chọn (lấy biến thể đầu còn hàng)
    var firstAvail = null;
    for (var k = 0; k < VARIANTS.length; k++) {
        if (VARIANTS[k].color === color && VARIANTS[k].stock > 0) {
            firstAvail = VARIANTS[k];
            break;
        }
    }
    if (firstAvail) updatePrice(firstAvail.price, firstAvail.original_price);

    document.getElementById('stock-hint').textContent = 'Vui lòng chọn kích thước';
    document.getElementById('stock-hint').className = 'stock-hint';
    resetQty(0);
}

// ── Cập nhật size buttons theo màu đã chọn ───────────
function refreshSizeButtons() {
    var sizeBtns = document.querySelectorAll('#size-group .variant-btn');
    for (var i = 0; i < sizeBtns.length; i++) {
        var size = sizeBtns[i].getAttribute('data-size');
        var available = false;
        for (var j = 0; j < VARIANTS.length; j++) {
            var v = VARIANTS[j];
            if (v.size === size && v.stock > 0) {
                if (selectedColor === null || v.color === selectedColor) {
                    available = true;
                    break;
                }
            }
        }
        if (available) {
            sizeBtns[i].classList.remove('disabled');
            sizeBtns[i].disabled = false;
            sizeBtns[i].style.textDecoration = '';
        } else {
            sizeBtns[i].classList.add('disabled');
            sizeBtns[i].disabled = true;
            sizeBtns[i].style.textDecoration = 'line-through';
        }
    }
}

// ── Chọn kích thước ──────────────────────────────────
function selectSize(btn, size) {
    if (btn.disabled) return;
    selectedSize = size;

    var sizeBtns = document.querySelectorAll('#size-group .variant-btn');
    for (var i = 0; i < sizeBtns.length; i++) {
        sizeBtns[i].classList.remove('selected');
    }
    btn.classList.add('selected');

    // Tìm biến thể tương ứng
    var variant = null;
    for (var j = 0; j < VARIANTS.length; j++) {
        var v = VARIANTS[j];
        if (v.size === size && (selectedColor === null || v.color === selectedColor)) {
            variant = v;
            break;
        }
    }

    if (variant) {
        updatePrice(variant.price, variant.original_price);

        var hintEl = document.getElementById('stock-hint');
        if (variant.stock === 0) {
            hintEl.textContent = 'Hết hàng';
            hintEl.className   = 'stock-hint out';
            resetQty(0);
        } else {
            hintEl.textContent = 'Còn lại: ' + variant.stock + ' sản phẩm';
            hintEl.className   = 'stock-hint';
            resetQty(variant.stock);
        }

        // Điền form ẩn
        document.getElementById('f-variant-id').value   = variant.id;
        document.getElementById('f-variant-name').value  = variant.name;
        document.getElementById('f-price').value         = variant.price;
        document.getElementById('f-stock').value         = variant.stock;
        document.getElementById('f-quantity').value      = 1;
    }
}

// ── Cập nhật hiển thị giá ─────────────────────────────
function updatePrice(price, original) {
    var pct = Math.round((original - price) / original * 100);
    document.getElementById('price-current').textContent  = formatVND(price);
    document.getElementById('price-original').textContent = formatVND(original);
    document.getElementById('price-badge').textContent    = '-' + pct + '% GIẢM';
    document.getElementById('discount-badge').textContent = '-' + pct + '%';
}

function formatVND(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '₫';
}

// ── Số lượng ─────────────────────────────────────────
function resetQty(maxStock) {
    var dec = document.getElementById('btn-dec');
    var inc = document.getElementById('btn-inc');
    var inp = document.getElementById('qty-input');
    var stk = document.getElementById('qty-stock');

    if (maxStock <= 0) {
        inp.value = 1; inp.max = 1;
        dec.disabled = true; inc.disabled = true;
        stk.textContent = '';
    } else {
        inp.value = 1; inp.max = maxStock;
        dec.disabled = true;
        inc.disabled = (maxStock <= 1);
        stk.textContent = maxStock + ' sản phẩm có sẵn';
    }
    document.getElementById('f-quantity').value = inp.value;
}

function changeQty(delta) {
    var inp = document.getElementById('qty-input');
    var max = parseInt(inp.max, 10) || 1;
    var val = parseInt(inp.value, 10) || 1;
    val = Math.min(max, Math.max(1, val + delta));
    inp.value = val;
    document.getElementById('btn-dec').disabled = (val <= 1);
    document.getElementById('btn-inc').disabled = (val >= max);
    document.getElementById('f-quantity').value = val;
}

function onQtyInput(inp) {
    var max = parseInt(inp.max, 10) || 1;
    var val = parseInt(inp.value, 10);
    if (isNaN(val) || val < 1) val = 1;
    if (val > max) val = max;
    inp.value = val;
    document.getElementById('btn-dec').disabled = (val <= 1);
    document.getElementById('btn-inc').disabled = (val >= max);
    document.getElementById('f-quantity').value = val;
}

// ── Validate trước khi submit ─────────────────────────
function validateCart(e, buyNow) {
    if (!selectedColor) { showToast('Vui lòng chọn màu sắc!'); return false; }
    if (!selectedSize)  { showToast('Vui lòng chọn kích thước!'); return false; }

    var stock = parseInt(document.getElementById('f-stock').value, 10);
    if (stock <= 0) { showToast('Sản phẩm này đã hết hàng!'); return false; }

    if (buyNow) {
        window.location.href = 'checkout.php';
        return false;
    }
    return true;
}

// ── Toast JS (không cần reload) ───────────────────────
function showToast(msg) {
    var old = document.getElementById('toast');
    if (old) old.remove();
    var t = document.createElement('div');
    t.id = 'toast'; t.className = 'toast'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 2800);
}

// Init: làm mờ size buttons khi chưa chọn màu
refreshSizeButtons();
</script>
</body>
</html>
