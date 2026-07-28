import paramiko, time
from scp import SCPClient
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
B = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
R = "/home/parthenoun/ClickFix/Web/ClickFix"
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(B + "/api/llm.php", R + "/api/llm.php")
scp.close()

# Reset opcache hard
ssh.exec_command("php -r 'if(function_exists(\"opcache_reset\")){opcache_reset();echo \"reset\\n\";}' 2>/dev/null")
# Kill php-fpm if exists
ssh.exec_command("killall -9 php-fpm 2>/dev/null; killall -9 php-cgi 2>/dev/null; echo done")
# Touch the file
ssh.exec_command("touch " + R + "/api/llm.php")
time.sleep(1)

# Verify
_, out, _ = ssh.exec_command("grep -c 'get_investigation_any' " + R + "/api/llm.php")
print("get_investigation_any count on server:", out.read().decode().strip())

_, out, _ = ssh.exec_command("grep -c 'Parts' " + R + "/api/llm.php")
print("Nodes/Edges context:", out.read().decode().strip())

ssh.close()
print("Done. Recarga y prueba /iocs")
