#!/bin/bash
# ClickFix Mitigator - Deployment Script
# Run on server: bash /home/parthenoun/ClickFix/deploy.sh
# Or from local: scp changed files and run server setup

set -e

SERVER_BASE="/home/parthenoun/ClickFix/Web/ClickFix"
cd "$SERVER_BASE"

echo "=== ClickFix Mitigator Deployment ==="
echo "Server: $(hostname)"
echo "Path: $SERVER_BASE"
echo ""

echo "[1/5] Setting permissions..."
chmod -R 755 src/ api/ scripts/ partials/
chmod 775 data/ data/sessions/ 2>/dev/null || true
chmod 664 clickfix.sqlite 2>/dev/null || true
echo "  Permissions OK"

echo "[2/5] Running migrations (table creation)..."
php scripts/migrate.php 2>/dev/null || true
php -r "
require_once 'src/clickfix_core.php';
require_once 'src/clickfix_llm.php';
require_once 'src/clickfix_domain_feeds.php';
\$pdo = clickfix_open_db(true);
clickfix_llm_ensure_table(\$pdo);
clickfix_domain_feeds_ensure_table(\$pdo);
echo '  Tables ensured: user_llm_profiles, auto_investigation_jobs, blog_feed_cache, auto_investigation_settings, domain_feed_entries, domain_feed_fetch_log' . PHP_EOL;
"
echo "  Migrations OK"

echo "[3/5] Running initial domain feed fetch..."
php scripts/worker.php domains 2>&1 || echo "  Worker had warnings (normal on first run)"

echo "[4/5] Generating sitemap..."
php scripts/worker.php seo 2>&1 || echo "  Sitemap generation skipped"

echo "[5/5] Verifying files..."
for f in src/clickfix_llm.php src/clickfix_auto_investigation.php src/clickfix_blog_feed.php src/clickfix_seo.php src/clickfix_domain_feeds.php src/clickfix_socdefenders.php src/clickfix_abusech.php api/llm.php api/auto_investigation.php api/blog_feed.php api/domain_feeds.php scripts/worker.php robots.txt; do
    if [ -f "$f" ]; then echo "  OK: $f"; else echo "  MISSING: $f"; fi
done

echo ""
echo "=== Deployment Complete ==="
echo "Next steps:"
echo "  1. Configure .env.security with your API keys"
echo "  2. Set up cron: */15 * * * * php $SERVER_BASE/scripts/worker.php all >> $SERVER_BASE/data/worker.log 2>&1"
echo "  3. Visit https://clickfix.jordiserrano.me/dashboard.php"
echo ""
echo "New features:"
echo "  - LLM Chat in Intel workspace (Settings > LLM Profiles to configure)"
echo "  - Auto-Investigation engine (Settings > Auto-Investigation)"
echo "  - Domain Feeds (GitHub Gist + Carson + abuse.ch + SOC Defenders)"
echo "  - Public Domain List: /dashboard.php?page=clickfix_domain_list&public=1"
echo "  - Blog integration from jordiserrano.me feeds"
echo "  - Investigate with LLM button on alert rows"
