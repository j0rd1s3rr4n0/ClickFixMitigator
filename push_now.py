import paramiko
from scp import SCPClient
import time

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
print("Connected")

BASE = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
REMOTE = "/home/parthenoun/ClickFix/Web/ClickFix"

# Deploy files
scp = SCPClient(ssh.get_transport(), socket_timeout=30)
for f in ["dashboard.php", "partials/dashboard_style.php", "partials/dashboard_scripts.php"]:
    scp.put(BASE + "/" + f, REMOTE + "/" + f)
    print("SCP:", f)
scp.close()

# Reset OPcache
_, out, _ = ssh.exec_command("php -r 'if(function_exists(\"opcache_reset\")){opcache_reset();echo \"reset ok\";}' 2>&1")
print("OPcache:", out.read().decode().strip())

# Touch files
_, out, _ = ssh.exec_command("touch " + REMOTE + "/dashboard.php " + REMOTE + "/partials/dashboard_style.php " + REMOTE + "/partials/dashboard_scripts.php && echo touched")
print(out.read().decode().strip())

# Verify new content on server
_, out, _ = ssh.exec_command("head -c 200 " + REMOTE + "/dashboard.php")
content = out.read().decode()
has_cache_control = "no-cache" in content
print("Cache-Control header present:", has_cache_control)

_, out, _ = ssh.exec_command("grep -c 'llm-chat-input' " + REMOTE + "/dashboard.php")
print("llm-chat-input count:", out.read().decode().strip())

# Try Apache reload
_, out, _ = ssh.exec_command("echo '" + pw + "' | sudo -S systemctl reload apache2 2>&1")
result = out.read().decode().strip()
print("Apache reload:", result[:100] if result else "no output")

# Final test
time.sleep(2)
_, out, _ = ssh.exec_command("curl -sk -H 'Cache-Control: no-cache' 'https://localhost/dashboard.php?page=intel&_t=" + str(int(time.time())) + "' 2>&1 | grep -c 'llm-config-toggle'")
print("Server returns new HTML:", out.read().decode().strip())

ssh.close()
print("Done")
