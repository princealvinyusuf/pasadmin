<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_home_popup_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'paskerid_db_prod');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function redirect_back(): void
{
    header('Location: home_popup_settings');
    exit;
}

function table_has_column(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

$conn->query("CREATE TABLE IF NOT EXISTS home_popup_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    title VARCHAR(255) NULL,
    subtitle TEXT NULL,
    image_base64 LONGTEXT NULL,
    mime_type VARCHAR(100) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS home_popup_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    setting_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NULL,
    subtitle TEXT NULL,
    image_base64 LONGTEXT NULL,
    mime_type VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_home_popup_items_setting_order (setting_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!table_has_column($conn, 'home_popup_items', 'is_enabled')) {
    $conn->query("ALTER TABLE home_popup_items ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER setting_id");
}
if (!table_has_column($conn, 'home_popup_items', 'sort_order')) {
    $conn->query("ALTER TABLE home_popup_items ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_enabled");
}

$resInit = $conn->query("SELECT id FROM home_popup_settings WHERE id = 1 LIMIT 1");
if (!$resInit || $resInit->num_rows === 0) {
    $conn->query("INSERT INTO home_popup_settings (id, is_enabled, title, subtitle, image_base64, mime_type) VALUES (1, 0, '', '', NULL, NULL)");
}

// Backward compatibility: migrate legacy single popup data into first item.
$legacyRes = $conn->query("SELECT title, subtitle, image_base64, mime_type FROM home_popup_settings WHERE id = 1 LIMIT 1");
$legacyRow = $legacyRes ? $legacyRes->fetch_assoc() : null;
$itemCountRes = $conn->query("SELECT COUNT(*) AS total FROM home_popup_items WHERE setting_id = 1");
$itemCount = 0;
if ($itemCountRes && ($countRow = $itemCountRes->fetch_assoc())) {
    $itemCount = (int) ($countRow['total'] ?? 0);
}
if ($itemCount === 0 && is_array($legacyRow)) {
    $legacyTitle = trim((string) ($legacyRow['title'] ?? ''));
    $legacySubtitle = trim((string) ($legacyRow['subtitle'] ?? ''));
    $legacyImage = trim((string) ($legacyRow['image_base64'] ?? ''));
    if ($legacyTitle !== '' || $legacySubtitle !== '' || $legacyImage !== '') {
        $legacyMime = trim((string) ($legacyRow['mime_type'] ?? ''));
        $stmtSeed = $conn->prepare("INSERT INTO home_popup_items (setting_id, is_enabled, sort_order, title, subtitle, image_base64, mime_type) VALUES (1, 1, 1, ?, ?, ?, ?)");
        if ($stmtSeed) {
            $stmtSeed->bind_param('ssss', $legacyTitle, $legacySubtitle, $legacyImage, $legacyMime);
            $stmtSeed->execute();
            $stmtSeed->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $titles = isset($_POST['title']) && is_array($_POST['title']) ? $_POST['title'] : [];
    $subtitles = isset($_POST['subtitle']) && is_array($_POST['subtitle']) ? $_POST['subtitle'] : [];
    $sortOrders = isset($_POST['sort_order']) && is_array($_POST['sort_order']) ? $_POST['sort_order'] : [];
    $itemIds = isset($_POST['item_id']) && is_array($_POST['item_id']) ? $_POST['item_id'] : [];
    $itemEnabled = isset($_POST['item_enabled']) && is_array($_POST['item_enabled']) ? $_POST['item_enabled'] : [];
    $removeImages = isset($_POST['remove_image']) && is_array($_POST['remove_image']) ? $_POST['remove_image'] : [];

    $existingItemsById = [];
    $resExistingItems = $conn->query("SELECT id, image_base64, mime_type FROM home_popup_items WHERE setting_id = 1");
    if ($resExistingItems) {
        while ($row = $resExistingItems->fetch_assoc()) {
            $existingItemsById[(int) $row['id']] = $row;
        }
    }

    $activeItemCount = 0;
    $rowsToPersist = [];

    foreach ($titles as $key => $rawTitle) {
        $title = trim((string) $rawTitle);
        $subtitle = trim((string) ($subtitles[$key] ?? ''));
        $sortOrder = (int) ($sortOrders[$key] ?? 0);
        $itemId = (int) ($itemIds[$key] ?? 0);
        $isItemEnabled = isset($itemEnabled[$key]) ? 1 : 0;
        $removeImage = isset($removeImages[$key]) ? 1 : 0;

        $existingImageBase64 = null;
        $existingMimeType = null;
        if ($itemId > 0 && isset($existingItemsById[$itemId])) {
            $existingImageBase64 = (string) ($existingItemsById[$itemId]['image_base64'] ?? '');
            $existingMimeType = (string) ($existingItemsById[$itemId]['mime_type'] ?? '');
        }

        $newImageBase64 = null;
        $newMimeType = null;
        $hasNewUpload = false;
        if (isset($_FILES['image_file']['error'][$key]) && (int) $_FILES['image_file']['error'][$key] === UPLOAD_ERR_OK) {
            $tmp = (string) ($_FILES['image_file']['tmp_name'][$key] ?? '');
            $imageInfo = @getimagesize($tmp);
            if ($imageInfo === false) {
                $_SESSION['error'] = 'File gambar tidak valid.';
                redirect_back();
            }
            $imageData = @file_get_contents($tmp);
            if ($imageData === false) {
                $_SESSION['error'] = 'Gagal membaca file gambar.';
                redirect_back();
            }
            $newImageBase64 = base64_encode($imageData);
            $newMimeType = (string) ($imageInfo['mime'] ?? 'image/jpeg');
            $hasNewUpload = true;
        }

        $imageBase64 = null;
        $mimeType = null;
        if ($hasNewUpload) {
            $imageBase64 = $newImageBase64;
            $mimeType = $newMimeType;
        } elseif ($removeImage === 1) {
            $imageBase64 = null;
            $mimeType = null;
        } else {
            $imageBase64 = $existingImageBase64;
            $mimeType = $existingMimeType;
        }

        $hasContent = ($title !== '' || $subtitle !== '' || trim((string) $imageBase64) !== '' || $itemId > 0);
        if (!$hasContent) {
            continue;
        }

        if ($isEnabled === 1 && $isItemEnabled === 1 && $title === '') {
            $_SESSION['error'] = 'Setiap item popup aktif wajib memiliki judul.';
            redirect_back();
        }

        if ($isEnabled === 1 && $isItemEnabled === 1 && $subtitle === '') {
            $_SESSION['error'] = 'Setiap item popup aktif wajib memiliki subjudul.';
            redirect_back();
        }

        if ($isItemEnabled === 1) {
            $activeItemCount++;
        }

        $rowsToPersist[] = [
            'item_id' => $itemId,
            'is_enabled' => $isItemEnabled,
            'sort_order' => $sortOrder,
            'title' => $title,
            'subtitle' => $subtitle,
            'image_base64' => $imageBase64,
            'mime_type' => $mimeType,
        ];

    }

    if ($isEnabled === 1 && $activeItemCount === 0) {
        $_SESSION['error'] = 'Minimal satu item popup harus aktif saat popup diaktifkan.';
        redirect_back();
    }

    $conn->begin_transaction();
    try {
        $stmtMain = $conn->prepare("UPDATE home_popup_settings SET is_enabled = ? WHERE id = 1");
        if (!$stmtMain) {
            throw new RuntimeException('Gagal menyiapkan penyimpanan pengaturan utama.');
        }
        $stmtMain->bind_param('i', $isEnabled);
        $stmtMain->execute();
        $stmtMain->close();

        $persistedItemIds = [];
        foreach ($rowsToPersist as $rowData) {
            $itemId = (int) $rowData['item_id'];
            $itemEnabledValue = (int) $rowData['is_enabled'];
            $sortOrderValue = (int) $rowData['sort_order'];
            $titleValue = (string) $rowData['title'];
            $subtitleValue = (string) $rowData['subtitle'];
            $imageBase64Value = $rowData['image_base64'] !== null ? (string) $rowData['image_base64'] : null;
            $mimeTypeValue = $rowData['mime_type'] !== null ? (string) $rowData['mime_type'] : null;

            if ($itemId > 0) {
                $stmtUpdateItem = $conn->prepare("UPDATE home_popup_items SET is_enabled = ?, sort_order = ?, title = ?, subtitle = ?, image_base64 = ?, mime_type = ? WHERE id = ? AND setting_id = 1");
                if (!$stmtUpdateItem) {
                    throw new RuntimeException('Gagal menyiapkan update item popup.');
                }
                $stmtUpdateItem->bind_param(
                    'iissssi',
                    $itemEnabledValue,
                    $sortOrderValue,
                    $titleValue,
                    $subtitleValue,
                    $imageBase64Value,
                    $mimeTypeValue,
                    $itemId
                );
                $stmtUpdateItem->execute();
                $stmtUpdateItem->close();
                $persistedItemIds[] = $itemId;
            } else {
                $stmtInsertItem = $conn->prepare("INSERT INTO home_popup_items (setting_id, is_enabled, sort_order, title, subtitle, image_base64, mime_type) VALUES (1, ?, ?, ?, ?, ?, ?)");
                if (!$stmtInsertItem) {
                    throw new RuntimeException('Gagal menyiapkan insert item popup.');
                }
                $stmtInsertItem->bind_param(
                    'iissss',
                    $itemEnabledValue,
                    $sortOrderValue,
                    $titleValue,
                    $subtitleValue,
                    $imageBase64Value,
                    $mimeTypeValue
                );
                $stmtInsertItem->execute();
                $persistedItemIds[] = (int) $stmtInsertItem->insert_id;
                $stmtInsertItem->close();
            }
        }

        if (!empty($persistedItemIds)) {
            $safeIds = implode(',', array_map('intval', $persistedItemIds));
            $conn->query("DELETE FROM home_popup_items WHERE setting_id = 1 AND id NOT IN ({$safeIds})");
        } else {
            $conn->query("DELETE FROM home_popup_items WHERE setting_id = 1");
        }

        $conn->commit();
        $_SESSION['success'] = 'Home popup berhasil disimpan.';
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
    }

    redirect_back();
}

$settings = [
    'is_enabled' => 0,
    'updated_at' => null,
];

$res = $conn->query("SELECT is_enabled, updated_at FROM home_popup_settings WHERE id = 1 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $settings = $row;
}

$items = [];
$resItems = $conn->query("SELECT id, is_enabled, sort_order, title, subtitle, image_base64, mime_type, updated_at FROM home_popup_items WHERE setting_id = 1 ORDER BY sort_order ASC, id ASC");
if ($resItems) {
    while ($row = $resItems->fetch_assoc()) {
        $items[] = $row;
    }
}

if (count($items) === 0) {
    $items[] = [
        'id' => 0,
        'is_enabled' => 1,
        'sort_order' => 1,
        'title' => '',
        'subtitle' => '',
        'image_base64' => null,
        'mime_type' => null,
        'updated_at' => null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Popup Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h3 class="mb-3">Home Popup Settings</h3>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form id="popup-settings-form" method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?php echo ((int)$settings['is_enabled'] === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_enabled">
                            Aktifkan popup notifikasi di Home Page
                        </label>
                    </div>
                    <div class="form-text">Popup di homepage akan auto-slide setiap 5 detik.</div>
                </div>

                <div class="col-md-6">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="add-popup-item">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Informasi Popup
                    </button>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">Urutan mengikuti posisi card dari atas ke bawah.</small>
                </div>

                <div class="col-12" id="popup-items-list">
                    <?php foreach ($items as $index => $item): ?>
                        <?php
                            $rowKey = (string) ('existing_' . ($item['id'] ?: ('new_' . $index)));
                            $itemId = (int) ($item['id'] ?? 0);
                            $itemEnabledChecked = ((int) ($item['is_enabled'] ?? 1) === 1) ? 'checked' : '';
                            $titleValue = htmlspecialchars((string) ($item['title'] ?? ''));
                            $subtitleValue = htmlspecialchars((string) ($item['subtitle'] ?? ''));
                            $sortOrderValue = (int) ($item['sort_order'] ?? ($index + 1));
                            $mimeType = htmlspecialchars((string) (($item['mime_type'] ?? '') ?: 'image/jpeg'));
                            $imageBase64 = (string) ($item['image_base64'] ?? '');
                        ?>
                        <div class="card mb-3 popup-item-card" data-row-key="<?php echo htmlspecialchars($rowKey); ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Informasi <span class="popup-item-number"><?php echo $index + 1; ?></span></h6>
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-popup-item">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>

                                <input type="hidden" name="item_id[<?php echo htmlspecialchars($rowKey); ?>]" value="<?php echo $itemId; ?>">
                                <input type="hidden" class="popup-sort-order" name="sort_order[<?php echo htmlspecialchars($rowKey); ?>]" value="<?php echo $sortOrderValue; ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Judul</label>
                                        <input type="text" maxlength="255" class="form-control" name="title[<?php echo htmlspecialchars($rowKey); ?>]" value="<?php echo $titleValue; ?>" placeholder="Contoh: Informasi Penting">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Subjudul</label>
                                        <input type="text" class="form-control" name="subtitle[<?php echo htmlspecialchars($rowKey); ?>]" value="<?php echo $subtitleValue; ?>" placeholder="Contoh: Silakan baca informasi berikutnya">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gambar Popup</label>
                                        <input type="file" class="form-control" name="image_file[<?php echo htmlspecialchars($rowKey); ?>]" accept="image/*">
                                        <div class="form-text">Upload jika ingin mengganti gambar.</div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="w-100">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="item_enabled[<?php echo htmlspecialchars($rowKey); ?>]" id="item_enabled_<?php echo htmlspecialchars($rowKey); ?>" <?php echo $itemEnabledChecked; ?>>
                                                <label class="form-check-label" for="item_enabled_<?php echo htmlspecialchars($rowKey); ?>">Aktifkan item ini</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="remove_image[<?php echo htmlspecialchars($rowKey); ?>]" id="remove_image_<?php echo htmlspecialchars($rowKey); ?>" value="1">
                                                <label class="form-check-label" for="remove_image_<?php echo htmlspecialchars($rowKey); ?>">Hapus gambar item ini</label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($imageBase64 !== ''): ?>
                                        <div class="col-12">
                                            <label class="form-label d-block">Preview gambar saat ini</label>
                                            <img src="data:<?php echo $mimeType; ?>;base64,<?php echo htmlspecialchars($imageBase64); ?>" alt="Current popup image" class="img-thumbnail" style="max-width: 240px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="col-12">
                    <div class="small text-muted">Last updated: <?php echo htmlspecialchars((string)($settings['updated_at'] ?? '-')); ?></div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const form = document.getElementById('popup-settings-form');
        const itemsList = document.getElementById('popup-items-list');
        const addBtn = document.getElementById('add-popup-item');

        function updateOrderNumbers() {
            const cards = itemsList.querySelectorAll('.popup-item-card');
            cards.forEach((card, index) => {
                const numberEl = card.querySelector('.popup-item-number');
                const sortInput = card.querySelector('.popup-sort-order');
                if (numberEl) numberEl.textContent = String(index + 1);
                if (sortInput) sortInput.value = String(index + 1);
            });
        }

        function createNewCard(rowKey) {
            const wrapper = document.createElement('div');
            wrapper.className = 'card mb-3 popup-item-card';
            wrapper.setAttribute('data-row-key', rowKey);
            wrapper.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Informasi <span class="popup-item-number"></span></h6>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-popup-item">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </div>
                    <input type="hidden" name="item_id[${rowKey}]" value="0">
                    <input type="hidden" class="popup-sort-order" name="sort_order[${rowKey}]" value="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Judul</label>
                            <input type="text" maxlength="255" class="form-control" name="title[${rowKey}]" placeholder="Contoh: Informasi Penting">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subjudul</label>
                            <input type="text" class="form-control" name="subtitle[${rowKey}]" placeholder="Contoh: Silakan baca informasi berikutnya">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gambar Popup</label>
                            <input type="file" class="form-control" name="image_file[${rowKey}]" accept="image/*">
                            <div class="form-text">Upload jika ingin mengganti gambar.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="w-100">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="item_enabled[${rowKey}]" id="item_enabled_${rowKey}" checked>
                                    <label class="form-check-label" for="item_enabled_${rowKey}">Aktifkan item ini</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_image[${rowKey}]" id="remove_image_${rowKey}" value="1">
                                    <label class="form-check-label" for="remove_image_${rowKey}">Hapus gambar item ini</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return wrapper;
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                const rowKey = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                const newCard = createNewCard(rowKey);
                itemsList.appendChild(newCard);
                updateOrderNumbers();
            });
        }

        itemsList.addEventListener('click', function (event) {
            const btn = event.target.closest('.remove-popup-item');
            if (!btn) return;
            const card = btn.closest('.popup-item-card');
            if (!card) return;
            card.remove();
            updateOrderNumbers();
        });

        if (form) {
            form.addEventListener('submit', function () {
                updateOrderNumbers();
            });
        }

        updateOrderNumbers();
    })();
</script>
</body>
</html>
<?php $conn->close(); ?>
