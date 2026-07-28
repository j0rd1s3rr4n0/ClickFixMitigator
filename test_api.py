import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f:
    pw = f.read().strip()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
_, out, _ = ssh.exec_command("curl -s -o /dev/null -w '%{http_code}' https://clickfix.jordiserrano.me/api/llm.php 2>&1")
print("LLM API HTTP:", out.read().decode().strip())
_, out, _ = ssh.exec_command("curl -s https://clickfix.jordiserrano.me/api/llm.php 2>&1 | head -c 200")
print("LLM API body:", out.read().decode().strip())
ssh.close()
