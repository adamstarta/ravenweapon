#!/usr/bin/env python3
"""Deploy and run color options check script on the server"""

import paramiko
import os
import json

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

# Paths
LOCAL_PHP = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\scripts\check-snigel-color-options.php"
LOCAL_JSON = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\scripts\snigel-data\products-with-variants.json"
REMOTE_PHP = "/var/www/html/check-snigel-color-options.php"
REMOTE_JSON_DIR = "/var/www/html/snigel-data"
REMOTE_JSON = "/var/www/html/snigel-data/products-with-variants.json"

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)

    print("Connected!")

    # Create snigel-data directory in container
    print("Creating snigel-data directory in container...")
    cmd = f"docker exec {CONTAINER} mkdir -p {REMOTE_JSON_DIR}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    stdout.channel.recv_exit_status()

    # Upload PHP script
    print("Uploading PHP script...")
    with open(LOCAL_PHP, 'r', encoding='utf-8') as f:
        php_content = f.read()

    # Create temp file and copy to container
    cmd = f"cat > /tmp/check-color-options.php << 'PHPEOF'\n{php_content}\nPHPEOF"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error creating PHP temp file: {stderr.read().decode()}")
        return

    cmd = f"docker cp /tmp/check-color-options.php {CONTAINER}:{REMOTE_PHP}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error copying PHP to container: {stderr.read().decode()}")
        return
    print("PHP script uploaded!")

    # Upload JSON data (split into chunks if needed)
    print("Uploading JSON data file (this may take a moment)...")
    with open(LOCAL_JSON, 'r', encoding='utf-8') as f:
        json_content = f.read()

    print(f"JSON file size: {len(json_content)} bytes")

    # Split JSON into chunks to avoid command line length limits
    chunk_size = 50000
    chunks = [json_content[i:i+chunk_size] for i in range(0, len(json_content), chunk_size)]

    print(f"Splitting into {len(chunks)} chunks...")

    # First chunk - create file
    cmd = f"cat > /tmp/products-with-variants.json << 'JSONEOF'\n{chunks[0]}\nJSONEOF"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error creating first JSON chunk: {stderr.read().decode()}")
        return

    # Append remaining chunks
    for i, chunk in enumerate(chunks[1:], 2):
        print(f"  Uploading chunk {i}/{len(chunks)}...")
        # Escape special characters for shell
        escaped_chunk = chunk.replace("'", "'\"'\"'")
        cmd = f"cat >> /tmp/products-with-variants.json << 'JSONEOF'\n{chunk}\nJSONEOF"
        stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
        exit_code = stdout.channel.recv_exit_status()
        if exit_code != 0:
            print(f"Error appending JSON chunk {i}: {stderr.read().decode()}")
            return

    # Copy JSON to container
    cmd = f"docker cp /tmp/products-with-variants.json {CONTAINER}:{REMOTE_JSON}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error copying JSON to container: {stderr.read().decode()}")
        return
    print("JSON data uploaded!")

    # Run the PHP script
    print("\n=== Running color options check ===\n")
    cmd = f"docker exec {CONTAINER} php {REMOTE_PHP}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=300)
    output = stdout.read().decode()
    errors = stderr.read().decode()

    print(output)
    if errors:
        print(f"\nErrors:\n{errors}")

    # Cleanup
    print("\nCleaning up temporary files...")
    cmd = f"docker exec {CONTAINER} rm -f {REMOTE_PHP}"
    ssh.exec_command(cmd, timeout=30)
    cmd = f"docker exec {CONTAINER} rm -rf {REMOTE_JSON_DIR}"
    ssh.exec_command(cmd, timeout=30)

    ssh.close()
    print("\nDone!")

if __name__ == "__main__":
    main()
