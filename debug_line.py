import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
# Check what's at the error line in dashboard.php
_, out, _ = ssh.exec_command("sed -n '10240,10250p' /home/parthenoun/ClickFix/Web/ClickFix/dashboard.php")
print("Lines 10240-10250:")
print(out.read().decode())
# Check for JS regex errors in dashboard_scripts
_, out, _ = ssh.exec_command("grep -n 'new RegExp\|/\/\|/g\|/i' /home/parthenoun/ClickFix/Web/ClickFix/partials/dashboard_scripts.php | head -5")
print("\nRegex patterns in JS:")
print(out.read().decode()[:500])
ssh.close()
