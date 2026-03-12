<?php
include 'config.php';
check(); 

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['uid']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
$u_role = $me['role'] ?? 'user';

if ($u_role !== 'admin' && $u_role !== 'mod') die("TRUY CẬP BỊ TỪ CHỐI");
$is_admin = ($u_role == 'admin');
$msg = "";
$current_sid = floor(time() / 60);

/* ================= XỬ LÝ API POST (CHỈ ADMIN) ================= */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin) {
    
    // TAB 1: AUTO & FIX KẾT QUẢ
    if (isset($_POST['auto_on'])) {
        $db->exec("UPDATE auto_results SET active=1");
        // REMOVED RANDOM GENERATION - KHÔNG RANDOM
        // for ($i=0; $i<=20; $i++) {
        //     $sid = $current_sid + $i;
        //     $res = rand(0,1) ? 'TĂNG' : 'GIẢM';
        //     $db->prepare("REPLACE INTO results(session_id,result) VALUES(?,?)")->execute([$sid, $res]);
        // }
        $msg = "🤖 AUTO ON ! (Chế độ không random)";
    }
    if (isset($_POST['auto_off'])) {
        $db->exec("UPDATE auto_results SET active=0");
        $msg = "⛔ AUTO OFF";
    }
    if (isset($_POST['set_result'])) {
        $db->prepare("REPLACE INTO results(session_id,result) VALUES(?,?)")->execute([$_POST['sid'], $_POST['res']]);
        $msg = "✅ Đã set ID: {$_POST['sid']} ra {$_POST['res']}";
    }

    // TAB 2: CẬP NHẬT THÀNH VIÊN FULL
    if (isset($_POST['act']) && $_POST['act'] == 'update_info') {
        $db->prepare("UPDATE users SET balance=?, fullname=?, bank_name=?, bank_number=?, bank_user=?, role=? WHERE id=?")
           ->execute([$_POST['bal'], $_POST['fullname'], $_POST['b_name'], $_POST['b_num'], $_POST['b_user'], $_POST['role'], $_POST['target_uid']]);
        if(!empty($_POST['user_pass'])) $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$_POST['user_pass'], $_POST['target_uid']]);
        $msg = "✅ Đã cập nhật thành viên!";
    }

    // TAB 3: DUYỆT GIAO DỊCH
    if (isset($_POST['update_trans'])) {
        $tid = $_POST['tid']; $act = $_POST['action']; $amt = $_POST['amount']; $uid_target = $_POST['user_id'];
        if ($act === 'approve') {
            if ($_POST['type'] == 'deposit') $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amt, $uid_target]);
            $db->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")->execute([$tid]);
        } else {
            if ($_POST['type'] == 'withdraw') $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amt, $uid_target]);
            $db->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?")->execute([$tid]);
        }
        $msg = "✅ Đã xử lý lệnh giao dịch!";
    }

    // TAB 5: BANK SÀN
    if (isset($_POST['save_bank_setting'])) {
        $db->prepare("INSERT OR REPLACE INTO settings (key_name, key_value) VALUES ('bank_name', ?)")->execute([strtoupper($_POST['b_name'])]);
        $db->prepare("INSERT OR REPLACE INTO settings (key_name, key_value) VALUES ('bank_number', ?)")->execute([$_POST['b_num']]);
        $db->prepare("INSERT OR REPLACE INTO settings (key_name, key_value) VALUES ('bank_user', ?)")->execute([strtoupper($_POST['b_user'])]);
        $msg = "✅ Đã cập nhật Bank nạp!";
    }
}

/* ================= LẤY DATA ================= */
$auto_active = (int)$db->query("SELECT active FROM auto_results WHERE active=1 LIMIT 1")->fetchColumn();
$future = []; for($i=0;$i<=20;$i++) $future[] = $current_sid + $i;
$results = $db->query("SELECT session_id,result FROM results WHERE session_id >= $current_sid ORDER BY session_id ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
$live_bets = $db->query("SELECT b.*,u.username FROM bets b JOIN users u ON b.user_id=u.id ORDER BY b.id DESC LIMIT 5000")->fetchAll();
$users_list = ($is_admin) ? $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll() : [];
$pending_list = ($is_admin) ? $db->query("SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending' ORDER BY t.id DESC")->fetchAll() : [];
$bank_setting = $db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>SÀN QUẢN TRỊ ALL-IN-ONE</title>
    <style>
        :root { --bg:#05070c; --panel:#0c1220; --border:#1f2f4a; --blue:#3a7afe; --green:#1adf90; --red:#ff5b5b; --yellow:#f5c542; --gray:#64748b; }
        body { margin:0; background:var(--bg); color:#fff; font-family:sans-serif; font-size:12px; padding-bottom:30px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; padding:15px; background:var(--panel); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; }
        .tabs { display:flex; background:var(--panel); border-bottom:1px solid var(--border); overflow-x:auto; }
        .tab-item { flex-shrink:0; padding:15px 20px; text-align:center; cursor:pointer; color:var(--gray); font-weight:bold; text-transform:uppercase; font-size:10px; }
        .tab-item.active { color:var(--blue); border-bottom:3px solid var(--blue); }
        .tab-content { display:none; padding:10px; } .tab-content.active { display:block; }
        .card { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:15px; margin-bottom:10px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(105px,1fr)); gap:10px; }
        .box { background:#121a30; border-radius:10px; padding:10px; text-align:center; border:1px solid var(--border); }
        .btn { padding:10px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; color:#fff; width:100%; margin-top:5px; transition:0.2s; }
        .btn:active { opacity:0.6; }
        input, select { background:#000; border:1px solid #333; color:#fff; padding:12px; border-radius:8px; width:100%; margin-bottom:8px; box-sizing:border-box; }
        .cccd-img { width:48%; height:90px; border-radius:8px; border:1px solid var(--border); object-fit:cover; display:inline-block; }
        .up { color:var(--green); } .down { color:var(--red); }
    </style>
    
<style>
:root{
 --bg:#05070c;--panel:#0c1220;--border:#1f2f4a;
 --green:#1adf90;--red:#ff5b5b;--blue:#3a7afe;--yellow:#f5c542
}
body{margin:0;background:var(--bg);color:#fff;font-family:sans-serif;padding:15px;font-size:13px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:15px;margin-bottom:15px}
.auto-box{display:flex;justify-content:space-between;align-items:center}
.btn{padding:8px 14px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
.green{background:var(--green)} .red{background:var(--red);color:#fff}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px}
.box{background:#121a30;border-radius:10px;padding:10px;text-align:center}
.up{color:var(--green);font-weight:800}
.down{color:var(--red);font-weight:800}
.scroll{max-height:300px;overflow-y:auto}
.bet{border-bottom:1px solid #1f2f4a;padding:6px;font-size:13px}
.flash{color:var(--green);font-weight:800;margin-bottom:10px}
/* MENU */
.header{display:flex;justify-content:space-between;align-items:center;position:relative}
.menu-btn{font-size:22px;cursor:pointer;padding:6px 10px;background:#121a30;border-radius:8px}
#menuDropdown{
 display:none;position:absolute;right:0;top:45px;
 background:#0c1220;border:1px solid #1f2f4a;border-radius:10px;
 min-width:180px;overflow:hidden;z-index:99
}
.menu-item{padding:10px 14px;border-bottom:1px solid #1f2f4a;cursor:pointer}
.menu-item:hover{background:#161d31}
.menu-item:last-child{border:none}
</style>
</head>
<body>


<div class="topbar">

<div class="menu-btn" onclick="toggleMenu()">☰</div>
    <b style="color:var(--yellow)">ADMIN CONTROL</b>
    <div id="timer" style="color:var(--red); font-weight:900; font-size:18px;">00s</div>
<div id="menuDropdown">
<div class="menu-item" onclick="location.href='/'"> TRANG CHỦ</div>
<?php if(in_array($me['role'],['admin'])): ?>
<div class="menu-item" onclick="location.href='quanly'"> Admin</div>
<?php endif; ?>
<div class="menu-item" onclick="location.href='/home'">GIAO DỊCH</div>
<div class="menu-item" onclick="location.href='/thoat'" style="color:var(--red)">ĐĂNG XUẤT</div>
</div>
</div>
<div class="tabs">
    <div class="tab-item active" onclick="openTab(event,'t1')">ĐIỀU CHỈNH CẦU</div>
    <div class="tab-item" onclick="openTab(event,'t2')">THÀNH VIÊN</div>
    <div class="tab-item" onclick="openTab(event,'t3')">DUYỆT NẠP RÚT</div>
    <div class="tab-item" onclick="openTab(event,'t5')">BANK SÀN</div>
</div>

<?php if($msg) echo "<div style='background:var(--green); color:#000; padding:12px; text-align:center; font-weight:bold;'>$msg</div>"; ?>

<div id="t1" class="tab-content active">
    <div class="card" style="display:flex; justify-content:space-between; align-items:center;">
        <b>AUTO: <?=$auto_active?'🟢 ON':'🔴 OFF'?></b>
        <form method="POST">
            <button name="<?=$auto_active?'auto_off':'auto_on'?>" class="btn" style="width:120px; background:<?= $auto_active?'var(--red)':'var(--green)' ?>;">
                <?= $auto_active?'TẮT':'BẬT' ?> AUTO
            </button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0">ĐIỀU CHỈNH CẦU</h3>
        <div class="grid">
            <?php foreach($future as $sid): ?>
            <div class="box">
                <small style="color:var(--gray)">ID: <?=$sid?></small>
                <div class="<?=($results[$sid]??'')=='TĂNG'?'up':'down'?>" style="font-weight:bold; margin:5px 0;">
                    <?= $results[$sid] ?? '---' ?>
                </div>
                <form method="POST" style="display:flex; gap:3px;">
                    <input type="hidden" name="sid" value="<?=$sid?>">
                    <button name="res" value="TĂNG" class="btn up" style="padding:5px; font-size:10px; background:#1adf9022; border:1px solid var(--green)">TĂNG</button>
                    <button name="res" value="GIẢM" class="btn down" style="padding:5px; font-size:10px; background:#ff5b5b22; border:1px solid var(--red)">GIẢM</button>
                    <input type="hidden" name="set_result">
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🔥 CƯỢC TRỰC TIẾP</h3>
        <div class="scroll">
            <?php if(empty($live_bets)): ?>
                <div style="text-align:center; padding:20px; color:var(--gray)">Chưa có dữ liệu cược.</div>
            <?php endif; ?>
            <?php foreach($live_bets as $b): ?>
            <div class="card" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding:10px; background:rgba(255,255,255,0.03)">
                <span>ID: <b style="color:var(--blue)"><?=$b['session_id']?></b> | <b><?=$b['username']?></b></span>
                <b class="<?=$b['bet_type']=='TĂNG'?'up':'down'?>">
                    <?=$b['bet_type']?> (<?=number_format($b['amount'])?>đ)
                </b>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<div id="t2" class="tab-content">
    <?php foreach($users_list as $u): ?>
    <div class="card">
        <form method="POST">
            <input type="hidden" name="target_uid" value="<?=$u['id']?>">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <b style="color:var(--yellow)"><?=$u['username']?></b>
                <select name="role" style="width:100px; margin:0; padding:5px;">
                    <option value="user" <?=$u['role']=='user'?'selected':''?>>User</option>
                    <option value="mod" <?=$u['role']=='mod'?'selected':''?>>Mod</option>
                    <option value="admin" <?=$u['role']=='admin'?'selected':''?>>Admin</option>
                </select>
            </div>
            <input type="text" name="user_pass" placeholder="Mật khẩu mới (Bỏ trống nếu không đổi)">
            <input type="number" name="bal" value="<?=$u['balance']?>" placeholder="Số dư">
            <input type="text" name="fullname" value="<?=$u['fullname']?>" placeholder="Tên thật">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:5px;">
                <input type="text" name="b_name" value="<?=$u['bank_name']?>" placeholder="Ngân hàng">
                <input type="text" name="b_num" value="<?=$u['bank_number']?>" placeholder="Số TK">
            </div>
            <input type="text" name="b_user" value="<?=$u['bank_user']?>" placeholder="Chủ TK">
            <div style="margin:5px 0;">
                <img src="<?=$u['cccd_front']?>" class="cccd-img">
                <img src="<?=$u['cccd_back']?>" class="cccd-img">
            </div>
            <button name="act" value="update_info" class="btn" style="background:var(--blue)">LƯU THÔNG TIN</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div id="t3" class="tab-content">
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <button onclick="filterTrans('deposit', this)" class="sub-tab active" style="flex:1; padding:10px; border-radius:8px; border:1px solid var(--border); background:var(--panel); color:#fff; cursor:pointer;">📥 CHỜ NẠP</button>
        <button onclick="filterTrans('withdraw', this)" class="sub-tab" style="flex:1; padding:10px; border-radius:8px; border:1px solid var(--border); background:var(--panel); color:#fff; cursor:pointer;">📤 CHỜ RÚT</button>
    </div>

    <div id="trans-container">
        <?php foreach($pending_list as $t): ?>
        <div class="card trans-card" data-type="<?=$t['type']?>" style="display: <?=$t['type']=='deposit'?'block':'none'?>;">
            <b><?=$t['username']?> | <span style="color:<?=$t['type']=='deposit'?'var(--green)':'var(--red)'?>"><?=strtoupper($t['type'])?></span></b>
            <h2 style="margin:10px 0; color:var(--yellow);"><?=number_format($t['amount'])?>đ</h2>
            <div style="font-size:11px; color:var(--gray); padding:5px; border-left:2px solid var(--blue);">Nội dung: <?=$t['info']?></div>
            
            <form method="POST" style="display:flex; gap:10px; margin-top:10px;">
                <input type="hidden" name="tid" value="<?=$t['id']?>">
                <input type="hidden" name="user_id" value="<?=$t['user_id']?>">
                <input type="hidden" name="amount" value="<?=$t['amount']?>">
                <input type="hidden" name="type" value="<?=$t['type']?>">
                <button name="update_trans" onclick="this.form.action.value='approve'" class="btn" style="background:var(--green); color:#000">DUYỆT</button>
                <button name="update_trans" onclick="this.form.action.value='reject'" class="btn" style="background:var(--red)">HỦY</button>
                <input type="hidden" name="action">
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .sub-tab.active { border-color: var(--blue) !important; background: rgba(58, 122, 254, 0.1) !important; color: var(--blue) !important; font-weight: bold; }
</style>

<script>
function filterTrans(type, el) {
    // Chuyển màu nút
    document.querySelectorAll('.sub-tab').forEach(btn => btn.classList.remove('active'));
    el.classList.add('active');

    // Lọc danh sách card
    document.querySelectorAll('.trans-card').forEach(card => {
        if(card.getAttribute('data-type') === type) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<div id="t5" class="tab-content">
    <div class="card">
        <h3>CÀI ĐẶT BANK NHẬN NẠP</h3>
        <form method="POST">
            <input type="text" name="b_name" value="<?=$bank_setting['bank_name']??''?>" placeholder="Tên Bank (VD: MB Bank)">
            <input type="text" name="b_num" value="<?=$bank_setting['bank_number']??''?>" placeholder="Số tài khoản">
            <input type="text" name="b_user" value="<?=$bank_setting['bank_user']??''?>" placeholder="Họ tên chủ thẻ">
            <button name="save_bank_setting" class="btn" style="background:var(--blue)">LƯU CÀI ĐẶT</button>
        </form>
    </div>
</div>

<script>
function toggleMenu(){
 let m=document.getElementById('menuDropdown');
 m.style.display=(m.style.display==='block')?'none':'block';
}
document.addEventListener('click',e=>{
 if(!e.target.closest('.menu-btn')&&!e.target.closest('#menuDropdown')){
  document.getElementById('menuDropdown').style.display='none';
 }
});
 function openTab(evt, name) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        document.getElementById(name).classList.add('active');
        evt.currentTarget.classList.add('active');
   }
setInterval(()=>{
 let s=60-(Math.floor(Date.now()/1000)%60);
 document.getElementById('timer').innerText=s+'s';
 if(s===59) location.reload();
},1000);
</script>

</body>
</html>
