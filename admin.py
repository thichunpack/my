from app import (
    app,
    ADMIN_HTML,
    check_login,
    get_db_connection,
    get_current_sid,
    generate_password_hash,
    redirect,
    render_template_string,
    request,
    session,
    time,
    url_for,
)


def admin_handler_full():
    if not check_login():
        return redirect(url_for('login'))

    uid = session['uid']
    conn = get_db_connection()
    user = conn.execute('SELECT role FROM users WHERE id=?', (uid,)).fetchone()
    if user['role'] not in ['admin', 'mod']:
        conn.close()
        return 'Access Denied'

    msg = ''
    current_sid = get_current_sid()

    if request.method == 'POST':
        # Live Control
        if 'kill_up' in request.form:
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (current_sid, 'GIẢM'))
            msg = 'Đã BẺ CẦU -> GIẢM'
        elif 'kill_down' in request.form:
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (current_sid, 'TĂNG'))
            msg = 'Đã BẺ CẦU -> TĂNG'
        elif 'win_small' in request.form:
            bets = conn.execute('SELECT bet_type, amount FROM bets WHERE session_id=?', (current_sid,)).fetchall()
            up = sum(b['amount'] for b in bets if b['bet_type'] == 'TĂNG')
            down = sum(b['amount'] for b in bets if b['bet_type'] == 'GIẢM')
            res = 'GIẢM' if up > down else 'TĂNG'
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (current_sid, res))
            msg = f'Đã ĂN CỬA NHỎ -> {res} (Tăng:{up} vs Giảm:{down})'

        # Auto/Result
        elif 'auto_on' in request.form:
            conn.execute('UPDATE auto_results SET active=1')
            msg = 'AUTO ON'
        elif 'auto_off' in request.form:
            conn.execute('UPDATE auto_results SET active=0')
            msg = 'AUTO OFF'
        elif 'set_result' in request.form:
            sid = request.form.get('sid')
            res = request.form.get('res')
            conn.execute('INSERT OR REPLACE INTO results (session_id, result) VALUES (?, ?)', (sid, res))
            msg = f'Set ID {sid} -> {res}'

        # User Actions
        elif 'create_user' in request.form:
            u = request.form.get('new_username')
            p = request.form.get('new_password')
            try:
                hp = generate_password_hash(p)
                conn.execute(
                    "INSERT INTO users (username, password, role, status) VALUES (?, ?, 'user', 'active')",
                    (u, hp),
                )
                msg = f'Tạo user {u} thành công'
            except Exception:
                msg = 'Lỗi tạo user'
        elif 'lock_user' in request.form:
            conn.execute("UPDATE users SET status='locked' WHERE id=?", (request.form.get('uid'),))
            msg = 'Đã khóa user'
        elif 'unlock_user' in request.form:
            conn.execute("UPDATE users SET status='active' WHERE id=?", (request.form.get('uid'),))
            msg = 'Đã mở khóa user'
        elif 'add_money' in request.form:
            amt = int(request.form.get('amount', 0))
            tuid = request.form.get('uid')
            conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (amt, tuid))
            conn.execute(
                "INSERT INTO transactions (user_id, type, amount, status, info) VALUES (?, 'deposit_admin', ?, 'completed', 'Admin cộng tiền')",
                (tuid, amt),
            )
            msg = f'Đã cộng {amt} cho user {tuid}'
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
            conn.execute(
                'UPDATE users SET balance=?, fullname=?, bank_name=?, bank_number=?, bank_user=?, role=? WHERE id=?',
                (bal, fullname, bank_name, bank_number, bank_user, role, tuid),
            )
            if user_pass:
                conn.execute('UPDATE users SET password=? WHERE id=?', (user_pass, tuid))
            msg = f'Đã cập nhật user ID {tuid}'

        # Transaction Actions
        elif 'approve_trans' in request.form:
            tid = request.form.get('tid')
            trans = conn.execute('SELECT * FROM transactions WHERE id=?', (tid,)).fetchone()
            if trans and trans['status'] == 'pending':
                if trans['type'] == 'deposit':
                    conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (trans['amount'], trans['user_id']))
                conn.execute("UPDATE transactions SET status='completed' WHERE id=?", (tid,))
                msg = 'Đã duyệt giao dịch'
        elif 'reject_trans' in request.form:
            tid = request.form.get('tid')
            trans = conn.execute('SELECT * FROM transactions WHERE id=?', (tid,)).fetchone()
            if trans and trans['status'] == 'pending':
                if trans['type'] == 'withdraw':
                    conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (trans['amount'], trans['user_id']))
                conn.execute("UPDATE transactions SET status='rejected' WHERE id=?", (tid,))
                msg = 'Đã hủy giao dịch'

        # Bet Actions
        elif 'delete_bet' in request.form:
            bid = request.form.get('bid')
            bet = conn.execute('SELECT * FROM bets WHERE id=?', (bid,)).fetchone()
            if bet:
                conn.execute('DELETE FROM bets WHERE id=?', (bid,))
                conn.execute('UPDATE users SET balance = balance + ? WHERE id=?', (bet['amount'], bet['user_id']))
                msg = 'Đã xóa lệnh và hoàn tiền'

        # System Actions
        elif 'clear_history' in request.form:
            conn.execute('DELETE FROM results')
            conn.execute('DELETE FROM bets')
            conn.execute('DELETE FROM transactions')
            msg = 'Đã xóa toàn bộ dữ liệu lịch sử'

        conn.commit()

    live_bets = conn.execute(
        'SELECT b.*, u.username FROM bets b JOIN users u ON b.user_id = u.id WHERE b.session_id = ? ORDER BY b.id DESC',
        (current_sid,),
    ).fetchall()
    total_up = sum(b['amount'] for b in live_bets if b['bet_type'] == 'TĂNG')
    total_down = sum(b['amount'] for b in live_bets if b['bet_type'] == 'GIẢM')

    users_list = conn.execute('SELECT * FROM users ORDER BY id DESC').fetchall()
    pending_trans = conn.execute(
        "SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status='pending' ORDER BY t.id DESC"
    ).fetchall()
    recent_results = conn.execute('SELECT * FROM results ORDER BY session_id DESC LIMIT 200').fetchall()
    future_sids = [sid for sid in range(current_sid - 30, current_sid + 61)]
    future_rows = conn.execute(
        'SELECT session_id, result FROM results WHERE session_id >= ? AND session_id <= ? ORDER BY session_id ASC',
        (current_sid - 30, current_sid + 60),
    ).fetchall()
    future_results = {row['session_id']: row['result'] for row in future_rows}
    bet_history = conn.execute(
        'SELECT b.*, u.username FROM bets b JOIN users u ON b.user_id = u.id ORDER BY b.id DESC LIMIT 1000'
    ).fetchall()
    recent_trans = conn.execute(
        'SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.id DESC LIMIT 1000'
    ).fetchall()

    try:
        auto_active = conn.execute('SELECT active FROM auto_results').fetchone()['active']
    except Exception:
        auto_active = 0

    conn.close()
    return render_template_string(
        ADMIN_HTML,
        msg=msg,
        users_list=users_list,
        pending_trans=pending_trans,
        live_bets=live_bets,
        total_up=total_up,
        total_down=total_down,
        current_sid=current_sid,
        time_now=time.time(),
        recent_results=recent_results,
        auto_active=auto_active,
        bet_history=bet_history,
        recent_trans=recent_trans,
        future_sids=future_sids,
        future_results=future_results,
    )


# Override existing /admin view with full handler from this module.
app.view_functions['admin'] = admin_handler_full


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
