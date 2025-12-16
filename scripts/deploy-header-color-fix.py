#!/usr/bin/env python3
"""Deploy header.html.twig with color save fix to server"""

import paramiko
import os

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

# Paths
LOCAL_FILE = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\shopware-theme\RavenTheme\src\Resources\views\storefront\layout\header\header.html.twig"
REMOTE_PATH = "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig"

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)

    print("Connected! Reading local file...")
    with open(LOCAL_FILE, 'r', encoding='utf-8') as f:
        content = f.read()

    # Escape for bash
    content_escaped = content.replace("'", "'\"'\"'")

    print(f"Uploading {len(content)} bytes to container...")

    # Create temp file with content
    cmd = f"cat > /tmp/header_new.twig << 'HEADEREOF'\n{content}\nHEADEREOF"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error creating temp file: {stderr.read().decode()}")
        return

    # Copy to container
    cmd = f"docker cp /tmp/header_new.twig {CONTAINER}:{REMOTE_PATH}"
    print(f"Copying to container: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error copying to container: {stderr.read().decode()}")
        return
    print("File copied to container!")

    # Verify the file has the new content
    print("\nVerifying new code is present...")
    cmd = f"docker exec {CONTAINER} grep -c 'initSnigelColorSave' {REMOTE_PATH}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    output = stdout.read().decode().strip()
    if output and int(output) > 0:
        print(f"SUCCESS: initSnigelColorSave found {output} times in file!")
    else:
        print("WARNING: initSnigelColorSave NOT found in file!")

    # Clear all caches
    print("\nClearing caches...")

    # Clear Shopware cache
    cmd = f"docker exec -e APP_ENV=prod {CONTAINER} php bin/console cache:clear"
    print(f"Running: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    print(stdout.read().decode())

    # Clear Twig cache specifically
    cmd = f"docker exec {CONTAINER} rm -rf /var/www/html/var/cache/prod*/twig"
    print(f"Running: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)

    # Clear theme cache
    cmd = f"docker exec {CONTAINER} rm -rf /var/www/html/var/cache/prod*/pools/*/4q"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)

    # Warmup cache
    cmd = f"docker exec -e APP_ENV=prod {CONTAINER} php bin/console cache:warmup"
    print(f"Running: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    print(stdout.read().decode())

    print("\n=== DEPLOYMENT COMPLETE ===")
    print("The header.html.twig now includes initSnigelColorSave() function")
    print("This will save selected colors to localStorage for ALL Snigel products")
    print("\nNote: You may need to wait a few minutes for Cloudflare CDN cache to expire")
    print("Or use incognito/private browsing to test immediately")

    ssh.close()

if __name__ == "__main__":
    main()
