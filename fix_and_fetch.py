import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
print("Connected")

php_code = r"""<?php
require_once '/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_core.php';
require_once '/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_llm.php';
require_once '/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_domain_feeds.php';
$pdo = clickfix_open_db(true);
echo "DB opened\n";
clickfix_llm_ensure_table($pdo);
echo "LLM tables OK\n";
clickfix_domain_feeds_ensure_table($pdo);
echo "Domain feed tables OK\n";
echo "Migration done\n";
?>"""

sftp = ssh.open_sftp()
f = sftp.file("/tmp/mig_fix.php", "w")
f.write(php_code)
f.close()
sftp.close()

print("Running migration...")
_, stdout, stderr = ssh.exec_command("php /tmp/mig_fix.php 2>&1", timeout=30)
print(stdout.read().decode().strip())
e = stderr.read().decode().strip()
if e:
    print("ERR:", e[:400])

print("\nRunning fetch_all...")
_, stdout, stderr = ssh.exec_command("php /home/parthenoun/ClickFix/Web/ClickFix/scripts/fetch_all.php 2>&1", timeout=120)
print(stdout.read().decode().strip())
e = stderr.read().decode().strip()
if e:
    print("ERR:", e[:400])

ssh.close()
print("\nDone")
