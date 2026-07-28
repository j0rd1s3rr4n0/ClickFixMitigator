import paramiko
from scp import SCPClient
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
B = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
R = "/home/parthenoun/ClickFix/Web/ClickFix"
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
for f in ["partials/dashboard_scripts.php", "partials/dashboard_chat_bubble.php", "api/blog_feed.php"]:
    scp.put(B + "/" + f, R + "/" + f)
scp.close()
ssh.exec_command("php -r 'opcache_reset();' 2>/dev/null")
print("Deployed")
ssh.close()
