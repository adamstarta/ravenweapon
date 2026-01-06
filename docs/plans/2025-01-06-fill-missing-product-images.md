# Fill Missing Product Images Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a Python script that fills missing image_2, image_3, image_4 columns in the products Excel file by deriving URLs from existing image_1 URLs using sequential S3 IDs.

**Architecture:** Read Excel → Filter products (exclude Raven Swiss) → For each product with image_1, derive missing image URLs by incrementing S3 bucket ID → Verify URLs exist via HTTP HEAD request → Save updated Excel.

**Tech Stack:** Python, pandas, openpyxl, requests

---

## Context

### Excel File Location
- Input: `c:\Users\alama\Downloads\products (2).xlsx`
- Output: `c:\Users\alama\Downloads\products-updated.xlsx`

### Image URL Pattern
```
https://makaris-prod-public.s3.ewstorage.ch/{ID}/{filename}.{ext}?v={timestamp}
```

Example (sequential IDs):
- image_1: `.../26374/VG1624-IR.tag.1.png?v=1752697760` (ID: 26374)
- image_2: `.../26375/VG1624-IR.tag.0.png?v=1752697765` (ID: 26375)
- image_3: `.../26376/VG1624-IR.tag.2.png?v=1752697771` (ID: 26376)
- image_4: `.../26377/VG1624-IR.tag.3.png?v=1752697775` (ID: 26377)

### Products to Exclude
- Any product with "Raven Swiss" in `name_en` column (25 products)

### Stats
- Total products: 162
- Products to process: 137 (excluding Raven Swiss)
- Missing image_2: 82
- Missing image_3: 87
- Missing image_4: 92

---

## Task 1: Create Script File with Imports and Config

**Files:**
- Create: `scripts/fill-missing-images.py`

**Step 1: Create the script with imports and configuration**

```python
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

def main():
    print("Fill Missing Product Images Script")
    print("=" * 40)

if __name__ == "__main__":
    main()
```

**Step 2: Run to verify script loads**

Run: `python scripts/fill-missing-images.py`
Expected: Prints header without errors

---

## Task 2: Add Excel Reading Function

**Files:**
- Modify: `scripts/fill-missing-images.py`

**Step 1: Add function to read and filter Excel data**

```python
def load_products(filepath: str, exclude_pattern: str) -> pd.DataFrame:
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
```

**Step 2: Update main() to use the function**

```python
def main():
    print("Fill Missing Product Images Script")
    print("=" * 40)

    # Load data
    df_full, df_process = load_products(INPUT_FILE, EXCLUDE_PATTERN)
```

**Step 3: Run to verify Excel loads**

Run: `python scripts/fill-missing-images.py`
Expected: Shows product counts

---

## Task 3: Add URL Parsing Function

**Files:**
- Modify: `scripts/fill-missing-images.py`

**Step 1: Add function to extract S3 ID from URL**

```python
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
```

**Step 2: Add test in main()**

```python
def main():
    print("Fill Missing Product Images Script")
    print("=" * 40)

    # Load data
    df_full, df_process = load_products(INPUT_FILE, EXCLUDE_PATTERN)

    # Test URL generation
    test_url = df_process[df_process['image_1'].notna()].iloc[0]['image_1']
    print(f"\nTest URL parsing:")
    print(f"  Original: {test_url}")
    print(f"  ID: {extract_s3_id(test_url)}")
    print(f"  +1: {generate_image_url(test_url, 1)}")
    print(f"  +2: {generate_image_url(test_url, 2)}")
```

**Step 3: Run to verify URL generation**

Run: `python scripts/fill-missing-images.py`
Expected: Shows original URL and generated URLs with incremented IDs

---

## Task 4: Add URL Verification Function

**Files:**
- Modify: `scripts/fill-missing-images.py`

**Step 1: Add function to verify URL exists**

```python
def verify_url_exists(url: str, timeout: int = 5) -> bool:
    """Check if URL exists using HTTP HEAD request."""
    if pd.isna(url) or not url:
        return False
    try:
        response = requests.head(url, timeout=timeout, allow_redirects=True)
        return response.status_code == 200
    except requests.RequestException:
        return False
```

**Step 2: Add verification test in main()**

```python
    # Test URL verification
    print(f"\nTest URL verification:")
    print(f"  Exists: {verify_url_exists(test_url)}")
    generated = generate_image_url(test_url, 1)
    print(f"  Generated exists: {verify_url_exists(generated)}")
```

**Step 3: Run to verify HTTP checks work**

Run: `python scripts/fill-missing-images.py`
Expected: Shows True for existing URLs

---

## Task 5: Add Image Filling Logic

**Files:**
- Modify: `scripts/fill-missing-images.py`

**Step 1: Add function to fill missing images for a product**

```python
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
```

---

## Task 6: Add Main Processing Loop

**Files:**
- Modify: `scripts/fill-missing-images.py`

**Step 1: Add processing function**

```python
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
        print(f"  Processing: {row['name_en'][:50]}...", end=" ")

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
```

**Step 2: Update main() to process and save**

```python
def main():
    print("Fill Missing Product Images Script")
    print("=" * 40)

    # Load data
    df_full, df_process = load_products(INPUT_FILE, EXCLUDE_PATTERN)

    # Process products (excluding Raven Swiss)
    print("\n" + "=" * 40)
    print("Processing products...")
    df_updated = process_products(df_process, verify=True)

    # Merge back with excluded products
    excluded_mask = df_full['name_en'].str.contains(EXCLUDE_PATTERN, case=False, na=False)
    df_excluded = df_full[excluded_mask]

    # Update the full dataframe with processed rows
    for idx in df_updated.index:
        for col in ['image_2', 'image_3', 'image_4']:
            df_full.at[idx, col] = df_updated.at[idx, col]

    # Save
    print("\n" + "=" * 40)
    print(f"Saving to: {OUTPUT_FILE}")
    df_full.to_excel(OUTPUT_FILE, index=False)
    print("Done!")
```

**Step 3: Run full script**

Run: `python scripts/fill-missing-images.py`
Expected: Processes products, fills images, saves new Excel file

---

## Task 7: Add Progress Bar and Error Handling

**Files:**
- Modify: `scripts/fill-missing-images.py`

**Step 1: Add tqdm for progress bar (optional)**

```python
try:
    from tqdm import tqdm
    HAS_TQDM = True
except ImportError:
    HAS_TQDM = False
    def tqdm(iterable, **kwargs):
        return iterable
```

**Step 2: Add error handling wrapper**

```python
def main():
    try:
        # ... existing code ...
    except FileNotFoundError as e:
        print(f"ERROR: File not found - {e}")
        return 1
    except Exception as e:
        print(f"ERROR: {e}")
        return 1
    return 0

if __name__ == "__main__":
    exit(main())
```

---

## Task 8: Final Testing

**Step 1: Run the complete script**

Run: `python scripts/fill-missing-images.py`

Expected output:
```
Fill Missing Product Images Script
========================================
Loading: c:\Users\alama\Downloads\products (2).xlsx
Total products: 162
Excluded 'Raven Swiss': 25
Products to process: 137

========================================
Processing products...
Products to fill: 75
  Processing: VENGEANCE 4-20X50 PHR II IR... filled: image_4
  Processing: VENGEANCE 4-20X50 R3 ILLUM... filled: image_4
  ...

Summary:
  image_2: XX filled
  image_3: XX filled
  image_4: XX filled

========================================
Saving to: c:\Users\alama\Downloads\products-updated.xlsx
Done!
```

**Step 2: Verify output file**

Run: `python -c "import pandas as pd; df = pd.read_excel(r'c:\Users\alama\Downloads\products-updated.xlsx'); print(df[['image_1','image_2','image_3','image_4']].notna().sum())"`

Expected: Higher counts for image_2, image_3, image_4 than before

---

## Complete Script Reference

The final script should be approximately 120 lines and handle:
- Excel reading/writing with pandas
- URL pattern matching with regex
- HTTP HEAD verification with requests
- Progress feedback
- Error handling
- Exclusion of "Raven Swiss" products

