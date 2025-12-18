#!/usr/bin/env python3
"""Deploy price decimals fix (header live search + search page) to production"""

import paramiko
import os

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

BASE_PATH = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\shopware-theme\RavenTheme\src\Resources\views\storefront"

TEMPLATES = [
    {
        "local": os.path.join(BASE_PATH, "layout", "header", "header.html.twig"),
        "remote": "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig",
        "name": "header (live search fix)"
    },
    {
        "local": os.path.join(BASE_PATH, "page", "search", "index.html.twig"),
        "remote": "/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/search/index.html.twig",
        "name": "search page (price decimals fix)"
    }
]

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)
    print("Connected!")

    sftp = ssh.open_sftp()

    for template in TEMPLATES:
        print(f"\n--- Deploying {template['name']} ---")

        # Upload to /tmp
        tmp_path = f"/tmp/{os.path.basename(template['local'])}"
        print(f"Uploading to {tmp_path}...")
        sftp.put(template['local'], tmp_path)

        # Copy to container
        print(f"Copying to container...")
        cmd = f"docker cp {tmp_path} {CONTAINER}:{template['remote']}"
        stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
        stdout.channel.recv_exit_status()
        errors = stderr.read().decode()
        if errors:
            print(f"ERROR: {errors}")
        else:
            print("OK!")

    sftp.close()

    # Clear cache and compile theme
    print("\n--- Clearing cache and compiling theme ---")

    print("Compiling theme...")
    cmd = f"docker exec {CONTAINER} php /var/www/html/bin/console theme:compile"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=180)
    output = stdout.read().decode()
    errors = stderr.read().decode()
    print(output)
    if errors:
        print(f"Theme compile warnings: {errors}")

    print("\nClearing cache...")
    cmd = f"docker exec {CONTAINER} php /var/www/html/bin/console cache:clear"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    output = stdout.read().decode()
    errors = stderr.read().decode()
    print(output)
    if errors:
        print(f"Cache clear warnings: {errors}")

    ssh.close()
    print("\n=== Deployment complete! ===")
    print("Test by searching for 'Flight' in ortak.ch - prices should show decimals (CHF 321.35)")

if __name__ == "__main__":
    main()
