import paramiko
from scp import SCPClient

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
print("Connected")

BASE = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
REMOTE = "/home/parthenoun/ClickFix/Web/ClickFix"
files = [
    "dashboard.php",
    "api/llm.php",
    "api/auto_investigation.php",
    "partials/dashboard_scripts.php",
]

scp = SCPClient(ssh.get_transport(), socket_timeout=30)
for f in files:
    scp.put(BASE + "/" + f, REMOTE + "/" + f)
    print(f"SCP: {f} OK")
scp.close()

# Set perms
_, _, _ = ssh.exec_command("chmod -R 755 " + REMOTE + "/api/ " + REMOTE + "/partials/ " + REMOTE + "/dashboard.php")

ssh.close()
print("Deploy complete. Test at https://clickfix.jordiserrano.me/dashboard.php?page=intel&graph_id=31")
