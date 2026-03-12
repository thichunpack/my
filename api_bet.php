<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['uid'])) {
    exit(json_encode(['st'=>'err','msg'=>'Chưa đăng nhập']));
}

$data = json_decode(file_get_contents("php://input"), true);

$uid  = (int)$_SESSION['uid'];
$amt  = (int)($data['amt'] ?? 0);
$type = trim($data['type'] ?? '');
$sid  = (int)($data['sid'] ?? 0);

if ($amt <= 0 || !$type || !$sid) {
    exit(json_encode(['st'=>'err','msg'=>'Dữ liệu không hợp lệ']));
}

/* ⛔ CHẶN 10 GIÂY CUỐI */
if ((time() % 60) >= 50) {
    exit(json_encode(['st'=>'err','msg'=>'Phiên đã khoá']));
}

/* ✅ XÁC ĐỊNH CỬA */
$betType = (stripos($type, 'TĂNG') !== false) ? 'TĂNG' : 'GIẢM';

try {
    $db->beginTransaction();

    /* 1️⃣ LẤY SỐ DƯ */
    $stmt = $db->prepare("SELECT balance FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $balance = (float)$stmt->fetchColumn();

    if ($balance < $amt) {
        $db->rollBack();
        exit(json_encode(['st'=>'err','msg'=>'Số dư không đủ']));
    }

    /* 2️⃣ KIỂM TRA ĐÃ CƯỢC PHIÊN NÀY CHƯA */
    $stmt = $db->prepare("
        SELECT id 
        FROM bets 
        WHERE user_id=? 
          AND session_id=? 
          AND bet_type=? 
          AND status='pending'
        LIMIT 1
    ");
    $stmt->execute([$uid,$sid,$betType]);
    $betId = $stmt->fetchColumn();

    if ($betId) {
        /* 👉 GỘP TIỀN */
        $stmt = $db->prepare("
            UPDATE bets 
            SET amount = amount + ? 
            WHERE id=?
        ");
        $stmt->execute([$amt,$betId]);
    } else {
        /* 👉 TẠO CƯỢC MỚI */
        $stmt = $db->prepare("
            INSERT INTO bets (user_id, session_id, bet_type, amount, status, created_at)
            VALUES (?,?,?,?, 'pending', ?)
        ");
        $stmt->execute([$uid,$sid,$betType,$amt,time()]);
    }

    /* 3️⃣ TRỪ TIỀN */
    $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id=?");
    $stmt->execute([$amt,$uid]);

    /* 4️⃣ LẤY BALANCE MỚI */
    $stmt = $db->prepare("SELECT balance FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $newBal = (float)$stmt->fetchColumn();

    $db->commit();

    echo json_encode([
    'st'         => 'ok',
    'new'        => $newBal,        // ❗ số, KHÔNG format
    'session_id' => $sid,
    'amount'     => $amt,
    'bet_type'   => $betType
]);

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode([
        'st'=>'err',
        'msg'=>'Lỗi hệ thống',
        'debug'=>$e->getMessage() // ❗ xoá khi LIVE
    ]);
}