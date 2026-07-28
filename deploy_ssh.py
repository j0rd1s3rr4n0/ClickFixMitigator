import os
import sys
import paramiko
from scp import SCPClient

PASS_FILE = r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass"
HOST = "213.165.80.114"
PORT = 22
USER = "parthenoun"
REMOTE_BASE = "/home/parthenoun/ClickFix/Web/ClickFix"
LOCAL_BASE = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"

FILES = [
    "src/clickfix_core.php",
    "src/clickfix_llm.php",
    "src/clickfix_auto_investigation.php",
    "src/clickfix_blog_feed.php",
    "src/clickfix_seo.php",
    "src/clickfix_domain_feeds.php",
    "src/clickfix_socdefenders.php",
    "src/clickfix_abusech.php",
    "api/llm.php",
    "api/auto_investigation.php",
    "api/blog_feed.php",
    "api/domain_feeds.php",
    "scripts/worker.php",
    "scripts/fetch_all.php",
    "dashboard.php",
    "deploy.php",
    "partials/dashboard_sidebar.php",
    "partials/dashboard_style.php",
    "partials/dashboard_scripts.php",
    "robots.txt",
    ".env.security.example",
]

with open(PASS_FILE, "r") as f:
    password = f.read().strip()

print(f"Connecting to {USER}@{HOST}:{PORT}...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, PORT, USER, password, timeout=15, banner_timeout=15)
print("Connected.")

scp = SCPClient(ssh.get_transport(), socket_timeout=30)

for f in FILES:
    local = os.path.join(LOCAL_BASE, f)
    remote = REMOTE_BASE + "/" + f.replace("\\", "/")
    remote_dir = os.path.dirname(remote)
    try:
        ssh.exec_command(f"mkdir -p {remote_dir}", timeout=10)
    except:
        pass
    print(f"  SCP: {f}", end="")
    try:
        scp.put(local, remote)
        print(" OK")
    except Exception as e:
        print(f" FAILED: {e}")

scp.close()

print("\nRunning DB migration and fetch...")
stdin, stdout, stderr = ssh.exec_command(
    'cd /home/parthenoun/ClickFix && '
    'php -r "require_once \'Web/ClickFix/src/clickfix_core.php\'; require_once \'Web/ClickFix/src/clickfix_llm.php\'; require_once \'Web/ClickFix/src/clickfix_domain_feeds.php\'; \$pdo=clickfix_open_db(true); clickfix_llm_ensure_table(\$pdo); clickfix_domain_feeds_ensure_table(\$pdo); echo \'Tables OK\n\';" 2>&1 && '
    'php Web/ClickFix/scripts/fetch_all.php 2>&1',
    timeout=120
)
out = stdout.read().decode()
err = stderr.read().decode()
print(out)
if err:
    print("STDERR:", err)

ssh.close()
print("\nDone.")
