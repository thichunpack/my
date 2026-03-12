<?php 
include 'config.php'; 
check(); // Kiểm tra đăng nhập

$uid = $_SESSION['uid'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Lấy kết quả bẻ cầu Admin nếu có
$current_sid = floor(time() / 60);
$check_res = $db->prepare("SELECT result FROM results WHERE session_id = ?");
$check_res->execute([$current_sid]);
$forced_res = $check_res->fetchColumn();

// Lịch sử 5 phiên gần nhất kèm cược
$history_query = "
SELECT r.*, b.amount as bet_amt, b.bet_type,
CASE 
    WHEN b.bet_type LIKE r.result || '%' THEN (b.amount * 0.90)
    WHEN b.bet_type IS NOT NULL THEN (b.amount * -1)
    ELSE 0
END as win_loss
FROM results r
LEFT JOIN bets b ON r.session_id = b.session_id AND b.user_id = ?
WHERE r.session_id < $current_sid
ORDER BY r.session_id DESC LIMIT 3000";
$stmt_hist = $db->prepare($history_query);
$stmt_hist->execute([$uid]);
$history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

// KIỂM TRA THẮNG PHIÊN TRƯỚC (Để hiện thông báo)
$last_win_amount = 0;
if (!empty($history)) {
    $last_result = $history[0];
    // Nếu kết quả mới nhất là của phiên vừa kết thúc (phiên hiện tại - 1) và có tiền thắng
    if ($last_result['session_id'] == $current_sid - 1 && $last_result['win_loss'] > 0) {
        $last_win_amount = $last_result['win_loss'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Nền tảng giao dịch kỹ thuật số Mycsingtrade</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
<link rel="icon" href="https://mycsingtrade.com/logo.png" type="image/png">

<!-- SweetAlert2 & TradingView -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://s3.tradingview.com/tv.js"></script>

<style>
:root{ --bg:#05070c; --panel:#0c1220; --border:#1f2f4a; --green:#1adf90; --red:#ff5b5b; --blue:#3a7afe; --yellow:#f5c542; --gray:#64748b; }
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
body{margin:0;background:var(--bg);color:#fff;font-family:-apple-system,sans-serif; overflow-x:hidden; height:100vh; display:flex; flex-direction:column;}
.topbar{display:flex;justify-content:space-between;align-items:center; padding:12px 15px; background:var(--panel); border-bottom:1px solid var(--border); z-index:1001;}
.menu-btn{font-size:22px;cursor:pointer;}
.trade-view-wrapper{flex:1; overflow:hidden; position:relative; background:#000; border-bottom:1px solid #1a2233;}
#tvchart{width:100%;height:100%;}
.section{padding:10px 15px; background:var(--panel);}

/* CHỈNH SỬA GRID 4 CỘT CHO ĐẸP HƠN */
.amounts{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;}
.amounts button{ background:#0d1422;border:1px solid var(--border); border-radius:8px;padding:10px 0;color:#fff; font-size:12px; cursor:pointer; transition:0.2s;}
.amounts button:active{transform:scale(0.95);}
.btn-reset{ background: #1f2f4a !important; color: var(--red) !important; font-weight: bold; }
.btn-allin{ background: var(--blue) !important; color: #fff !important; font-weight: bold; border:1px solid var(--blue) !important; }

.action{display:flex;gap:10px;margin-top:10px}
.btn-up,.btn-down{ flex:1;border:none;border-radius:10px; padding:16px;font-weight:800; font-size:16px; cursor:pointer; transition:0.2s; }
.btn-up:active,.btn-down:active{transform:scale(0.95);}
.btn-up{background:var(--green);color:#000}
.btn-down{background:var(--red);color:#fff}
.amount-box{ flex:1;text-align:center;line-height:48px; border:1px solid var(--border);border-radius:10px; font-weight:bold; color:var(--yellow); background:#070b14; font-size:18px;}
.info-bar{background:var(--panel);padding:10px 15px;font-size:11px;display:flex;align-items:center;border-top:1px solid var(--border);border-bottom:1px solid var(--border);}
.info-bar > div{flex:1;}
.col-left{text-align:left;}
.col-center{text-align:center;}
.col-right{text-align:right;}
.history-box{padding:10px 15px; background:var(--bg); max-height:160px; overflow-y:auto; font-size:12px; border-top:1px solid var(--border);}
.history-item{display:flex; flex-direction:column; padding:8px 0; border-bottom:1px solid #111;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:2000;}
.box{background:var(--panel);padding:20px;border-radius:15px;width:85%;max-width:320px;text-align:center;border:1px solid var(--border);}
.ok{background:var(--blue);color:#fff;padding:12px;width:100%;border:none;border-radius:8px;font-weight:bold;cursor:pointer;margin-top:10px;}
</style>
<style>
.btn-history{
  padding:6px 12px;
  border-radius:8px;
  border:none;
  background:#3a7afe;
  color:#fff;
  font-weight:700;
  cursor:pointer;
}

.history-modal{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.6);
  display:none;
  justify-content:center;
  align-items:center;
  z-index:9999;
}

.history-box{
  width:320px;
  max-height:80vh;
  background:#0c1220;
  border-radius:14px;
  padding:12px;
  overflow-y:auto;
  border:1px solid #1f2f4a;
}

.history-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-weight:800;
  margin-bottom:8px;
}

.close-btn{
  cursor:pointer;
  color:#ff5b5b;
  font-size:14px;
}

.history-item{
  background:#121a30;
  border-radius:8px;
  padding:8px;
  margin-bottom:6px;
  font-size:11px;
}
</style>
</head>
<body>

<div class="topbar">
  <div>
    TÊN TÀI KHOẢN: <b style="text-transform:uppercase"><?=htmlspecialchars($user['username'])?></b><br>
    VÍ ĐIỆN TỬ: <b id="display_bal" style="color:var"><?=number_format($user['balance'])?></b>
  </div>
  <div class="menu-btn" onclick="toggleMenu()">☰</div>
  <div id="menuDropdown" style="display:none; position:absolute; top:60px; right:15px; background:var(--panel); border:1px solid var(--border); border-radius:10px; width:180px; z-index:1002;">
    <div onclick="location.href='/'" style="padding:12px; border-bottom:1px solid var(--border);">TRANG CHỦ</div>
    <div style="padding:12px; border-bottom:1px solid var(--border);" onclick="location.href='naprut'">NẠP RÚT TIỀN</div>
    <div style="padding:12px; border-bottom:1px solid var(--border);" onclick="location.href='lichsugiaodich'">LỊCH SỬ GIAO DỊCH</div>
    <?php if($user['role']=='admin'): ?><div style="padding:12px; color:var(--green);" onclick="location.href='/quanly'">QUẢN TRỊ ADMIN</div><?php endif; ?>
    <?php if($user['role']=='mod'): ?><div style="padding:12px; color:var(--green);" onclick="location.href='/mod'">QUẢN TRỊ MOD</div><?php endif; ?>
    <div onclick="location.href='/thoat'" style="padding:12px; color:var(--red);">ĐĂNG XUẤT</div>
  </div>
</div>

<div class="trade-view-wrapper"><div id="tvchart"></div></div>

<div class="section">
  <div class="amounts" id="amounts">
    <button onclick="addAmount(20000)">20K</button>
    <button onclick="addAmount(50000)">50K</button>
    <button onclick="addAmount(100000)">100K</button>
    <button onclick="addAmount(200000)">200K</button>
    <button onclick="addAmount(500000)">500K</button>
    <button onclick="addAmount(1000000)">1M</button>
    <button onclick="addAmount(2000000)">2M</button>
    <button onclick="addAmount(5000000)">5M</button>
    <button onclick="addAmount(10000000)">10M</button>
    <button onclick="addAmount(20000000)">20M</button>
    <button onclick="addAmount(50000000)">50M</button>
    <button onclick="addAmount(100000000)">100M</button>
    <button onclick="addAmount(200000000)">200M</button>
    <button onclick="addAmount(500000000)">500M</button>
    <button class="btn-allin" onclick="allIn()">TẤT CẢ</button>
    <button class="btn-reset" onclick="resetBet()">XOÁ</button>
  </div>

  <div class="action">
    <button class="btn-up" onclick="askTrade('TĂNG 90%')">TĂNG 90%</button>
    <div class="amount-box" id="amountBox">0</div>
    <button class="btn-down" onclick="askTrade('GIẢM 90%')">GIẢM 90%</button>
  </div>
</div>

<div class="info-bar">
  <div class="col-left">Ngày: <b id="cur_date">--/--</b></div>
  <div class="col-center">ID: <b id="cur_sid">...</b></div>
  <div class="col-right">Đóng sau: <b id="timer" style="color:var(--yellow)">60</b></div>
</div>

<button class="btn-history" onclick="openHistory()">📜 Lịch sử đặt cược</button>

<div id="historyModal" class="history-modal">

  <div class="history-box">
    <div class="history-header">
      <span>📜 LỊCH SỬ ĐẶT CƯỢC</span>
      <span class="close-btn" onclick="closeHistory()">✖</span>
    </div>

    <div id="history_list">
      <?php foreach($history as $h): ?>
        <div class="history-item">
          <div style="display:flex; justify-content:space-between; font-weight:bold;">
            <span>ID: <?=$h['session_id']?></span>
            <span style="color:<?=$h['result']=='TĂNG'?'var(--green)':'var(--red)'?>">
              <?=$h['result']?>
            </span>
          </div>

          <?php if($h['bet_amt']>0): ?>
            <div style="display:flex; justify-content:space-between; font-size:10px; margin-top:3px;">
              <span>
                Cược: <?=number_format($h['bet_amt'])?> (<?=$h['bet_type']?>)
              </span>
              <b style="color:<?=$h['win_loss']>0?'var(--green)':'var(--red)'?>">
                <?=$h['win_loss']>0?'+'.number_format($h['win_loss']):number_format($h['win_loss'])?>
              </b>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<div id="popupTrade" class="overlay">
  <div class="box">
    <h3 id="popTitle" style="color: var(--blue); margin-bottom:5px;">XÁC NHẬN LỆNH</h3>
    <div id="popupTradeType" style="font-size:20px; font-weight:800; margin-bottom:5px;"></div>
    <div style="font-size:14px;color:var(--gray)">Số tiền cược:</div>
    <div id="popupTradeAmount" style="font-size:28px;font-weight:900;color:var(--yellow);margin:10px 0;">0</div>
    <button class="ok" onclick="confirmTrade()">XÁC NHẬN ĐẶT CƯỢC</button>
    <button class="ok" style="background:transparent;border:1px solid var(--border);color:var(--gray);margin-top:10px;" onclick="closePopup()">HUỶ BỎ</button>
  </div>
</div>
<script>
function openHistory(){
  document.getElementById('historyModal').style.display = 'flex';
}
function closeHistory(){
  document.getElementById('historyModal').style.display = 'none';
}
</script>
<script>
let currentAmount = 0, pendingAction='', isLocked=false;
const SERVER_SID = <?=$current_sid?>;
const LAST_WIN = <?=$last_win_amount?>;

// TradingView Chart
new TradingView.widget({container_id:"tvchart", autosize:true, symbol:"BINANCE:BTCUSDT", interval:"1", theme:"dark", locale:"vi", hide_top_toolbar:true, hide_legend:true});

// Toast Config
const Toast = Swal.mixin({
  toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
  background: '#0c1220', color: '#fff', didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  }
});

// Check Win on Load
document.addEventListener('DOMContentLoaded', () => {
    if(LAST_WIN > 0){
        Toast.fire({
            icon: 'success',
            title: 'CHÚC MỪNG THẮNG CƯỢC',
            text: `+${LAST_WIN.toLocaleString()}đ `
        });
    }
});

// Thời gian + Phiên
function updateSystem(){
  const now = new Date(new Date().toLocaleString("en-US",{timeZone:"Asia/Ho_Chi_Minh"}));
  const ts = Math.floor(now.getTime()/1000);
  const sid = Math.floor(ts/60);
  const rem = 60 - (ts%60);
  document.getElementById('cur_sid').innerText = sid;
  document.getElementById('cur_date').innerText = now.toLocaleDateString('vi-VN');
  const timerEl = document.getElementById('timer');
  timerEl.innerText = rem + 's';
  const btns = document.querySelectorAll('.btn-up,.btn-down');

  if(rem<=10){isLocked=true; timerEl.style.color="#ef4444"; btns.forEach(b=>b.style.opacity="0.3"); closePopup();}
  else{isLocked=false; timerEl.style.color="var(--yellow)"; btns.forEach(b=>b.style.opacity="1");}
  if(rem===60) location.reload();
}
setInterval(updateSystem,1000);
updateSystem();

// Cược tiền
function addAmount(v){if(!isLocked){currentAmount+=v;document.getElementById('amountBox').innerText=currentAmount.toLocaleString();}}
function resetBet(){currentAmount=0;document.getElementById('amountBox').innerText='0';}
function allIn(){
    if(!isLocked){
        // Lấy số dư hiện tại từ giao diện
        const balStr = document.getElementById('display_bal').innerText.replace(/,/g, '');
        const bal = parseInt(balStr);
        if(bal > 0) {
            currentAmount = bal;
            document.getElementById('amountBox').innerText = currentAmount.toLocaleString();
        } else {
            Toast.fire({icon: 'warning', title: 'Số dư không đủ!'});
        }
    }
}

// Popup đặt cược
function askTrade(type){
  if(isLocked){Toast.fire({icon: 'error', title: 'Hết thời gian đặt lệnh!'}); return;}
  if(currentAmount<=0){Toast.fire({icon: 'warning', title: 'Vui lòng chọn số tiền cược!'}); return;}
  pendingAction=type;
  document.getElementById('popupTradeType').innerHTML = type.includes('TĂNG')?'<span style="color:var(--green)">TĂNG 90%</span>':'<span style="color:var(--red)">GIẢM 90%</span>';
  document.getElementById('popupTradeAmount').innerText=currentAmount.toLocaleString();
  document.getElementById('popupTrade').style.display='flex';
}
function closePopup(){document.getElementById('popupTrade').style.display='none';}

// Xác nhận đặt cược
async function confirmTrade(){
  if(isLocked){Toast.fire({icon: 'error', title: 'Hết thời gian đặt lệnh!'}); closePopup(); return;}
  if(currentAmount<=0){Toast.fire({icon: 'warning', title: 'Số tiền cược phải lớn hơn 0!'}); return;}

  const res = await fetch('api_bet.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({amt:currentAmount,type:pendingAction,sid:SERVER_SID})
  });
  const result = await res.json();
  closePopup();
  if(result.st==='ok'){
    document.getElementById('display_bal').innerText=result.new;

    const historyList=document.getElementById('history_list');
    if(historyList){
      historyList.insertAdjacentHTML('afterbegin',`
        <div class="history-item" style="background: rgba(58,122,254,0.1); border-left:3px solid var(--blue); padding:8px 0;">
          <div style="display:flex; justify-content:space-between; font-weight:bold;">
            <span>ID: ${SERVER_SID}</span>
            <span style="color: var(--gray)">Đang chờ...</span>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:10px; margin-top:3px;">
            <span>Cược: ${currentAmount.toLocaleString()} (${pendingAction})</span>
            <b style="color: var(--yellow)">XỬ LÝ</b>
          </div>
        </div>
      `);
    }
    
    // Thông báo thành công (Toast)
    Toast.fire({
        icon: 'success',
        title: 'ĐẶT CƯỢC THÀNH CÔNG',
        html: `Cược <b>${currentAmount.toLocaleString()}đ</b> vào cửa <b>${pendingAction}</b>`
    });
    resetBet();

  } else { 
      Toast.fire({icon: 'error', title: 'Thất bại', text: result.msg}); 
  }
}

// Menu
function toggleMenu(){
  const m=document.getElementById('menuDropdown');
  m.style.display=(m.style.display==='block')?'none':'block';
}
</script>

</body>
</html>