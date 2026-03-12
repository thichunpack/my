<?php
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['uid'])) {
    echo json_encode(['hasBet' => false]);
    exit;
}

$uid = $_SESSION['uid'];
$current_sid = floor(time() / 60);
$check_sid   = $current_sid - 1; // chỉ check phiên ĐÃ ĐÓNG

/* ===== LẤY BET PHIÊN GẦN NHẤT ===== */
$stmt = $db->prepare("
    SELECT id, session_id, bet_type, amount
    FROM bets
    WHERE user_id = ?
      AND session_id = ?
    LIMIT 1
");
$stmt->execute([$uid, $check_sid]);
$bet = $stmt->fetch(PDO::FETCH_ASSOC);

/* ❌ KHÔNG CƯỢC → IM LẶNG */
if (!$bet) {
    echo json_encode(['hasBet' => false]);
    exit;
}

/* ===== CHỐNG BÁO TRÙNG ===== */
if (
    isset($_SESSION['notified_sid']) &&
    $_SESSION['notified_sid'] == $check_sid
) {
    echo json_encode(['hasBet' => true, 'notified' => true]);
    exit;
}

/* ===== LẤY KẾT QUẢ PHIÊN ===== */
$stmt = $db->prepare("
    SELECT result 
    FROM results 
    WHERE session_id = ?
    LIMIT 1
");
$stmt->execute([$check_sid]);
$result = $stmt->fetchColumn();

if (!$result) {
    echo json_encode(['hasBet' => true, 'waiting' => true]);
    exit;
}

/* ===== TÍNH WIN / LOSS ===== */
$isWin = ($bet['bet_type'] === $result);
$winAmount = 0;

if ($isWin) {
    $winAmount = floor($bet['amount'] * 0.90);

    // cộng tiền
    $db->prepare("
        UPDATE users 
        SET balance = balance + ?
        WHERE id = ?
    ")->execute([$winAmount, $uid]);
}

/* ===== LẤY BALANCE MỚI ===== */
$stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$uid]);
$newBalance = $stmt->fetchColumn();

/* ===== ĐÁNH DẤU ĐÃ BÁO ===== */
$_SESSION['notified_sid'] = $check_sid;

/* ===== BIG WIN ===== */
$bigWin = ($isWin && $winAmount >= 10000000);

/* ===== RESPONSE ===== */
echo json_encode([
    'hasBet'      => true,
    'sid'         => $check_sid,
    'bet_type'    => $bet['bet_type'],
    'bet_amount'  => $bet['amount'],
    'result'      => $result,
    'status'      => $isWin ? 'win' : 'loss',
    'win_amount'  => $winAmount,
    'bigwin'      => $bigWin,
    'new_balance' => $newBalance
]);