import paramiko
from scp import SCPClient
import os

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
print("Connected")

# SCP the fixed LLM file
local_llm = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix\src\clickfix_llm.php"
remote_llm = "/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_llm.php"
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(local_llm, remote_llm)
scp.close()
print("SCP: clickfix_llm.php OK")

# Verify
_, out, _ = ssh.exec_command("grep -c 'function clickfix_llm_ensure_table' " + remote_llm)
count = out.read().decode().strip()
print("ensure_table count on server:", count)

# Run migration directly via PHP
_, out, err = ssh.exec_command(
    "cd /home/parthenoun/ClickFix/Web/ClickFix && "
    "php -r \"require_once 'src/clickfix_core.php'; require_once 'src/clickfix_llm.php'; "
    "require_once 'src/clickfix_domain_feeds.php'; "
    "\\$pdo = clickfix_open_db(true); clickfix_llm_ensure_table(\\$pdo); echo 'LLM tables OK\n'; "
    "clickfix_domain_feeds_ensure_table(\\$pdo); echo 'Feed tables OK\n';\" 2>&1",
    timeout=30
)
print("Migration:", out.read().decode().strip())
e = err.read().decode().strip()
if e:
    print("ERR:", e[:400])

# Run fetch
_, out, err = ssh.exec_command(
    "cd /home/parthenoun/ClickFix/Web/ClickFix && php scripts/fetch_all.php 2>&1",
    timeout=120
)
print("\nFetch:\n" + out.read().decode().strip()[:2000])

ssh.close()
print("\nDone")
