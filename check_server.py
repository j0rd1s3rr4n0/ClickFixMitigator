import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
print("Connected")

# Check if the function exists in the uploaded file
_, stdout, stderr = ssh.exec_command('grep -c "function clickfix_llm_ensure_table" /home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_llm.php', timeout=10)
print("ensure_table count:", stdout.read().decode().strip())

# Check last few lines 
_, stdout, stderr = ssh.exec_command('tail -3 /home/parthenoun/ClickFix/Web/ClickFix/src/clickfix_llm.php', timeout=10)
print("Last 3 lines:", stdout.read().decode().strip())

# Run migration using dashboard.php's include path
_, stdout, stderr = ssh.exec_command('cd /home/parthenoun/ClickFix/Web/ClickFix && php -d display_errors=1 -r "require_once \"src/clickfix_core.php\"; require_once \"src/clickfix_llm.php\"; require_once \"src/clickfix_domain_feeds.php\"; \$pdo=clickfix_open_db(true); clickfix_llm_ensure_table(\$pdo); echo \"OK\n\";" 2>&1', timeout=30)
print("Migration:", stdout.read().decode().strip())
e = stderr.read().decode().strip()
if e: print("ERR:", e[:400])

ssh.close()
