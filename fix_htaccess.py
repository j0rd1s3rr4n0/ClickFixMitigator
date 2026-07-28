import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Check if there was another .htaccess
_, out, _ = ssh.exec_command("find /home/parthenoun/ClickFix -name '.htaccess' 2>/dev/null")
print("Found .htaccess files:")
print(out.read().decode())

# Check the full content of our new .htaccess
_, out, _ = ssh.exec_command("cat /home/parthenoun/ClickFix/Web/ClickFix/.htaccess")
print("\nContent:")
print(out.read().decode())

# Restore proper .htaccess with security rules + CF fix
htaccess_content = '''# Block sensitive files
<FilesMatch "\\.(env|sqlite|pem|log|json)$">
    Require all denied
</FilesMatch>
<FilesMatch "^(clickfix\\.sqlite|clickfixlist|clickfixallowlist|alertsites|investigatesites)$">
    Require all denied
</FilesMatch>

# Disable Cloudflare Rocket Loader
Header set CF-RocketLoader off

# Mod rewrite
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
'''
sftp = ssh.open_sftp()
f = sftp.file("/home/parthenoun/ClickFix/Web/ClickFix/.htaccess", "w")
f.write(htaccess_content)
f.close()
sftp.close()
print("\n.htaccess rewritten with full rules")

ssh.close()
