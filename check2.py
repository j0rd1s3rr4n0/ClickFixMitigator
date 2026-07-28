import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
# Check lines around extract_iocs
_, out, _ = ssh.exec_command("sed -n '98,120p' /home/parthenoun/ClickFix/Web/ClickFix/api/llm.php")
print("extract_iocs section:")
print(out.read().decode())
ssh.close()
