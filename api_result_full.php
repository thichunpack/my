<?php
include 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['uid'])){
    echo json_encode(['hasBet'=>false]);
    exit;
}

$uid = $_SESSION['uid'];
$current_sid = floor(time()/60);

/* ==== LẤY CƯỢC PHIÊN VỪA ĐÓNG ==== */
$stmt = $db->prepare("
    SELECT b.session_id, b.amount, b.bet_type, r.result
    FROM bets b
    JOIN results r ON r.session_id = b.session_id
    WHERE b.user_id = ?
      AND b.session_id < ?
    ORDER BY b.session_id DESC
    LIMIT 1
");
$stmt->execute([$uid,$current_sid]);
$bet = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$bet){
    echo json_encode(['hasBet'=>false]);
    exit;
}

/* ==== CHỐNG BÁO TRÙNG ==== */
if(isset($_SESSION['notified_sid']) && $_SESSION['notified_sid']==$bet['session_id']){
    echo json_encode(['hasBet'=>true,'notified'=>true]);
    exit;
}
$_SESSION['notified_sid'] = $bet['session_id'];

/* ==== TÍNH KẾT QUẢ ==== */
$isWin = str_starts_with($bet['bet_type'],$bet['result']);
$winAmount = $isWin ? floor($bet['amount']*0.9) : 0;

/* ==== CẬP NHẬT BALANCE ==== */
if($isWin){
    $db->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
       ->execute([$winAmount,$uid]);
}

/* ==== BALANCE MỚI ==== */
$bal = $db->prepare("SELECT balance FROM users WHERE id=?");
$bal->execute([$uid]);
$newBal = $bal->fetchColumn();

echo json_encode([
    'hasBet'      => true,
    'sid'         => $bet['session_id'],
    'status'      => $isWin ? 'win' : 'loss',
    'win_amount'  => $winAmount,
    'bigwin'      => $winAmount >= 10000000,
    'new_balance' => $newBal
]);