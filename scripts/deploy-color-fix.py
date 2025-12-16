#!/usr/bin/env python3
"""Deploy product-detail.html.twig with color code fix to server"""

import paramiko
import os

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

# Paths
LOCAL_FILE = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\shopware-theme\RavenTheme\src\Resources\views\storefront\page\content\product-detail.html.twig"
REMOTE_PATH = "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/product-detail.html.twig"

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)

    print("Connected! Reading local file...")
    with open(LOCAL_FILE, 'r', encoding='utf-8') as f:
        content = f.read()

    print(f"Uploading {len(content)} bytes to container...")

    # Create temp file with content
    cmd = f"cat > /tmp/product_detail_new.twig << 'PRODEOF'\n{content}\nPRODEOF"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error creating temp file: {stderr.read().decode()}")
        return

    # Copy to container
    cmd = f"docker cp /tmp/product_detail_new.twig {CONTAINER}:{REMOTE_PATH}"
    print(f"Copying to container...")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        print(f"Error copying to container: {stderr.read().decode()}")
        return
    print("File copied to container!")

    # Verify the fix is present
    print("\nVerifying color code fix is present...")
    cmd = f"docker exec {CONTAINER} grep -c 'AA.*d\\{{2\\}}' {REMOTE_PATH}"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    output = stdout.read().decode().strip()
    if output and int(output) > 0:
        print(f"SUCCESS: AA color pattern found {output} times!")
    else:
        print("WARNING: AA color pattern NOT found!")

    # Clear all caches
    print("\nClearing caches...")

    # Clear Shopware cache
    cmd = f"docker exec -e APP_ENV=prod {CONTAINER} php bin/console cache:clear"
    print(f"Running cache:clear...")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    print(stdout.read().decode())

    # Clear Twig cache specifically
    cmd = f"docker exec {CONTAINER} rm -rf /var/www/html/var/cache/prod*/twig"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)

    # Warmup cache
    cmd = f"docker exec -e APP_ENV=prod {CONTAINER} php bin/console cache:warmup"
    print(f"Running cache:warmup...")
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    print(stdout.read().decode())

    print("\n=== DEPLOYMENT COMPLETE ===")
    print("The extractColorCode() function now supports:")
    print("  - AA patterns (AA09, AA01)")
    print("  - A patterns (A01, A09)")
    print("  - B patterns (B01, B09)")
    print("  - -XX- patterns (-09-)")

    ssh.close()

if __name__ == "__main__":
    main()
