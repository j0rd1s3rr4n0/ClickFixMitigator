import paramiko, time
from scp import SCPClient

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

BASE = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
REMOTE = "/home/parthenoun/ClickFix/Web/ClickFix"

scp = SCPClient(ssh.get_transport(), socket_timeout=30)
for f in ["api/auto_investigation.php", "api/llm.php", "dashboard.php"]:
    scp.put(BASE + "/" + f, REMOTE + "/" + f)
    print("SCP:", f)
scp.close()

ssh.exec_command("php -r 'opcache_reset();' 2>/dev/null")
ssh.exec_command("touch " + REMOTE + "/api/auto_investigation.php " + REMOTE + "/api/llm.php " + REMOTE + "/dashboard.php")
time.sleep(1)

# Test with session - need a real session cookie. Instead, test the bootstrap loads correctly.
_, out, _ = ssh.exec_command("cd " + REMOTE + " && php -r \"require_once 'api/auto_investigation.php';\" 2>&1 | head -c 300")
print("\nDirect PHP test:", out.read().decode().strip()[:300])

# Test via dashboard session - use wget with cookie
_, out, _ = ssh.exec_command("curl -sk -b /tmp/test_cookie -c /tmp/test_cookie 'https://clickfix.jordiserrano.me/dashboard.php?page=access&public=1' > /dev/null 2>&1; curl -sk -b /tmp/test_cookie 'https://clickfix.jordiserrano.me/api/auto_investigation.php?action=status' 2>&1")
print("\nAPI with cookie:", out.read().decode().strip()[:200])

ssh.close()
print("\nDone. Reload: https://clickfix.jordiserrano.me/dashboard.php?page=intel&graph_id=32")
