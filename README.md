# Python Backend

This is the Python port of the trading system backend, using Flask and SQLite.

## Prerequisites

- Python 3.8+
- pip

## Setup

1.  **Install Dependencies**:
    ```bash
    pip install -r requirements.txt
    ```

2.  **Initialize Database**:
    ```bash
    python db.py
    ```
    This will create `trade.db`.

3.  **Run the Server**:
    ```bash
    python app.py
    ```
    The server will start at `http://localhost:5000`.

4.  **Run the Cron Job** (Background Task):
    Open a new terminal and run:
    ```bash
    python cron.py
    ```
    This script handles the result calculation and payouts at the end of each session.

## Structure

- `app.py`: Main Flask application containing all API endpoints and page routes.
- `db.py`: Database connection and schema initialization.
- `cron.py`: Background script to process rewards and enforce "House Wins" logic if no admin result is set.
- `templates/`: HTML templates for the frontend pages (Home, Admin, etc.).
- `static/`: Static files (CSS, JS, Images).

## Features Ported

- **Auth**: Session-based authentication.
- **Betting**: Real-time betting API with balance checks and time locking.
- **Admin**: Admin panel to set results manually and toggle Auto mode.
- **Logic**:
    - **Sync IDs**: History and API results are filtered to show only completed sessions.
    - **No Random**: Auto mode uses "House Wins" logic, not random.
    - **Admin Priority**: Admin-set results are respected 100%.
