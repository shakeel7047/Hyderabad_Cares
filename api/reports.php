<?php
/**
 * Reports API.
 *
 *   GET  api/reports.php?category=roads&area=Latifabad&status=Reported&limit=50
 *   POST api/reports.php   (multipart/form-data, same fields as the form)
 *
 * Public responses never include reporter_name, contact, or ip_hash.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    handle_list();
}

if ($method === 'POST') {
    handle_create();
}

json_out(['ok' => false, 'error' => 'Method not allowed.'], 405);

/* ------------------------------------------------------------------ */

function handle_list(): void
{
    $where = [];
    $params = [];

    $category = $_GET['category'] ?? '';
    if ($category !== '' && in_array($category, ALLOWED_CATEGORIES, true)) {
        $where[] = 'category = :category';
        $params[':category'] = $category;
    }

    $area = $_GET['area'] ?? '';
    if ($area !== '' && in_array($area, ALLOWED_AREAS, true)) {
        $where[] = 'area = :area';
        $params[':area'] = $area;
    }

    $status = $_GET['status'] ?? '';
    if ($status !== '' && in_array($status, ALLOWED_STATUSES, true)) {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    $limit = (int) ($_GET['limit'] ?? 50);
    $limit = max(1, min(200, $limit));

    $sql = "SELECT public_id, category, area, landmark, title, details, photo, status,
                   DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at
            FROM reports";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['photo_url'] = $row['photo'] ? UPLOAD_URL . '/' . rawurlencode($row['photo']) : null;
            unset($row['photo']);
        }
        unset($row);

        json_out(['ok' => true, 'count' => count($rows), 'reports' => $rows]);
    } catch (Throwable $e) {
        error_log('reports list failed: ' . $e->getMessage());
        fail('Could not load reports.', 500);
    }
}

/* ------------------------------------------------------------------ */

function handle_create(): void
{
    // Honeypot: real browsers leave this empty.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        fail('Submission rejected.', 422);
    }

    if (empty($_POST['consent'])) {
        fail('Please confirm that the report is accurate.', 422);
    }

    $category = clean_text($_POST['category'] ?? '', 32);
    if (!in_array($category, ALLOWED_CATEGORIES, true)) {
        fail('Please choose a valid category.', 422);
    }

    $area = clean_text($_POST['area'] ?? '', 64);
    if (!in_array($area, ALLOWED_AREAS, true)) {
        fail('Please choose a valid area.', 422);
    }

    $landmark = clean_text($_POST['landmark'] ?? '', 160);
    $title = clean_text($_POST['title'] ?? '', 120);
    $details = clean_text($_POST['details'] ?? '', 2000);
    $name = clean_text($_POST['name'] ?? '', 80);
    $contact = clean_text($_POST['contact'] ?? '', 120);

    if (mb_strlen($landmark) < 3) {
        fail('Please add the nearest landmark.', 422);
    }
    if (mb_strlen($title) < 5) {
        fail('Please add a short title.', 422);
    }
    if (mb_strlen($details) < 15) {
        fail('Please describe the problem in a little more detail.', 422);
    }

    $pdo = db();

    // Light throttle: max 5 reports per IP per 10 minutes.
    try {
        $ipHash = client_ip_hash();
        $throttle = $pdo->prepare(
            'SELECT COUNT(*) FROM reports WHERE ip_hash = :ip AND created_at > (NOW() - INTERVAL 10 MINUTE)'
        );
        $throttle->execute([':ip' => $ipHash]);
        if ((int) $throttle->fetchColumn() >= MAX_REPORTS_PER_10_MIN) {
            fail('Too many reports from this connection. Please try again later.', 429);
        }
    } catch (Throwable $e) {
        error_log('throttle check failed: ' . $e->getMessage());
        $ipHash = null;
    }

    $photoName = null;
    if (!empty($_FILES['photo']['name'])) {
        $photoName = store_photo($_FILES['photo']);
    }

    $publicId = null;
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $candidate = 'FMH-' . date('Y') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $check = $pdo->prepare('SELECT 1 FROM reports WHERE public_id = :pid');
        $check->execute([':pid' => $candidate]);
        if (!$check->fetchColumn()) {
            $publicId = $candidate;
            break;
        }
    }

    if ($publicId === null) {
        if ($photoName) {
            @unlink(UPLOAD_DIR . '/' . $photoName);
        }
        fail('Could not create a report ID. Please try again.', 500);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO reports
                (public_id, category, area, landmark, title, details, photo, reporter_name, contact, status, ip_hash)
             VALUES
                (:public_id, :category, :area, :landmark, :title, :details, :photo, :name, :contact, \'Reported\', :ip)'
        );
        $stmt->execute([
            ':public_id' => $publicId,
            ':category' => $category,
            ':area' => $area,
            ':landmark' => $landmark,
            ':title' => $title,
            ':details' => $details,
            ':photo' => $photoName,
            ':name' => $name !== '' ? $name : null,
            ':contact' => $contact !== '' ? $contact : null,
            ':ip' => $ipHash,
        ]);

        json_out([
            'ok' => true,
            'public_id' => $publicId,
            'status' => 'Reported',
            'message' => 'Report saved. It is now on the map.',
        ], 201);
    } catch (Throwable $e) {
        if ($photoName) {
            @unlink(UPLOAD_DIR . '/' . $photoName);
        }
        error_log('report insert failed: ' . $e->getMessage());
        fail('Could not save the report right now. Please try again.', 500);
    }
}

/* ------------------------------------------------------------------ */

/**
 * Validate and move an uploaded photo. Returns the stored filename.
 */
function store_photo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        fail('The photo could not be uploaded. Please try a smaller image.', 422);
    }

    if (($file['size'] ?? 0) > MAX_PHOTO_BYTES) {
        fail('The photo is too large. Maximum size is 5 MB.', 422);
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        fail('Invalid photo upload.', 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        fail('Please upload a JPG, PNG, or WebP image.', 422);
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
        fail('Upload folder is not writable.', 500);
    }

    $name = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $target = UPLOAD_DIR . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        fail('Could not store the photo.', 500);
    }

    @chmod($target, 0644);

    return $name;
}
