import paramiko
from scp import SCPClient

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix\src\clickfix_abusech.php",
        "/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_abusech.php")
scp.close()
print("SCP OK")

_, out, _ = ssh.exec_command("cd /home/parthenoun/ClickFix/Web/ClickFix && php scripts/fetch_all.php 2>&1", timeout=120)
print(out.read().decode().strip())
ssh.close()
print("Done")
