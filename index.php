<?php
/**
 * index.php - Trang danh sách sản phẩm kiểu Shopee
 * PHP 7.2 | mysqli | Pure CSS + Vanilla JS
 */

session_start();
require_once __DIR__ . '/includes/db.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($n) { return number_format((float)$n, 0, ',', '.') . '₫'; }

// ── Tham số lọc / sắp xếp / tìm kiếm ─────────────────────────
$search  = isset($_GET['q'])    ? trim($_GET['q'])           : '';
$sort    = isset($_GET['sort']) ? $_GET['sort']               : 'newest';
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset   = ($page - 1) * $per_page;

// ── Lấy sản phẩm ──────────────────────────────────────────────
$products   = array();
$total_rows = 0;

if ($conn !== null) {
    $where  = '';
    $params = array();
    $types  = '';

    if ($search !== '') {
        $where   = 'WHERE p.name LIKE ?';
        $params[] = '%' . $search . '%';
        $types   .= 's';
    }

    $order_sql = array(
        'newest'    => 'p.id DESC',
        'top_sold'  => 'p.sold DESC',
        'rating'    => 'p.rating DESC',
        'price_asc' => 'min_price ASC',
        'price_desc'=> 'min_price DESC',
    );
    $order = isset($order_sql[$sort]) ? $order_sql[$sort] : 'p.id DESC';

    // Tổng số hàng
    $sql_count = "SELECT COUNT(*) cnt
                  FROM products p
                  $where";
    $stmt_c = mysqli_prepare($conn, $sql_count);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt_c, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_c);
    $row_c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
    $total_rows = (int)$row_c['cnt'];
    mysqli_stmt_close($stmt_c);

    // Lấy sản phẩm kèm ảnh đầu và giá min
    $sql = "SELECT p.id, p.name, p.rating, p.review_count, p.sold,
                   (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order LIMIT 1) AS cover,
                   (SELECT MIN(pv.price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock > 0) AS min_price,
                   (SELECT MAX(pv.original_price) FROM product_variants pv WHERE pv.product_id = p.id) AS max_orig
            FROM products p
            $where
            ORDER BY $order
            LIMIT ? OFFSET ?";

    $limit_params  = array_merge($params, array($per_page, $offset));
    $limit_types   = $types . 'ii';

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $limit_types, ...$limit_params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $products[] = $row; }
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
} else {
    // Fallback sample
    $products = array(
        array('id'=>1,'name'=>'Áo Thun Nam Cao Cấp Premium Cotton','rating'=>4.8,'review_count'=>2341,'sold'=>15892,
              'cover'=>'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&q=80',
              'min_price'=>185000,'max_orig'=>289000),
        array('id'=>1,'name'=>'Áo Polo Nam Vải Pique Cao Cấp','rating'=>4.7,'review_count'=>1823,'sold'=>9234,
              'cover'=>'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=400&q=80',
              'min_price'=>220000,'max_orig'=>350000),
        array('id'=>1,'name'=>'Áo Sơ Mi Nam Slim Fit Oxford','rating'=>4.9,'review_count'=>3102,'sold'=>21000,
              'cover'=>'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=400&q=80',
              'min_price'=>295000,'max_orig'=>420000),
    );
    $total_rows = count($products);
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── Cart count ─────────────────────────────────────────────────
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$cart_count = 0;
foreach ($cart as $it) { $cart_count += $it['quantity']; }

// ── Build URL helper ───────────────────────────────────────────
function build_url($overrides = array()) {
    $params = array();
    if (!empty($_GET['q']))    { $params['q']    = $_GET['q']; }
    if (!empty($_GET['sort'])) { $params['sort'] = $_GET['sort']; }
    if (!empty($_GET['page'])) { $params['page'] = $_GET['page']; }
    foreach ($overrides as $k => $v) { $params[$k] = $v; }
    return 'index.php' . (empty($params) ? '' : '?' . http_build_query($params));
}
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopee - Mua Sắm Online</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #222; font-size: 14px; }
a { text-decoration: none; color: inherit; }
button { cursor: pointer; font-family: inherit; }

/* ── Header ── */
.header { background: linear-gradient(to right,#f53d2d,#ff6633); padding: 14px 0; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.header-inner { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; gap: 16px; }
.logo { color: #fff; font-size: 30px; font-weight: 800; font-style: italic; flex-shrink: 0; }
.search-form { flex: 1; display: flex; max-width: 580px; }
.search-form input { flex: 1; padding: 10px 16px; border: none; border-radius: 4px 0 0 4px; font-size: 14px; outline: none; }
.search-form button { background: #fb5533; border: none; border-radius: 0 4px 4px 0; padding: 10px 22px; color: #fff; font-size: 18px; transition: background .15s; }
.search-form button:hover { background: #e04a2a; }
.cart-link { position: relative; color: #fff; font-size: 28px; flex-shrink: 0; }
.cart-badge { position: absolute; top: -7px; right: -8px; background: #fff; color: #ee4d2d; font-size: 11px; font-weight: 700; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

/* ── Sub-header (cats / sort) ── */
.sub-header { background: #fff; border-bottom: 1px solid #f0f0f0; }
.sub-inner { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; gap: 0; height: 44px; }
.cat-link { padding: 0 14px; height: 44px; display: flex; align-items: center; font-size: 13px; color: #555; border-bottom: 2px solid transparent; white-space: nowrap; }
.cat-link:hover, .cat-link.active { color: #ee4d2d; border-bottom-color: #ee4d2d; }

/* ── Breadcrumb + sort bar ── */
.bar { max-width: 1200px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.bar-left { font-size: 13px; color: #555; }
.bar-left strong { color: #222; }
.sort-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.sort-group span { font-size: 13px; color: #888; }
.sort-btn { padding: 6px 14px; border-radius: 4px; border: 1px solid #d9d9d9; font-size: 13px; background: #fff; color: #555; text-decoration: none; }
.sort-btn.active { background: #ee4d2d; color: #fff; border-color: #ee4d2d; }
.sort-btn:hover:not(.active) { border-color: #ee4d2d; color: #ee4d2d; }

/* ── Product grid ── */
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }

/* ── Product card ── */
.prod-card { background: #fff; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.07); overflow: hidden; transition: box-shadow .2s, transform .2s; }
.prod-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.13); transform: translateY(-2px); }
.prod-card a { display: block; }
.prod-img-wrap { position: relative; aspect-ratio: 1/1; overflow: hidden; }
.prod-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s; }
.prod-card:hover .prod-img-wrap img { transform: scale(1.04); }
.prod-badge { position: absolute; top: 8px; left: 8px; background: #ee4d2d; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 2px; }
.prod-info { padding: 10px 10px 12px; }
.prod-name { font-size: 13px; color: #333; line-height: 1.4; height: 36px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 6px; }
.prod-price-row { display: flex; align-items: baseline; gap: 6px; margin-bottom: 6px; }
.prod-price { color: #ee4d2d; font-size: 16px; font-weight: 600; }
.prod-orig  { color: #bbb; font-size: 12px; text-decoration: line-through; }
.prod-footer { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #999; }
.prod-stars { color: #f5a623; }
.prod-sold { }

/* ── Add to cart overlay ── */
.prod-card { position: relative; }
.prod-add-btn {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: rgba(238,77,45,.9); color: #fff;
    border: none; padding: 9px;
    font-size: 13px; font-weight: 500;
    transform: translateY(100%); transition: transform .2s;
}
.prod-card:hover .prod-add-btn { transform: translateY(0); }

/* ── Empty / no results ── */
.no-results { text-align: center; padding: 60px 20px; color: #bbb; }
.no-results .icon { font-size: 48px; margin-bottom: 12px; }

/* ── Pagination ── */
.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 32px; }
.page-link { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 4px; border: 1px solid #d9d9d9; font-size: 14px; color: #555; background: #fff; text-decoration: none; }
.page-link:hover { border-color: #ee4d2d; color: #ee4d2d; }
.page-link.active { background: #ee4d2d; color: #fff; border-color: #ee4d2d; }
.page-link.disabled { opacity: .4; pointer-events: none; }

/* ── Banner ── */
.banner { background: linear-gradient(to right,#fff4f2,#ffe8e4); border-radius: 6px; padding: 20px 28px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
.banner h2 { font-size: 20px; color: #ee4d2d; font-weight: 700; }
.banner p { color: #888; font-size: 13px; margin-top: 4px; }

/* ── Stats bar ── */
.stats-bar { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.stat-chip { background: #fff; border-radius: 4px; padding: 10px 18px; display: flex; align-items: center; gap: 8px; font-size: 13px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.stat-chip .num { font-size: 18px; font-weight: 700; color: #ee4d2d; }

@media (max-width: 600px) {
    .product-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .bar { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<!-- ── Header ── -->
<header class="header">
  <div class="header-inner">
    <a href="index.php" class="logo">shopee</a>
    <form class="search-form" method="GET" action="index.php">
      <input type="text" name="q" placeholder="Tìm kiếm sản phẩm..." value="<?php echo e($search); ?>">
      <button type="submit">🔍</button>
    </form>
    <a href="checkout.php" class="cart-link">
      🛒
      <?php if ($cart_count > 0): ?>
        <div class="cart-badge"><?php echo $cart_count; ?></div>
      <?php endif; ?>
    </a>
  </div>
</header>

<!-- ── Category nav ── -->
<div class="sub-header">
  <div class="sub-inner">
    <a href="index.php" class="cat-link active">🏠 Tất cả</a>
    <a href="index.php?q=áo+thun" class="cat-link">👕 Áo Thun</a>
    <a href="index.php?q=áo+polo" class="cat-link">👔 Áo Polo</a>
    <a href="index.php?q=sơ+mi" class="cat-link">👗 Sơ Mi</a>
    <a href="index.php?q=quần" class="cat-link">👖 Quần</a>
  </div>
</div>

<!-- ── Content ── -->
<div class="container">

  <!-- Banner -->
  <?php if ($search === ''): ?>
  <div style="margin-top:16px">
    <div class="banner">
      <div>
        <h2>🛒 Mua sắm thông minh</h2>
        <p>Hàng ngàn sản phẩm chính hãng, giao hàng nhanh toàn quốc</p>
      </div>
      <div style="font-size:48px">🎉</div>
    </div>
    <div class="stats-bar">
      <div class="stat-chip"><span class="num"><?php echo $total_rows; ?></span> Sản phẩm</div>
      <div class="stat-chip"><span class="num">Free</span> Vận chuyển</div>
      <div class="stat-chip"><span class="num">15</span> Ngày đổi trả</div>
      <div class="stat-chip"><span class="num">100%</span> Chính hãng</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Sort & info bar -->
  <div class="bar" style="padding-left:0;padding-right:0">
    <div class="bar-left">
      <?php if ($search !== ''): ?>
        Kết quả cho <strong>"<?php echo e($search); ?>"</strong> &mdash; <?php echo $total_rows; ?> sản phẩm
      <?php else: ?>
        Hiển thị <strong><?php echo count($products); ?></strong> / <?php echo $total_rows; ?> sản phẩm
      <?php endif; ?>
    </div>
    <div class="sort-group">
      <span>Sắp xếp:</span>
      <a href="<?php echo build_url(array('sort'=>'newest','page'=>1)); ?>"   class="sort-btn <?php echo $sort==='newest'    ?'active':''; ?>">Mới nhất</a>
      <a href="<?php echo build_url(array('sort'=>'top_sold','page'=>1)); ?>" class="sort-btn <?php echo $sort==='top_sold'   ?'active':''; ?>">Bán chạy</a>
      <a href="<?php echo build_url(array('sort'=>'rating','page'=>1)); ?>"   class="sort-btn <?php echo $sort==='rating'     ?'active':''; ?>">Đánh giá</a>
      <a href="<?php echo build_url(array('sort'=>'price_asc','page'=>1)); ?>"  class="sort-btn <?php echo $sort==='price_asc'  ?'active':''; ?>">Giá ↑</a>
      <a href="<?php echo build_url(array('sort'=>'price_desc','page'=>1)); ?>" class="sort-btn <?php echo $sort==='price_desc' ?'active':''; ?>">Giá ↓</a>
    </div>
  </div>

  <!-- Product grid -->
  <?php if (empty($products)): ?>
    <div class="no-results">
      <div class="icon">🔍</div>
      <div>Không tìm thấy sản phẩm nào với từ khóa "<?php echo e($search); ?>"</div>
      <a href="index.php" style="color:#ee4d2d;margin-top:8px;display:inline-block">← Xem tất cả sản phẩm</a>
    </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p):
        $cover     = $p['cover'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
        $min_price = $p['min_price'] ?: 0;
        $max_orig  = $p['max_orig']  ?: $min_price;
        $disc      = ($max_orig > 0 && $min_price > 0) ? round(($max_orig - $min_price) / $max_orig * 100) : 0;
    ?>
    <div class="prod-card">
      <a href="product.php?id=<?php echo $p['id']; ?>">
        <div class="prod-img-wrap">
          <img src="<?php echo e($cover); ?>" alt="<?php echo e($p['name']); ?>" loading="lazy">
          <?php if ($disc >= 5): ?>
            <div class="prod-badge">-<?php echo $disc; ?>%</div>
          <?php endif; ?>
        </div>
        <div class="prod-info">
          <div class="prod-name"><?php echo e($p['name']); ?></div>
          <div class="prod-price-row">
            <span class="prod-price">
              <?php echo $min_price > 0 ? fmt($min_price) : 'Liên hệ'; ?>
            </span>
            <?php if ($max_orig > $min_price): ?>
              <span class="prod-orig"><?php echo fmt($max_orig); ?></span>
            <?php endif; ?>
          </div>
          <div class="prod-footer">
            <span class="prod-stars">
              <?php
              $r = (float)$p['rating'];
              for ($s=1;$s<=5;$s++) echo $s<=$r?'★':'☆';
              ?>
            </span>
            <span class="prod-sold">Đã bán <?php echo number_format($p['sold']); ?></span>
          </div>
        </div>
      </a>
      <a href="product.php?id=<?php echo $p['id']; ?>" class="prod-add-btn">🛒 Xem sản phẩm</a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <a href="<?php echo build_url(array('page'=>max(1,$page-1))); ?>"
       class="page-link <?php echo $page<=1?'disabled':''; ?>">‹</a>
    <?php for ($i=1;$i<=$total_pages;$i++): ?>
      <?php if ($i===1||$i===$total_pages||abs($i-$page)<=2): ?>
        <a href="<?php echo build_url(array('page'=>$i)); ?>"
           class="page-link <?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
      <?php elseif (abs($i-$page)===3): ?>
        <span class="page-link" style="border:none">…</span>
      <?php endif; ?>
    <?php endfor; ?>
    <a href="<?php echo build_url(array('page'=>min($total_pages,$page+1))); ?>"
       class="page-link <?php echo $page>=$total_pages?'disabled':''; ?>">›</a>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div><!-- /.container -->

</body>
</html>
