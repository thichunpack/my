<?php
include 'config.php';
header('Content-Type: application/json');

$current_sid = floor(time() / 60);

// Lấy danh sách cược phiên hiện tại
$stmt = $db->prepare("SELECT b.amount, b.bet_type, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.session_id = ? ORDER BY b.id DESC");
$stmt->execute([$current_sid]);
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tính tổng tiền
$up = 0; $down = 0;
foreach($bets as $b) {
    if($b['bet_type'] == 'TĂNG') $up += $b['amount'];
    else $down += $b['amount'];
}

// Lấy 20 phiên lịch sử gần nhất (Cũ -> Mới)
$history = $db->query("SELECT session_id, result FROM results WHERE session_id < $current_sid ORDER BY session_id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$history = array_reverse($history);

echo json_encode([
    'sid' => $current_sid,
    'total_up' => $up,
    'total_down' => $down,
    'bets' => $bets,
    'history' => $history
]);
