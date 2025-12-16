#!/usr/bin/env python3
"""Deploy and run color options check script on the server using SFTP"""

import paramiko
import os

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

# Paths
LOCAL_PHP = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\scripts\check-snigel-color-options.php"
LOCAL_JSON = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\scripts\snigel-data\products-with-variants.json"
REMOTE_DIR = "/tmp/snigel-check"
REMOTE_PHP = f"{REMOTE_DIR}/check-snigel-color-options.php"
REMOTE_JSON_DIR = f"{REMOTE_DIR}/snigel-data"
REMOTE_JSON = f"{REMOTE_JSON_DIR}/products-with-variants.json"

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)

    print("Connected!")

    # Create directories on host
    print("Creating directories...")
    cmd = f"mkdir -p {REMOTE_DIR} {REMOTE_JSON_DIR}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    stdout.channel.recv_exit_status()

    # Use SFTP to upload files
    print("Uploading files via SFTP...")
    sftp = ssh.open_sftp()

    print("  Uploading PHP script...")
    sftp.put(LOCAL_PHP, REMOTE_PHP)

    print("  Uploading JSON data file...")
    sftp.put(LOCAL_JSON, REMOTE_JSON)

    sftp.close()
    print("Files uploaded!")

    # Create snigel-data directory in container and copy files
    print("\nCopying files to container...")
    cmd = f"docker exec {CONTAINER} mkdir -p /var/www/html/snigel-data"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    stdout.channel.recv_exit_status()

    cmd = f"docker cp {REMOTE_PHP} {CONTAINER}:/var/www/html/check-snigel-color-options.php"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error copying PHP: {stderr.read().decode()}")
        return

    cmd = f"docker cp {REMOTE_JSON} {CONTAINER}:/var/www/html/snigel-data/products-with-variants.json"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error copying JSON: {stderr.read().decode()}")
        return
    print("Files copied to container!")

    # Run the PHP script
    print("\n=== Running color options check ===\n")
    cmd = f"docker exec {CONTAINER} php /var/www/html/check-snigel-color-options.php"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=300)
    output = stdout.read().decode()
    errors = stderr.read().decode()

    print(output)
    if errors:
        print(f"\nPHP Errors:\n{errors}")

    # Cleanup
    print("\nCleaning up...")
    cmd = f"rm -rf {REMOTE_DIR}"
    ssh.exec_command(cmd, timeout=30)
    cmd = f"docker exec {CONTAINER} rm -f /var/www/html/check-snigel-color-options.php"
    ssh.exec_command(cmd, timeout=30)
    cmd = f"docker exec {CONTAINER} rm -rf /var/www/html/snigel-data"
    ssh.exec_command(cmd, timeout=30)

    ssh.close()
    print("\nDone!")

if __name__ == "__main__":
    main()
