import paramiko, time
from scp import SCPClient
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
B = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
R = "/home/parthenoun/ClickFix/Web/ClickFix"
scp = SCPClient(ssh.get_transport())
scp.put(B + "/src/clickfix_core.php", R + "/src/clickfix_core.php")
scp.close()
ssh.exec_command("php -r 'opcache_reset();' 2>/dev/null")
ssh.exec_command("touch " + R + "/src/clickfix_core.php")
time.sleep(1)
_, out, _ = ssh.exec_command("curl -skI https://clickfix.jordiserrano.me/dashboard.php 2>&1 | grep -i 'cf-rocketloader'")
print("CF-RocketLoader header:", out.read().decode().strip())
ssh.close()
