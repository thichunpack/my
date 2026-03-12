<?php
require 'config.php';
check(true); // Kiểm tra session người dùng
$uid = $_SESSION['uid'];

// 1. Lấy lệnh cược đã có kết quả (win/loss) và chưa được đọc (is_read=0)
$stmt = $db->prepare("
    SELECT b.*, r.result 
    FROM bets b 
    JOIN results r ON b.session_id = r.session_id 
    WHERE b.user_id = ? AND b.status IN ('win','loss') AND b.is_read = 0
    ORDER BY b.id ASC LIMIT 1
");
$stmt->execute([$uid]);
$bet = $stmt->fetch(PDO::FETCH_ASSOC);

if($bet){
    // 2. Đánh dấu đã đọc để không hiển thị lại thông báo này
    $update = $db->prepare("UPDATE bets SET is_read = 1 WHERE id = ?");
    $update->execute([$bet['id']]);

    // 3. Tính toán số tiền thắng (1.90 = Gốc + 90% lãi)
    $is_win = ($bet['status'] === 'win');
    $win_amount = $is_win ? ($bet['amount'] * 1.90) : 0;

    // 4. Lấy số dư mới nhất của người dùng từ DB
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $new_bal = $stmt->fetchColumn();

    // 5. Trả về JSON cho Frontend
    echo json_encode([
        'hasBet' => true,
        'sid' => $bet['session_id'],
        'status' => $bet['status'],
        'win_amount' => (float)$win_amount,
        'new_balance' => (float)$new_bal
    ]);
} else {
    echo json_encode(['hasBet' => false]);
}
?>
