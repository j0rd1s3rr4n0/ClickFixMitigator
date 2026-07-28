import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Check Apache modules
_, out, _ = ssh.exec_command("apache2ctl -M 2>/dev/null | grep -i headers || httpd -M 2>/dev/null | grep -i headers || echo 'cant check modules'")
print("mod_headers:", out.read().decode().strip())

# Check if .htaccess is being read (AllowOverride)
_, out, _ = ssh.exec_command("grep -r 'AllowOverride' /etc/apache2/ /etc/httpd/ 2>/dev/null | head -5 || echo 'cant find apache config'")
print("\nAllowOverride:", out.read().decode().strip()[:300])

# Check current .htaccess
_, out, _ = ssh.exec_command("cat /home/parthenoun/ClickFix/Web/ClickFix/.htaccess")
print("\n.htaccess:\n" + out.read().decode())

# Test if header is sent
_, out, _ = ssh.exec_command("curl -skI https://clickfix.jordiserrano.me/dashboard.php 2>&1 | grep -i 'cf-rocketloader\|server'")
print("\nHeaders from CF:", out.read().decode().strip())

# Try adding to Apache config directly
cmd = """grep -q 'CF-RocketLoader' /etc/apache2/apache2.conf 2>/dev/null && echo 'EXISTS' || echo 'NOT FOUND'"""
_, out, _ = ssh.exec_command(cmd)
print("\nApache config:", out.read().decode().strip())

ssh.close()
