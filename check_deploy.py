import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Check if the new HTML is on the server
_, out, _ = ssh.exec_command("grep -c 'llm-config-toggle' /home/parthenoun/ClickFix/Web/ClickFix/dashboard.php")
count = out.read().decode().strip()
print("llm-config-toggle count:", count)

_, out, _ = ssh.exec_command("grep -c 'llm-chat-box' /home/parthenoun/ClickFix/Web/ClickFix/dashboard.php")
count2 = out.read().decode().strip()
print("llm-chat-box count:", count2)

# Check what the server returns
_, out, _ = ssh.exec_command("curl -sk https://localhost/dashboard.php?page=intel 2>&1 | grep -o 'llm-config-toggle\|llm-chat-box\|AI Analyst Chat' | head -3")
print("Server HTML:", out.read().decode().strip())

# Check opcache
_, out, _ = ssh.exec_command("php -r 'echo function_exists(\"opcache_reset\") ? \"opcache_available\" : \"no_opcache\";'")
print("OPcache:", out.read().decode().strip())

ssh.close()
