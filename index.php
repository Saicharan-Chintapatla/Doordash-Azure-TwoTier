<?php
// ============================================================
// index.php — Frontend VM
// Connects directly to SQL Server on the second VM
// Gets image_url + all restaurant details from DB
// ============================================================

require_once 'db.php';

$restaurants = [];

try {

    $stmt = $pdo->query("
        SELECT name, category, rating, distance_miles, delivery_time, image_url, delivery_fee
        FROM restaurants
        WHERE is_active = 1
        ORDER BY rating DESC
    ");

    $restaurants = $stmt->fetchAll();

} catch (PDOException $e) {
    // silently fall through to empty state
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DoorDash – Delivery & Takeout</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar">
    <div class="nav-inner">
        <div class="nav-logo">
            <svg width="130" height="32" viewBox="0 0 130 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <text x="0" y="26" font-family="DM Sans,sans-serif" font-weight="700" font-size="26" fill="#FF3008">DoorDash</text>
            </svg>
        </div>
        <div class="nav-address">
            <span class="pin-icon">📍</span>
             <input type="text" class="address-input" placeholder="Enter delivery address" value="San Francisco, CA">
            <button class="address-btn">Update</button>
        </div>
        <nav class="nav-links">
            <a href="#">Sign In</a>
            <a href="#" class="btn-signup">Sign Up</a>
        </nav>
    </div>
</header>

<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-content">
        <h1>Delivery & Pickup<br>from the best</h1>
        <p>Get food, groceries and more delivered to your door</p>
        <div class="hero-search">
            <span class="search-pin">📍</span>
            <input type="text" placeholder="Enter delivery address or zip code">
            <button class="search-btn">Find Food</button>
        </div>
    </div>
</section>

<!-- ── CATEGORY PILLS ── -->
<section class="categories">
    <div class="container">
        <div class="cat-scroll">
            <?php
            $cats = ["🍔 Burgers","🍕 Pizza","🍣 Sushi","🌮 Mexican","🥗 Healthy","🍜 Chinese","🍗 Chicken","🌯 Sandwiches","🍦 Desserts"];
            foreach ($cats as $cat) echo "<button class='cat-pill'>$cat</button>";
            ?>
        </div>
    </div>
</section>

<!-- ── RESTAURANT GRID ── -->
<section class="restaurants">
    <div class="container">
        <h2 class="section-title">Restaurants near you</h2>

        <?php if (empty($restaurants)): ?>
            <p style="color:#999; text-align:center; padding: 60px 0;">No restaurants found. Add some via the admin panel.</p>
        <?php else: ?>

        <div class="restaurant-grid">
            <?php foreach ($restaurants as $r):
                $name          = htmlspecialchars($r['name']);
                $category      = htmlspecialchars($r['category'])
                 $rating        = (float) $r['rating'];
                $distance      = number_format($r['distance_miles'], 1) . ' mi';
                $delivery_time = htmlspecialchars($r['delivery_time']);
                $delivery_fee  = (float) $r['delivery_fee'];
                $free_delivery = ($delivery_fee === 0.00);
                $img_url       = htmlspecialchars($r['image_url']); // straight from DB — is the Blob URL
                $fallback      = "https://placehold.co/400x220/f0f0f0/999?text=" . urlencode($r['name']);
                $full_stars    = floor($rating);
                $half_star     = ($rating - $full_stars) >= 0.5 ? 1 : 0;
                $star_html     = str_repeat("★", $full_stars) . ($half_star ? "½" : "") . str_repeat("☆", 5 - $full_stars - $half_star);
            ?>
            <div class="card">
                <div class="card-img-wrap">
                    <img src="<?= $img_url ?>" alt="<?= $name ?>" onerror="this.onerror=null;this.src='<?= $fallback ?>'" loading="lazy">
                    <?php if ($free_delivery): ?>
                        <span class="badge-free">Free Delivery</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="card-top">
                        <h3><?= $name ?></h3>
                        <span class="card-category"><?= $category ?></span>
                    </div>
                    <div class="card-meta">
                        <span class="rating"><span class="stars"><?= $star_html ?></span> <?= number_format($rating, 1) ?></span>
                        <span class="dot">·</span>
                        <span class="distance"><?= $distance ?></span>
                        <span class="dot">·</span>
                        <span class="time"><?= $delivery_time ?></span>
                    </div>
                    <div class="card-footer">
                        <?php if ($free_delivery): ?>
                            <span class="fee free">Free delivery</span>
                        <?php else: ?>
                            <span class="fee">$<?= number_format($delivery_fee, 2) ?> delivery fee</span>
                        <?php endif; ?>
                        <button class="order-btn">Order Now</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
</section>

<!-- ── FOOTER ── -->
 <footer class="footer">
    <div class="container footer-inner">
        <span class="footer-logo">DoorDash</span>
        <span class="footer-note">© 2024 DoorDash – Azure DevOps Project</span>
    </div>
</footer>

<script>
document.querySelectorAll('.cat-pill').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
</body>
</html>