<?php
include 'config.php';
check();
$uid = $_SESSION['uid'];

// Lấy thông tin User để hiển thị số dư và tên
$u_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$u_stmt->execute([$uid]);
$u = $u_stmt->fetch(PDO::FETCH_ASSOC);
$u_role = $u['role'] ?? 'user';

$current_sid = floor(time() / 60);

// Lấy lịch sử Giao dịch
$trans_stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30000");
$trans_stmt->execute([$uid]);
$list_trans = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy lịch sử Đặt cược
$bets_stmt = $db->prepare("
    SELECT b.*, r.result AS session_result 
    FROM bets b 
    LEFT JOIN results r ON b.session_id = r.session_id AND r.session_id < $current_sid 
    WHERE b.user_id = ? 
    ORDER BY b.id DESC LIMIT 30000
");
$bets_stmt->execute([$uid]);
$list_bets = $bets_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>Lịch sử Giao Dịch</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --bg: #05070c; --panel: #0c1220; --border: #1f2f4a; --text: #fff; --blue: #3a7afe; --green: #1adf90; --red: #ff5b5b; --gray: #64748b; --yellow: #f5c542; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: -apple-system, sans-serif; }
        
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px; background: var(--panel); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 1001;
        }
        .balance-box { background: rgba(58, 122, 254, 0.1); padding: 5px 12px; border-radius: 20px; border: 1px solid var(--blue); color: var(--yellow); font-weight: bold; }
        
        .menu-btn { font-size: 24px; cursor: pointer; color: var(--blue); }
        .menu-dropdown {
            position: absolute; top: 65px; right: 15px; width: 220px;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 12px; display: none; z-index: 1002; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .menu-dropdown div { padding: 15px; border-bottom: 1px solid var(--border); cursor: pointer; font-size: 14px; color: #fff; }

        .container { padding: 15px; max-width: 600px; margin: auto; }
        .tabs { display: flex; background: var(--panel); border: 1px solid var(--border); padding: 4px; border-radius: 12px; margin-bottom: 20px; }
        .tab { flex: 1; padding: 12px; text-align: center; border-radius: 10px; cursor: pointer; font-weight: bold; font-size: 13px; color: var(--gray); }
        .tab.active { background: var(--blue); color: #fff; }

        .content { display: none; }
        .content.active { display: block; }

        .item-card { background: var(--panel); padding: 15px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .item-info b { display: block; font-size: 14px; color: var(--yellow); }
        .item-info small { color: var(--gray); font-size: 11px; }
        
        .status-tag { font-size: 10px; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; font-weight: 800; margin-top: 6px; display: inline-block; }
        .bg-green { background: rgba(26, 223, 144, 0.1); color: var(--green); }
        .bg-red { background: rgba(255, 91, 91, 0.1); color: var(--red); }
        .bg-blue { background: rgba(58, 122, 254, 0.1); color: var(--blue); }
    </style>
</head>
<body>

<div class="topbar">
    <div>
        <small style="color: var(--gray); font-size: 10px;">XIN CHÀO, <?= strtoupper($u['username']) ?></small><br>
        <div class="balance-box" id="userBalance"><?= number_format($u['balance']) ?>đ</div>
    </div>
    <div class="menu-btn" onclick="toggleMenu()">☰</div>
    <div id="menuDropdown" class="menu-dropdown">
        <div onclick="location.href='/'">TRANG CHỦ</div>
        <div onclick="location.href='/trade'">GIAO DỊCH</div>
        <div onclick="location.href='/naprut'">NẠP / RÚT TIỀN</div>
        <?php if($u_role == 'admin'): ?><div onclick="location.href='admin.php'" style="color: var(--green);">QUẢN TRỊ</div><?php endif; ?>
        <div onclick="location.href='/thoat'" style="color: var(--red);">ĐĂNG XUẤT</div>
    </div>
</div>

<div class="container"> 
    <div class="tabs">
        <div class="tab active" onclick="switchTab('tab-bets', this)">ĐẶT CƯỢC</div>
        <div class="tab" onclick="switchTab('tab-trans', this)">NẠP / RÚT</div>
    </div>

    <div id="tab-bets" class="content active">
        <?php foreach($list_bets as $b): 
            $status = $b['status'];
            $win_amt = $b['amount'] * 1.90; 
        ?>
        <div class="item-card">
            <div class="item-info">
                <b>ID: <?= $b['session_id'] ?> (<?= $b['bet_type'] ?>)</b>
                <small><?= date('H:i:s d/m/Y', $b['created_at']) ?></small><br>
                <div class="status-tag <?= $status=='win' ? 'bg-green' : ($status=='loss' ? 'bg-red' : 'bg-blue') ?>">
                    <?= $status == 'win' ? 'THẮNG ▲' : ($status == 'loss' ? 'THUA ▼' : 'ĐANG CHỜ..<b id="timer" style="color:red">00s</b>') ?>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 800; font-size: 16px; color: <?= $status=='win' ? 'var(--green)' : ($status=='loss' ? 'var(--red)' : '#fff') ?>">
                    <?= $status == 'win' ? '+'.number_format($win_amt) : ($status == 'loss' ? '-'.number_format($b['amount']) : number_format($b['amount'])) ?>đ
                </div>
                <small style="color: var(--gray);">Vốn: <?= number_format($b['amount']) ?>đ</small>
            </div>
        </div>
        <?php endforeach; if(!$list_bets) echo "<p style='text-align:center; color:var(--gray);'>Chưa có dữ liệu cược</p>"; ?>
    </div>

    <div id="tab-trans" class="content">
        <?php foreach($list_trans as $t): ?>
        <div class="item-card">
            <div class="item-info">
                <b><?= $t['type'] == 'deposit' ? 'Nạp tiền vào ví' : 'Rút tiền ngân hàng' ?></b>
                <small><?= $t['created_at'] ?></small><br>
                <div class="status-tag <?= $t['status']=='approved' ? 'bg-green' : ($t['status']=='pending' ? 'bg-blue' : 'bg-red') ?>">
                    <?= $t['status'] == 'approved' ? 'Thành công' : ($t['status'] == 'pending' ? 'Chờ duyệt' : 'Đã hủy') ?>
                </div>
            </div>
            <div style="font-weight: 800; font-size: 16px; color: <?= $t['type']=='deposit' ? 'var(--green)' : 'var(--red)' ?>">
                <?= ($t['type'] == 'deposit' ? '+' : '-') . number_format($t['amount']) ?>đ
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // Lưu phiên đã thông báo để tránh lặp
    let notifiedSessions = new Set();

    async function checkLastResult() {
        try {
            const res = await fetch('api_check_win.php'); // API trả JSON {hasBet, sid, status, win_amount}
            const data = await res.json();

            if (!data.hasBet) return;

            // Nếu đã thông báo rồi thì bỏ qua
            if (notifiedSessions.has(data.sid)) return;

            notifiedSessions.add(data.sid);

            if (data.status === 'win') {
                Swal.fire({
                    title: '🎉 CHÚC MỪNG!',
                    html: `Bạn đã THẮNG ID: <b>${data.sid}</b><br><span style="color:#1adf90; font-size:20px; font-weight:bold">+${Number(data.win_amount).toLocaleString('vi-VN')}đ</span>`,
                    icon: 'success',
                    background: '#0c1220',
                    color: '#fff',
                    confirmButtonColor: '#3a7afe'
                });
            } else if (data.status === 'loss') {
                Swal.fire({
                    title: 'RẤT TIẾC...',
                    text: `Phiên #${data.sid} không may mắn. Hãy thử lại!`,
                    icon: 'error',
                    background: '#0c1220',
                    color: '#fff',
                    confirmButtonColor: '#3a7afe'
                });
            }
        } catch (err) {
            console.log('API check lỗi hoặc chưa có kết quả mới.');
        }
    }

    // Check mỗi 5 giây
    setInterval(checkLastResult, 5000);

    // Menu dropdown
    function toggleMenu() {
        const m = document.getElementById('menuDropdown');
        m.style.display = m.style.display === 'block' ? 'none' : 'block';
    }
    function switchTab(id, el) {
        document.querySelectorAll('.content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        el.classList.add('active');
    }
    window.onclick = function(e) {
        if (!e.target.matches('.menu-btn')) {
            const m = document.getElementById('menuDropdown');
            if (m) m.style.display = 'none';
        }
    }
</script>
<script>
let lastSession = null;

function loadStatus(){
    fetch('api_status.php')
        .then(r => r.json())
        .then(d => {
            if(d.st !== 'ok') return;

            document.getElementById('timer').innerText = d.timer + 's';

            // Sang phiên mới → reload 1 lần
            if(lastSession !== null && d.session_id !== lastSession){
                location.reload();
            }

            lastSession = d.session_id;
        });
}

loadStatus();
setInterval(loadStatus, 1000);
</script>
</body>
</html>
