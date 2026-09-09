# PHP deployment configuration

The following files contain environment-specific credentials in the local legacy
application and are intentionally excluded from Git:

- `database.php`
- `jwt.php`
- `vapid.php`
- `config.php`

For a deployment, provision these files from the approved secret manager or
deployment environment. Do not copy a local production configuration into the
repository. The PHP dependencies are restored from the tracked `composer.lock`
with `composer install --no-dev --optimize-autoloader` in the PHP application
directory.
