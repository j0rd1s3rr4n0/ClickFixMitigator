import os
import paramiko
from scp import SCPClient

PASS_FILE = r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass"
HOST = "213.165.80.114"
PORT = 22
USER = "parthenoun"

with open(PASS_FILE, "r") as f:
    password = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, PORT, USER, password, timeout=15)
print("Connected.")

# Create migration script on server
migrate_cmd = """cat > /tmp/clickfix_migrate.php << 'PHPEOF'
<?php
require_once '/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_core.php';
require_once '/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_llm.php';
require_once '/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_domain_feeds.php';
$pdo = clickfix_open_db(true);
echo "DB opened\\n";
clickfix_llm_ensure_table($pdo);
echo "LLM tables OK\\n";
clickfix_domain_feeds_ensure_table($pdo);
echo "Domain feed tables OK\\n";
echo "Migration complete\\n";
PHPEOF
php /tmp/clickfix_migrate.php"""

print("Running migration...")
stdin, stdout, stderr = ssh.exec_command(migrate_cmd, timeout=30)
print(stdout.read().decode())
err = stderr.read().decode()
if err:
    print("ERR:", err)

print("\nRunning fetch_all.php...")
stdin, stdout, stderr = ssh.exec_command('php /home/parthenoun/ClickFix/Web/ClickFix/scripts/fetch_all.php 2>&1', timeout=120)
print(stdout.read().decode())
err = stderr.read().decode()
if err:
    print("ERR:", err)

ssh.close()
print("Done.")
