import time
import math
from db import get_db_connection

def process_rewards():
    conn = get_db_connection()
    try:
        current_sid = math.floor(time.time() / 60)
        last_sid = current_sid - 1
        
        # Check pending bets for last session
        bets = conn.execute("SELECT * FROM bets WHERE session_id = ? AND status = 'pending'", (last_sid,)).fetchall()
        
        if not bets:
            print(f"No pending bets for session {last_sid}")
            return

        # 1. Check if Admin set result
        res = conn.execute("SELECT result FROM results WHERE session_id = ?", (last_sid,)).fetchone()
        final_res = res['result'] if res else None
        
        # 2. If not set, calculate House Win
        if not final_res:
            sums = conn.execute("SELECT bet_type, SUM(amount) as total FROM bets WHERE session_id = ? GROUP BY bet_type", (last_sid,)).fetchall()
            totals = {row['bet_type']: row['total'] for row in sums}
            
            up_total = totals.get('TĂNG', 0)
            down_total = totals.get('GIẢM', 0)
            
            final_res = 'GIẢM' if up_total > down_total else 'TĂNG'
            
            conn.execute("INSERT OR IGNORE INTO results (session_id, result) VALUES (?, ?)", (last_sid, final_res))
            print(f"Auto-generated result for {last_sid}: {final_res}")
        else:
            print(f"Using admin result for {last_sid}: {final_res}")

        # 3. Payout
        for bet in bets:
            is_win = (bet['bet_type'] == final_res)

            if is_win:
                profit = bet['amount'] * 0.9
                payout = bet['amount'] + profit
                conn.execute("UPDATE users SET balance = balance + ? WHERE id = ?", (payout, bet['user_id']))
                conn.execute("UPDATE bets SET status = 'win', profit = ? WHERE id = ?", (profit, bet['id']))
                conn.execute(
                    "INSERT INTO transactions (user_id, type, amount, info, status) VALUES (?, 'reward', ?, ?, 'completed')",
                    (bet['user_id'], payout, f"Trả thưởng phiên {last_sid} | Cược {bet['bet_type']} | KQ {final_res} | Lãi {profit}")
                )
            else:
                conn.execute("UPDATE bets SET status = 'loss', profit = ? WHERE id = ?", (-bet['amount'], bet['id']))
                conn.execute(
                    "INSERT INTO transactions (user_id, type, amount, info, status) VALUES (?, 'bet_settle', ?, ?, 'completed')",
                    (bet['user_id'], -bet['amount'], f"Chốt phiên {last_sid} | Cược {bet['bet_type']} | KQ {final_res} | Không trúng")
                )
                
        conn.commit()
        print(f"Processed rewards for session {last_sid}")
        
    except Exception as e:
        print(f"Error: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == "__main__":
    print("Starting Cron Job...")
    while True:
        process_rewards()
        time.sleep(5) # Check every 5 seconds
