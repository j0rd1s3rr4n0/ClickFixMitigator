import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Test API status endpoint
_, out, _ = ssh.exec_command("curl -sk 'https://clickfix.jordiserrano.me/api/auto_investigation.php?action=status' 2>&1")
print("Status API:", out.read().decode().strip()[:300])

# Test with session cookie by accessing from localhost
_, out, _ = ssh.exec_command("curl -sk 'https://localhost/api/auto_investigation.php?action=status' 2>&1")
print("\nLocalhost:", out.read().decode().strip()[:300])

# Test jobs endpoint
_, out, _ = ssh.exec_command("curl -sk 'https://localhost/api/auto_investigation.php?action=jobs' 2>&1")
print("\nJobs:", out.read().decode().strip()[:300])

# Check if auto_investigation.php has session_start
_, out, _ = ssh.exec_command("grep -n 'session_start\|session_status' /home/parthenoun/ClickFix/Web/ClickFix/api/auto_investigation.php")
print("\nSession code:\n" + out.read().decode())

# Check if clickfix_auto_investigation.php is loadable
_, out, _ = ssh.exec_command("cd /home/parthenoun/ClickFix/Web/ClickFix && php -r \"require_once 'src/clickfix_core.php'; require_once 'src/clickfix_auto_investigation.php'; echo 'OK';\" 2>&1")
print("\nModule load:", out.read().decode().strip())

ssh.close()
