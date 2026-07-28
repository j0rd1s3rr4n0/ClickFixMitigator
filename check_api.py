import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
# Check if the fix is on the server
_, out, _ = ssh.exec_command("grep 'graphId.*extract_iocs\|get_investigation_any.*extract' /home/parthenoun/ClickFix/Web/ClickFix/api/llm.php")
print("Current extract_iocs code on server:")
print(out.read().decode()[:500])
ssh.close()
