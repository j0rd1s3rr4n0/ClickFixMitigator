# ClickFix Server Deploy (Copy/Paste)

Target deploy path:

- Filesystem (example): `/var/www/clickfix.jordiserrano.me`
- URL base: `https://clickfix.jordiserrano.me/`

## 1) Copy files to server

Copy the full `Web/ClickFix/` folder contents into your vhost docroot (example):

```bash
/var/www/clickfix.jordiserrano.me
```

## 2) Run one command (recommended)

From inside your deploy folder (example: `/var/www/clickfix.jordiserrano.me`):

```bash
bash scripts/server_install.sh --domain clickfix.jordiserrano.me --path / --web-user www-data --web-group www-data
```

What it does automatically:

- Creates required directories (`data/`, `data/backups/`, `data/sessions/`, `data/logs/`, `keys/`)
- Creates required list files (`clickfixlist`, `clickfixallowlist`, `alertsites`)
- Generates keys + `.env.security` (`scripts/generate_keys.php`)
- Applies DB migration without deleting data (`scripts/migrate.php`)
- Sets permissions/ownership
- Runs strict preflight checks (`scripts/preflight.php --strict`)

## 3) If ownership fails (no sudo)

Run:

```bash
bash scripts/server_install.sh --domain clickfix.jordiserrano.me --path / --skip-chown
```

Then manually apply ownership:

```bash
sudo chown -R www-data:www-data /var/www/clickfix.jordiserrano.me/data /var/www/clickfix.jordiserrano.me/keys
sudo chown www-data:www-data /var/www/clickfix.jordiserrano.me/.env.security /var/www/clickfix.jordiserrano.me/clickfixlist /var/www/clickfix.jordiserrano.me/clickfixallowlist /var/www/clickfix.jordiserrano.me/alertsites
```

## 4) Quick health checks

```bash
php scripts/preflight.php --strict
curl -I https://clickfix.jordiserrano.me/dashboard.php
curl -I https://clickfix.jordiserrano.me/clickfix-report.php
```

## 5) Notes

- Migration is non-destructive: existing data is preserved.
- `.htaccess` rules in this folder block direct access to `.env`, sqlite files, logs, and private keys.
- If you deploy under subpath instead of root, pass it to the install script with `--path` and keep exact casing.
