# Backend PHP

This directory contains the PHP backend files for the trading system.

## Changes Made

1.  **Admin Panel (`admin.php`)**:
    *   **Removed Random Auto-Generation**: The "AUTO ON" feature no longer generates random results.
    *   **Sorted IDs**: Results are sorted by `session_id` ASC.

2.  **Home & API (`home.php`, `api.php`, `history.php`, `api_result.php`)**:
    *   **Sync IDs & Security**: Modified ALL queries to only fetch results for **completed sessions** (`session_id < current_sid`).
    *   **Prevent Leaks**: Future results set by Admin are now strictly hidden from users until the session ends. This applies to the Home chart, History page, and API endpoints.
    *   **Consistent Display**: The history bar now strictly follows the timeline and matches the finalized results in the database.

3.  **Logic Flow**:
    *   **Admin Priority**: Admin-set results are respected 100%.
    *   **System Logic**: If no admin result, system uses "House Wins" logic.

## Setup

1.  Ensure your web server (Apache/Nginx) points to this directory.
2.  The database `trade.db` (SQLite) will be automatically created.
