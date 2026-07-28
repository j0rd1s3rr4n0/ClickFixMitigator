import paramiko
with open(r"C:\Users\kunakawi\.agents\skills\ssh-connect\ssh.pass") as f: pw = f.read().strip()
ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("213.165.80.114", 22, "parthenoun", pw, timeout=15)
_, out, _ = ssh.exec_command("curl -sk 'https://clickfix.carsonww.com/domains?limit=50&page=1' 2>&1 | head -c 1000")
print("Carson page 1 HTML:")
print(out.read().decode()[:1000])
ssh.close()
