#!/usr/bin/env python3
import re

filepath = "shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Replacements: old URL and name -> new URL and name
replacements = [
    ('/admin-gear/', 'Admin Gear', '/Ausruestung/Verwaltungsausruestung/', 'Verwaltungsausrüstung'),
    ('/bags-backpacks/', 'Bags & Backpacks', '/Ausruestung/Taschen-Rucksaecke/', 'Taschen & Rucksäcke'),
    ('/ballistic-protection/', 'Ballistic Protection', '/Ausruestung/Ballistischer-Schutz/', 'Ballistischer Schutz'),
    ('/belts/', 'Belts', '/Ausruestung/Guertel/', 'Gürtel'),
    ('/covert-gear/', 'Covert Gear', '/Ausruestung/Verdeckte-Ausruestung/', 'Verdeckte Ausrüstung'),
    ('/duty-gear/', 'Duty Gear', '/Ausruestung/Dienstausruestung/', 'Dienstausrüstung'),
    ('/highvis/', 'HighVis', '/Ausruestung/Warnschutz/', 'Warnschutz'),
    ('/holders-pouches/', 'Holders & Pouches', '/Ausruestung/Halter-Taschen/', 'Halter & Taschen'),
    ('/k9-gear/', 'K9 Gear', '/Ausruestung/K9-Ausruestung/', 'K9 Ausrüstung'),
    ('/leg-panels/', 'Leg Panels', '/Ausruestung/Beinpaneele/', 'Beinpaneele'),
    ('/medical-gear/', 'Medical Gear', '/Ausruestung/Medizinische-Ausruestung/', 'Medizinische Ausrüstung'),
    ('/miscellaneous/', 'Miscellaneous', '/Ausruestung/Verschiedenes/', 'Verschiedenes'),
    ('/multicam/', 'Multicam', '/Ausruestung/Multicam/', 'Multicam'),
    ('/patches/', 'Patches', '/Ausruestung/Patches/', 'Patches'),
    ('/police-gear/', 'Police Gear', '/Ausruestung/Polizeiausruestung/', 'Polizeiausrüstung'),
    ('/slings-holsters/', 'Slings & Holsters', '/Ausruestung/Tragegurte-Holster/', 'Tragegurte & Holster'),
    ('/sniper-gear/', 'Sniper Gear', '/Ausruestung/Scharfschuetzen-Ausruestung/', 'Scharfschützen-Ausrüstung'),
    ('/source-hydration/', 'Source Hydration', '/Ausruestung/Source-Hydration/', 'Source Hydration'),
    ('/tactical-clothing/', 'Tactical Clothing', '/Ausruestung/Taktische-Bekleidung/', 'Taktische Bekleidung'),
    ('/tactical-gear/', 'Tactical Gear', '/Ausruestung/Taktische-Ausruestung/', 'Taktische Ausrüstung'),
    ('/vests-chest-rigs/', 'Vests & Chest Rigs', '/Ausruestung/Westen-Chest-Rigs/', 'Westen & Chest Rigs'),
]

count = 0
for old_url, old_name, new_url, new_name in replacements:
    old_pattern = f'href="{old_url}"'
    new_pattern = f'href="{new_url}"'
    if old_pattern in content:
        content = content.replace(old_pattern, new_pattern)
        content = content.replace(f'>{old_name}</a>', f'>{new_name}</a>')
        count += 1
        print(f"Updated: {old_name} -> {new_name}")

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(content)

print(f"\nTotal updated: {count}")
