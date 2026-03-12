<?php
include 'config.php';
check();
$uid = $_SESSION['uid'];
$msg = "";

// 1. Lấy thông tin User & Cài đặt Admin
$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$uid]);
$u = $user->fetch();
$bank_setting = $db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$has_bank = !empty($u['bank_number']);
$has_cccd = !empty($u['cccd_front']);

// 2. Xử lý Lệnh NẠP
if(isset($_POST['confirm_deposit'])) {
    $amount = floor(floatval($_POST['deposit_amount']));
    if($amount >= 10000) {
        $db->prepare("INSERT INTO transactions (user_id, type, amount, info, status) VALUES (?, 'deposit', ?, ?, 'pending')")
           ->execute([$uid, $amount, "Nạp tiền qua ngân hàng"]);
        $msg = "✅ Lệnh nạp " . number_format($amount) . "đ đang chờ duyệt!";
    } else { $msg = "❌ Số tiền tối thiểu là 10.000"; }
}

// 3. Xử lý Lệnh RÚT
if(isset($_POST['confirm_withdraw'])) {
    $amount = floor(floatval($_POST['withdraw_amount']));
    $b_name = $has_bank ? $u['bank_name'] : strtoupper(trim($_POST['bank_name'] ?? ''));
    $b_num  = $has_bank ? $u['bank_number'] : trim($_POST['bank_number'] ?? '');
    $b_user = $has_bank ? $u['bank_user'] : strtoupper(trim($_POST['bank_user_name'] ?? ''));
    
    $front = $u['cccd_front']; $back  = $u['cccd_back'];

    if (!$has_cccd) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        if (!empty($_FILES["cccd_f"]["name"]) && !empty($_FILES["cccd_b"]["name"])) {
            $front = $target_dir . time() . "_f_" . basename($_FILES["cccd_f"]["name"]);
            $back = $target_dir . time() . "_b_" . basename($_FILES["cccd_b"]["name"]);
            move_uploaded_file($_FILES["cccd_f"]["tmp_name"], $front);
            move_uploaded_file($_FILES["cccd_b"]["tmp_name"], $back);
        } else { $msg = "❌ Vui lòng tải lên ảnh CCCD!"; }
    }

    if(empty($msg)) {
        if($amount <= $u['balance'] && $amount >= 50000) {
            if (!$has_bank || !$has_cccd) {
                $db->prepare("UPDATE users SET bank_name=?, bank_number=?, bank_user=?, cccd_front=?, cccd_back=? WHERE id=?")
                   ->execute([$b_name, $b_num, $b_user, $front, $back, $uid]);
            }
            $info = "Rút về: $b_name | STK: $b_num | Tên: $b_user";
            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $uid]);
            $db->prepare("INSERT INTO transactions (user_id, type, amount, info, status) VALUES (?, 'withdraw', ?, ?, 'pending')")
               ->execute([$uid, $amount, $info]);
            header("Location: wallet.php?msg=" . urlencode("✅ Lệnh rút thành công!")); exit;
        } else { $msg = "❌ Số dư không đủ hoặc tối thiểu 50K!"; }
    }
}
if(isset($_GET['msg'])) $msg = $_GET['msg'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Ví Điện Tử - Mycsingtrade</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
<style>
:root { 
    --bg: #f2f5f8; 
    --card: #ffffff; 
    --border: #e2e8f0; 
    --text: #1e293b; 
    --blue: #3a7afe; 
    --green: #10b981; 
    --red: #ef4444; 
    --gray: #64748b; 
}

/* GLOBAL */
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body { margin: 0; background: var(--bg); color: var(--text); font-family: -apple-system, sans-serif; }

/* TOPBAR */
.topbar {
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    padding: calc(12px + env(safe-area-inset-top)) 15px 12px;
    background: #fff; 
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 1001;
}
.menu-btn { font-size: 24px; cursor: pointer; color: var(--text); }
.menu-dropdown {
    position: absolute; top: 70px; right: 15px; width: 220px;
    background: #fff; border: 1px solid var(--border);
    border-radius: 15px; display: none; z-index: 1002; overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.menu-dropdown div { padding: 15px; border-bottom: 1px solid var(--border); cursor: pointer; font-size: 14px; font-weight: 500; }
.menu-dropdown div:active { background: #f8fafc; }

/* CONTAINER */
.container { padding: 15px; max-width: 500px; margin: auto; }

/* BALANCE CARD */
.balance-card { background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--border); text-align: center; margin-bottom: 25px; }
.balance-card b { font-size: 30px; color: var(--blue); display: block; margin-top: 5px; }

/* TABS */
.tabs { display: flex; background: #e2e8f0; padding: 4px; border-radius: 12px; margin-bottom: 20px; }
.tab { flex: 1; padding: 10px; text-align: center; border-radius: 10px; cursor: pointer; font-weight: bold; font-size: 13px; color: var(--gray); }
.tab.active { background: #fff; color: var(--blue); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.content { display: none; }
.content.active { display: block; }

/* BANK INFO BOX */
.bank-info-box { background: #f8fafc; border: 1px solid var(--border); border-radius: 15px; padding: 15px; margin-bottom: 20px; font-size: 14px; }
.bank-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
.group-label { font-size: 11px; font-weight: 700; color: var(--gray); margin: 15px 0 5px; display: block; text-transform: uppercase; }
input { width: 100%; padding: 14px; background: #fff; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; font-size: 15px; outline: none; }
input:read-only { background: #f1f5f9; color: var(--gray); cursor: not-allowed; border-style: dashed; }

/* BUTTONS */
.btn { width: 100%; padding: 16px; border: none; border-radius: 12px; font-weight: 800; font-size: 15px; cursor: pointer; }
.btn-blue { background: var(--blue); color: #fff; }
.btn-green { background: var(--green); color: #fff; }

/* OVERLAY & POPUP */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.popup { background: #fff; padding: 25px; border-radius: 20px; width: 85%; max-width: 320px; text-align: center; }

/* TOAST */
#toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 12px 20px; border-radius: 25px; font-weight: 700; display: none; z-index: 3000; color: #fff; }

/* NOTICE */
.notice { font-size: 12px; color: var(--red); margin-top: -5px; margin-bottom: 10px; }

/* ITEM CARD */
.item-card { background: #fff; padding: 15px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.item-info b { display: block; font-size: 14px; color: #000; }
.item-info small { color: var(--gray); font-size: 11px; margin-top: 2px; display: block; }
.amount { font-weight: 800; font-size: 15px; text-align: right; }
.win { color: var(--green); }
.lose { color: var(--red); }
.status-tag { font-size: 10px; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; font-weight: 800; margin-top: 6px; display: inline-block; }
.bg-green { background: rgba(16, 185, 129, 0.1); color: var(--green); }
.bg-red { background: rgba(239, 68, 68, 0.1); color: var(--red); }
.bg-gray { background: #eee; color: #888; }

/* ALERT BOX */
.alert-box {
    border: 2px solid #f39c12;
    background-color: #fff3e0;
    padding: 20px;
    border-radius: 8px;
    max-width: 500px;
    font-family: Arial, sans-serif;
    color: #333;
    margin: 20px auto;
}
.alert-box h2 { color: #d35400; }
.alert-box ul { padding-left: 20px; }
</style>

</head>
<body>

<div id="toast"></div>

<div class="topbar">
    <div style="line-height: 1.2;">
        <div><b>VÍ ĐIỆN TỬ</b><br><small style="color:var(--gray)">TÊN TÀI KHOẢN: <?=strtoupper($u['username'])?></small></div>
        
    </div>
    <div class="menu-btn" onclick="toggleMenu()">☰</div>
    <div id="menuDropdown" class="menu-dropdown">
        <div onclick="location.href='/'" style="color: var(--blue);">TRANG CHỦ</div>
        <div onclick="location.href='/trade'" style="color:var(--down)">GIAO DỊCH</div>
        <div onclick="location.href='/naprut'">NẠP / RÚT TIỀN</div>
        <div onclick="location.href='/lichsugiaodich'">LỊCH SỬ GIAO DỊCH</div>
        <?php if($u_role == 'admin'): ?>
        <div onclick="location.href='/quanly'" style="color: var(--green);">QUẢN TRỊ VIÊN</div>
        <?php endif; ?>
        <div onclick="location.href='/thoat'" style="color: var(--red);">ĐĂNG XUẤT</div>
    </div>
</div>

<div class="container">
    <div class="balance-card">
        <span>Số dư khả dụng</span>
        <b><?= number_format($u['balance']) ?>đ</b>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('nap', this)">NẠP TIỀN</div>
        <div class="tab" onclick="switchTab('rut', this)">RÚT TIỀN</div>
    </div>

    <div id="nap" class="content active">
        <div class="bank-info-box">
            <div class="bank-row"><span>Ngân hàng:</span> <b><?= $bank_setting['bank_name'] ?? 'BANK' ?></b></div>
            <div class="bank-row"><span>Số tài khoản:</span> <b style="color:var(--blue)"><?= $bank_setting['bank_number'] ?? '000' ?></b></div>
            <div class="bank-row"><span>Chủ thẻ:</span> <b><?= $bank_setting['bank_user'] ?? 'ADMIN' ?></b></div>
            <div class="bank-row"><span>Nội dung:</span> <b style="color:var(--green)">NAP <?= $u['username'] ?></b></div>
        </div>
        <input type="number" id="inp_nap" placeholder="Nhập số tiền muốn nạp">
        <button class="btn btn-blue" onclick="openPop('nap')">NẠP TIỀN</button>
    </div>

    <div id="rut" class="content">
        <form method="POST" enctype="multipart/form-data" id="form_rut">
            <span class="group-label">Số tiền rút</span>
            <input type="number" name="withdraw_amount" id="inp_rut" placeholder="Tối thiểu 50,000" required>
            
            <span class="group-label">Thông tin ngân hàng nhận</span>
            <?php if($has_bank): ?>
                <input type="text" value="<?= $u['bank_name'] ?>" readonly>
                <input type="text" value="<?= $u['bank_number'] ?>" readonly>
                <input type="text" value="<?= $u['bank_user'] ?>" readonly>
                <div class="notice">⚠️ Liên hệ CSKH để thay đổi thông tin ngân hàng.</div>
            <?php else: ?>
                <input type="text" name="bank_name" id="b_name" placeholder="Tên ngân hàng" oninput="this.value = this.value.toUpperCase()" required>
                <input type="text" name="bank_number" id="b_num" placeholder="Số tài khoản" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                <input type="text" name="bank_user_name" id="b_user" placeholder="Họ tên chủ thẻ" oninput="validateName(this)" required>
            <?php endif; ?>

            <?php if (!$has_cccd): ?>
                <span class="group-label">Xác minh danh tính (CCCD)</span>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div><small>Mặt trước</small><input type="file" name="cccd_f" accept="image/*" required></div>
                    <div><small>Mặt sau</small><input type="file" name="cccd_b" accept="image/*" required></div>
                </div>
            <?php else: ?>
                <div style="padding:15px; background:#eef2ff; color:var(--blue); border-radius:12px; font-size:13px; text-align:center; margin-bottom:15px; border:1px solid #c7d2fe;">
                    ✅ Tài khoản đã xác minh danh tính
                </div>
            <?php endif; ?>
                     <button type="button" class="btn btn-green" onclick="openPop('rut')"> RÚT TIỀN</button>
            <div class="box">
  <ul>
    <li><strong>Tài khoản cần xác minh, nhập đúng thông tin ngân hàng chính chủ để đảm bảo không rủi ro trong quá trình xử lý rút tiền.</strong></li>
    <li><strong>Mỗi tài khoản sàn giao dịch chỉ được liên kết duy nhất 1 tài khoản ngân hàng.</strong> </li>
    <li><strong>Phí xử lý rút tiền 5% tổng lệnh rút tiền đầu tư.</strong> </li>
    <li><strong>Liên hệ DVKH ngay nếu gặp khó khăn trong quá trình giao dịch.</strong> </li>
  </ul>
</div>

   

            <div class="overlay" id="popRut">
                <div class="popup">
                    <h3>XÁC NHẬN RÚT</h3>
                    <h2 id="show_amt_rut" style="color:var(--blue);">0</h2>
                    <button type="submit" name="confirm_withdraw" class="btn btn-blue">XÁC NHẬN</button>
                    <p onclick="closePop()" style="margin-top:15px; color:var(--gray); cursor:pointer;">Hủy bỏ</p>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="popNap">
    <div class="popup">
        <h3>LỆNH NẠP TIỀN</h3>
        <h2 id="show_amt_nap" style="color:var(--blue);">0</h2>
        <form method="POST">
            <input type="hidden" name="deposit_amount" id="hid_nap">
            <button type="submit" name="confirm_deposit" class="btn btn-blue">GỬI YÊU CẦU</button>
        </form>
        <p onclick="closePop()" style="margin-top:15px; color:var(--gray); cursor:pointer;">Hủy bỏ</p>
    </div>
</div>

<script>
    // Tự động viết hoa và ngăn nhập số cho tên chủ tài khoản
    function validateName(el) {
        el.value = el.value.toUpperCase();
        if (/[0-9]/.test(el.value)) {
            el.value = el.value.replace(/[0-9]/g, '');
            showToast("❌ Tên không được chứa chữ số!");
        }
    }

    function switchTab(id, el) {
        document.querySelectorAll('.content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        el.classList.add('active');
    }

    function openPop(type) {
        if(type === 'nap') {
            let amt = parseInt(document.getElementById('inp_nap').value) || 0;
            if(amt < 10000) return alert("Tối thiểu nạp 10,000đ");
            document.getElementById('show_amt_nap').innerText = amt.toLocaleString() + "đ";
            document.getElementById('hid_nap').value = amt;
            document.getElementById('popNap').style.display = 'flex';
        } else {
            let amt = parseInt(document.getElementById('inp_rut').value) || 0;
            if(amt < 50000) return alert("Tối thiểu rút 50,000đ");
            
            // Kiểm tra thông tin bank lần cuối trước khi mở popup
            <?php if(!$has_bank): ?>
                let bName = document.getElementById('b_name').value;
                let bNum = document.getElementById('b_num').value;
                let bUser = document.getElementById('b_user').value;
                if(!bName || !bNum || !bUser) return alert("Vui lòng nhập đủ thông tin ngân hàng!");
            <?php endif; ?>

            document.getElementById('show_amt_rut').innerText = amt.toLocaleString() + "đ";
            document.getElementById('popRut').style.display = 'flex';
        }
    }

    function closePop() { document.querySelectorAll('.overlay').forEach(o => o.style.display = 'none'); }

    function showToast(m) {
        const t = document.getElementById('toast');
        t.innerText = m;
        t.style.background = m.includes('✅') ? 'var(--green)' : 'var(--red)';
        t.style.display = 'block';
        setTimeout(() => t.style.display='none', 3000);
    }
    <?php if($msg) echo "showToast('$msg');"; ?>
</script>

<script>
    function toggleMenu() {
        const m = document.getElementById('menuDropdown');
        m.style.display = m.style.display === 'block' ? 'none' : 'block';
    }

    // Đóng menu khi click ra ngoài
    window.onclick = function(e) {
        if (!e.target.matches('.menu-btn')) {
            const m = document.getElementById('menuDropdown');
            if (m) m.style.display = 'none';
        }
    }

    function switchTab(id, el) {
        document.querySelectorAll('.content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        el.classList.add('active');
    }
</script>

</body>
</html>
