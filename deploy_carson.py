import paramiko
from scp import SCPClient
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
B = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
R = "/home/parthenoun/ClickFix/Web/ClickFix"
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(B + "/src/clickfix_domain_feeds.php", R + "/src/clickfix_domain_feeds.php")
scp.close()
ssh.exec_command("php -r 'if(function_exists(\"opcache_reset\"))opcache_reset();' 2>/dev/null")
print("Deployed. Fetching Carson now...")
_, out, _ = ssh.exec_command("cd " + R + " && php scripts/fetch_all.php 2>&1", timeout=180)
print(out.read().decode()[:2000])
ssh.close()
