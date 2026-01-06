#!/usr/bin/env python3
"""
Fill missing product images in Excel file.
Derives image_2, image_3, image_4 URLs from image_1 using sequential S3 IDs.
Excludes 'Raven Swiss' products.
"""

import pandas as pd
import requests
import re
from pathlib import Path

# Configuration
INPUT_FILE = r"c:\Users\alama\Downloads\products (2).xlsx"
OUTPUT_FILE = r"c:\Users\alama\Downloads\products-updated.xlsx"
EXCLUDE_PATTERN = "Raven Swiss"
TIMEOUT = 5  # seconds for HTTP requests


def load_products(filepath: str, exclude_pattern: str) -> tuple:
    """Load products from Excel, excluding specified pattern."""
    print(f"Loading: {filepath}")
    df = pd.read_excel(filepath)
    print(f"Total products: {len(df)}")

    # Filter out excluded products
    mask = ~df['name_en'].str.contains(exclude_pattern, case=False, na=False)
    df_filtered = df[mask].copy()
    excluded_count = len(df) - len(df_filtered)
    print(f"Excluded '{exclude_pattern}': {excluded_count}")
    print(f"Products to process: {len(df_filtered)}")

    return df, df_filtered


def extract_s3_id(url: str) -> int | None:
    """Extract the S3 bucket ID from an image URL."""
    if pd.isna(url):
        return None
    match = re.search(r'/(\d+)/', str(url))
    return int(match.group(1)) if match else None


def generate_image_url(base_url: str, id_offset: int) -> str | None:
    """Generate new image URL by incrementing the S3 ID."""
    if pd.isna(base_url):
        return None

    base_id = extract_s3_id(base_url)
    if base_id is None:
        return None

    new_id = base_id + id_offset
    # Replace the ID in the URL
    new_url = re.sub(r'/\d+/', f'/{new_id}/', str(base_url), count=1)
    return new_url


def verify_url_exists(url: str, timeout: int = 5) -> bool:
    """Check if URL exists using HTTP HEAD request."""
    if pd.isna(url) or not url:
        return False
    try:
        response = requests.head(url, timeout=timeout, allow_redirects=True)
        return response.status_code == 200
    except requests.RequestException:
        return False


def fill_missing_images(row: pd.Series, verify: bool = True) -> dict:
    """
    Fill missing image_2, image_3, image_4 based on image_1.
    Returns dict with filled values.
    """
    result = {
        'image_2': row['image_2'],
        'image_3': row['image_3'],
        'image_4': row['image_4'],
    }

    base_url = row['image_1']
    if pd.isna(base_url):
        return result

    # Fill missing images
    for i, col in enumerate(['image_2', 'image_3', 'image_4'], start=1):
        if pd.isna(row[col]):
            new_url = generate_image_url(base_url, i)
            if new_url:
                if verify:
                    if verify_url_exists(new_url):
                        result[col] = new_url
                else:
                    result[col] = new_url

    return result


def process_products(df: pd.DataFrame, verify: bool = True) -> pd.DataFrame:
    """Process all products and fill missing images."""
    df = df.copy()

    # Find products with image_1 but missing other images
    has_image1 = df['image_1'].notna()
    missing_any = (
        df['image_2'].isna() |
        df['image_3'].isna() |
        df['image_4'].isna()
    )
    to_process = df[has_image1 & missing_any]

    print(f"\nProducts to fill: {len(to_process)}")

    filled_count = {'image_2': 0, 'image_3': 0, 'image_4': 0}

    for idx, row in to_process.iterrows():
        name = row['name_en'][:50] if pd.notna(row['name_en']) else 'Unknown'
        print(f"  Processing: {name}...", end=" ")

        filled = fill_missing_images(row, verify=verify)

        updates = []
        for col in ['image_2', 'image_3', 'image_4']:
            if pd.isna(row[col]) and pd.notna(filled[col]):
                df.at[idx, col] = filled[col]
                filled_count[col] += 1
                updates.append(col)

        if updates:
            print(f"filled: {', '.join(updates)}")
        else:
            print("no valid images found")

    print(f"\nSummary:")
    for col, count in filled_count.items():
        print(f"  {col}: {count} filled")

    return df


def main():
    try:
        print("Fill Missing Product Images Script")
        print("=" * 40)

        # Load data
        df_full, df_process = load_products(INPUT_FILE, EXCLUDE_PATTERN)

        # Process products (excluding Raven Swiss)
        print("\n" + "=" * 40)
        print("Processing products...")
        df_updated = process_products(df_process, verify=True)

        # Update the full dataframe with processed rows
        for idx in df_updated.index:
            for col in ['image_2', 'image_3', 'image_4']:
                df_full.at[idx, col] = df_updated.at[idx, col]

        # Save
        print("\n" + "=" * 40)
        print(f"Saving to: {OUTPUT_FILE}")
        df_full.to_excel(OUTPUT_FILE, index=False)
        print("Done!")
        return 0

    except FileNotFoundError as e:
        print(f"ERROR: File not found - {e}")
        return 1
    except Exception as e:
        print(f"ERROR: {e}")
        return 1


if __name__ == "__main__":
    exit(main())
