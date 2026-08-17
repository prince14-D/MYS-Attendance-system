# MySQL setup on Ubuntu

Install MySQL and the PHP MySQL extension:

```bash
sudo apt update
sudo apt install mysql-server php-mysql
sudo mysql
```

Inside the MySQL prompt, create an application account:

```sql
CREATE USER 'mys_attendance_user'@'localhost' IDENTIFIED BY '3246prince';
GRANT ALL PRIVILEGES ON mys_attendance.* TO 'mys_attendance_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Create the database tables from the project directory:

```bash
mysql -u mys_attendance_user -p < database/schema.sql
```

Then copy `database/config.mysql.php.example` to `database/config.mysql.php`, enter the same password, and keep that real config file out of Git.

The current application still reads the JSON files in `storage/`. The schema is the safe first step; switching the live application to MySQL requires updating its storage functions and migrating the existing JSON records.
