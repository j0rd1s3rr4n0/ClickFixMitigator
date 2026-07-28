import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Check and add CF header
cmd = '''grep -q "CF-RocketLoader" /home/parthenoun/ClickFix/Web/ClickFix/.htaccess && echo "EXISTS" || { echo "" >> /home/parthenoun/ClickFix/Web/ClickFix/.htaccess; echo "# Disable Cloudflare Rocket Loader for dashboard" >> /home/parthenoun/ClickFix/Web/ClickFix/.htaccess; echo "Header set CF-RocketLoader off" >> /home/parthenoun/ClickFix/Web/ClickFix/.htaccess; echo "ADDED"; }'''
_, out, err = ssh.exec_command(cmd, timeout=10)
print(out.read().decode().strip())
e = err.read().decode().strip()
if e: print("ERR:", e)

# Verify
_, out, _ = ssh.exec_command("tail -3 /home/parthenoun/ClickFix/Web/ClickFix/.htaccess", timeout=5)
print("\n.htaccess tail:\n" + out.read().decode())

ssh.close()
print("Done")
