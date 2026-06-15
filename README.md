# LAB COFFEE Backend Admin System (PHP & MySQL)

This is a modular and secure backend system designed in native PHP and MySQL to manage the data for the LAB COFFEE & Trading Lounge website. It serves JSON endpoints for your React/Vite frontend and handles admin dashboard session authentication and file uploads safely.

---

## 📂 Directory Structure

*   `config/database.php` - PDO database connection settings.
*   `api/get_menu.php` - Fetches products from MySQL and outputs JSON with CORS enabled for your React app.
*   `admin/auth.php` - Authentication session verification utility.
*   `admin/login.php` - Handles admin logins (session setup).
*   `admin/upload.php` - Secure file uploader (stores files in `public/uploads/` directory).
*   `schema.sql` - Database table structures and default user credentials seed.

---

## 🚀 Setup & Installation

1.  **Database Configuration:**
    *   Create a MySQL database named `lab_coffee`.
    *   Import the tables and seed data using `schema.sql`:
        ```bash
        mysql -u root -p lab_coffee < schema.sql
        ```
    *   Configure connection credentials (host, db_name, username, password) inside `config/database.php`.

2.  **Default Admin Account:**
    *   **Username:** `admin`
    *   **Password:** `admin123`
    *   *Note: In production, generate a new hash using PHP's `password_hash('your_new_password', PASSWORD_BCRYPT)` and update the `users` table.*

---

## 🔒 Security Best Practices & Directory Permissions

As a Senior Developer, please follow these guidelines to secure your web server:

### 1. Upload Directory Folder Permissions
The uploader script writes image files to `public/uploads/`.
*   **Ownership:** Ensure the owner of the `uploads/` folder is the web server process user (e.g. `www-data` on Apache/Ubuntu, `nginx` on Nginx, or `IUSR` on Windows IIS).
*   **Write Permissions:** The folder needs write access, but **never** grant `777` permissions (read/write/execute for everyone) in production.
*   **Correct Permissions:**
    *   On Linux/macOS, use: `chmod 755 public/uploads` or `chmod 750 public/uploads`.
    *   On Windows, right-click the folder, go to Security, and grant "Modify" and "Write" permissions specifically to the `IIS_IUSRS` or Web Server user.

### 2. Disable PHP Script Execution inside `/uploads`
Even though our script checks file extensions and MIME types, you should enforce an extra layer of server-level defense to prevent anyone from executing malicious PHP scripts (e.g. `web_shell.php`) if they bypass our filters.

#### For Apache servers:
Create a file named `.htaccess` inside your `public/uploads/` folder with the following content:
```apache
# Disable PHP execution inside the uploads folder
<Files *.php>
    deny from all
</Files>
RemoveHandler .php .phtml .php3 .php4 .php5 .phps
RemoveType .php .phtml .php3 .php4 .php5 .phps
php_flag engine off
```

#### For Nginx servers:
Add the following location block inside your server configuration file to deny executing scripts in the uploads directory:
```nginx
location ~* ^/uploads/.*.(php|phtml|php3|php4|php5|phps)$ {
    deny all;
    access_log off;
    log_not_found off;
}
```

### 3. PDO Prepared Statements
*   All queries inside `login.php`, `get_menu.php`, etc., strictly use PDO Prepared Statements (e.g. `$stmt->prepare()`).
*   This separates database queries from the inputs, completely eliminating the risk of SQL Injection attacks. Never concatenate variables directly into SQL queries.
