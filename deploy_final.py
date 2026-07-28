import paramiko, time
from scp import SCPClient

with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
print("Connected")

BASE = r"C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix"
REMOTE = "/home/parthenoun/ClickFix/Web/ClickFix"

scp = SCPClient(ssh.get_transport(), socket_timeout=30)
scp.put(BASE + "/dashboard.php", REMOTE + "/dashboard.php")
scp.close()
print("SCP done")

# OPcache reset
ssh.exec_command("php -r 'opcache_reset();' 2>/dev/null")
print("OPcache reset")

# Try sudo with password
cmd = "echo '" + pw + "' | sudo -S /usr/sbin/apachectl graceful 2>&1"
_, out, err = ssh.exec_command(cmd, timeout=10)
result = out.read().decode().strip() + err.read().decode().strip()
print("Apache reload:", result[:150])

# Touch
ssh.exec_command("touch " + REMOTE + "/dashboard.php")
time.sleep(1)

# Test with fresh request
ts = int(time.time())
_, out, _ = ssh.exec_command("curl -sk -H 'Cache-Control: no-cache' -H 'Pragma: no-cache' 'https://clickfix.jordiserrano.me/dashboard.php?page=intel&_v=" + str(ts) + "' 2>&1 | grep -c 'llm-config-toggle'")
print("New HTML visible:", out.read().decode().strip())

# Check headers
_, out, _ = ssh.exec_command("curl -skI 'https://clickfix.jordiserrano.me/dashboard.php?_v=" + str(ts) + "' 2>&1 | grep -i 'cache\|cf-cache'")
print("Cache headers:\n" + out.read().decode())

ssh.close()
print("\nDone. Open: https://clickfix.jordiserrano.me/dashboard.php?page=intel&graph_id=32&_v=" + str(ts))
