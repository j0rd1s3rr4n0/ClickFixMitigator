import paramiko
from scp import SCPClient
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix\dashboard.php", "/home/parthenoun/ClickFix/Web/ClickFix/dashboard.php")
scp.close()
ssh.exec_command("php -r 'if(function_exists(\"opcache_reset\"))opcache_reset();' 2>/dev/null")
print("Deployed")
ssh.close()
