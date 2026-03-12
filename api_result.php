<?php
require 'config.php';
check(); // check login

header('Content-Type: application/json');

$uid = $_SESSION['uid'] ?? 0;
$sid = intval($_GET['sid'] ?? 0);

if (!$uid || !$sid) {
    echo json_encode(['st'=>'err','msg'=>'Thiếu dữ liệu']);
    exit;
}

$current_sid = floor(time() / 60);
if ($sid >= $current_sid) {
    echo json_encode(['st'=>'wait']);
    exit;
}

/* ================= LẤY KẾT QUẢ PHIÊN ================= */
$stmt = $db->prepare("SELECT result FROM results WHERE session_id = ?");
$stmt->execute([$sid]);
$resultRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resultRow) {
    echo json_encode(['st'=>'wait']); // chưa có kết quả
    exit;
}

$finalResult = $resultRow['result']; // up / down

/* ================= LẤY CƯỢC USER ================= */
$stmt = $db->prepare("
    SELECT amount, type, status 
    FROM bets 
    WHERE user_id = ? AND session_id = ?
    LIMIT 1
");
$stmt->execute([$uid, $sid]);
$bet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bet) {
    echo json_encode([
        'st'  => 'ok',
        'bet' => false
    ]);
    exit;
}

/* ================= TÍNH THẮNG / THUA ================= */
$amount = (float)$bet['amount'];
$type   = $bet['type']; // TĂNG 90% | GIẢM 90%

$isWin = (
    ($finalResult === 'up'   && strpos($type,'TĂNG') !== false) ||
    ($finalResult === 'down' && strpos($type,'GIẢM') !== false)
);

if ($isWin) {
    $status = 'win';
    $profit = round($amount * 0.9);
} else {
    $status = 'loss';
    $profit = -$amount;
}

/* ================= UPDATE BET (CHỈ 1 LẦN) ================= */
if ($bet['status'] === 'pending') {

    $db->beginTransaction();

    try {
        // update bet
        $stmt = $db->prepare("
            UPDATE bets 
            SET status = ?, profit = ? 
            WHERE user_id = ? AND session_id = ?
        ");
        $stmt->execute([$status, $profit, $uid, $sid]);

        // update balance nếu thắng
        if ($isWin) {
            $stmt = $db->prepare("
                UPDATE users 
                SET balance = balance + ?
                WHERE id = ?
            ");
            $stmt->execute([$profit, $uid]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['st'=>'err','msg'=>'DB error']);
        exit;
    }
}

/* ================= RESPONSE ================= */
echo json_encode([
    'st'     => 'ok',
    'bet'    => true,
    'status' => $status,
    'profit' => abs($profit)
]);