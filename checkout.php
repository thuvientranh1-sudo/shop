<?php
/**
 * checkout.php - Trang đặt hàng / giỏ hàng
 * PHP 7.2 | mysqli | Session cart
 */

session_start();
require_once __DIR__ . '/includes/db.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($n) { return number_format((float)$n, 0, ',', '.') . '₫'; }
function redirect($url) { header('Location: ' . $url); exit; }

// ── Khởi tạo giỏ hàng ─────────────────────────────────────────
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = array(); }
$cart = &$_SESSION['cart'];

// ── Xử lý các action ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';

    // Xóa khỏi giỏ
    if ($act === 'remove') {
        $vid = (int)$_POST['variant_id'];
        foreach ($cart as $k => $item) {
            if ($item['variant_id'] === $vid) { unset($cart[$k]); break; }
        }
        $cart = array_values($cart);
        redirect('checkout.php');
    }

    // Cập nhật số lượng
    if ($act === 'update_qty') {
        $vid = (int)$_POST['variant_id'];
        $qty = max(1, (int)$_POST['quantity']);
        foreach ($cart as &$item) {
            if ($item['variant_id'] === $vid) { $item['quantity'] = $qty; break; }
        }
        unset($item);
        redirect('checkout.php');
    }

    // Đặt hàng
    if ($act === 'place_order') {
        $name    = trim($_POST['customer_name']);
        $phone   = trim($_POST['customer_phone']);
        $address = trim($_POST['customer_address']);
        $note    = trim($_POST['customer_note']);
        $payment = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cod';

        $errors = array();
        if ($name    === '') { $errors[] = 'Vui lòng nhập họ tên.'; }
        if ($phone   === '') { $errors[] = 'Vui lòng nhập số điện thoại.'; }
        if ($address === '') { $errors[] = 'Vui lòng nhập địa chỉ giao hàng.'; }
        if (empty($cart))   { $errors[] = 'Giỏ hàng của bạn đang trống.'; }

        if (empty($errors) && $conn !== null) {
            // Tính tổng (bao gồm phí ship)
            $sub = 0;
            foreach ($cart as $item) { $sub += $item['price'] * $item['quantity']; }
            $ship       = ($sub >= 200000) ? 0 : 25000;
            $grand      = $sub + $ship;

            // ── Kiểm tra bảng orders tồn tại ─────────────────
            $tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
            if (!$tbl_check || mysqli_num_rows($tbl_check) === 0) {
                $errors[] = 'Bảng orders chưa được tạo. Vui lòng chạy setup.sql trước!';
            } else {
                // ── Thêm đơn hàng ──────────────────────────────
                $stmt = mysqli_prepare($conn,
                    'INSERT INTO orders
                        (customer_name, customer_phone, customer_address, customer_note, payment_method, total_amount, status)
                     VALUES (?,?,?,?,?,?,\'pending\')');

                if ($stmt === false) {
                    $errors[] = 'Lỗi chuẩn bị lệnh SQL: ' . mysqli_error($conn);
                } else {
                    $grand_int = (int)$grand;
                    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $phone, $address, $note, $payment, $grand_int);

                    if (!mysqli_stmt_execute($stmt)) {
                        $errors[] = 'Không thể lưu đơn hàng: ' . mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                    } else {
                        $order_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($stmt);

                        // ── Thêm chi tiết đơn ──────────────────
                        $item_ok = true;
                        foreach ($cart as $item) {
                            $stmt2 = mysqli_prepare($conn,
                                'INSERT INTO order_items (order_id, variant_id, variant_name, price, quantity)
                                 VALUES (?,?,?,?,?)');
                            if ($stmt2 === false) { $item_ok = false; break; }
                            $vid   = (int)$item['variant_id'];
                            $vname = (string)$item['variant_name'];
                            $price = (int)$item['price'];
                            $qty   = (int)$item['quantity'];
                            mysqli_stmt_bind_param($stmt2, 'iisii', $order_id, $vid, $vname, $price, $qty);
                            if (!mysqli_stmt_execute($stmt2)) { $item_ok = false; }
                            mysqli_stmt_close($stmt2);
                        }

                        $_SESSION['cart']             = array();
                        $_SESSION['order_success_id'] = $order_id;
                        redirect('checkout.php?success=1');
                    }
                }
            }
        } elseif (empty($errors) && $conn === null) {
            // Fallback khi không có DB
            $_SESSION['cart']             = array();
            $_SESSION['order_success_id'] = rand(1000, 9999);
            redirect('checkout.php?success=1');
        }

        if (!empty($errors)) {
            $_SESSION['checkout_errors'] = $errors;
            redirect('checkout.php');
        }
    }
}

if ($conn !== null) { mysqli_close($conn); }

// ── Pop messages ──────────────────────────────────────────────
$errors      = isset($_SESSION['checkout_errors']) ? $_SESSION['checkout_errors'] : array();
$order_id    = isset($_SESSION['order_success_id']) ? $_SESSION['order_success_id'] : 0;
$is_success  = isset($_GET['success']) && $order_id > 0;
unset($_SESSION['checkout_errors'], $_SESSION['order_success_id']);

// ── Tính tổng ─────────────────────────────────────────────────
$subtotal  = 0;
foreach ($cart as $item) { $subtotal += $item['price'] * $item['quantity']; }
$shipping  = ($subtotal >= 200000 || $subtotal === 0) ? 0 : 25000;
$total     = $subtotal + $shipping;
$cart_count = 0;
foreach ($cart as $item) { $cart_count += $item['quantity']; }
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đặt Hàng - Shopee</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #222; font-size: 14px; }
a { text-decoration: none; color: inherit; }
button { cursor: pointer; font-family: inherit; }
:root { --orange: #ee4d2d; }

/* Header */
.header { background: linear-gradient(to right,#f53d2d,#ff6633); padding: 0; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.header-inner { max-width: 1200px; margin: 0 auto; padding: 0 20px; height: 56px; display: flex; align-items: center; justify-content: space-between; }
.logo { color: #fff; font-size: 28px; font-weight: 800; font-style: italic; display: flex; align-items: center; gap: 12px; }
.logo span { font-size: 14px; font-weight: 400; font-style: normal; border-left: 1px solid rgba(255,255,255,.5); padding-left: 12px; }
.header-secure { color: rgba(255,255,255,.8); font-size: 13px; }

/* Container */
.container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.layout { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
.left  { flex: 1 1 560px; }
.right { flex: 0 0 360px; }

/* Card */
.card { background: #fff; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,.07); margin-bottom: 16px; overflow: hidden; }
.card-header { padding: 16px 20px; border-bottom: 1px solid #f5f5f5; display: flex; align-items: center; justify-content: space-between; }
.card-header h2 { font-size: 15px; font-weight: 600; color: #333; }
.card-body { padding: 20px; }

/* Steps */
.steps { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 24px; }
.step { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #bbb; }
.step-num { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #ddd; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
.step.done .step-num { background: var(--orange); border-color: var(--orange); color: #fff; }
.step.active .step-num { border-color: var(--orange); color: var(--orange); }
.step.active { color: var(--orange); font-weight: 600; }
.step-line { flex: 1; height: 1px; background: #ddd; min-width: 40px; }

/* Form */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; color: #888; margin-bottom: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: .4px; }
.form-group input[type=text],
.form-group input[type=tel],
.form-group textarea,
.form-group select {
    width: 100%; padding: 11px 14px; border: 1px solid #e0e0e0;
    border-radius: 6px; font-size: 14px; outline: none; transition: border-color .15s;
    font-family: inherit;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--orange); }
.form-group textarea { resize: vertical; min-height: 80px; }
.form-row { display: flex; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 0; }

/* Payment methods */
.pay-options { display: flex; flex-direction: column; gap: 8px; }
.pay-option { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: border-color .15s; }
.pay-option:hover { border-color: var(--orange); }
.pay-option input[type=radio] { accent-color: var(--orange); width: 16px; height: 16px; }
.pay-option.selected { border-color: var(--orange); background: #fff8f7; }
.pay-icon { font-size: 22px; }
.pay-label { font-weight: 500; font-size: 14px; }
.pay-desc  { font-size: 12px; color: #888; margin-top: 2px; }

/* Cart items */
.cart-item { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #f5f5f5; align-items: flex-start; }
.cart-item:last-child { border-bottom: none; }
.item-img { width: 64px; height: 64px; object-fit: cover; border-radius: 4px; border: 1px solid #f0f0f0; flex-shrink: 0; }
.item-info { flex: 1; min-width: 0; }
.item-name { font-size: 13px; color: #333; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-variant { font-size: 12px; color: #999; margin-top: 2px; }
.item-price { font-size: 14px; font-weight: 600; color: var(--orange); margin-top: 4px; }
.item-controls { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
.qty-btn { width: 28px; height: 28px; border: 1px solid #ddd; background: #fff; border-radius: 4px; font-size: 16px; display: flex; align-items: center; justify-content: center; }
.qty-num { font-size: 14px; min-width: 28px; text-align: center; }
.item-del { background: none; border: none; color: #ccc; font-size: 18px; cursor: pointer; padding: 0; line-height: 1; }
.item-del:hover { color: var(--orange); }

/* Order summary */
.summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 10px; }
.summary-row .label { color: #888; }
.summary-divider { border: none; border-top: 1px dashed #f0f0f0; margin: 12px 0; }
.summary-total { display: flex; justify-content: space-between; align-items: center; }
.summary-total .label { font-size: 15px; font-weight: 600; }
.summary-total .amount { font-size: 22px; font-weight: 700; color: var(--orange); }

/* Shipping badge */
.free-ship { background: #dcfce7; color: #166534; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }

/* Buttons */
.btn-order { width: 100%; padding: 14px; background: var(--orange); color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; margin-top: 16px; transition: background .15s; }
.btn-order:hover { background: #d94226; }
.btn-order:disabled { opacity: .5; cursor: not-allowed; }
.btn-back { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #888; }
.btn-back:hover { color: var(--orange); }

/* Alerts */
.alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
.alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

/* Empty cart */
.empty-cart { text-align: center; padding: 48px 20px; }
.empty-cart .icon { font-size: 64px; margin-bottom: 16px; }
.empty-cart h3 { font-size: 18px; color: #333; margin-bottom: 8px; }
.empty-cart p { color: #888; margin-bottom: 20px; }
.btn-shop { display: inline-block; padding: 11px 28px; background: var(--orange); color: #fff; border-radius: 4px; font-size: 14px; font-weight: 500; }

/* Success */
.success-box { text-align: center; padding: 48px 20px; }
.success-icon { font-size: 72px; margin-bottom: 16px; }
.success-box h2 { font-size: 22px; color: #16a34a; margin-bottom: 8px; }
.success-box p { color: #666; margin-bottom: 4px; font-size: 15px; }
.order-code { display: inline-block; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 8px 20px; border-radius: 20px; font-size: 15px; font-weight: 700; margin: 12px 0 20px; }
.success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 8px; }
.btn-primary { padding: 11px 28px; background: var(--orange); color: #fff; border-radius: 4px; font-size: 14px; font-weight: 500; border: none; }
.btn-ghost { padding: 11px 28px; border: 1px solid #ddd; background: #fff; color: #555; border-radius: 4px; font-size: 14px; }

/* Guarantee strip */
.guarantee-strip { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: #666; padding-top: 14px; border-top: 1px solid #f5f5f5; }
.guarantee-strip span { display: flex; align-items: center; gap: 5px; }

@media (max-width: 780px) {
    .layout { flex-direction: column; }
    .right { flex: 1 1 100%; }
}
</style>
</head>
<body>

<!-- Header -->
<header class="header">
  <div class="header-inner">
    <div class="logo">
      <a href="index.php" style="color:#fff">shopee</a>
      <span>Thanh Toán</span>
    </div>
    <div class="header-secure">🔒 Giao dịch bảo mật</div>
  </div>
</header>

<div class="container">

<?php if ($is_success): ?>
<!-- ═══════════════════════════════════════
     ĐẶT HÀNG THÀNH CÔNG
═══════════════════════════════════════ -->
<div class="card" style="max-width:560px;margin:40px auto">
  <div class="card-body success-box">
    <div class="success-icon">🎉</div>
    <h2>Đặt hàng thành công!</h2>
    <p>Cảm ơn bạn đã mua hàng tại Shopee.</p>
    <p>Chúng tôi sẽ liên hệ xác nhận đơn hàng trong thời gian sớm nhất.</p>
    <div class="order-code">Mã đơn hàng: #<?php echo sprintf('%06d', $order_id); ?></div>
    <p style="color:#888;font-size:13px">Dự kiến giao hàng trong <strong>3–5 ngày</strong> làm việc</p>
    <div class="success-actions">
      <a href="index.php" class="btn-primary">🛍️ Tiếp tục mua sắm</a>
      <a href="checkout.php" class="btn-ghost">Xem giỏ hàng</a>
    </div>
  </div>
</div>

<?php elseif (empty($cart)): ?>
<!-- ═══════════════════════════════════════
     GIỎ HÀNG TRỐNG
═══════════════════════════════════════ -->
<div class="card" style="max-width:480px;margin:40px auto">
  <div class="card-body empty-cart">
    <div class="icon">🛒</div>
    <h3>Giỏ hàng của bạn đang trống</h3>
    <p>Hãy thêm sản phẩm vào giỏ trước khi đặt hàng</p>
    <a href="index.php" class="btn-shop">🛍️ Mua sắm ngay</a>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════
     GIỎ HÀNG + FORM ĐẶT HÀNG
═══════════════════════════════════════ -->

<!-- Steps -->
<div class="steps" style="max-width:400px;margin:0 auto 20px">
  <div class="step done"><div class="step-num">✓</div> Giỏ hàng</div>
  <div class="step-line"></div>
  <div class="step active"><div class="step-num">2</div> Thông tin</div>
  <div class="step-line"></div>
  <div class="step"><div class="step-num">3</div> Xác nhận</div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?>
      <div>⚠️ <?php echo e($err); ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="POST" action="checkout.php" id="order-form">
<input type="hidden" name="act" value="place_order">
<div class="layout">

  <!-- ── LEFT ─────────────────────────────── -->
  <div class="left">

    <!-- Thông tin giao hàng -->
    <div class="card">
      <div class="card-header"><h2>📍 Địa chỉ giao hàng</h2></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Họ và tên *</label>
            <input type="text" name="customer_name" placeholder="Nguyễn Văn A" required value="<?php echo e($_POST['customer_name'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label>Số điện thoại *</label>
            <input type="tel" name="customer_phone" placeholder="0901 234 567" required value="<?php echo e($_POST['customer_phone'] ?? ''); ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Địa chỉ giao hàng *</label>
          <input type="text" name="customer_address" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố" required value="<?php echo e($_POST['customer_address'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label>Ghi chú (tùy chọn)</label>
          <textarea name="customer_note" placeholder="Ví dụ: Giao giờ hành chính, gọi trước khi giao..."><?php echo e($_POST['customer_note'] ?? ''); ?></textarea>
        </div>
      </div>
    </div>

    <!-- Phương thức thanh toán -->
    <div class="card">
      <div class="card-header"><h2>💳 Phương thức thanh toán</h2></div>
      <div class="card-body">
        <div class="pay-options" id="pay-opts">
          <label class="pay-option selected">
            <input type="radio" name="payment_method" value="cod" checked onchange="highlightPay(this)">
            <span class="pay-icon">💵</span>
            <div>
              <div class="pay-label">Thanh toán khi nhận hàng (COD)</div>
              <div class="pay-desc">Trả tiền mặt khi nhận được hàng</div>
            </div>
          </label>
          <label class="pay-option">
            <input type="radio" name="payment_method" value="bank" onchange="highlightPay(this)">
            <span class="pay-icon">🏦</span>
            <div>
              <div class="pay-label">Chuyển khoản ngân hàng</div>
              <div class="pay-desc">MB Bank / VCB / Techcombank</div>
            </div>
          </label>
          <label class="pay-option">
            <input type="radio" name="payment_method" value="momo" onchange="highlightPay(this)">
            <span class="pay-icon">🟣</span>
            <div>
              <div class="pay-label">Ví MoMo</div>
              <div class="pay-desc">Thanh toán nhanh qua ví điện tử</div>
            </div>
          </label>
        </div>
      </div>
    </div>

  </div><!-- /.left -->

  <!-- ── RIGHT ─────────────────────────────── -->
  <div class="right">

    <!-- Giỏ hàng -->
    <div class="card">
      <div class="card-header">
        <h2>🛒 Đơn hàng (<?php echo $cart_count; ?> sản phẩm)</h2>
        <a href="index.php" class="btn-back">← Thêm sản phẩm</a>
      </div>
      <div class="card-body" style="padding-top:0">
        <?php foreach ($cart as $item):
          $img = 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=120&q=60';
        ?>
        <div class="cart-item">
          <img src="<?php echo e($img); ?>" class="item-img" alt="">
          <div class="item-info">
            <div class="item-name">Sản phẩm: <?php echo e($item['variant_name']); ?></div>
            <div class="item-variant">Phân loại: <?php echo e($item['variant_name']); ?></div>
            <div class="item-price"><?php echo fmt($item['price']); ?></div>
            <div class="item-controls">
              <form method="POST" style="display:inline">
                <input type="hidden" name="act" value="update_qty">
                <input type="hidden" name="variant_id" value="<?php echo $item['variant_id']; ?>">
                <button type="submit" name="quantity" value="<?php echo max(1, $item['quantity']-1); ?>" class="qty-btn">−</button>
              </form>
              <span class="qty-num"><?php echo $item['quantity']; ?></span>
              <form method="POST" style="display:inline">
                <input type="hidden" name="act" value="update_qty">
                <input type="hidden" name="variant_id" value="<?php echo $item['variant_id']; ?>">
                <button type="submit" name="quantity" value="<?php echo $item['quantity']+1; ?>" class="qty-btn">+</button>
              </form>
              &nbsp;
              <form method="POST" style="display:inline" onsubmit="return confirm('Xóa sản phẩm này?')">
                <input type="hidden" name="act" value="remove">
                <input type="hidden" name="variant_id" value="<?php echo $item['variant_id']; ?>">
                <button type="submit" class="item-del" title="Xóa">×</button>
              </form>
            </div>
          </div>
          <div style="font-weight:600;white-space:nowrap;padding-top:4px"><?php echo fmt($item['price'] * $item['quantity']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tổng tiền -->
    <div class="card">
      <div class="card-body">
        <div class="summary-row">
          <span class="label">Tạm tính</span>
          <span><?php echo fmt($subtotal); ?></span>
        </div>
        <div class="summary-row">
          <span class="label">Phí vận chuyển</span>
          <span>
            <?php if ($shipping === 0): ?>
              <span class="free-ship">MIỄN PHÍ</span>
            <?php else: ?>
              <?php echo fmt($shipping); ?>
            <?php endif; ?>
          </span>
        </div>
        <?php if ($shipping > 0): ?>
        <div class="summary-row">
          <span class="label" style="font-size:12px;color:#bbb">Mua thêm <?php echo fmt(200000 - $subtotal); ?> để được miễn phí ship</span>
          <span></span>
        </div>
        <?php endif; ?>
        <hr class="summary-divider">
        <div class="summary-total">
          <span class="label">Tổng thanh toán</span>
          <span class="amount"><?php echo fmt($total); ?></span>
        </div>

        <button type="submit" class="btn-order" form="order-form">
          ✅ Đặt hàng ngay
        </button>

        <div class="guarantee-strip">
          <span>🛡️ Bảo đảm chính hãng</span>
          <span>🔒 Thanh toán an toàn</span>
          <span>↩️ Đổi trả 15 ngày</span>
        </div>
      </div>
    </div>

  </div><!-- /.right -->
</div>
</form>

<?php endif; ?>
</div><!-- /.container -->

<script>
function highlightPay(radio) {
    document.querySelectorAll('.pay-option').forEach(function(el) {
        el.classList.remove('selected');
    });
    radio.closest('.pay-option').classList.add('selected');
}
</script>
</body>
</html>
