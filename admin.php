<?php
/**
 * admin.php - Trang quản trị sản phẩm Shopee
 * PHP 7.2 | mysqli | Không cần framework
 *
 * Truy cập: http://localhost/admin.php
 * Mật khẩu mặc định: admin123  (đổi ở dòng ADMIN_PASS bên dưới)
 */

session_start();
require_once __DIR__ . '/includes/db.php';

// ============================================================
//  CẤU HÌNH
// ============================================================
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');   // ← đổi mật khẩu ở đây

// ============================================================
//  ĐĂNG NHẬP / ĐĂNG XUẤT
// ============================================================
if (isset($_POST['do_login'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $_SESSION['admin_error'] = 'Sai tài khoản hoặc mật khẩu!';
    }
    header('Location: admin.php'); exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php'); exit;
}
$logged_in = !empty($_SESSION['admin_logged_in']);

// ============================================================
//  HELPERS
// ============================================================
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($n) { return number_format((float)$n, 0, ',', '.') . '₫'; }
function redirect($url) { header('Location: ' . $url); exit; }

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = array('msg' => $msg, 'type' => $type);
}
function pop_flash() {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}

// ============================================================
//  DATABASE CHECK
// ============================================================
if ($conn === null && $logged_in) {
    // Không có DB → chỉ thông báo
}

// ============================================================
//  ACTIONS (chỉ khi đã đăng nhập & có DB)
// ============================================================
if ($logged_in && $conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $act = isset($_POST['act']) ? $_POST['act'] : '';

    // ── Thêm sản phẩm ──────────────────────────────────────
    if ($act === 'add_product') {
        $name  = trim($_POST['name']);
        $desc  = trim($_POST['description']);
        $rating = (float)str_replace(',', '.', $_POST['rating']);
        $reviews= (int)$_POST['review_count'];
        $sold   = (int)$_POST['sold'];

        if ($name !== '') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO products (name, description, rating, review_count, sold) VALUES (?,?,?,?,?)');
            mysqli_stmt_bind_param($stmt, 'ssdii', $name, $desc, $rating, $reviews, $sold);
            mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            flash('Thêm sản phẩm thành công! ID: ' . $new_id);
            redirect('admin.php?action=edit&id=' . $new_id);
        } else {
            flash('Tên sản phẩm không được để trống!', 'error');
            redirect('admin.php?action=add');
        }
    }

    // ── Cập nhật sản phẩm ─────────────────────────────────
    if ($act === 'update_product') {
        $id     = (int)$_POST['product_id'];
        $name   = trim($_POST['name']);
        $desc   = trim($_POST['description']);
        $rating = (float)str_replace(',', '.', $_POST['rating']);
        $reviews= (int)$_POST['review_count'];
        $sold   = (int)$_POST['sold'];
        $stmt = mysqli_prepare($conn, 'UPDATE products SET name=?, description=?, rating=?, review_count=?, sold=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssdiid', $name, $desc, $rating, $reviews, $sold, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã cập nhật sản phẩm.');
        redirect('admin.php?action=edit&id=' . $id);
    }

    // ── Xóa sản phẩm ──────────────────────────────────────
    if ($act === 'delete_product') {
        $id = (int)$_POST['product_id'];
        $stmt = mysqli_prepare($conn, 'DELETE FROM products WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã xóa sản phẩm.');
        redirect('admin.php');
    }

    // ── Thêm ảnh (URL hoặc Upload file) ───────────────────
    if ($act === 'add_image') {
        $pid   = (int)$_POST['product_id'];
        $color = trim(isset($_POST['image_color']) ? $_POST['image_color'] : '');
        $color = $color === '' ? null : $color;
        $final_url = '';

        // ── Xử lý upload file ─────────────────────────────
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['image_file']['tmp_name'];
            $name = $_FILES['image_file']['name'];
            $size = $_FILES['image_file']['size'];
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = array('jpg','jpeg','png','gif','webp');

            if (!in_array($ext, $allowed)) {
                flash('Định dạng ảnh không hợp lệ. Chỉ chấp nhận: jpg, png, gif, webp', 'error');
            } elseif ($size > 5 * 1024 * 1024) {
                flash('Ảnh quá lớn. Tối đa 5MB.', 'error');
            } else {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $new_name   = time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $dest       = $upload_dir . $new_name;
                if (move_uploaded_file($tmp, $dest)) {
                    $final_url = 'uploads/' . $new_name;
                } else {
                    flash('Không thể lưu file. Kiểm tra quyền ghi thư mục uploads/', 'error');
                }
            }
        }

        // ── Dùng URL nếu không upload ──────────────────────
        if ($final_url === '') {
            $final_url = trim(isset($_POST['image_url']) ? $_POST['image_url'] : '');
        }

        if ($final_url !== '') {
            $stmt = mysqli_prepare($conn,
                'INSERT INTO product_images (product_id, image, color, sort_order)
                 VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM product_images pi2 WHERE pi2.product_id=?))');
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, 'issi', $pid, $final_url, $color, $pid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                flash('Đã thêm ảnh thành công.');
            } else {
                flash('Lỗi database: ' . mysqli_error($conn), 'error');
            }
        } elseif (!isset($_SESSION['flash'])) {
            flash('Vui lòng chọn file hoặc nhập URL ảnh.', 'error');
        }
        redirect('admin.php?action=edit&id=' . $pid . '#images');
    }

    // ── Xóa ảnh ───────────────────────────────────────────
    if ($act === 'delete_image') {
        $img_id = (int)$_POST['image_id'];
        $pid    = (int)$_POST['product_id'];
        $stmt = mysqli_prepare($conn, 'DELETE FROM product_images WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $img_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã xóa ảnh.');
        redirect('admin.php?action=edit&id=' . $pid . '#images');
    }

    // ── Thêm biến thể ─────────────────────────────────────
    if ($act === 'add_variant') {
        $pid   = (int)$_POST['product_id'];
        $color = trim($_POST['color']);
        $size  = trim($_POST['size']);
        $price = (int)preg_replace('/[^0-9]/', '', $_POST['price']);
        $orig  = (int)preg_replace('/[^0-9]/', '', $_POST['original_price']);
        $stock = (int)$_POST['stock'];
        $name  = $color . ' - ' . $size;

        if ($color !== '' && $size !== '') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO product_variants (product_id, name, color, size, price, original_price, stock) VALUES (?,?,?,?,?,?,?)');
            mysqli_stmt_bind_param($stmt, 'isssiid', $pid, $name, $color, $size, $price, $orig, $stock);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flash('Đã thêm biến thể ' . $name . '.');
        }
        redirect('admin.php?action=edit&id=' . $pid . '#variants');
    }

    // ── Cập nhật biến thể ─────────────────────────────────
    if ($act === 'update_variant') {
        $vid   = (int)$_POST['variant_id'];
        $pid   = (int)$_POST['product_id'];
        $color = trim($_POST['color']);
        $size  = trim($_POST['size']);
        $price = (int)preg_replace('/[^0-9]/', '', $_POST['price']);
        $orig  = (int)preg_replace('/[^0-9]/', '', $_POST['original_price']);
        $stock = (int)$_POST['stock'];
        $name  = $color . ' - ' . $size;

        $stmt = mysqli_prepare($conn, 'UPDATE product_variants SET name=?, color=?, size=?, price=?, original_price=?, stock=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'sssiiid', $name, $color, $size, $price, $orig, $stock, $vid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã cập nhật biến thể.');
        redirect('admin.php?action=edit&id=' . $pid . '#variants');
    }

    // ── Xóa biến thể ──────────────────────────────────────
    if ($act === 'delete_variant') {
        $vid = (int)$_POST['variant_id'];
        $pid = (int)$_POST['product_id'];
        $stmt = mysqli_prepare($conn, 'DELETE FROM product_variants WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $vid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã xóa biến thể.');
        redirect('admin.php?action=edit&id=' . $pid . '#variants');
    }

    // ── Cập nhật trạng thái đơn hàng ─────────────────────
    if ($act === 'update_order_status') {
        $oid    = (int)$_POST['order_id'];
        $valid  = array('pending','processing','shipped','delivered','cancelled');
        $status = isset($_POST['status']) && in_array($_POST['status'], $valid) ? $_POST['status'] : 'pending';
        $stmt = mysqli_prepare($conn, 'UPDATE orders SET status=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'si', $status, $oid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã cập nhật trạng thái đơn hàng #' . $oid . '.');
        redirect('admin.php?action=order_detail&id=' . $oid);
    }

    // ── Xóa đơn hàng ──────────────────────────────────────
    if ($act === 'delete_order') {
        $oid = (int)$_POST['order_id'];
        $stmt = mysqli_prepare($conn, 'DELETE FROM orders WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $oid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('Đã xóa đơn hàng #' . $oid . '.');
        redirect('admin.php?action=orders');
    }
}

// ============================================================
//  LẤY DỮ LIỆU ĐỂ HIỂN THỊ
// ============================================================
$action     = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_id    = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$products   = array();
$edit_prod  = null;
$edit_imgs  = array();
$edit_vars  = array();
// Đơn hàng
$orders          = array();
$order_detail    = null;
$order_items_det = array();
$order_counts    = array('pending'=>0,'processing'=>0,'shipped'=>0,'delivered'=>0,'cancelled'=>0,'total'=>0);

if ($logged_in && $conn !== null) {
    if ($action === 'list' || $action === '') {
        $res = mysqli_query($conn, 'SELECT id, name, rating, review_count, sold FROM products ORDER BY id DESC');
        while ($row = mysqli_fetch_assoc($res)) { $products[] = $row; }
        mysqli_free_result($res);
    }

    if (($action === 'edit') && $edit_id > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM products WHERE id=? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $edit_id);
        mysqli_stmt_execute($stmt);
        $edit_prod = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $stmt2 = mysqli_prepare($conn, 'SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order');
        mysqli_stmt_bind_param($stmt2, 'i', $edit_id);
        mysqli_stmt_execute($stmt2);
        $r2 = mysqli_stmt_get_result($stmt2);
        while ($row = mysqli_fetch_assoc($r2)) { $edit_imgs[] = $row; }
        mysqli_stmt_close($stmt2);

        $stmt3 = mysqli_prepare($conn, 'SELECT * FROM product_variants WHERE product_id=? ORDER BY color, size');
        mysqli_stmt_bind_param($stmt3, 'i', $edit_id);
        mysqli_stmt_execute($stmt3);
        $r3 = mysqli_stmt_get_result($stmt3);
        while ($row = mysqli_fetch_assoc($r3)) { $edit_vars[] = $row; }
        mysqli_stmt_close($stmt3);
    }

    // ── Đơn hàng: đếm badges ──────────────────────────────
    $res_c = mysqli_query($conn, 'SELECT status, COUNT(*) cnt FROM orders GROUP BY status');
    if ($res_c) {
        while ($row = mysqli_fetch_assoc($res_c)) {
            $order_counts[$row['status']] = (int)$row['cnt'];
            $order_counts['total'] += (int)$row['cnt'];
        }
        mysqli_free_result($res_c);
    }

    // ── Danh sách đơn hàng ───────────────────────────────
    if ($action === 'orders') {
        $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
        $valid_statuses = array('pending','processing','shipped','delivered','cancelled');
        $where_order = '';
        if (in_array($filter_status, $valid_statuses)) {
            $where_order = "WHERE status = '" . $filter_status . "'";
        }
        $res_o = mysqli_query($conn, "SELECT * FROM orders $where_order ORDER BY id DESC");
        if ($res_o) {
            while ($row = mysqli_fetch_assoc($res_o)) { $orders[] = $row; }
            mysqli_free_result($res_o);
        }
    }

    // ── Chi tiết đơn hàng ─────────────────────────────────
    if ($action === 'order_detail' && $edit_id > 0) {
        $stmt_o = mysqli_prepare($conn, 'SELECT * FROM orders WHERE id=? LIMIT 1');
        mysqli_stmt_bind_param($stmt_o, 'i', $edit_id);
        mysqli_stmt_execute($stmt_o);
        $order_detail = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_o));
        mysqli_stmt_close($stmt_o);

        if ($order_detail) {
            $stmt_oi = mysqli_prepare($conn, 'SELECT * FROM order_items WHERE order_id=?');
            mysqli_stmt_bind_param($stmt_oi, 'i', $edit_id);
            mysqli_stmt_execute($stmt_oi);
            $r_oi = mysqli_stmt_get_result($stmt_oi);
            while ($row = mysqli_fetch_assoc($r_oi)) { $order_items_det[] = $row; }
            mysqli_stmt_close($stmt_oi);
        }
    }
}

$flash = pop_flash();
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản Trị Sản Phẩm</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; color: #222; font-size: 14px; min-height: 100vh; }

/* ── Topbar ── */
.topbar {
    background: linear-gradient(to right,#f53d2d,#ff6633);
    padding: 0 24px; height: 56px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.topbar-brand { color:#fff; font-size:20px; font-weight:700; font-style:italic; display:flex; align-items:center; gap:10px; }
.topbar-brand span { font-size:13px; font-weight:400; font-style:normal; opacity:.85; }
.topbar-user { display:flex; align-items:center; gap:12px; color:#fff; font-size:13px; }
.topbar-user a { color:#fff; opacity:.85; text-decoration:none; }
.topbar-user a:hover { opacity:1; text-decoration:underline; }

/* ── Sidebar + layout ── */
.layout { display:flex; min-height: calc(100vh - 56px); }
.sidebar {
    width: 220px; background:#fff; flex-shrink:0;
    border-right:1px solid #e8e8e8;
    padding: 16px 0;
}
.sidebar a {
    display:flex; align-items:center; gap:10px;
    padding: 10px 20px; color:#555; text-decoration:none;
    font-size:14px; transition: background .15s, color .15s;
}
.sidebar a:hover, .sidebar a.active { background:#fff4f2; color:#ee4d2d; border-right:3px solid #ee4d2d; }
.sidebar .section-label { padding:14px 20px 4px; font-size:11px; color:#bbb; text-transform:uppercase; letter-spacing:.8px; }

.content { flex:1; padding:24px; min-width:0; }

/* ── Card ── */
.card { background:#fff; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,.07); margin-bottom:20px; }
.card-header {
    padding:16px 20px; border-bottom:1px solid #f0f0f0;
    display:flex; align-items:center; justify-content:space-between;
}
.card-header h2 { font-size:16px; font-weight:600; color:#222; }
.card-body { padding:20px; }

/* ── Flash ── */
.flash { padding:12px 16px; border-radius:4px; margin-bottom:16px; font-size:13px; }
.flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
.flash.error   { background:#fff4f4; border:1px solid #fecaca; color:#b91c1c; }

/* ── Buttons ── */
.btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:4px; font-size:13px; font-weight:500; cursor:pointer; border:none; text-decoration:none; transition:opacity .15s; }
.btn:hover { opacity:.88; }
.btn-primary { background:#ee4d2d; color:#fff; }
.btn-secondary { background:#f5f5f5; color:#555; border:1px solid #d9d9d9; }
.btn-danger  { background:#fff; color:#dc2626; border:1px solid #fca5a5; }
.btn-sm { padding:5px 10px; font-size:12px; }
.btn-success { background:#16a34a; color:#fff; }

/* ── Form ── */
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-size:13px; color:#555; margin-bottom:6px; font-weight:500; }
.form-group input[type=text],
.form-group input[type=number],
.form-group input[type=password],
.form-group textarea,
.form-group select {
    width:100%; padding:9px 12px; border:1px solid #d9d9d9;
    border-radius:4px; font-size:14px; outline:none;
    transition:border-color .15s;
}
.form-group input:focus,
.form-group textarea:focus { border-color:#ee4d2d; }
.form-group textarea { resize:vertical; min-height:100px; }
.form-row { display:flex; gap:16px; flex-wrap:wrap; }
.form-row .form-group { flex:1; min-width:140px; }
.form-hint { font-size:12px; color:#999; margin-top:4px; }

/* ── Table ── */
.tbl { width:100%; border-collapse:collapse; font-size:13px; }
.tbl th { background:#fafafa; border-bottom:2px solid #f0f0f0; padding:10px 12px; text-align:left; color:#666; font-weight:600; white-space:nowrap; }
.tbl td { padding:10px 12px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
.tbl tr:last-child td { border-bottom:none; }
.tbl tr:hover td { background:#fafafa; }

/* ── Badge ── */
.badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.badge-green  { background:#dcfce7; color:#166534; }
.badge-red    { background:#fee2e2; color:#991b1b; }
.badge-gray   { background:#f3f4f6; color:#6b7280; }

/* ── Product list card ── */
.product-row-img { width:48px; height:48px; object-fit:cover; border-radius:4px; border:1px solid #f0f0f0; }
.product-row-name { font-weight:500; color:#222; }
.product-row-sub  { font-size:12px; color:#999; margin-top:2px; }

/* ── Image preview grid ── */
.img-grid { display:flex; flex-wrap:wrap; gap:12px; }
.img-item { position:relative; width:120px; }
.img-item img { width:120px; height:120px; object-fit:cover; border-radius:4px; border:1px solid #f0f0f0; display:block; }
.img-item .img-del { position:absolute; top:4px; right:4px; width:22px; height:22px; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1; }
.img-item .img-color { font-size:11px; color:#666; margin-top:4px; text-align:center; }

/* ── Variant table inline edit ── */
.var-edit-row td { background:#fffbf0 !important; }
.stock-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; }

/* ── Tabs ── */
.tabs { display:flex; border-bottom:2px solid #f0f0f0; margin-bottom:20px; }
.tab { padding:10px 20px; font-size:14px; font-weight:500; color:#888; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; text-decoration:none; }
.tab:hover { color:#ee4d2d; }
.tab.active { color:#ee4d2d; border-bottom-color:#ee4d2d; }

/* ── Login ── */
.login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f0f2f5; }
.login-card { background:#fff; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.1); padding:36px; width:360px; }
.login-logo { text-align:center; margin-bottom:24px; }
.login-logo .logo-text { font-size:32px; font-weight:800; font-style:italic; color:#ee4d2d; }
.login-logo p { color:#888; font-size:13px; margin-top:4px; }

/* ── Empty state ── */
.empty { text-align:center; padding:40px; color:#bbb; }
.empty .empty-icon { font-size:40px; margin-bottom:8px; }

/* ── Confirm ── */
.confirm-delete { display:inline; }
.confirm-delete button { cursor:pointer; }

/* Responsive */
@media (max-width:768px) {
    .sidebar { display:none; }
    .layout { flex-direction:column; }
}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ══════════════════════════════════════════════════
     TRANG ĐĂNG NHẬP
══════════════════════════════════════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-text">shopee</div>
      <p>Trang quản trị sản phẩm</p>
    </div>

    <?php if (isset($_SESSION['admin_error'])): ?>
      <div class="flash error"><?php echo e($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="admin.php">
      <div class="form-group">
        <label>Tài khoản</label>
        <input type="text" name="username" placeholder="admin" required autofocus>
      </div>
      <div class="form-group">
        <label>Mật khẩu</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <input type="hidden" name="do_login" value="1">
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
        Đăng nhập
      </button>
    </form>
    <p style="margin-top:14px;font-size:12px;color:#bbb;text-align:center">Mặc định: admin / admin123</p>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════
     GIAO DIỆN QUẢN TRỊ
══════════════════════════════════════════════════ -->

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-brand">
    shopee <span>Admin</span>
  </div>
  <div class="topbar-user">
    👤 <?php echo e(ADMIN_USER); ?>
    &nbsp;|&nbsp;
    <a href="product.php?id=1" target="_blank">Xem trang sản phẩm ↗</a>
    &nbsp;|&nbsp;
    <a href="admin.php?logout=1">Đăng xuất</a>
  </div>
</div>

<div class="layout">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="section-label">Sản phẩm</div>
    <a href="admin.php" class="<?php echo $action === 'list' ? 'active' : ''; ?>">📦 Danh sách sản phẩm</a>
    <a href="admin.php?action=add" class="<?php echo $action === 'add' ? 'active' : ''; ?>">➕ Thêm sản phẩm</a>
    <div class="section-label">Đơn hàng</div>
    <a href="admin.php?action=orders" class="<?php echo $action === 'orders' || $action === 'order_detail' ? 'active' : ''; ?>">
      🧾 Tất cả đơn hàng
      <?php if ($order_counts['total'] > 0): ?>
        <span style="margin-left:auto;background:#ee4d2d;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px"><?php echo $order_counts['total']; ?></span>
      <?php endif; ?>
    </a>
    <a href="admin.php?action=orders&status=pending">
      ⏳ Chờ xác nhận
      <?php if ($order_counts['pending'] > 0): ?>
        <span style="margin-left:auto;background:#f59e0b;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px"><?php echo $order_counts['pending']; ?></span>
      <?php endif; ?>
    </a>
    <a href="admin.php?action=orders&status=processing">🔄 Đang xử lý</a>
    <a href="admin.php?action=orders&status=shipped">🚚 Đang giao</a>
    <a href="admin.php?action=orders&status=delivered">✅ Đã giao</a>
    <a href="admin.php?action=orders&status=cancelled">❌ Đã hủy</a>
    <div class="section-label">Hệ thống</div>
    <a href="index.php" target="_blank">🏪 Trang cửa hàng</a>
    <a href="product.php?id=1" target="_blank">🛍️ Trang sản phẩm</a>
    <a href="admin.php?logout=1">🔓 Đăng xuất</a>
  </div>

  <!-- Main content -->
  <div class="content">

    <?php if ($flash): ?>
      <div class="flash <?php echo e($flash['type']); ?>"><?php echo e($flash['msg']); ?></div>
    <?php endif; ?>

    <?php if ($conn === null): ?>
      <div class="card card-body">
        <div class="flash error">
          ⚠️ Không kết nối được database. Vui lòng kiểm tra cấu hình trong <code>includes/db.php</code> và chạy <code>setup.sql</code>.
        </div>
      </div>
    <?php endif; ?>

    <!-- ================================================
         DANH SÁCH SẢN PHẨM
    ================================================ -->
    <?php if ($action === 'list'): ?>
    <div class="card">
      <div class="card-header">
        <h2>📦 Danh sách sản phẩm</h2>
        <a href="admin.php?action=add" class="btn btn-primary">➕ Thêm sản phẩm</a>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($products)): ?>
          <div class="empty">
            <div class="empty-icon">📭</div>
            <div>Chưa có sản phẩm nào. <a href="admin.php?action=add" style="color:#ee4d2d">Thêm ngay!</a></div>
          </div>
        <?php else: ?>
          <table class="tbl">
            <thead>
              <tr>
                <th>ID</th>
                <th>Sản phẩm</th>
                <th>Đánh giá</th>
                <th>Đã bán</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
              <tr>
                <td style="color:#bbb">#<?php echo $p['id']; ?></td>
                <td>
                  <div class="product-row-name"><?php echo e(mb_substr($p['name'], 0, 60, 'UTF-8')); ?>...</div>
                  <div class="product-row-sub">⭐ <?php echo $p['rating']; ?> &nbsp;·&nbsp; <?php echo number_format($p['review_count']); ?> đánh giá</div>
                </td>
                <td>
                  <span class="badge badge-green"><?php echo $p['rating']; ?> ★</span>
                </td>
                <td><?php echo number_format($p['sold']); ?></td>
                <td style="white-space:nowrap">
                  <a href="admin.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm">✏️ Sửa</a>
                  &nbsp;
                  <a href="product.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-secondary btn-sm">👁️ Xem</a>
                  &nbsp;
                  <form class="confirm-delete" method="POST" action="admin.php" onsubmit="return confirm('Xóa sản phẩm này?')">
                    <input type="hidden" name="act" value="delete_product">
                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Xóa</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- ================================================
         THÊM SẢN PHẨM
    ================================================ -->
    <?php elseif ($action === 'add'): ?>
    <div class="card">
      <div class="card-header">
        <h2>➕ Thêm sản phẩm mới</h2>
        <a href="admin.php" class="btn btn-secondary btn-sm">← Quay lại</a>
      </div>
      <div class="card-body">
        <form method="POST" action="admin.php">
          <input type="hidden" name="act" value="add_product">

          <div class="form-group">
            <label>Tên sản phẩm *</label>
            <input type="text" name="name" placeholder="Nhập tên đầy đủ của sản phẩm..." required>
          </div>

          <div class="form-group">
            <label>Mô tả sản phẩm</label>
            <textarea name="description" placeholder="Mô tả chi tiết, chất liệu, đặc điểm nổi bật..."></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Đánh giá (0–5)</label>
              <input type="number" name="rating" value="5.0" min="0" max="5" step="0.1">
            </div>
            <div class="form-group">
              <label>Số lượt đánh giá</label>
              <input type="number" name="review_count" value="0" min="0">
            </div>
            <div class="form-group">
              <label>Đã bán</label>
              <input type="number" name="sold" value="0" min="0">
            </div>
          </div>

          <div style="display:flex;gap:10px">
            <button type="submit" class="btn btn-primary">✅ Tạo sản phẩm</button>
            <a href="admin.php" class="btn btn-secondary">Hủy</a>
          </div>
        </form>
      </div>
    </div>

    <!-- ================================================
         CHỈNH SỬA SẢN PHẨM
    ================================================ -->
    <?php elseif ($action === 'edit' && $edit_prod): ?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div>
        <a href="admin.php" style="color:#888;text-decoration:none;font-size:13px">← Danh sách</a>
        <span style="color:#ddd;margin:0 8px">/</span>
        <span style="font-size:15px;font-weight:600">Sản phẩm #<?php echo $edit_prod['id']; ?></span>
      </div>
      <a href="product.php?id=<?php echo $edit_prod['id']; ?>" target="_blank" class="btn btn-secondary btn-sm">👁️ Xem trang</a>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <a href="#info"     class="tab active" onclick="showTab('info',this)">📝 Thông tin</a>
      <a href="#images"   class="tab"        onclick="showTab('images',this)">🖼️ Hình ảnh (<?php echo count($edit_imgs); ?>)</a>
      <a href="#variants" class="tab"        onclick="showTab('variants',this)">🏷️ Biến thể (<?php echo count($edit_vars); ?>)</a>
    </div>

    <!-- ── TAB: Thông tin ────────────────────────── -->
    <div id="tab-info" class="tab-panel">
      <div class="card">
        <div class="card-header"><h2>📝 Thông tin sản phẩm</h2></div>
        <div class="card-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="act" value="update_product">
            <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">

            <div class="form-group">
              <label>Tên sản phẩm *</label>
              <input type="text" name="name" value="<?php echo e($edit_prod['name']); ?>" required>
            </div>

            <div class="form-group">
              <label>Mô tả sản phẩm</label>
              <textarea name="description"><?php echo e($edit_prod['description']); ?></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Đánh giá (0–5)</label>
                <input type="number" name="rating" value="<?php echo e($edit_prod['rating']); ?>" min="0" max="5" step="0.1">
              </div>
              <div class="form-group">
                <label>Số lượt đánh giá</label>
                <input type="number" name="review_count" value="<?php echo e($edit_prod['review_count']); ?>" min="0">
              </div>
              <div class="form-group">
                <label>Đã bán</label>
                <input type="number" name="sold" value="<?php echo e($edit_prod['sold']); ?>" min="0">
              </div>
            </div>

            <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ── TAB: Hình ảnh ──────────────────────────── -->
    <div id="tab-images" class="tab-panel" style="display:none">

      <!-- Danh sách ảnh hiện tại -->
      <div class="card">
        <div class="card-header"><h2>🖼️ Hình ảnh hiện tại</h2></div>
        <div class="card-body">
          <?php if (empty($edit_imgs)): ?>
            <div class="empty"><div class="empty-icon">🖼️</div><div>Chưa có ảnh nào.</div></div>
          <?php else: ?>
            <div class="img-grid">
              <?php foreach ($edit_imgs as $img): ?>
                <div class="img-item">
                  <img src="<?php echo e($img['image']); ?>" alt="ảnh">
                  <div class="img-color">
                    <?php echo $img['color'] ? '🎨 ' . e($img['color']) : '(ảnh chung)'; ?>
                  </div>
                  <form method="POST" action="admin.php" onsubmit="return confirm('Xóa ảnh này?')">
                    <input type="hidden" name="act" value="delete_image">
                    <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">
                    <button type="submit" class="img-del" title="Xóa ảnh">×</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Thêm ảnh mới -->
      <div class="card">
        <div class="card-header"><h2>➕ Thêm ảnh mới</h2></div>
        <div class="card-body">
          <form method="POST" action="admin.php" enctype="multipart/form-data">
            <input type="hidden" name="act" value="add_image">
            <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">

            <!-- Upload từ máy -->
            <div class="form-group">
              <label>📁 Tải ảnh từ máy tính</label>
              <div id="drop-zone" style="border:2px dashed #d9d9d9;border-radius:6px;padding:24px;text-align:center;cursor:pointer;transition:border-color .2s;background:#fafafa" onclick="document.getElementById('file-input').click()">
                <div style="font-size:32px;margin-bottom:8px">🖼️</div>
                <div style="color:#888;font-size:13px">Kéo thả ảnh vào đây hoặc <span style="color:#ee4d2d;font-weight:500">click để chọn</span></div>
                <div style="color:#bbb;font-size:11px;margin-top:4px">JPG, PNG, WebP, GIF · Tối đa 5MB</div>
              </div>
              <input type="file" id="file-input" name="image_file" accept="image/*" style="display:none" onchange="previewFile(this)">
              <!-- Preview file được chọn -->
              <div id="file-preview-wrap" style="display:none;margin-top:12px;align-items:center;gap:12px">
                <img id="file-preview-img" src="" style="width:80px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #f0f0f0">
                <div>
                  <div id="file-preview-name" style="font-size:13px;font-weight:500;color:#333"></div>
                  <div id="file-preview-size" style="font-size:12px;color:#888;margin-top:2px"></div>
                  <button type="button" onclick="clearFile()" style="font-size:12px;color:#ee4d2d;background:none;border:none;cursor:pointer;margin-top:4px">✕ Xóa</button>
                </div>
              </div>
            </div>

            <!-- HOẶC: Nhập URL -->
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
              <div style="flex:1;border-top:1px solid #f0f0f0"></div>
              <span style="color:#bbb;font-size:13px;white-space:nowrap">hoặc nhập URL ảnh</span>
              <div style="flex:1;border-top:1px solid #f0f0f0"></div>
            </div>

            <div class="form-group">
              <input type="text" name="image_url" id="url-input" placeholder="https://images.unsplash.com/..." oninput="previewUrl(this.value)">
              <div class="form-hint">Dán link ảnh từ internet (Unsplash, Imgur, CDN...)</div>
            </div>

            <!-- Preview URL real-time -->
            <div id="img-preview-wrap" style="display:none;margin-bottom:12px">
              <p style="font-size:12px;color:#888;margin-bottom:6px">Xem trước:</p>
              <img id="img-preview" src="" style="width:120px;height:120px;object-fit:cover;border-radius:4px;border:1px solid #f0f0f0">
            </div>

            <div class="form-group">
              <label>🎨 Gắn với màu sắc (tùy chọn)</label>
              <input type="text" name="image_color" placeholder="Trắng / Đen / Xanh Navy...">
              <div class="form-hint">Để trống nếu là ảnh chung. Phải khớp đúng tên màu trong biến thể.</div>
            </div>

            <button type="submit" class="btn btn-primary">⬆️ Thêm ảnh</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ── TAB: Biến thể ──────────────────────────── -->
    <div id="tab-variants" class="tab-panel" style="display:none">

      <!-- Danh sách biến thể -->
      <div class="card">
        <div class="card-header">
          <h2>🏷️ Danh sách biến thể</h2>
          <span style="font-size:12px;color:#888"><?php echo count($edit_vars); ?> biến thể</span>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (empty($edit_vars)): ?>
            <div class="empty"><div class="empty-icon">🏷️</div><div>Chưa có biến thể nào.</div></div>
          <?php else: ?>
            <table class="tbl">
              <thead>
                <tr>
                  <th>Màu sắc</th>
                  <th>Kích thước</th>
                  <th>Giá bán</th>
                  <th>Giá gốc</th>
                  <th>Tồn kho</th>
                  <th>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($edit_vars as $v): ?>
                <tr id="row-<?php echo $v['id']; ?>">
                  <td><?php echo e($v['color']); ?></td>
                  <td><strong><?php echo e($v['size']); ?></strong></td>
                  <td style="color:#ee4d2d;font-weight:500"><?php echo fmt($v['price']); ?></td>
                  <td style="text-decoration:line-through;color:#999"><?php echo fmt($v['original_price']); ?></td>
                  <td>
                    <?php
                    $s = (int)$v['stock'];
                    $cls = $s === 0 ? 'badge-red' : ($s < 10 ? 'badge-gray' : 'badge-green');
                    ?>
                    <span class="stock-badge badge <?php echo $cls; ?>"><?php echo $s; ?></span>
                  </td>
                  <td style="white-space:nowrap">
                    <button class="btn btn-secondary btn-sm" onclick="toggleEditVar(<?php echo $v['id']; ?>, <?php echo $edit_prod['id']; ?>, '<?php echo e($v['color']); ?>', '<?php echo e($v['size']); ?>', <?php echo $v['price']; ?>, <?php echo $v['original_price']; ?>, <?php echo $v['stock']; ?>)">
                      ✏️ Sửa
                    </button>
                    &nbsp;
                    <form class="confirm-delete" method="POST" action="admin.php" onsubmit="return confirm('Xóa biến thể này?')">
                      <input type="hidden" name="act" value="delete_variant">
                      <input type="hidden" name="variant_id" value="<?php echo $v['id']; ?>">
                      <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                  </td>
                </tr>
                <!-- Inline edit row (ẩn) -->
                <tr id="edit-row-<?php echo $v['id']; ?>" class="var-edit-row" style="display:none">
                  <td colspan="6">
                    <form method="POST" action="admin.php" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:8px 0">
                      <input type="hidden" name="act" value="update_variant">
                      <input type="hidden" name="variant_id" value="<?php echo $v['id']; ?>">
                      <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">
                      <div><label style="font-size:11px;color:#888">Màu</label><br>
                        <input type="text" name="color" id="ec-color-<?php echo $v['id']; ?>" style="width:110px;padding:6px 8px;border:1px solid #d9d9d9;border-radius:4px"></div>
                      <div><label style="font-size:11px;color:#888">Size</label><br>
                        <input type="text" name="size" id="ec-size-<?php echo $v['id']; ?>" style="width:70px;padding:6px 8px;border:1px solid #d9d9d9;border-radius:4px"></div>
                      <div><label style="font-size:11px;color:#888">Giá bán (₫)</label><br>
                        <input type="number" name="price" id="ec-price-<?php echo $v['id']; ?>" style="width:120px;padding:6px 8px;border:1px solid #d9d9d9;border-radius:4px"></div>
                      <div><label style="font-size:11px;color:#888">Giá gốc (₫)</label><br>
                        <input type="number" name="original_price" id="ec-orig-<?php echo $v['id']; ?>" style="width:120px;padding:6px 8px;border:1px solid #d9d9d9;border-radius:4px"></div>
                      <div><label style="font-size:11px;color:#888">Tồn kho</label><br>
                        <input type="number" name="stock" id="ec-stock-<?php echo $v['id']; ?>" min="0" style="width:80px;padding:6px 8px;border:1px solid #d9d9d9;border-radius:4px"></div>
                      <div style="display:flex;gap:6px;align-items:flex-end">
                        <button type="submit" class="btn btn-success btn-sm">💾 Lưu</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('edit-row-<?php echo $v['id']; ?>').style.display='none'">✕ Đóng</button>
                      </div>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Thêm biến thể mới -->
      <div class="card">
        <div class="card-header"><h2>➕ Thêm biến thể mới</h2></div>
        <div class="card-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="act" value="add_variant">
            <input type="hidden" name="product_id" value="<?php echo $edit_prod['id']; ?>">

            <div class="form-row">
              <div class="form-group">
                <label>Màu sắc *</label>
                <input type="text" name="color" placeholder="Trắng / Đen / Xanh Navy" required>
              </div>
              <div class="form-group">
                <label>Kích thước *</label>
                <input type="text" name="size" placeholder="S / M / L / XL" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Giá bán (₫) *</label>
                <input type="number" name="price" placeholder="185000" min="0" required>
              </div>
              <div class="form-group">
                <label>Giá gốc / Giá cũ (₫)</label>
                <input type="number" name="original_price" placeholder="259000" min="0">
                <div class="form-hint">Dùng để hiện giá gạch ngang và % giảm</div>
              </div>
              <div class="form-group">
                <label>Tồn kho</label>
                <input type="number" name="stock" placeholder="0" min="0" value="0">
              </div>
            </div>

            <button type="submit" class="btn btn-primary">➕ Thêm biến thể</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Nếu edit_id không tồn tại -->
    <?php elseif ($action === 'edit'): ?>
    <div class="card card-body">
      <div class="flash error">Không tìm thấy sản phẩm ID <?php echo $edit_id; ?>.</div>
      <a href="admin.php" class="btn btn-secondary">← Quay lại</a>
    </div>

    <!-- ================================================
         DANH SÁCH ĐƠN HÀNG
    ================================================ -->
    <?php elseif ($action === 'orders'): ?>

    <?php
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
    $status_labels = array(
        'pending'    => array('label'=>'Chờ xác nhận', 'color'=>'#f59e0b', 'bg'=>'#fffbeb'),
        'processing' => array('label'=>'Đang xử lý',  'color'=>'#3b82f6', 'bg'=>'#eff6ff'),
        'shipped'    => array('label'=>'Đang giao',    'color'=>'#8b5cf6', 'bg'=>'#f5f3ff'),
        'delivered'  => array('label'=>'Đã giao',      'color'=>'#16a34a', 'bg'=>'#f0fdf4'),
        'cancelled'  => array('label'=>'Đã hủy',       'color'=>'#dc2626', 'bg'=>'#fef2f2'),
    );
    $payment_labels = array('cod'=>'💵 COD','bank'=>'🏦 Chuyển khoản','momo'=>'🟣 MoMo');
    ?>

    <!-- Stats -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach ($status_labels as $st => $info): ?>
      <a href="admin.php?action=orders&status=<?php echo $st; ?>" style="text-decoration:none">
        <div style="background:#fff;border-radius:6px;padding:12px 18px;box-shadow:0 1px 3px rgba(0,0,0,.07);border-left:4px solid <?php echo $info['color']; ?>;min-width:130px">
          <div style="font-size:20px;font-weight:700;color:<?php echo $info['color']; ?>"><?php echo $order_counts[$st]; ?></div>
          <div style="font-size:12px;color:#888;margin-top:2px"><?php echo $info['label']; ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>
          🧾 Đơn hàng
          <?php if ($filter_status !== '' && isset($status_labels[$filter_status])): ?>
            <span style="font-size:13px;font-weight:400;color:#888;margin-left:8px">— <?php echo $status_labels[$filter_status]['label']; ?></span>
          <?php endif; ?>
        </h2>
        <?php if ($filter_status !== ''): ?>
          <a href="admin.php?action=orders" class="btn btn-secondary btn-sm">✕ Bỏ lọc</a>
        <?php endif; ?>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($orders)): ?>
          <div class="empty"><div class="empty-icon">📭</div><div>Chưa có đơn hàng nào.</div></div>
        <?php else: ?>
          <table class="tbl">
            <thead>
              <tr>
                <th>Mã đơn</th>
                <th>Khách hàng</th>
                <th>Sản phẩm</th>
                <th>Tổng tiền</th>
                <th>Thanh toán</th>
                <th>Trạng thái</th>
                <th>Ngày đặt</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o):
                $st_info = isset($status_labels[$o['status']]) ? $status_labels[$o['status']] : array('label'=>$o['status'],'color'=>'#888','bg'=>'#f5f5f5');
                $pm = isset($payment_labels[$o['payment_method']]) ? $payment_labels[$o['payment_method']] : $o['payment_method'];
              ?>
              <tr>
                <td><strong>#<?php echo sprintf('%06d',$o['id']); ?></strong></td>
                <td>
                  <div style="font-weight:500"><?php echo e($o['customer_name']); ?></div>
                  <div style="font-size:12px;color:#999"><?php echo e($o['customer_phone']); ?></div>
                </td>
                <td style="max-width:200px">
                  <div style="font-size:12px;color:#666;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo e(mb_substr($o['customer_address'], 0, 40, 'UTF-8')); ?>...</div>
                </td>
                <td style="font-weight:600;color:#ee4d2d;white-space:nowrap"><?php echo fmt($o['total_amount']); ?></td>
                <td style="font-size:12px"><?php echo $pm; ?></td>
                <td>
                  <span style="display:inline-block;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;background:<?php echo $st_info['bg']; ?>;color:<?php echo $st_info['color']; ?>">
                    <?php echo $st_info['label']; ?>
                  </span>
                </td>
                <td style="font-size:12px;color:#999;white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                <td style="white-space:nowrap">
                  <a href="admin.php?action=order_detail&id=<?php echo $o['id']; ?>" class="btn btn-secondary btn-sm">👁️ Chi tiết</a>
                  &nbsp;
                  <form class="confirm-delete" method="POST" action="admin.php" onsubmit="return confirm('Xóa đơn hàng này?')">
                    <input type="hidden" name="act" value="delete_order">
                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- ================================================
         CHI TIẾT ĐƠN HÀNG
    ================================================ -->
    <?php elseif ($action === 'order_detail'): ?>

    <?php
    $status_labels2 = array(
        'pending'    => array('label'=>'Chờ xác nhận', 'color'=>'#f59e0b'),
        'processing' => array('label'=>'Đang xử lý',  'color'=>'#3b82f6'),
        'shipped'    => array('label'=>'Đang giao',    'color'=>'#8b5cf6'),
        'delivered'  => array('label'=>'Đã giao',      'color'=>'#16a34a'),
        'cancelled'  => array('label'=>'Đã hủy',       'color'=>'#dc2626'),
    );
    $payment_labels2 = array('cod'=>'💵 COD','bank'=>'🏦 Chuyển khoản','momo'=>'🟣 MoMo');
    ?>

    <?php if (!$order_detail): ?>
      <div class="card card-body"><div class="flash error">Không tìm thấy đơn hàng.</div><a href="admin.php?action=orders" class="btn btn-secondary">← Quay lại</a></div>
    <?php else:
      $st_d = isset($status_labels2[$order_detail['status']]) ? $status_labels2[$order_detail['status']] : array('label'=>$order_detail['status'],'color'=>'#888');
      $pm_d = isset($payment_labels2[$order_detail['payment_method']]) ? $payment_labels2[$order_detail['payment_method']] : $order_detail['payment_method'];
    ?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div>
        <a href="admin.php?action=orders" style="color:#888;text-decoration:none;font-size:13px">← Danh sách đơn</a>
        <span style="color:#ddd;margin:0 8px">/</span>
        <span style="font-size:16px;font-weight:700">Đơn #<?php echo sprintf('%06d',$order_detail['id']); ?></span>
        <span style="margin-left:10px;padding:3px 12px;border-radius:10px;font-size:12px;font-weight:600;color:<?php echo $st_d['color']; ?>;background:#f5f5f5">
          <?php echo $st_d['label']; ?>
        </span>
      </div>
      <!-- Xóa đơn -->
      <form method="POST" action="admin.php" onsubmit="return confirm('Xóa đơn hàng này?')">
        <input type="hidden" name="act" value="delete_order">
        <input type="hidden" name="order_id" value="<?php echo $order_detail['id']; ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑️ Xóa đơn</button>
      </form>
    </div>

    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">

      <!-- Thông tin khách hàng -->
      <div class="card" style="flex:1;min-width:300px">
        <div class="card-header"><h2>👤 Thông tin khách hàng</h2></div>
        <div class="card-body">
          <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tr><td style="color:#888;padding:6px 0;width:120px">Họ tên</td><td><strong><?php echo e($order_detail['customer_name']); ?></strong></td></tr>
            <tr><td style="color:#888;padding:6px 0">Điện thoại</td><td><?php echo e($order_detail['customer_phone']); ?></td></tr>
            <tr><td style="color:#888;padding:6px 0">Địa chỉ</td><td><?php echo e($order_detail['customer_address']); ?></td></tr>
            <tr><td style="color:#888;padding:6px 0">Ghi chú</td><td><?php echo $order_detail['customer_note'] ? e($order_detail['customer_note']) : '<span style="color:#bbb">Không có</span>'; ?></td></tr>
            <tr><td style="color:#888;padding:6px 0">Thanh toán</td><td><?php echo $pm_d; ?></td></tr>
            <tr><td style="color:#888;padding:6px 0">Ngày đặt</td><td><?php echo date('d/m/Y H:i:s', strtotime($order_detail['created_at'])); ?></td></tr>
          </table>
        </div>
      </div>

      <!-- Cập nhật trạng thái -->
      <div class="card" style="min-width:260px">
        <div class="card-header"><h2>🔄 Cập nhật trạng thái</h2></div>
        <div class="card-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="act" value="update_order_status">
            <input type="hidden" name="order_id" value="<?php echo $order_detail['id']; ?>">
            <div class="form-group">
              <label>Trạng thái mới</label>
              <select name="status">
                <?php foreach ($status_labels2 as $sv => $si): ?>
                  <option value="<?php echo $sv; ?>" <?php echo $order_detail['status']===$sv?'selected':''; ?>>
                    <?php echo $si['label']; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">💾 Cập nhật</button>
          </form>

          <!-- Luồng trạng thái -->
          <div style="margin-top:16px;padding-top:14px;border-top:1px solid #f0f0f0">
            <div style="font-size:12px;color:#888;margin-bottom:10px">Quy trình xử lý:</div>
            <?php
            $flow = array('pending','processing','shipped','delivered');
            $cur_idx = array_search($order_detail['status'], $flow);
            foreach ($flow as $fi => $fs):
              $finfo = $status_labels2[$fs];
              $is_done = $cur_idx !== false && $fi <= $cur_idx;
            ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
              <div style="width:20px;height:20px;border-radius:50%;background:<?php echo $is_done ? $finfo['color'] : '#e5e7eb'; ?>;display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;flex-shrink:0">
                <?php echo $is_done ? '✓' : ($fi+1); ?>
              </div>
              <span style="font-size:13px;color:<?php echo $is_done ? $finfo['color'] : '#bbb'; ?>;font-weight:<?php echo $is_done?'600':'400'; ?>">
                <?php echo $finfo['label']; ?>
              </span>
            </div>
            <?php endforeach; ?>
            <?php if ($order_detail['status'] === 'cancelled'): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
              <div style="width:20px;height:20px;border-radius:50%;background:#dc2626;display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff">✕</div>
              <span style="font-size:13px;color:#dc2626;font-weight:600">Đã hủy</span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Sản phẩm trong đơn -->
    <div class="card" style="margin-top:4px">
      <div class="card-header"><h2>🛍️ Sản phẩm trong đơn hàng</h2></div>
      <div class="card-body" style="padding:0">
        <table class="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>Sản phẩm / Phân loại</th>
              <th>Đơn giá</th>
              <th>Số lượng</th>
              <th>Thành tiền</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $subtotal = 0;
            foreach ($order_items_det as $idx => $oi):
              $line = $oi['price'] * $oi['quantity'];
              $subtotal += $line;
            ?>
            <tr>
              <td style="color:#bbb"><?php echo $idx+1; ?></td>
              <td><strong><?php echo e($oi['variant_name']); ?></strong></td>
              <td><?php echo fmt($oi['price']); ?></td>
              <td><?php echo $oi['quantity']; ?></td>
              <td style="font-weight:600;color:#ee4d2d"><?php echo fmt($line); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#fafafa">
              <td colspan="3"></td>
              <td style="font-weight:600;color:#555">Tổng cộng:</td>
              <td style="font-size:18px;font-weight:700;color:#ee4d2d"><?php echo fmt($order_detail['total_amount']); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <?php endif; // order_detail exists ?>
    <?php endif; ?>

  </div><!-- /.content -->
</div><!-- /.layout -->

<?php endif; ?>

<script>
// ── Tab switching ──────────────────────────────────────
function showTab(name, el) {
    ['info','images','variants'].forEach(function(t) {
        var panel = document.getElementById('tab-' + t);
        if (panel) panel.style.display = (t === name) ? '' : 'none';
    });
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    if (el) el.classList.add('active');
    window.location.hash = name;
    return false;
}

// ── Auto-switch tab từ hash URL ───────────────────────
(function() {
    var hash = window.location.hash.replace('#','');
    var valid = ['info','images','variants'];
    if (valid.indexOf(hash) > -1) {
        var el = document.querySelector('.tab[href="#' + hash + '"]');
        showTab(hash, el);
    }
})();

// ── Image URL preview real-time ───────────────────────
function previewUrl(url) {
    var wrap = document.getElementById('img-preview-wrap');
    var prev = document.getElementById('img-preview');
    if (!wrap || !prev) return;
    if (url.trim()) {
        prev.src = url.trim();
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
}

// ── File upload preview ────────────────────────────────
function previewFile(input) {
    var wrap = document.getElementById('file-preview-wrap');
    var img  = document.getElementById('file-preview-img');
    var nm   = document.getElementById('file-preview-name');
    var sz   = document.getElementById('file-preview-size');
    var dz   = document.getElementById('drop-zone');
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var reader = new FileReader();
    reader.onload = function(e) {
        if (img) img.src = e.target.result;
        if (nm) nm.textContent = file.name;
        if (sz) sz.textContent = (file.size / 1024).toFixed(1) + ' KB';
        if (wrap) { wrap.style.display = 'flex'; }
        if (dz)   { dz.style.borderColor = '#ee4d2d'; }
        // Xóa URL input khi chọn file
        var urlInp = document.getElementById('url-input');
        if (urlInp) { urlInp.value = ''; previewUrl(''); }
    };
    reader.readAsDataURL(file);
}

function clearFile() {
    var inp = document.getElementById('file-input');
    if (inp) inp.value = '';
    var wrap = document.getElementById('file-preview-wrap');
    if (wrap) wrap.style.display = 'none';
    var dz = document.getElementById('drop-zone');
    if (dz)  dz.style.borderColor = '#d9d9d9';
}

// ── Drag & Drop ───────────────────────────────────────
(function() {
    var dz = document.getElementById('drop-zone');
    if (!dz) return;
    dz.addEventListener('dragover', function(e) {
        e.preventDefault();
        dz.style.borderColor = '#ee4d2d';
        dz.style.background  = '#fff8f7';
    });
    dz.addEventListener('dragleave', function() {
        dz.style.borderColor = '#d9d9d9';
        dz.style.background  = '#fafafa';
    });
    dz.addEventListener('drop', function(e) {
        e.preventDefault();
        dz.style.borderColor = '#d9d9d9';
        dz.style.background  = '#fafafa';
        var fi = document.getElementById('file-input');
        if (fi && e.dataTransfer.files.length) {
            fi.files = e.dataTransfer.files;
            previewFile(fi);
        }
    });
})();

// ── Inline edit variant ───────────────────────────────
function toggleEditVar(id, pid, color, size, price, orig, stock) {
    var row = document.getElementById('edit-row-' + id);
    document.getElementById('ec-color-' + id).value = color;
    document.getElementById('ec-size-'  + id).value = size;
    document.getElementById('ec-price-' + id).value = price;
    document.getElementById('ec-orig-'  + id).value = orig;
    document.getElementById('ec-stock-' + id).value = stock;
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
</body>
</html>
