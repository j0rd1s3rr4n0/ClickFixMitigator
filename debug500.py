import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)

# Check error logs
_, out, _ = ssh.exec_command("tail -20 /var/log/apache2/error.log 2>/dev/null || tail -20 /var/log/httpd/error_log 2>/dev/null || echo 'no log found'")
print("=== Apache error log ===")
print(out.read().decode()[:2000])

# Check PHP error log
_, out, _ = ssh.exec_command("tail -20 /home/parthenoun/ClickFix/Web/ClickFix/data/php_errors.log 2>/dev/null || tail -20 /var/log/php*.log 2>/dev/null || echo 'no php log'")
print("\n=== PHP error log ===")
print(out.read().decode()[:2000])

# Test the page directly
_, out, _ = ssh.exec_command("cd /home/parthenoun/ClickFix/Web/ClickFix && php -r \"\$_GET['page']='intel';\$_GET['graph_id']='33';require 'dashboard.php';\" 2>&1 | head -c 500")
print("\n=== Direct PHP test ===")
print(out.read().decode()[:500])

ssh.close()
