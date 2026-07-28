import paramiko
from scp import SCPClient

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# SCP fixed abusech
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix\src\clickfix_abusech.php",
        "/home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_abusech.php")
scp.close()
print("SCP abusech OK")

# Show lines 99-103
_, out, _ = ssh.exec_command("sed -n '99,103p' /home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_abusech.php")
print("Lines 99-103:")
print(out.read().decode())

# Run fetch again
_, out, _ = ssh.exec_command("cd /home/parthenoun/ClickFix/Web/ClickFix && php scripts/fetch_all.php 2>&1", timeout=120)
print("\nFetch:\n" + out.read().decode().strip()[:2000])

ssh.close()
