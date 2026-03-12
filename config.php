<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

/* =========================
   DB CONNECT
========================= */
try {
    $db = new PDO("sqlite:" . __DIR__ . "/trade.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    /* =========================
       TABLES
    ========================= */

    /* USERS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT,
            fullname TEXT,
            phone TEXT,
            ref_code TEXT,
            balance REAL DEFAULT 0,
            role TEXT DEFAULT 'user',
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    /* BETS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS bets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            amount REAL,
            bet_type TEXT,
            session_id INTEGER,
            status TEXT DEFAULT 'pending'
        )
    ");

    /* RESULTS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS results (
            session_id INTEGER PRIMARY KEY,
            result TEXT
        )
    ");

    /* TRANSACTIONS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            type TEXT,
            amount REAL,
            info TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    /* SETTINGS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            key_value TEXT
        )
    ");

    /* ADMIN LOGS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            action TEXT,
            detail TEXT,
            ip TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    /* AUTO RESULTS */
    $db->exec("
        CREATE TABLE IF NOT EXISTS auto_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            start_sid INTEGER,
            pattern TEXT,
            pointer INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    /* =========================
       DEFAULT ADMIN
    ========================= */
    if (!$db->query("SELECT 1 FROM users WHERE username='admin'")->fetch()) {
        $db->prepare("
            INSERT INTO users (username,password,fullname,role,balance)
            VALUES ('admin', ?, 'Administrator', 'admin', 1000000)
        ")->execute([password_hash('123456', PASSWORD_DEFAULT)]);
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['st'=>'err','msg'=>'DB ERROR']);
    exit;
}

/* =========================
   ADMIN LOG
========================= */
function admin_log($db, $admin_id, $action, $detail=''){
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $db->prepare("
        INSERT INTO admin_logs (admin_id,action,detail,ip)
        VALUES (?,?,?,?)
    ")->execute([$admin_id, $action, $detail, $ip]);
}

/* =========================
   APPLY AUTO RESULT (CHUỖI)
========================= */
function apply_auto_result($db, $sid){
    $auto = $db->query("
        SELECT * FROM auto_results
        WHERE active=1
        ORDER BY id DESC LIMIT 1
    ")->fetch();

    if (!$auto) return false;

    $pattern = explode(',', $auto['pattern']);
    $ptr = (int)$auto['pointer'];

    if (!isset($pattern[$ptr])) {
        $db->prepare("UPDATE auto_results SET active=0 WHERE id=?")
           ->execute([$auto['id']]);
        return false;
    }

    $final = ($pattern[$ptr] === 'T') ? 'TĂNG' : 'GIẢM';

    $db->prepare("
        INSERT INTO results (session_id,result)
        VALUES (?,?)
    ")->execute([$sid, $final]);

    $db->prepare("
        UPDATE auto_results SET pointer=pointer+1 WHERE id=?
    ")->execute([$auto['id']]);

    admin_log($db, 0, 'AUTO_RUN', "SID:$sid → $final");

    return true;
}

/* =========================
   AUTO SETTLE (SAFE – 1 LẦN / PHIÊN)
========================= */
$current_sid = floor(time() / 60);
$settle_sid  = $current_sid - 1;

/* nếu phiên trước chưa có kết quả */
$chk = $db->prepare("SELECT 1 FROM results WHERE session_id=?");
$chk->execute([$settle_sid]);

if (!$chk->fetch()) {

    /* ƯU TIÊN AUTO */
    if (!apply_auto_result($db, $settle_sid)) {

        /* LOGIC NGƯỢC CƯỢC */
        $sum = $db->prepare("
            SELECT bet_type, SUM(amount) total
            FROM bets WHERE session_id=?
            GROUP BY bet_type
        ");
        $sum->execute([$settle_sid]);
        $s = $sum->fetchAll(PDO::FETCH_KEY_PAIR);

        $t = $s['TĂNG'] ?? 0;
        $g = $s['GIẢM'] ?? 0;

        if ($t > $g)      $final = 'GIẢM';
        elseif ($g > $t)  $final = 'TĂNG';
        else              $final = ($settle_sid % 2 === 0 ? 'TĂNG' : 'GIẢM');

        $db->prepare("
            INSERT INTO results (session_id,result)
            VALUES (?,?)
        ")->execute([$settle_sid, $final]);
    }

    /* LẤY KẾT QUẢ CHÍNH THỨC */
    $final = $db->prepare("
        SELECT result FROM results WHERE session_id=?
    ");
    $final->execute([$settle_sid]);
    $final = $final->fetchColumn();

    /* TRẢ THƯỞNG */
    $bets = $db->prepare("
        SELECT * FROM bets
        WHERE session_id=? AND status='pending'
    ");
    $bets->execute([$settle_sid]);

    foreach ($bets->fetchAll() as $b) {
        if ($b['bet_type'] === $final) {
            $db->prepare("
                UPDATE users SET balance=balance+?
                WHERE id=?
            ")->execute([$b['amount'] * 1.90, $b['user_id']]);

            $db->prepare("UPDATE bets SET status='win' WHERE id=?")
               ->execute([$b['id']]);
        } else {
            $db->prepare("UPDATE bets SET status='loss' WHERE id=?")
               ->execute([$b['id']]);
        }
    }
}

/* =========================
   AUTH CHECK
========================= */
function check($api=false){
    if (!isset($_SESSION['uid'])) {
        if ($api) {
            header('Content-Type: application/json');
            echo json_encode(['st'=>'err','msg'=>'Chưa đăng nhập']);
        } else {
            header("Location: login");
        }
        exit;
    }
}
function require_admin(PDO $db) {
    if (!isset($_SESSION['uid'])) {
        http_response_code(401);
        exit(json_encode(['st'=>'err','msg'=>'Chưa đăng nhập']));
    }

    $st = $db->prepare("SELECT role FROM users WHERE id=?");
    $st->execute([$_SESSION['uid']]);
    $r = $st->fetchColumn();

    if ($r !== 'admin') {
        http_response_code(403);
        exit(json_encode(['st'=>'err','msg'=>'Chỉ ADMIN được phép']));
    }
}