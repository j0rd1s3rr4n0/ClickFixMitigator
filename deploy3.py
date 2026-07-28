import paramiko
from scp import SCPClient
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
BASE = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
REMOTE = "/home/parthenoun/ClickFix/Web/ClickFix"
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
for f in ["api/llm.php", "api/auto_investigation.php", "partials/dashboard_scripts.php"]:
    scp.put(BASE + "/" + f, REMOTE + "/" + f)
    print("SCP:", f)
scp.close()
ssh.exec_command("chmod 755 " + REMOTE + "/api/llm.php " + REMOTE + "/api/auto_investigation.php")
print("Permissions set")

# Test API from inside server with session cookie
_, out, _ = ssh.exec_command("curl -s https://clickfix.jordiserrano.me/api/llm.php 2>&1 | head -c 150")
print("API test:", out.read().decode().strip()[:150])
ssh.close()
print("Done")
