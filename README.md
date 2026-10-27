# Railway deployment

This application runs on Railway using the included Dockerfile, PHP 8.3, Apache, and MySQLi.

## Deploy the web service

1. Create a **private** GitHub repository and push the application files. Do not commit `.env` files, SQL exports, or database credentials. `register.sql` and `login.sql` are ignored because SQL exports may contain private student data.
2. In Railway, create a project and deploy the GitHub repository as a service. Railway uses `railway.toml` and `Dockerfile` to build the service.
3. Add a Railway MySQL service to the project. Use Railway's private/internal database connection values; do not use a public TCP proxy for the app-to-database connection.
4. In the web service variables, set `APP_ENV=production` and connect these variables to the MySQL service's corresponding values:
   - `DB_HOST` ← `MYSQLHOST`
   - `DB_PORT` ← `MYSQLPORT`
   - `DB_NAME` ← `MYSQLDATABASE`
   - `DB_USER` ← `MYSQLUSER`
   - `DB_PASSWORD` ← `MYSQLPASSWORD`

   Railway's variable-reference picker can insert the references. The PHP connection code also recognizes the `MYSQL*` names directly.
5. To create the first registrar account on a fresh database, temporarily set `BOOTSTRAP_ADMIN_EMAIL`, `BOOTSTRAP_ADMIN_STUDENT_ID`, and a strong `BOOTSTRAP_ADMIN_PASSWORD` in the web service variables. Deploy and confirm that the account is created, then remove those bootstrap variables. The password is never hard-coded for production.
6. Generate a Railway domain and test registration, login, appointment booking, queue calls, and the public display. Keep the existing registrar account if importing a database that already contains one.

## Database migration and data

The application creates missing tables and applies several schema updates when it connects. Give the web service database user the required schema privileges.

For an existing local database, export the actual database from XAMPP/phpMyAdmin and import it into the Railway MySQL service using a secure database client. Protect the export as sensitive data and delete temporary copies after verifying the import. Do not import `register.sql` over a database containing data: it is a local SQL dump with `DROP TABLE` statements and can erase tables. Back up the Railway database before any import or migration.

The Railway health check uses `/health.php`. Apache listens on Railway's assigned `PORT`; the server does not need a public admin route to pass the health check.
