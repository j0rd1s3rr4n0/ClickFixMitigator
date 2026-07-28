import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Verify file contents on server
_, out, _ = ssh.exec_command("grep -A2 'llm-chat-input' /home/parthenoun/ClickFix/Web/ClickFix/dashboard.php | head -6")
print("=== Chat HTML on server ===")
print(out.read().decode())

# Check if the old "AI Analyst Chat" text still exists
_, out, _ = ssh.exec_command("grep -c 'AI Analyst Chat' /home/parthenoun/ClickFix/Web/ClickFix/dashboard.php")
print("\nOld 'AI Analyst Chat' count:", out.read().decode().strip())

# Try reload Apache with password via stdin
cmd = "echo '" + pw + "' | sudo -S systemctl reload apache2 2>&1 || echo '" + pw + "' | sudo -S service apache2 reload 2>&1 || echo 'reload failed'"
_, out, err = ssh.exec_command(cmd, timeout=15)
print("\nApache reload:", out.read().decode().strip()[:200])
e = err.read().decode().strip()
if e: print("STDERR:", e[:200])

ssh.close()
