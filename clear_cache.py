import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# 1. Reset PHP OPcache
_, out, err = ssh.exec_command("php -r 'if(function_exists(\"opcache_reset\")){opcache_reset();echo \"OPcache reset OK\n\";}else{echo \"no opcache\n\";}' 2>&1")
print("OPcache:", out.read().decode().strip())

# 2. Touch the file to force reload
_, out, _ = ssh.exec_command("touch /home/parthenoun/ClickFix/Web/ClickFix/dashboard.php /home/parthenoun/ClickFix/Web/ClickFix/partials/dashboard_scripts.php /home/parthenoun/ClickFix/Web/ClickFix/partials/dashboard_style.php && echo 'Files touched'")
print(out.read().decode().strip())

# 3. Clear Cloudflare cache via API or just touch files
# Try purging via curl to Cloudflare API
_, out, _ = ssh.exec_command("curl -s -X POST 'https://api.cloudflare.com/client/v4/zones' -H 'Authorization: Bearer test' 2>&1 | head -c 100")
print("CF test:", out.read().decode().strip()[:100])

# 4. Restart Apache/PHP-FPM to clear all caches
_, out, err = ssh.exec_command("sudo systemctl reload apache2 2>&1 || sudo service apache2 reload 2>&1 || echo 'no systemctl'")
print("Apache:", out.read().decode().strip())
e = err.read().decode().strip()
if e: print("ERR:", e[:200])

ssh.close()
