<?php
include 'config.php';
$last_sid = floor(time() / 60) - 1;

try {
    $db->beginTransaction();
    $stmt = $db->prepare("SELECT * FROM bets WHERE session_id = ? AND status = 'pending'");
    $stmt->execute([$last_sid]);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($bets) {
        // 1. Kiểm tra Admin báo trước kết quả chưa
        $res_st = $db->prepare("SELECT result FROM results WHERE session_id = ?");
        $res_st->execute([$last_sid]);
        $final = $res_st->fetchColumn();

        // 2. Nếu Admin không ép -> Tự động ép bên cược nhiều tiền hơn phải THUA
        if (!$final) {
            $sums = $db->prepare("SELECT bet_type, SUM(amount) as total FROM bets WHERE session_id = ? GROUP BY bet_type");
            $sums->execute([$last_sid]);
            $data = $sums->fetchAll(PDO::FETCH_KEY_PAIR);
            $final = (($data['TĂNG'] ?? 0) > ($data['GIẢM'] ?? 0)) ? 'GIẢM' : 'TĂNG';
            $db->prepare("INSERT OR IGNORE INTO results (session_id, result) VALUES (?,?)")->execute([$last_sid, $final]);
        }

        // 3. Trả tiền cho người thắng
        foreach ($bets as $b) {
            if ($b['bet_type'] == $final) {
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$b['amount'] * PAYOUT, $b['user_id']]);
                $db->prepare("UPDATE bets SET status = 'win' WHERE id = ?")->execute([$b['id']]);
            } else {
                $db->prepare("UPDATE bets SET status = 'loss' WHERE id = ?")->execute([$b['id']]);
            }
        }
    }
    $db->commit();
    echo "Success: $last_sid -> $final";
} catch (Exception $e) { if($db->inTransaction()) $db->rollBack(); }
