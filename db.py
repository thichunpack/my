import sqlite3
import os

DB_PATH = 'trade.db'

def get_db_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    conn = get_db_connection()
    cursor = conn.cursor()
    
    # Users
    cursor.execute('''
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
            bank_name TEXT,
            bank_number TEXT,
            bank_user TEXT,
            cccd_front TEXT,
            cccd_back TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # Bets
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS bets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            amount REAL,
            bet_type TEXT,
            session_id INTEGER,
            status TEXT DEFAULT 'pending',
            profit REAL DEFAULT 0,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # Results
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS results (
            session_id INTEGER PRIMARY KEY,
            result TEXT
        )
    ''')

    # Transactions
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            type TEXT,
            amount REAL,
            info TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # Settings
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            key_value TEXT
        )
    ''')
    
    # Auto Results (Flag)
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS auto_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            active INTEGER DEFAULT 0
        )
    ''')
    # Ensure one row exists
    cursor.execute("INSERT OR IGNORE INTO auto_results (id, active) VALUES (1, 0)")

    conn.commit()
    conn.close()

if __name__ == '__main__':
    init_db()
    print("Database initialized.")
