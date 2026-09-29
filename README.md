# Jerry's Clock Repair — Appointment Tracker (local)

Plain PHP + MySQL app. No frameworks, no auth — just a form and a records list,
running locally via the PHP built-in server.

## Files

- `schema.sql` — creates the `jerry_database` database and `appointments` table
- `db.php` — database connection settings (edit if your MySQL user/password differ)
- `index.php` — the new-appointment form (handles saving on submit), and,
  when visited as `index.php?id=N`, the full customer/service detail view
  for record `N` — editable fields plus activity log and service history
- `records.php` — lists all saved appointments (newest first); click a
  record to open its full detail view at `index.php?id=N`
- `style.css` — shared styling

## 1. Start MySQL

This machine has XAMPP/LAMPP installed at `/opt/lampp`. Its MySQL data directory
is owned by the `mysql` system user, so starting it requires root. Run this from
a normal terminal (not through Claude Code, since sudo needs an interactive
password prompt):

```
sudo /opt/lampp/lampp startmysql
```

To stop it later:

```
sudo /opt/lampp/lampp stopmysql
```

## 2. Load the database schema

This only needs to be done once:

```
/opt/lampp/bin/mysql -u root < schema.sql
```

(If your local MySQL root user has a password, add `-p` and enter it when prompted.)

This creates the `jerry_database` database and the `appointments`,
`activity_log`, and `service_history` tables.

If you already ran an older version of `schema.sql`, the `appointments`
table already exists and `CREATE TABLE IF NOT EXISTS` will **not** add the
new columns to it. Either drop and recreate the table, or manually `ALTER
TABLE appointments ADD COLUMN ...` to match `schema.sql`.

## 3. Check `db.php` credentials

By default `db.php` connects as `root` with no password, matching a stock
XAMPP/LAMPP install:

```php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'jerry_database';
$DB_USER = 'root';
$DB_PASS = '';
```

Edit these if your local MySQL setup differs.

## 4. Run the PHP built-in server

From this project directory:

```
/opt/lampp/bin/php -S localhost:8000
```

(If `php` is already on your PATH and resolves to a compatible version, plain
`php -S localhost:8000` works too.)

## 5. Use it

- Open http://localhost:8000/index.php to enter a new appointment.
- Open http://localhost:8000/records.php to see all saved appointments,
  newest appointment time first, and click one to open its full record
  at http://localhost:8000/index.php?id=N.

## Notes

- Required fields: client first/last name, client phone, appointment time,
  staff first/last name, and clock model/type. Everything else is optional.
- This is a bare local version on purpose — no login, no permissions, no
  sharing. Add those later once the basic flow is confirmed working for you.
