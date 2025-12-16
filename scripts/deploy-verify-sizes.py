#!/usr/bin/env python3
"""Run verify-size-options.php on the server"""

import paramiko

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

LOCAL_PHP = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\scripts\verify-size-options.php"

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)

    print("Connected! Uploading script...")
    sftp = ssh.open_sftp()
    sftp.put(LOCAL_PHP, "/tmp/verify-size-options.php")
    sftp.close()

    print("Copying to container...")
    cmd = f"docker cp /tmp/verify-size-options.php {CONTAINER}:/var/www/html/verify-size-options.php"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    stdout.channel.recv_exit_status()

    print("Running script...\n")
    cmd = f"docker exec {CONTAINER} php /var/www/html/verify-size-options.php"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    output = stdout.read().decode()
    errors = stderr.read().decode()

    print(output)
    if errors:
        print(f"\nErrors: {errors}")

    # Cleanup
    cmd = f"docker exec {CONTAINER} rm -f /var/www/html/verify-size-options.php"
    ssh.exec_command(cmd, timeout=30)

    ssh.close()

if __name__ == "__main__":
    main()
