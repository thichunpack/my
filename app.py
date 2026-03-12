from flask import Flask, render_template_string, request, jsonify, session, redirect, url_for, flash
from db import get_db_connection, init_db
from werkzeug.security import generate_password_hash, check_password_hash
import time
import math
import os

app = Flask(__name__)
app.secret_key = 'YOUR_SECRET_KEY_HERE'

# Initialize DB
if not os.path.exists('trade.db'):
    init_db()

# --- SECURITY SCRIPTS ---
PROTECTION_JS = """
<script>
// Anti-RightClick
document.addEventListener('contextmenu', event => event.preventDefault());

// Anti-DevTools Keys
document.onkeydown = function(e) {
    if(e.keyCode == 123) return false; // F12
    if(e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) return false; // Ctrl+Shift+I
    if(e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) return false; // Ctrl+Shift+C
    if(e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) return false; // Ctrl+Shift+J
    if(e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false; // Ctrl+U
}

// Debugger Trap (Anti-Debug)
setInterval(function(){
    var startTime = performance.now();
    debugger;
    var endTime = performance.now();
    if (endTime - startTime > 100) {
        window.location.href = "about:blank";
    }
}, 100);

// Console Spam
setInterval(function(){
    console.clear();
    console.log("%cCẢNH BÁO BẢO MẬT!", "color: red; font-size: 30px; font-weight: bold;");
    console.log("Hành vi của bạn đang được giám sát.");
}, 500);
</script>
"""

# --- HTML TEMPLATES ---

LOGIN_HTML = """<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập</title>
<style>
:root{--bg:#05070c;--panel:#0f172a;--border:#24314f;--text:#e5e7eb;--muted:#94a3b8;--accent:#3a7afe;}
*{box-sizing:border-box;}
body{background:radial-gradient(circle at top,#111827,#05070c 55%);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;user-select:none;padding:16px;}
form{background:linear-gradient(180deg,rgba(17,24,39,.92),rgba(12,18,32,.92));padding:26px 22px;border-radius:16px;width:340px;border:1px solid var(--border);box-shadow:0 20px 45px rgba(0,0,0,.4);backdrop-filter:blur(8px);}
h2{margin:4px 0 18px;text-align:center;letter-spacing:.08em;}
input{width:100%;padding:12px;margin:8px 0;background:#0a101d;border:1px solid var(--border);color:#fff;border-radius:10px;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(58,122,254,.2);}
button{width:100%;padding:12px;background:linear-gradient(90deg,#3a7afe,#2563eb);border:none;color:#fff;border-radius:10px;cursor:pointer;font-weight:700;letter-spacing:.03em;margin-top:6px;}
.link{text-align:center;margin-top:14px;font-size:13px;color:var(--muted);}
.link a{color:#60a5fa;text-decoration:none;font-weight:600;}
</style>
""" + PROTECTION_JS + """
</head>
<body>
<form method="POST">
    <h2>ĐĂNG NHẬP</h2>
    {% if error %}<div style="color:#ff5b5b;text-align:center;margin-bottom:10px">{{ error }}</div>{% endif %}
    {% if msg %}<div style="color:#1adf90;text-align:center;margin-bottom:10px">{{ msg }}</div>{% endif %}
    <input type="text" name="username" placeholder="Tên đăng nhập" required>
    <input type="password" name="password" placeholder="Mật khẩu" required>
    <button type="submit">ĐĂNG NHẬP</button>
    <div class="link">Chưa có tài khoản? <a href="/register">Đăng ký ngay</a></div>
</form>
</body>
</html>"""

REGISTER_HTML = """<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng ký</title>
<style>
:root{--bg:#05070c;--panel:#0f172a;--border:#24314f;--text:#e5e7eb;--muted:#94a3b8;--accent:#3a7afe;}
*{box-sizing:border-box;}
body{background:radial-gradient(circle at top,#111827,#05070c 55%);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;user-select:none;padding:16px;}
form{background:linear-gradient(180deg,rgba(17,24,39,.92),rgba(12,18,32,.92));padding:26px 22px;border-radius:16px;width:340px;border:1px solid var(--border);box-shadow:0 20px 45px rgba(0,0,0,.4);backdrop-filter:blur(8px);}
h2{margin:4px 0 18px;text-align:center;letter-spacing:.08em;}
input{width:100%;padding:12px;margin:8px 0;background:#0a101d;border:1px solid var(--border);color:#fff;border-radius:10px;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(58,122,254,.2);}
button{width:100%;padding:12px;background:linear-gradient(90deg,#3a7afe,#2563eb);border:none;color:#fff;border-radius:10px;cursor:pointer;font-weight:700;letter-spacing:.03em;margin-top:6px;}
.link{text-align:center;margin-top:14px;font-size:13px;color:var(--muted);}
.link a{color:#60a5fa;text-decoration:none;font-weight:600;}
</style>
""" + PROTECTION_JS + """
</head>
<body>
<form method="POST">
    <h2>ĐĂNG KÝ</h2>
    {% if error %}<div style="color:#ff5b5b;text-align:center;margin-bottom:10px">{{ error }}</div>{% endif %}
    <input type="text" name="username" placeholder="Tên đăng nhập" required>
    <input type="password" name="password" placeholder="Mật khẩu" required>
    <input type="text" name="fullname" placeholder="Họ và tên">
    <input type="text" name="phone" placeholder="Số điện thoại">
    <button type="submit">ĐĂNG KÝ</button>
    <div class="link">Đã có tài khoản? <a href="/login">Đăng nhập</a></div>
</form>
</body>
</html>"""

HOME_HTML = """<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Mycsingtrade</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://s3.tradingview.com/tv.js"></script>
<style>
:root{ --bg:#05070c; --panel:#0c1220; --border:#1f2f4a; --green:#1adf90; --red:#ff5b5b; --blue:#3a7afe; --yellow:#f5c542; --gray:#64748b; }
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
body{margin:0;background:var(--bg);color:#fff;font-family:-apple-system,sans-serif; overflow-x:hidden; height:100vh; display:flex; flex-direction:column; user-select:none;}
.topbar{display:flex;justify-content:space-between;align-items:center; padding:12px 15px; background:var(--panel); border-bottom:1px solid var(--border); z-index:1001;}
.menu-btn{font-size:22px;cursor:pointer;}
.trade-view-wrapper{flex:1; overflow:hidden; position:relative; background:#000; border-bottom:1px solid #1a2233;}
#tvchart{width:100%;height:100%;}
.section{padding:10px 15px; background:var(--panel);}
.amounts{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;}
.amounts button{ background:#0d1422;border:1px solid var(--border); border-radius:8px;padding:10px 0;color:#fff; font-size:12px; cursor:pointer; transition:0.2s;}
.action{display:flex;gap:10px;margin-top:10px}
.btn-up,.btn-down{ flex:1;border:none;border-radius:10px; padding:16px;font-weight:800; font-size:16px; cursor:pointer; transition:0.2s; }
.btn-up{background:var(--green);color:#000}
.btn-down{background:var(--red);color:#fff}
.amount-box{ flex:1;text-align:center;line-height:48px; border:1px solid var(--border);border-radius:10px; font-weight:bold; color:var(--yellow); background:#070b14; font-size:18px;}
.history-box{padding:10px 15px; background:linear-gradient(180deg,#060a12,#05070c); max-height:210px; overflow-y:auto; font-size:12px; border-top:1px solid var(--border);}
.history-item{display:flex; flex-direction:column; gap:4px; padding:10px; margin-bottom:8px; border:1px solid #13203a; border-radius:10px; background:#0a0f1a;}
.h-row{display:flex;justify-content:space-between;align-items:center;}
.badge{padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;}
.badge-win{background:rgba(26,223,144,.2);color:var(--green);}
.badge-loss{background:rgba(255,91,91,.2);color:var(--red);}
.badge-pending{background:rgba(245,197,66,.2);color:var(--yellow);}
.trans-btn{flex:1;padding:10px;border:none;border-radius:5px;color:#fff;font-weight:bold;cursor:pointer;}
.menu{position:fixed;top:0;right:-250px;width:250px;height:100%;background:var(--panel);border-left:1px solid var(--border);transition:0.3s;z-index:2000;padding:20px;}
.menu.active{right:0;}
.menu a{display:block;padding:10px 0;color:#fff;text-decoration:none;border-bottom:1px solid var(--border);}
.overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1999;display:none;}
.overlay.active{display:block;}
</style>
""" + PROTECTION_JS + """
</head>
<body>

<div class="overlay" onclick="toggleMenu()"></div>
<div class="menu" id="menu">
    <h3>Menu</h3>
    <a href="/logout">Đăng xuất</a>
</div>

<div class="topbar">
  <div>
    TK: <b style="text-transform:uppercase">{{ user['username'] }}</b><br>
    VÍ: <b id="display_bal" style="color:var(--yellow)">{{ "{:,.0f}".format(user['balance']) }}</b>
  </div>
  <div class="menu-btn" onclick="toggleMenu()">☰</div>
</div>

<div class="trade-view-wrapper"><div id="tvchart"></div></div>

<div class="section">
  <div style="display:flex;gap:10px;margin-bottom:10px">
    <div class="trans-btn" style="background:#111827;color:#6b7280;cursor:not-allowed;text-align:center;line-height:20px;">NẠP/RÚT ĐÃ TẮT</div>
  </div>

  <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:12px;color:var(--gray);">
    <span>ID: <b id="cur_sid" style="color:#fff">{{ current_sid }}</b></span>
    <span>THỜI GIAN: <b id="timer" style="color:var(--yellow)">--s</b></span>
  </div>
  
  <div class="amount-box" id="amountBox">0</div>
  
  <div class="amounts" style="margin-top:10px">
    <button onclick="addAmount(20000)">20K</button>
    <button onclick="addAmount(50000)">50K</button>
    <button onclick="addAmount(100000)">100K</button>
    <button onclick="addAmount(200000)">200K</button>
    <button onclick="addAmount(500000)">500K</button>
    <button onclick="addAmount(1000000)">1M</button>
    <button onclick="addAmount(2000000)">2M</button>
    <button onclick="addAmount(5000000)">5M</button>
    <button class="btn-allin" onclick="allIn()">TẤT CẢ</button>
    <button class="btn-reset" onclick="resetBet()">XOÁ</button>
  </div>

  <div class="action">
    <button class="btn-up" onclick="askTrade('TĂNG')">TĂNG 90%</button>
    <button class="btn-down" onclick="askTrade('GIẢM')">GIẢM 90%</button>
  </div>
</div>

<div class="history-box">
  {% for h in history %}
  <div class="history-item">
    <div class="h-row">
      <span>ID: <b>#{{ h['session_id'] }}</b></span>
      <span style="color:{{ '#1adf90' if h['result']=='TĂNG' else '#ff5b5b' }}"><b>{{ h['result'] }}</b></span>
    </div>
    {% if h['bet_amt'] %}
    <div class="h-row" style="font-size:11px;color:#a9b3c9">
      <span>Cược: {{ h['bet_type'] }} • {{ "{:,.0f}".format(h['bet_amt']) }}đ</span>
      <span class="badge {{ 'badge-win' if h['bet_status']=='win' else ('badge-loss' if h['bet_status']=='loss' else 'badge-pending') }}">{{ h['bet_status']|upper }}</span>
    </div>
    <div class="h-row" style="font-size:11px;color:#7f8ba3">
      <span>Trả thưởng: {{ "{:,.0f}".format(h['payout']) }}đ</span>
      <span style="color:{{ '#1adf90' if h['win_loss'] > 0 else ('#ff5b5b' if h['win_loss'] < 0 else '#f5c542') }}">
        {{ "+" if h['win_loss'] > 0 else "" }}{{ "{:,.0f}".format(h['win_loss']) }}đ
      </span>
    </div>
    {% else %}
    <div style="font-size:11px;color:#7f8ba3">Không có cược ở phiên này.</div>
    {% endif %}
  </div>
  {% endfor %}
</div>

<script>
const SERVER_SID = {{ current_sid }};
let currentAmount = 0;
let isLocked = false;

new TradingView.widget({container_id:"tvchart", autosize:true, symbol:"BINANCE:BTCUSDT", interval:"1", theme:"dark", locale:"vi", hide_top_toolbar:true, hide_legend:true});

function toggleMenu(){
    document.getElementById('menu').classList.toggle('active');
    document.querySelector('.overlay').classList.toggle('active');
}

function updateSystem(){
  const now = new Date();
  const ts = Math.floor(now.getTime()/1000);
  const rem = 60 - (ts%60);
  document.getElementById('timer').innerText = rem + 's';
  if(rem<=10) isLocked=true; else isLocked=false;
  if(rem===60) location.reload();
}
setInterval(updateSystem,1000);

function addAmount(v){if(!isLocked){currentAmount+=v;document.getElementById('amountBox').innerText=currentAmount.toLocaleString();}}
function resetBet(){currentAmount=0;document.getElementById('amountBox').innerText='0';}
function allIn(){
    const bal = parseInt(document.getElementById('display_bal').innerText.replace(/,/g, ''));
    if(bal>0){currentAmount=bal;document.getElementById('amountBox').innerText=currentAmount.toLocaleString();}
}

async function askTrade(type){
    if(isLocked) return Swal.fire('Thông báo', 'Hết giờ đặt cược!', 'warning');
    if(currentAmount<=0) return Swal.fire('Thông báo', 'Vui lòng chọn tiền cược!', 'warning');
    
    const res = await fetch('/api/bet', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({amt:currentAmount, type:type, sid:{{ current_sid }}})
    });
    const data = await res.json();
    if(data.st=='ok'){
        Swal.fire('Thành công', 'Đặt cược thành công!', 'success').then(()=>location.reload());
    } else {
        Swal.fire('Lỗi', data.msg, 'error');
    }
}

async function showDeposit(){
    const {value: amount} = await Swal.fire({
        title: 'Nạp tiền',
        input: 'number',
        inputLabel: 'Nhập số tiền muốn nạp',
        inputPlaceholder: 'Ví dụ: 100000',
        showCancelButton: true
    });
    if(amount){
        const res = await fetch('/api/trans', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({type:'deposit', amount:amount})
        });
        const data = await res.json();
        Swal.fire(data.st=='ok'?'Thành công':'Lỗi', data.msg, data.st=='ok'?'success':'error');
    }
}

async function showWithdraw(){
    const {value: amount} = await Swal.fire({
        title: 'Rút tiền',
        input: 'number',
        inputLabel: 'Nhập số tiền muốn rút',
        inputPlaceholder: 'Ví dụ: 100000',
        showCancelButton: true
    });
    if(amount){
        const res = await fetch('/api/trans', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({type:'withdraw', amount:amount})
        });
        const data = await res.json();
        Swal.fire(data.st=='ok'?'Thành công':'Lỗi', data.msg, data.st=='ok'?'success':'error');
    }
}


let checkingWin = false;

async function checkWinStatus(){
    if(checkingWin) return;
    checkingWin = true;
    try{
        const res = await fetch('/api/check_win');
        const data = await res.json();
        if(!data.hasBet) return;

        if(data.new_balance !== undefined){
            document.getElementById('display_bal').innerText = Number(data.new_balance).toLocaleString();
        }

        if(data.status === 'win'){
            Swal.fire('🎉 Trúng thưởng!', `Phiên #${data.sid} | Cược ${data.bet_type} ${Number(data.bet_amount).toLocaleString()}đ\nKQ: ${data.result} | Lãi: +${Number(data.profit).toLocaleString()}đ`, 'success');
        }else{
            Swal.fire('Kết quả phiên cược', `Phiên #${data.sid} | Cược ${data.bet_type} ${Number(data.bet_amount).toLocaleString()}đ\nKQ: ${data.result} | Kết quả: không trúng`, 'info');
        }
    }catch(e){
        console.log('checkWinStatus error', e);
    }finally{
        checkingWin = false;
    }
}

setInterval(checkWinStatus, 4000);
checkWinStatus();
</script>
</body>
</html>"""

ADMIN_HTML = """<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<meta http-equiv="refresh" content="10">
<style>
body{background:#f0f2f5;font-family:-apple-system,sans-serif;padding:20px;margin:0;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;background:#fff;padding:15px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:20px;}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.card h3{margin-top:0;border-bottom:1px solid #eee;padding-bottom:10px;}
.btn{padding:8px 15px;cursor:pointer;border:none;border-radius:4px;color:#fff;font-weight:bold;text-decoration:none;display:inline-block;}
.btn-green{background:#1adf90;color:#000;}
.btn-red{background:#ff5b5b;}
.btn-blue{background:#3a7afe;}
.btn-yellow{background:#f5c542;color:#000;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th,td{padding:8px;border-bottom:1px solid #eee;text-align:left;}
.live-box{display:flex;gap:20px;margin-bottom:20px;}
.live-card{flex:1;background:#fff;padding:20px;border-radius:8px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1);border:2px solid transparent;}
.live-card.up{border-color:#1adf90;}
.live-card.down{border-color:#ff5b5b;}
.big-num{font-size:24px;font-weight:bold;margin:10px 0;}
.status-badge{padding:3px 8px;border-radius:10px;font-size:11px;color:#fff;}
.admin-input{width:100%;padding:6px;border:1px solid #d5dbe3;border-radius:6px;margin-bottom:6px;font-size:12px;}
</style>
</head>
<body>

<div class="header">
    <div>
        <h2 style="margin:0">ADMIN DASHBOARD</h2>
        <small>Phiên hiện tại: <b>{{ current_sid }}</b> | Thời gian: <b style="color:red">{{ 60 - (time_now % 60) }}s</b></small>
    </div>
    <div>
        <a href="/admin" class="btn btn-blue">Làm mới</a>
        <a href="/logout" class="btn btn-red">Đăng xuất</a>
    </div>
</div>

{% if msg %}
<div style="background:#dff0d8;color:#3c763d;padding:10px;margin-bottom:20px;border-radius:4px;">{{ msg }}</div>
{% endif %}

<!-- LIVE CONTROL -->
<div class="live-box">
    <div class="live-card up">
        <h3 style="color:#1adf90">TỔNG TĂNG</h3>
        <div class="big-num">{{ "{:,.0f}".format(total_up) }}</div>
        <form method="POST">
            <button type="submit" name="kill_up" class="btn btn-red" style="width:100%">BẺ CẦU (Giet TĂNG)</button>
        </form>
    </div>
    
    <div class="live-card">
        <h3>ĐIỀU KHIỂN</h3>
        <div style="margin:10px 0;">
            Trạng thái: <b>{{ 'AUTO' if auto_active else 'MANUAL' }}</b>
        </div>
        <form method="POST" style="display:flex;gap:5px;justify-content:center;margin-bottom:10px;">
            <button type="submit" name="auto_on" class="btn btn-blue">Auto ON</button>
            <button type="submit" name="auto_off" class="btn btn-red">Auto OFF</button>
        </form>
        <form method="POST">
            <button type="submit" name="win_small" class="btn btn-yellow" style="width:100%">ĂN CỬA NHỎ</button>
        </form>
    </div>
    
    <div class="live-card down">
        <h3 style="color:#ff5b5b">TỔNG GIẢM</h3>
        <div class="big-num">{{ "{:,.0f}".format(total_down) }}</div>
        <form method="POST">
            <button type="submit" name="kill_down" class="btn btn-green" style="width:100%">BẺ CẦU (Giet GIẢM)</button>
        </form>
    </div>
</div>

<div class="grid">
    <!-- LIVE BETS -->
    <div class="card">
        <h3>Đang đặt cược (Phiên {{ current_sid }})</h3>
        <div style="max-height:300px;overflow-y:auto;">
        <table>
            <tr><th>User</th><th>Cửa</th><th>Tiền</th><th>Xóa</th></tr>
            {% for b in live_bets %}
            <tr>
                <td>{{ b['username'] }}</td>
                <td><span style="color:{{ '#1adf90' if b['bet_type']=='TĂNG' else '#ff5b5b' }}">{{ b['bet_type'] }}</span></td>
                <td>{{ "{:,.0f}".format(b['amount']) }}</td>
                <td>
                    <form method="POST" onsubmit="return confirm('Xóa lệnh này?');">
                        <input type="hidden" name="bid" value="{{ b['id'] }}">
                        <button type="submit" name="delete_bet" class="btn btn-red" style="padding:2px 5px;font-size:10px;">X</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        </table>
        </div>
    </div>

    <!-- SET RESULT -->
    <div class="card">
        <h3>Set Kết Quả</h3>
        <form method="POST" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <input type="number" name="sid" value="{{ current_sid }}" style="width:120px;padding:5px;">
            <select name="res" style="padding:5px;">
                <option value="TĂNG">TĂNG</option>
                <option value="GIẢM">GIẢM</option>
            </select>
            <button type="submit" name="set_result" class="btn btn-blue">SET</button>
        </form>

        <h4 style="margin-top:15px">Điều chỉnh nhanh nhiều phiên</h4>
        <div style="max-height:260px;overflow-y:auto;">
        <table>
            <tr><th>Phiên</th><th>KQ hiện tại</th><th>Đổi KQ</th><th>Lưu</th></tr>
            {% for sid in future_sids %}
            <tr>
                <td>{{ sid }}</td>
                <td>{{ future_results.get(sid, '--') }}</td>
                <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="sid" value="{{ sid }}">
                        <select name="res" style="padding:4px;">
                            <option value="TĂNG" {% if future_results.get(sid)=='TĂNG' %}selected{% endif %}>TĂNG</option>
                            <option value="GIẢM" {% if future_results.get(sid)=='GIẢM' %}selected{% endif %}>GIẢM</option>
                        </select>
                        <button type="submit" name="set_result" class="btn btn-blue" style="padding:4px 8px;">LƯU</button>
                    </form>
                </td>
                <td>{{ '✔' if future_results.get(sid) else '-' }}</td>
            </tr>
            {% endfor %}
        </table>
        </div>

        <h4 style="margin-top:15px">Kết quả gần đây</h4>
        <table>
            <tr><th>ID</th><th>KQ</th></tr>
            {% for r in recent_results %}
            <tr>
                <td>{{ r['session_id'] }}</td>
                <td><span style="color:{{ '#1adf90' if r['result']=='TĂNG' else '#ff5b5b' }}">{{ r['result'] }}</span></td>
            </tr>
            {% endfor %}
        </table>
    </div>
</div>
</div>

<div class="grid" style="margin-top:20px;">
    <!-- USERS -->
    <div class="card">
        <h3>Quản lý User</h3>
        <form method="POST" style="margin-bottom:10px;background:#f9f9f9;padding:10px;">
            <input type="text" name="new_username" placeholder="User" required style="width:80px">
            <input type="password" name="new_password" placeholder="Pass" required style="width:80px">
            <button type="submit" name="create_user" class="btn btn-green">Tạo</button>
        </form>
        <div style="max-height:420px;overflow-y:auto;">
        <table>
            <tr><th>User</th><th>Ví</th><th>Quản lý nhanh</th></tr>
            {% for u in users_list %}
            <tr>
                <td>{{ u['username'] }} <br><small style="color:{{ 'green' if u['status']=='active' else 'red' }}">{{ u['status'] }}</small></td>
                <td>{{ "{:,.0f}".format(u['balance']) }}</td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="uid" value="{{ u['id'] }}">
                        <input type="number" name="amount" placeholder="$" style="width:50px">
                        <button type="submit" name="add_money" class="btn btn-green">+</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="uid" value="{{ u['id'] }}">
                        {% if u['status']=='active' %}
                        <button type="submit" name="lock_user" class="btn btn-red">K</button>
                        {% else %}
                        <button type="submit" name="unlock_user" class="btn btn-yellow">M</button>
                        {% endif %}
                    </form>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <form method="POST" style="border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fafafa;">
                        <input type="hidden" name="target_uid" value="{{ u['id'] }}">
                        <div style="display:grid;grid-template-columns:repeat(3,minmax(120px,1fr));gap:6px;">
                            <input class="admin-input" type="number" name="bal" value="{{ u['balance'] }}" placeholder="Số dư">
                            <input class="admin-input" type="text" name="fullname" value="{{ u['fullname'] or '' }}" placeholder="Họ tên">
                            <input class="admin-input" type="text" name="bank_name" value="{{ u['bank_name'] or '' }}" placeholder="Ngân hàng">
                            <input class="admin-input" type="text" name="bank_number" value="{{ u['bank_number'] or '' }}" placeholder="Số TK">
                            <input class="admin-input" type="text" name="bank_user" value="{{ u['bank_user'] or '' }}" placeholder="Chủ TK">
                            <select class="admin-input" name="role">
                                <option value="user" {% if u['role']=='user' %}selected{% endif %}>user</option>
                                <option value="mod" {% if u['role']=='mod' %}selected{% endif %}>mod</option>
                                <option value="admin" {% if u['role']=='admin' %}selected{% endif %}>admin</option>
                            </select>
                            <input class="admin-input" type="text" name="user_pass" placeholder="Mật khẩu mới (để trống nếu giữ)">
                        </div>
                        <button type="submit" name="update_user_full" class="btn btn-blue" style="margin-top:6px;">CẬP NHẬT USER</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        </table>
        </div>
        </div>
    </div>

    <div class="card">
        <h3>Lịch sử đặt cược gần đây</h3>
        <div style="max-height:300px;overflow-y:auto;">
        <table>
            <tr><th>Time</th><th>User</th><th>Phiên</th><th>Cửa</th><th>Tiền</th><th>KQ</th><th>Lãi/Lỗ</th></tr>
            {% for bh in bet_history %}
            <tr>
                <td>{{ bh['created_at'] }}</td>
                <td>{{ bh['username'] }}</td>
                <td>#{{ bh['session_id'] }}</td>
                <td>{{ bh['bet_type'] }}</td>
                <td>{{ "{:,.0f}".format(bh['amount']) }}</td>
                <td>{{ bh['status'] }}</td>
                <td style="color:{{ '#1adf90' if bh['profit'] > 0 else ('#ff5b5b' if bh['profit'] < 0 else '#666') }}">{{ "{:,.0f}".format(bh['profit']) }}</td>
            </tr>
            {% endfor %}
        </table>
        </div>
    </div>

    <!-- TRANSACTIONS -->
    <div class="card">
        <h3>Yêu cầu Nạp/Rút</h3>
        <table>
            <tr><th>User</th><th>Loại</th><th>Tiền</th><th>Action</th></tr>
            {% for t in pending_trans %}
            <tr>
                <td>{{ t['username'] }}</td>
                <td>{{ t['type'] }}</td>
                <td>{{ "{:,.0f}".format(t['amount']) }}</td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="tid" value="{{ t['id'] }}">
                        <button type="submit" name="approve_trans" class="btn btn-green">V</button>
                        <button type="submit" name="reject_trans" class="btn btn-red">X</button>
                    </form>
                </td>
            </tr>
            {% endfor %}
        </table>
        
        <h3 style="margin-top:20px">Lịch sử Nạp/Rút gần đây</h3>
        <div style="max-height:220px;overflow-y:auto;">
        <table>
            <tr><th>Time</th><th>User</th><th>Loại</th><th>Tiền</th><th>Trạng thái</th></tr>
            {% for t in recent_trans %}
            <tr>
                <td>{{ t['created_at'] }}</td>
                <td>{{ t['username'] }}</td>
                <td>{{ t['type'] }}</td>
                <td>{{ "{:,.0f}".format(t['amount']) }}</td>
                <td>{{ t['status'] }}</td>
            </tr>
            {% endfor %}
        </table>
        </div>

        <h3 style="margin-top:20px">Hệ thống</h3>
        <form method="POST" onsubmit="return confirm('Xóa sạch dữ liệu?');">
            <button type="submit" name="clear_history" class="btn btn-red" style="width:100%">RESET TOÀN BỘ DATA</button>
        </form>
    </div>
</div>

</body>
</html>"""

# --- HELPERS ---

def get_current_sid():
    return math.floor(time.time() / 60)

def check_login():
    if 'uid' not in session:
        return False
    return True

def ensure_default_admin():
    conn = get_db_connection()
    try:
        admin = conn.execute("SELECT id FROM users WHERE username='admin' LIMIT 1").fetchone()
        if not admin:
            conn.execute("INSERT INTO users (username, password, role, status, fullname) VALUES (?, ?, 'admin', 'active', ?)", ('admin', '123', 'System Admin'))
        else:
            conn.execute("UPDATE users SET password='123', role='admin', status='active' WHERE username='admin'")
        conn.commit()
    finally:
        conn.close()

ensure_default_admin()

# --- ROUTES ---

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        conn = get_db_connection()
        user = conn.execute('SELECT * FROM users WHERE username = ?', (username,)).fetchone()
        conn.close()
        
        if user and (user['password'] == password or check_password_hash(user['password'], password)):
            if user['status'] == 'locked':
                return render_template_string(LOGIN_HTML, error="Tài khoản đã bị khóa")
            session['uid'] = user['id']
            return redirect(url_for('home'))
        else:
            return render_template_string(LOGIN_HTML, error="Sai tên đăng nhập hoặc mật khẩu")
            
    return render_template_string(LOGIN_HTML)

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        fullname = request.form.get('fullname')
        phone = request.form.get('phone')
        
        conn = get_db_connection()
        try:
            hashed_pw = generate_password_hash(password)
            conn.execute('INSERT INTO users (username, password, fullname, phone) VALUES (?, ?, ?, ?)',
                         (username, hashed_pw, fullname, phone))
            conn.commit()
            return render_template_string(LOGIN_HTML, msg="Đăng ký thành công! Hãy đăng nhập.")
        except Exception as e:
            return render_template_string(REGISTER_HTML, error="Tên đăng nhập đã tồn tại hoặc lỗi hệ thống.")
        finally:
            conn.close()
            
    return render_template_string(REGISTER_HTML)

@app.route('/logout')
def logout():
    session.pop('uid', None)
    return redirect(url_for('login'))

@app.route('/')
def home():
    if not check_login():
        return redirect(url_for('login'))
    
    uid = session['uid']
    conn = get_db_connection()
    user = conn.execute('SELECT * FROM users WHERE id = ?', (uid,)).fetchone()
    
    if not user:
        session.pop('uid', None)
        return redirect(url_for('login'))
        
    current_sid = get_current_sid()
    
    # History
    history = conn.execute('''
        SELECT r.session_id, r.result, b.amount as bet_amt, b.bet_type, b.status as bet_status,
        COALESCE(b.profit, 0) as profit,
        CASE 
            WHEN b.status = 'win' THEN (b.amount + COALESCE(b.profit, 0))
            ELSE 0
        END as payout,
        CASE 
            WHEN b.status = 'win' THEN COALESCE(b.profit, 0)
            WHEN b.status = 'loss' THEN (b.amount * -1)
            ELSE 0
        END as win_loss
        FROM results r
        LEFT JOIN bets b ON r.session_id = b.session_id AND b.user_id = ?
        WHERE r.session_id < ?
        ORDER BY r.session_id DESC LIMIT 50
    ''', (uid, current_sid)).fetchall()
    
    conn.close()
    return render_template_string(HOME_HTML, user=user, current_sid=current_sid, history=history)

@app.route('/api/bet', methods=['POST'])
def api_bet():
    if not check_login(): return jsonify({'st': 'err', 'msg': 'Chưa đăng nhập'})
    
    # Rate Limit
    last_bet = session.get('last_bet_time', 0)
    if time.time() - last_bet < 1: # 1 second cooldown
        return jsonify({'st': 'err', 'msg': 'Thao tác quá nhanh!'})
    session['last_bet_time'] = time.time()
    
    data = request.json
    uid = session['uid']
    amt = int(data.get('amt', 0))
    type_ = data.get('type', '').strip()
    sid = int(data.get('sid', 0))
    
    if amt <= 0 or not type_ or not sid: return jsonify({'st': 'err', 'msg': 'Dữ liệu không hợp lệ'})
    if (time.time() % 60) >= 50: return jsonify({'st': 'err', 'msg': 'Phiên đã khoá'})
        
    bet_type = 'TĂNG' if 'TĂNG' in type_.upper() else 'GIẢM'
    
    conn = get_db_connection()
    try:
        user = conn.execute('SELECT balance, status FROM users WHERE id = ?', (uid,)).fetchone()
        if user['status'] == 'locked': return jsonify({'st': 'err', 'msg': 'Tài khoản bị khóa'})
        if user['balance'] < amt: return jsonify({'st': 'err', 'msg': 'Số dư không đủ'})
            
        existing = conn.execute('SELECT id FROM bets WHERE user_id=? AND session_id=? AND bet_type=? AND status=\'pending\'', (uid, sid, bet_type)).fetchone()
        if existing:
            conn.execute('UPDATE bets SET amount = amount + ? WHERE id=?', (amt, existing['id']))
        else:
            conn.execute('INSERT INTO bets (user_id, session_id, bet_type, amount, status) VALUES (?, ?, ?, ?, \'pending\')', (uid, sid, bet_type, amt))
            
        conn.execute('UPDATE users SET balance = balance - ? WHERE id=?', (amt, uid))
        conn.commit()
        return jsonify({'st': 'ok'})
    except Exception as e:
        conn.rollback()
        return jsonify({'st': 'err', 'msg': str(e)})
    finally:
        conn.close()

@app.route('/api/trans', methods=['POST'])
def api_trans():
    if not check_login(): return jsonify({'st': 'err', 'msg': 'Chưa đăng nhập'})
    data = request.json
    uid = session['uid']
    type_ = data.get('type')
    amount = int(data.get('amount', 0))
    
    if amount <= 0: return jsonify({'st': 'err', 'msg': 'Số tiền không hợp lệ'})
    
    conn = get_db_connection()
    try:
        if type_ == 'withdraw':
            user = conn.execute('SELECT balance FROM users WHERE id=?', (uid,)).fetchone()
            if user['balance'] < amount:
                return jsonify({'st': 'err', 'msg': 'Số dư không đủ'})
            conn.execute('UPDATE users SET balance = balance - ? WHERE id=?', (amount, uid))
            
        conn.execute('INSERT INTO transactions (user_id, type, amount, status) VALUES (?, ?, ?, \'pending\')', (uid, type_, amount))
        conn.commit()
        return jsonify({'st': 'ok', 'msg': 'Yêu cầu thành công, vui lòng chờ duyệt'})
    except Exception as e:
        conn.rollback()
        return jsonify({'st': 'err', 'msg': str(e)})
    finally:
        conn.close()

@app.route('/api/result')
def api_result():
    uid = session.get('uid')
    sid = int(request.args.get('sid', 0))
    if not uid or not sid: return jsonify({'st': 'err'})
    current_sid = get_current_sid()
    if sid >= current_sid: return jsonify({'st': 'wait'})
    conn = get_db_connection()
    res = conn.execute('SELECT result FROM results WHERE session_id = ?', (sid,)).fetchone()
    conn.close()
    if not res: return jsonify({'st': 'wait'})
    return jsonify({'st': 'ok', 'result': res['result']})

@app.route('/api/check_win')
def api_check_win():
    if not check_login(): return jsonify({'hasBet': False})
    uid = session['uid']
    conn = get_db_connection()
    bet = conn.execute('''
        SELECT b.*, r.result FROM bets b JOIN results r ON b.session_id = r.session_id 
        WHERE b.user_id = ? AND b.status IN ('win','loss') AND b.is_read = 0 LIMIT 1
    ''', (uid,)).fetchone()
    if bet:
        conn.execute('UPDATE bets SET is_read = 1 WHERE id = ?', (bet['id'],))
        conn.commit()
        is_win = (bet['status'] == 'win')
        win_amount = (bet['amount'] * 1.90) if is_win else 0
        new_bal = conn.execute('SELECT balance FROM users WHERE id=?', (uid,)).fetchone()['balance']
        conn.close()
        return jsonify({'hasBet': True, 'sid': bet['session_id'], 'status': bet['status'], 'win_amount': win_amount, 'new_balance': new_bal, 'bet_type': bet['bet_type'], 'result': bet['result'], 'bet_amount': bet['amount'], 'profit': bet['profit']})
    conn.close()
    return jsonify({'hasBet': False})

@app.route('/admin', methods=['GET', 'POST'])
def admin():
    if not check_login(): return redirect(url_for('login'))
    uid = session['uid']
    conn = get_db_connection()
    user = conn.execute('SELECT role FROM users WHERE id=?', (uid,)).fetchone()
    if user['role'] not in ['admin', 'mod']:
        conn.close()
        return "Access Denied"
        
    msg = ""
    current_sid = get_current_sid()
    
    if request.method == 'POST':
        # Live Control
        if 'kill_up' in request.form:
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (current_sid, 'GIẢM'))
            msg = "Đã BẺ CẦU -> GIẢM"
        elif 'kill_down' in request.form:
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (current_sid, 'TĂNG'))
            msg = "Đã BẺ CẦU -> TĂNG"
        elif 'win_small' in request.form:
            # Calculate bets
            bets = conn.execute('SELECT bet_type, amount FROM bets WHERE session_id=?', (current_sid,)).fetchall()
            up = sum(b['amount'] for b in bets if b['bet_type'] == 'TĂNG')
            down = sum(b['amount'] for b in bets if b['bet_type'] == 'GIẢM')
            res = 'GIẢM' if up > down else 'TĂNG'
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (current_sid, res))
            msg = f"Đã ĂN CỬA NHỎ -> {res} (Tăng:{up} vs Giảm:{down})"
            
        # Auto/Result
        elif 'auto_on' in request.form:
            conn.execute('UPDATE auto_results SET active=1')
            msg = "AUTO ON"
        elif 'auto_off' in request.form:
            conn.execute('UPDATE auto_results SET active=0')
            msg = "AUTO OFF"
        elif 'set_result' in request.form:
            sid = request.form.get('sid')
            res = request.form.get('res')
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (sid, res))
            msg = f"Set ID {sid} -> {res}"
            
        # User Actions
        elif 'create_user' in request.form:
            u = request.form.get('new_username')
            p = request.form.get('new_password')
            try:
                hp = generate_password_hash(p)
                conn.execute('INSERT INTO users (username, password, role, status) VALUES (?, ?, \'user\', \'active\')', (u, hp))
                msg = f"Tạo user {u} thành công"
            except: msg = "Lỗi tạo user"
        elif 'lock_user' in request.form:
            conn.execute('UPDATE users SET status=\'locked\' WHERE id=?', (request.form.get('uid'),))
            msg = "Đã khóa user"
        elif 'unlock_user' in request.form:
            conn.execute('UPDATE users SET status=\'active\' WHERE id=?', (request.form.get('uid'),))
            msg = "Đã mở khóa user"
        elif 'add_money' in request.form:
            amt = int(request.form.get('amount', 0))
            tuid = request.form.get('uid')
            conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (amt, tuid))
            conn.execute('INSERT INTO transactions (user_id, type, amount, status, info) VALUES (?, \'deposit_admin\', ?, \'completed\', \'Admin cộng tiền\')', (tuid, amt))
            msg = f"Đã cộng {amt} cho user {tuid}"
        elif 'update_user_full' in request.form:
            tuid = request.form.get('target_uid')
            bal = float(request.form.get('bal', 0) or 0)
            fullname = request.form.get('fullname', '').strip()
            bank_name = request.form.get('bank_name', '').strip()
            bank_number = request.form.get('bank_number', '').strip()
            bank_user = request.form.get('bank_user', '').strip()
            role = request.form.get('role', 'user').strip()
            user_pass = request.form.get('user_pass', '').strip()
            if role not in ['user', 'mod', 'admin']:
                role = 'user'
            conn.execute('UPDATE users SET balance=?, fullname=?, bank_name=?, bank_number=?, bank_user=?, role=? WHERE id=?',
                         (bal, fullname, bank_name, bank_number, bank_user, role, tuid))
            if user_pass:
                conn.execute('UPDATE users SET password=? WHERE id=?', (user_pass, tuid))
            msg = f"Đã cập nhật user ID {tuid}"
            

        # Transaction Actions
        elif 'approve_trans' in request.form:
            tid = request.form.get('tid')
            trans = conn.execute('SELECT * FROM transactions WHERE id=?', (tid,)).fetchone()
            if trans and trans['status'] == 'pending':
                if trans['type'] == 'deposit':
                    conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (trans['amount'], trans['user_id']))
                conn.execute('UPDATE transactions SET status=\'completed\' WHERE id=?', (tid,))
                msg = "Đã duyệt giao dịch"
        elif 'reject_trans' in request.form:
            tid = request.form.get('tid')
            trans = conn.execute('SELECT * FROM transactions WHERE id=?', (tid,)).fetchone()
            if trans and trans['status'] == 'pending':
                if trans['type'] == 'withdraw':
                    conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (trans['amount'], trans['user_id']))
                conn.execute('UPDATE transactions SET status=\'rejected\' WHERE id=?', (tid,))
                msg = "Đã hủy giao dịch"
                
        # Bet Actions
        elif 'delete_bet' in request.form:
            bid = request.form.get('bid')
            bet = conn.execute('SELECT * FROM bets WHERE id=?', (bid,)).fetchone()
            if bet:
                conn.execute('DELETE FROM bets WHERE id=?', (bid,))
                conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (bet['amount'], bet['user_id']))
                msg = "Đã xóa lệnh và hoàn tiền"
                
        # System Actions
        elif 'clear_history' in request.form:
            conn.execute('DELETE FROM results')
            conn.execute('DELETE FROM bets')
            conn.execute('DELETE FROM transactions')
            msg = "Đã xóa toàn bộ dữ liệu lịch sử"
            
        conn.commit()
        
    # Fetch Live Data
    live_bets = conn.execute('SELECT b.*, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.session_id = ? ORDER BY b.id DESC', (current_sid,)).fetchall()
    total_up = sum(b['amount'] for b in live_bets if b['bet_type'] == 'TĂNG')
    total_down = sum(b['amount'] for b in live_bets if b['bet_type'] == 'GIẢM')
    
    # Fetch Other Data
    users_list = conn.execute('SELECT * FROM users ORDER BY id DESC').fetchall()
    pending_trans = conn.execute('SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status=\'pending\' ORDER BY t.id DESC').fetchall()
    recent_results = conn.execute('SELECT * FROM results ORDER BY session_id DESC LIMIT 10').fetchall()
    future_sids = [current_sid + i for i in range(0, 21)]
    future_rows = conn.execute('SELECT session_id, result FROM results WHERE session_id >= ? AND session_id <= ? ORDER BY session_id ASC', (current_sid, current_sid + 20)).fetchall()
    future_results = {row['session_id']: row['result'] for row in future_rows}
    bet_history = conn.execute('SELECT b.*, u.username FROM bets b JOIN users u ON b.user_id = u.id ORDER BY b.id DESC LIMIT 100').fetchall()
    recent_trans = conn.execute('SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.id DESC LIMIT 100').fetchall()
    
    try:
        auto_active = conn.execute('SELECT active FROM auto_results').fetchone()['active']
    except:
        auto_active = 0
    
    conn.close()
    return render_template_string(ADMIN_HTML, msg=msg, users_list=users_list, pending_trans=pending_trans, 
                                  live_bets=live_bets, total_up=total_up, total_down=total_down, 
                                  current_sid=current_sid, time_now=time.time(), recent_results=recent_results,
                                  auto_active=auto_active, bet_history=bet_history, recent_trans=recent_trans, future_sids=future_sids, future_results=future_results)

if __name__ == '__main__':
    app.run(debug=True, port=5000)
