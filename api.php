<?php
// api.php
// Simple JSON API for the ticketing system
header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'localhost';
$DB_NAME = 'bfd_ticketing_system';
$DB_USER = 'root';
$DB_PASS = ''; // set your password

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Allowed file upload size & types (adjust as needed)
$maxUploadSize = 5 * 1024 * 1024; // 5MB
$allowedExt = ['png','jpg','jpeg','pdf','zip','txt','log'];

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error'=>'DB connection failed','details'=> $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? 'list';

function jsonResponse($data){
    echo json_encode($data);
    exit;
}

if ($action === 'list') {
    // optional filters: q, status, department
    $q = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';
    $dept = $_GET['department'] ?? '';

    $sql = "SELECT * FROM tickets WHERE 1=1";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (title LIKE :q OR description LIKE :q OR github_url LIKE :q)";
        $params[':q'] = "%$q%";
    }
    if ($status !== '') {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    if ($dept !== '') {
        $sql .= " AND department = :dept";
        $params[':dept'] = $dept;
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse($rows);
}

if ($action === 'create') {
    // We support multipart/form-data (file upload) and normal POST
    $title = $_POST['title'] ?? '';
    $department = $_POST['department'] ?? '';
    $description = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? 'Medium';
    $end_at = $_POST['end_at'] ?? null; // expects YYYY-MM-DD or YYYY-MM-DD HH:MM
    $github_url = $_POST['github_url'] ?? null;

    if (trim($title) === '' || trim($department) === '') {
        http_response_code(400);
        jsonResponse(['error'=>'title and department required']);
    }

    // handle file upload if present
    $attachmentFilename = null;
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['attachment'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            jsonResponse(['error'=>'Upload error']);
        }
        if ($f['size'] > $GLOBALS['maxUploadSize']) {
            http_response_code(400);
            jsonResponse(['error'=>'File too large']);
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $GLOBALS['allowedExt'])) {
            http_response_code(400);
            jsonResponse(['error'=>'File type not allowed']);
        }
        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $GLOBALS['uploadDir'] . $safeName;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            http_response_code(500);
            jsonResponse(['error'=>'Failed to move uploaded file']);
        }
        $attachmentFilename = $safeName;
    }

    $sql = "INSERT INTO tickets (title, department, description, priority, end_at, github_url, attachment)
            VALUES (:title, :department, :description, :priority, :end_at, :github_url, :attachment)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':department' => $department,
        ':description' => $description,
        ':priority' => $priority,
        ':end_at' => $end_at ?: null,
        ':github_url' => $github_url ?: null,
        ':attachment' => $attachmentFilename
    ]);
    jsonResponse(['ok'=>true, 'id' => $pdo->lastInsertId()]);
}

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); jsonResponse(['error'=>'invalid id']); }
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    jsonResponse($row ?: []);
}

if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); jsonResponse(['error'=>'invalid id']); }

    $title = $_POST['title'] ?? '';
    $department = $_POST['department'] ?? '';
    $description = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? 'Medium';
    $status = $_POST['status'] ?? 'Open';
    $end_at = $_POST['end_at'] ?? null;
    $github_url = $_POST['github_url'] ?? null;

    // file upload handling (optional replace)
    $attachmentFilename = null;
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['attachment'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            jsonResponse(['error'=>'Upload error']);
        }
        if ($f['size'] > $GLOBALS['maxUploadSize']) {
            http_response_code(400);
            jsonResponse(['error'=>'File too large']);
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $GLOBALS['allowedExt'])) {
            http_response_code(400);
            jsonResponse(['error'=>'File type not allowed']);
        }
        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $GLOBALS['uploadDir'] . $safeName;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            http_response_code(500);
            jsonResponse(['error'=>'Failed to move uploaded file']);
        }
        $attachmentFilename = $safeName;
        // optionally delete previous attachment
        $prev = $pdo->prepare("SELECT attachment FROM tickets WHERE id = :id");
        $prev->execute([':id'=>$id]);
        $p = $prev->fetch(PDO::FETCH_ASSOC);
        if ($p && $p['attachment']) {
            @unlink($GLOBALS['uploadDir'] . $p['attachment']);
        }
    }

    $sql = "UPDATE tickets SET title=:title, department=:department, description=:description,
            priority=:priority, status=:status, end_at=:end_at, github_url=:github_url";
    if ($attachmentFilename !== null) $sql .= ", attachment=:attachment";
    $sql .= " WHERE id = :id";

    $params = [
        ':title'=>$title, ':department'=>$department, ':description'=>$description,
        ':priority'=>$priority, ':status'=>$status, ':end_at'=>$end_at ?: null,
        ':github_url'=>$github_url ?: null, ':id'=>$id
    ];
    if ($attachmentFilename !== null) $params[':attachment'] = $attachmentFilename;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['ok'=>true]);
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); jsonResponse(['error'=>'invalid id']); }
    // delete attachment
    $stmt = $pdo->prepare("SELECT attachment FROM tickets WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && $r['attachment']) {
        @unlink($uploadDir . $r['attachment']);
    }
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    jsonResponse(['ok'=>true]);
}

if ($action === 'download') {
    // serve attachments securely
    $file = $_GET['file'] ?? '';
    $path = realpath($uploadDir . '/' . basename($file));
    if (!$path || strpos($path, realpath($uploadDir)) !== 0 || !file_exists($path)) {
        http_response_code(404);
        echo "Not found";
        exit;
    }
    // send headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($action === 'export_csv') {
    // Simple CSV export of tickets
    $stmt = $pdo->query("SELECT id, title, department, priority, status, created_at, end_at, github_url, attachment FROM tickets ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tickets_export.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, array_keys($rows[0] ?? ['id','title','department','priority','status','created_at','end_at','github_url','attachment']));
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

http_response_code(400);
jsonResponse(['error'=>'unknown action']);
