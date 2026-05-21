<?php
// ============================================================
// BACKEND VM — admin.php
//
// All-in-one admin panel:
//  1. Upload image → Azure Blob Storage (cURL PUT)
//  2. Get Blob URL back
//  3. INSERT restaurant + Blob URL → SQL Server (same VM)
//
// Place this file on the Backend VM since it talks directly
// to SQL Server on localhost — no inter-VM API call needed.
// ============================================================

// ── Azure Blob Storage ───────────────────────────────────────
$storage_account = "doordash0511";
$container_name  = "doordashrestaurants";
$account_key     = "Azure_Access_Storage "; // Azure Portal → Storage Account → Access Keys → key1

require_once 'db.php';

$success_msg = "";
$error_msg   = "";

// Builds the Authorization header required by Azure Blob REST API
function buildAzureAuthHeader($account, $key, $method, $content_type, $content_length, $date, $container, $blob) {
    $string_to_sign = "{$method}\n\n\n{$content_length}\n\n{$content_type}\n\n\n\n\n\n\nx-ms-blob-type:BlockBlob\nx-ms-date:{$date}\nx-ms-version:2020-10-02\n/{$account}/{$container}/{$blob}";
    $signature      = base64_encode(hash_hmac('sha256', $string_to_sign, base64_decode($key), true));
    return "SharedKey {$account}:{$signature}";
}

// ── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name          = trim($_POST['name']          ?? '');
    $category      = trim($_POST['category']      ?? '');
    $rating        = trim($_POST['rating']        ?? '');
    $distance      = trim($_POST['distance']      ?? '');
    $delivery_time = trim($_POST['delivery_time'] ?? '');
    $delivery_fee  = trim($_POST['delivery_fee']  ?? '0');

    // Validate fields
    if (empty($name) || empty($category) || empty($rating) || empty($distance) || empty($delivery_time)) {
        $error_msg = "All fields are required.";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Please upload a valid image.";
    } else {

        $file     = $_FILES['image'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $error_msg = "Only JPG, PNG, and WEBP images are allowed.";
        } else {

            // ── STEP 1: Upload image to Azure Blob Storage ───────
            $blob_filename = strtolower(preg_replace('/\s+/', '_', $name)) . '_' . time() . '.' . $file_ext;
            $blob_base     = "https://{$storage_account}.blob.core.windows.net/{$container_name}";
            $upload_url    = "{$blob_base}/{$blob_filename}";
            $image_data    = file_get_contents($file['tmp_name']);
            $content_type  = mime_content_type($file['tmp_name']);
            $content_length = strlen($image_data);
            $date          = gmdate('D, d M Y H:i:s') . ' GMT';
            $auth_header   = buildAzureAuthHeader($storage_account, $account_key, 'PUT', $content_type, $content_length, $date, $container_name, $blob_filename);

            $ch = curl_init($upload_url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => "PUT",
                CURLOPT_POSTFIELDS     => $image_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    "x-ms-blob-type: BlockBlob",
                    "x-ms-date: {$date}",
                    "x-ms-version: 2020-10-02",
                    "Content-Type: {$content_type}",
                    "Content-Length: {$content_length}",
                    "Authorization: {$auth_header}",
                ],
            ]);
            curl_exec($ch);
            $blob_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($blob_http_code !== 201) {
                $error_msg = "Image upload to Blob Storage failed (HTTP {$blob_http_code}). Check your Storage Account Key.";
            } else {

                // ── STEP 2: Blob URL to save in DB ───────────────
                $image_url = "{$blob_base}/{$blob_filename}";

                // ── STEP 3: INSERT into SQL Server ────────────────
                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO restaurants
                            (name, category, rating, distance_miles, delivery_time, image_url, delivery_fee)
                             VALUES
                            (:name, :category, :rating, :distance, :delivery_time, :image_url, :delivery_fee)
                    ");

                    $stmt->execute([
                        ':name'          => $name,
                        ':category'      => $category,
                        ':rating'        => (float) $rating,
                        ':distance'      => (float) $distance,
                        ':delivery_time' => $delivery_time,
                        ':image_url'     => $image_url,
                        ':delivery_fee'  => (float) $delivery_fee,
                    ]);

                    $success_msg = "✅ <strong>{$name}</strong> added! Image saved to Blob Storage and URL stored in SQL.";

                } catch (PDOException $e) {
                    $error_msg = "Image uploaded to Blob but DB insert failed: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Add Restaurant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --red:      #FF3008;
            --red-dark: #d9260a;
            --bg:       #f4f4f4;
            --white:    #ffffff;
            --gray-1:   #f0f0f0;
            --gray-2:   #ddd;
            --gray-3:   #999;
            --dark:     #1a1a1a;
            --radius:   12px;
            --font:     'DM Sans', sans-serif;
              }

        body { font-family: var(--font); background: var(--bg); color: var(--dark); min-height: 100vh; }

        .admin-header {
            background: var(--dark);
            padding: 0 32px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .admin-header .logo  { color: var(--red); font-weight: 700; font-size: 20px; }
        .admin-header .badge {
            background: rgba(255,48,8,0.15);
            color: var(--red);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(255,48,8,0.3);
        }

        .admin-wrap  { max-width: 680px; margin: 48px auto; padding: 0 20px; }
        .page-title  { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-sub    { color: var(--gray-3); font-size: 14px; margin-bottom: 32px; }

        .form-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 36px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

        .field { margin-bottom: 20px; }
        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .field input,
        .field select {
             width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--gray-2);
            border-radius: 8px;
            font-family: var(--font);
            font-size: 14px;
            color: var(--dark);
            background: var(--white);
            transition: border-color 0.2s;
            outline: none;
        }
        .field input:focus,
        .field select:focus { border-color: var(--red); }

        .upload-area {
            border: 2px dashed var(--gray-2);
            border-radius: 10px;
            padding: 32px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }
        .upload-area:hover,
        .upload-area.dragover { border-color: var(--red); background: #fff5f4; }
        .upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .upload-icon  { font-size: 36px; margin-bottom: 10px; }
        .upload-label { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .upload-hint  { color: var(--gray-3); font-size: 13px; }

        #img-preview {
            display: none;
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 14px;
        }
        #file-name { margin-top: 10px; font-size: 13px; color: var(--red); font-weight: 500; }

        .divider { border: none; border-top: 1px solid var(--gray-1); margin: 28px 0; }
         .section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-3);
            margin-bottom: 16px;
        }

        .btn-submit {
            width: 100%;
            background: var(--red);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-family: var(--font);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover     { background: var(--red-dark); }
        .btn-submit:disabled  { background: #ccc; cursor: not-allowed; }

        .alert { padding: 14px 18px; border-radius: 10px; font-size: 14px; font-weight: 500; margin-bottom: 24px; }
        .alert-success { background: #e6faf0; color: #1a7a45; border: 1px solid #a3e6c3; }
        .alert-error   { background: #fff0ee; color: #c0392b; border: 1px solid #f5b7b1; }

        @media (max-width: 520px) {
            .form-row  { grid-template-columns: 1fr; }
            .form-card { padding: 24px 18px; }
        }
    </style>
</head>
<body>

<header class="admin-header">
    <span class="logo">DoorDash</span>
    <span class="badge">Admin Panel</span>
</header>

<div class="admin-wrap">
    <h1 class="page-title">Add Restaurant</h1>
    <p class="page-sub">Image uploads go to Azure Blob Storage. The Blob URL is saved directly into SQL Server.</p>

    <?php if ($success_msg): ?>
         <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data" id="admin-form">

            <p class="section-label">Restaurant Details</p>

            <div class="field">
                <label for="name">Restaurant Name</label>
                <input type="text" id="name" name="name" placeholder="e.g. Burger Palace" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>
                        <?php
                        foreach (["Burgers","Pizza","Sushi","Mexican","Healthy","Chinese","Chicken","Sandwiches","Desserts","Indian","Thai","Italian"] as $cat) {
                            $sel = (($_POST['category'] ?? '') === $cat) ? 'selected' : '';
                            echo "<option value='{$cat}' {$sel}>{$cat}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rating">Rating (1.0 – 5.0)</label>
                    <input type="number" id="rating" name="rating" step="0.1" min="1" max="5" placeholder="e.g. 4.5" required value="<?= htmlspecialchars($_POST['rating'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="distance">Distance (miles)</label>
                    <input type="number" id="distance" name="distance" step="0.1" min="0" placeholder="e.g. 1.2" required value="<?= htmlspecialchars($_POST['distance'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="delivery_time">Delivery Time</label>
                    <input type="text" id="delivery_time" name="delivery_time" placeholder="e.g. 20-30 min" required value="<?= htmlspecialchars($_POST['delivery_time'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label for="delivery_fee">Delivery Fee ($) — enter 0 for free</label>
                 <input type="number" id="delivery_fee" name="delivery_fee" step="0.01" min="0" placeholder="e.g. 1.99" value="<?= htmlspecialchars($_POST['delivery_fee'] ?? '0') ?>">
            </div>

            <hr class="divider">

            <p class="section-label">Restaurant Image → Azure Blob Storage</p>

            <div class="field">
                <div class="upload-area" id="upload-area">
                    <input type="file" name="image" id="image-input" accept="image/jpeg,image/png,image/webp" required>
                    <div class="upload-icon">🖼️</div>
                    <div class="upload-label">Click or drag & drop image here</div>
                    <div class="upload-hint">JPG, PNG, WEBP — uploaded directly to Azure Blob Storage</div>
                    <div id="file-name"></div>
                </div>
                <img id="img-preview" alt="Preview">
            </div>

            <button type="submit" class="btn-submit" id="submit-btn">Add Restaurant</button>

        </form>
    </div>
</div>

<script>
    const input    = document.getElementById('image-input');
    const preview  = document.getElementById('img-preview');
    const fileName = document.getElementById('file-name');
    const area     = document.getElementById('upload-area');
    const form     = document.getElementById('admin-form');
    const submitBtn = document.getElementById('submit-btn');

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        fileName.textContent = file.name;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    area.addEventListener('dragover',  e => { e.preventDefault(); area.classList.add('dragover'); });
    area.addEventListener('dragleave', ()  => area.classList.remove('dragover'));
    area.addEventListener('drop', e => {
        e.preventDefault();
         area.classList.remove('dragover');
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
    });

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';
    });
</script>
</body>
</html>