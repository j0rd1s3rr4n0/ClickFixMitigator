import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Test the dashboard headers
_, out, _ = ssh.exec_command("curl -sI https://clickfix.jordiserrano.me/dashboard.php 2>&1 | grep -i 'cf-rocketloader\|server\|content-type'")
print("Headers:")
print(out.read().decode())

# Check JS loads properly
_, out, _ = ssh.exec_command("curl -s https://clickfix.jordiserrano.me/dashboard.php?page=intel 2>&1 | grep -c 'DOMContentLoaded'")
print("\nDOMContentLoaded count:", out.read().decode().strip())

ssh.close()
