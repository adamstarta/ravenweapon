#!/usr/bin/env python3
"""Deploy size selector template update to production"""

import paramiko

# Server credentials
HOST = "77.42.19.154"
USER = "root"
PASSWORD = "93cupECnm3xH"
CONTAINER = "shopware-chf"

LOCAL_TEMPLATE = r"C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\shopware-theme\RavenTheme\src\Resources\views\storefront\page\content\product-detail.html.twig"

def main():
    print(f"Connecting to {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD)

    print("Connected! Uploading template...")
    sftp = ssh.open_sftp()
    sftp.put(LOCAL_TEMPLATE, "/tmp/product-detail.html.twig")
    sftp.close()
    print("Template uploaded!")

    # Copy to container
    print("Copying to container...")
    cmd = f"docker cp /tmp/product-detail.html.twig {CONTAINER}:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/product-detail.html.twig"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    stdout.channel.recv_exit_status()
    errors = stderr.read().decode()
    if errors:
        print(f"Copy errors: {errors}")

    # Clear cache
    print("Clearing Shopware cache...")
    cmd = f"docker exec {CONTAINER} php /var/www/html/bin/console cache:clear"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    output = stdout.read().decode()
    errors = stderr.read().decode()
    print(output)
    if errors:
        print(f"Cache clear errors: {errors}")

    # Verify the template was updated
    print("\nVerifying template contains size selector...")
    cmd = f"docker exec {CONTAINER} grep -c 'size-swatches' /var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/product-detail.html.twig"
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    output = stdout.read().decode().strip()
    if output and int(output) > 0:
        print(f"SUCCESS! Found {output} references to 'size-swatches' in template")
    else:
        print("WARNING: size-swatches not found in template!")

    ssh.close()
    print("\nDeployment complete!")

if __name__ == "__main__":
    main()
