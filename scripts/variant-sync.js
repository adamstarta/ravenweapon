/**
 * Variant Sync Script for Shopware 6 Staging
 *
 * Syncs product variants from Snigel scraped data to Shopware staging.
 * Creates property groups, options, variant products, and uploads images.
 *
 * Usage:
 *   node variant-sync.js --dry-run    # Test without making changes
 *   node variant-sync.js              # Run full sync
 *   node variant-sync.js --resume     # Resume from last progress
 *
 * Target: https://developing.ravenweapon.ch (STAGING ONLY)
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

// === CONFIGURATION ===
const CONFIG = {
  shopwareUrl: 'https://developing.ravenweapon.ch',
  accessKey: 'SWIACXRPWNU4YNYWMWR3CHDQEQ',
  secretKey: 'Tk04TGtSb2xSZEpOMFRieEQ1V1JNUE5zUFlSUEpXMUdmQ2tHU0c',
  scrapedDataPath: './output/snigel-full-scrape-2026-01-15.json',
  progressFile: './output/variant-sync-progress.json',
  resultsFile: './output/variant-sync-results.json',
  snigelImageBase: 'https://products.snigel.se/wp-content/uploads/',
  retryAttempts: 3,
  retryDelay: 2000,
};

// === SHOPWARE API CLASS ===
class ShopwareAPI {
  constructor(baseUrl, accessKey, secretKey) {
    this.baseUrl = baseUrl;
    this.accessKey = accessKey;
    this.secretKey = secretKey;
    this.accessToken = null;
    this.tokenExpiry = null;
  }

  async authenticate() {
    if (this.accessToken && this.tokenExpiry > Date.now()) {
      return this.accessToken;
    }

    const response = await this.request('POST', '/api/oauth/token', {
      grant_type: 'client_credentials',
      client_id: this.accessKey,
      client_secret: this.secretKey,
    }, false);

    this.accessToken = response.access_token;
    this.tokenExpiry = Date.now() + (response.expires_in * 1000) - 60000;
    return this.accessToken;
  }

  async request(method, endpoint, data = null, auth = true) {
    if (auth) {
      await this.authenticate();
    }

    return new Promise((resolve, reject) => {
      const url = new URL(endpoint, this.baseUrl);
      const options = {
        method,
        hostname: url.hostname,
        port: url.port || (url.protocol === 'https:' ? 443 : 80),
        path: url.pathname + url.search,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      };

      if (auth && this.accessToken) {
        options.headers['Authorization'] = `Bearer ${this.accessToken}`;
      }

      const req = (url.protocol === 'https:' ? https : http).request(options, (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => {
          try {
            if (res.statusCode >= 200 && res.statusCode < 300) {
              resolve(body ? JSON.parse(body) : {});
            } else if (res.statusCode === 204) {
              resolve({});
            } else {
              reject(new Error(`API Error ${res.statusCode}: ${body}`));
            }
          } catch (e) {
            reject(new Error(`Parse error: ${e.message}, body: ${body}`));
          }
        });
      });

      req.on('error', reject);
      req.setTimeout(30000, () => {
        req.destroy();
        reject(new Error('Request timeout'));
      });

      if (data) {
        req.write(JSON.stringify(data));
      }
      req.end();
    });
  }

  async retryRequest(method, endpoint, data = null, attempts = CONFIG.retryAttempts) {
    for (let i = 0; i < attempts; i++) {
      try {
        return await this.request(method, endpoint, data);
      } catch (error) {
        if (i === attempts - 1) throw error;
        console.log(`  Retry ${i + 1}/${attempts} after error: ${error.message}`);
        await sleep(CONFIG.retryDelay);
      }
    }
  }

  // Search for product by name
  async findProductByName(name) {
    const response = await this.retryRequest('POST', '/api/search/product', {
      filter: [
        { type: 'equals', field: 'name', value: name }
      ],
      includes: {
        product: ['id', 'productNumber', 'parentId', 'name']
      }
    });
    return response.data?.[0] || null;
  }

  // Search for product by product number
  async findProductByNumber(productNumber) {
    const response = await this.retryRequest('POST', '/api/search/product', {
      filter: [
        { type: 'equals', field: 'productNumber', value: productNumber }
      ],
      includes: {
        product: ['id', 'productNumber', 'parentId', 'name']
      }
    });
    return response.data?.[0] || null;
  }

  // Get all property groups
  async getPropertyGroups() {
    const response = await this.retryRequest('POST', '/api/search/property-group', {
      includes: {
        property_group: ['id', 'name']
      },
      limit: 500
    });
    return response.data || [];
  }

  // Create property group
  async createPropertyGroup(name) {
    const groupId = this.generateUuid();
    await this.retryRequest('POST', '/api/property-group', {
      id: groupId,
      name,
      sortingType: 'alphanumeric',
      displayType: 'text',
    });
    return { id: groupId };
  }

  // Get property options for a group
  async getPropertyOptions(groupId) {
    const response = await this.retryRequest('POST', '/api/search/property-group-option', {
      filter: [
        { type: 'equals', field: 'groupId', value: groupId }
      ],
      includes: {
        property_group_option: ['id', 'name', 'groupId']
      },
      limit: 500
    });
    return response.data || [];
  }

  // Create property option
  async createPropertyOption(groupId, name) {
    const optionId = this.generateUuid();
    await this.retryRequest('POST', '/api/property-group-option', {
      id: optionId,
      groupId,
      name,
    });
    return { id: optionId };
  }

  // Get product configurator settings
  async getConfiguratorSettings(productId) {
    const response = await this.retryRequest('POST', '/api/search/product-configurator-setting', {
      filter: [
        { type: 'equals', field: 'productId', value: productId }
      ],
      includes: {
        product_configurator_setting: ['id', 'optionId', 'productId']
      },
      limit: 500
    });
    return response.data || [];
  }

  // Add configurator setting
  async addConfiguratorSetting(productId, optionId) {
    const response = await this.retryRequest('POST', '/api/product-configurator-setting', {
      productId,
      optionId,
    });
    return response;
  }

  // Create variant product
  async createVariant(parentId, productNumber, name, optionIds) {
    // Generate ID upfront so we know it
    const variantId = this.generateUuid();
    await this.retryRequest('POST', '/api/product', {
      id: variantId,
      parentId,
      productNumber,
      name,
      stock: 0,
      active: true,
      options: optionIds.map(id => ({ id })),
    });
    return { id: variantId };
  }

  // Search media by filename or article number pattern
  async findMediaByFilename(filename) {
    // Remove -uai-516x516 suffix and extension for better matching
    let searchPattern = filename
      .replace(/-uai-\d+x\d+/i, '')
      .replace(/\.[^.]+$/, '');

    // Try exact match first
    let response = await this.retryRequest('POST', '/api/search/media', {
      filter: [
        { type: 'contains', field: 'fileName', value: searchPattern }
      ],
      includes: {
        media: ['id', 'fileName']
      },
      limit: 1
    });

    if (response.data?.[0]) {
      return response.data[0];
    }

    // Try matching by article number prefix (first part before last dash or underscore)
    const articlePrefix = searchPattern.split(/[-_]/).slice(0, -1).join('-');
    if (articlePrefix && articlePrefix.length > 5) {
      response = await this.retryRequest('POST', '/api/search/media', {
        filter: [
          { type: 'contains', field: 'fileName', value: articlePrefix }
        ],
        includes: {
          media: ['id', 'fileName']
        },
        limit: 1
      });
      return response.data?.[0] || null;
    }

    return null;
  }

  // Upload media from URL
  async uploadMediaFromUrl(url, filename) {
    // Generate a unique ID for the media
    const mediaId = this.generateUuid();
    const extension = filename.split('.').pop() || 'jpg';
    const fileNameWithoutExt = filename.replace(/\.[^.]+$/, '');

    // Create media entity first with required fields
    await this.retryRequest('POST', '/api/media', {
      id: mediaId,
      mediaFolderId: null,
    });

    // Upload from URL using the _action endpoint
    await this.retryRequest('POST', `/api/_action/media/${mediaId}/upload?extension=${extension}&fileName=${encodeURIComponent(fileNameWithoutExt)}`, {
      url: url
    });

    return mediaId;
  }

  // Generate UUID for Shopware
  generateUuid() {
    return 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/x/g, () => {
      return Math.floor(Math.random() * 16).toString(16);
    });
  }

  // Link media to product (set as cover)
  async linkMediaToProduct(productId, mediaId, position = 0) {
    await this.retryRequest('POST', '/api/product-media', {
      productId,
      mediaId,
      position,
    });
  }

  // Get product media
  async getProductMedia(productId) {
    const response = await this.retryRequest('POST', '/api/search/product-media', {
      filter: [
        { type: 'equals', field: 'productId', value: productId }
      ],
      includes: {
        product_media: ['id', 'mediaId', 'productId']
      },
      limit: 100
    });
    return response.data || [];
  }
}

// === HELPER FUNCTIONS ===
function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function loadJSON(filepath) {
  return JSON.parse(fs.readFileSync(filepath, 'utf8'));
}

function saveJSON(filepath, data) {
  fs.writeFileSync(filepath, JSON.stringify(data, null, 2));
}

function extractFilename(url) {
  if (!url) return null;
  const parts = url.split('/');
  return parts[parts.length - 1];
}

function buildSnigelImageUrl(filename) {
  if (!filename) return null;
  // Images are in year/month folders, but we'll search by filename
  return `https://products.snigel.se/wp-content/uploads/2025/12/${filename}`;
}

// Map Snigel option names to Shopware property group names
const OPTION_NAME_MAP = {
  'Colour': 'Farbe',
  'V Size': 'V Size',
  'Sizes': 'Sizes',
  'Backpack Size': 'Backpack Size',
  'Panel Sizes': 'Panel Size',
  'Number': 'Number',
  'Mag Size': 'Mag Size',
  'Variant': 'Variant',
  'Parts': 'Parts',
  'Sizes Double All': 'Sizes Double All',
};

// Map Snigel color names to Shopware color names
const COLOR_MAP = {
  'Black': 'Black',
  'Grey': 'Grey',
  'Multicam': 'Multicam',
  'Olive': 'Olive',
  'HighVis yellow': 'HighVis yellow',
  'White': 'White',
  'Navy': 'Navy',
};

// === MAIN SYNC CLASS ===
class VariantSync {
  constructor(dryRun = false, resume = false) {
    this.dryRun = dryRun;
    this.resume = resume;
    this.api = new ShopwareAPI(CONFIG.shopwareUrl, CONFIG.accessKey, CONFIG.secretKey);
    this.propertyGroups = new Map(); // name -> id
    this.propertyOptions = new Map(); // "groupId:optionName" -> id
    this.progress = { completed: [], failed: [], skipped: [] };
    this.results = [];
  }

  async init() {
    console.log('=== Variant Sync Script ===');
    console.log(`Mode: ${this.dryRun ? 'DRY RUN (no changes)' : 'LIVE'}`);
    console.log(`Target: ${CONFIG.shopwareUrl}`);
    console.log('');

    if (!this.dryRun) {
      // Test API connection
      console.log('Testing API connection...');
      await this.api.authenticate();
      console.log('API connection successful!');
      console.log('');
    }

    // Load existing progress if resuming
    if (this.resume && fs.existsSync(CONFIG.progressFile)) {
      this.progress = loadJSON(CONFIG.progressFile);
      console.log(`Resuming from previous run. ${this.progress.completed.length} products already done.`);
    }

    // Cache existing property groups
    if (!this.dryRun) {
      console.log('Loading existing property groups...');
      const groups = await this.api.getPropertyGroups();
      for (const group of groups) {
        this.propertyGroups.set(group.name, group.id);

        // Load options for each group
        const options = await this.api.getPropertyOptions(group.id);
        for (const opt of options) {
          this.propertyOptions.set(`${group.id}:${opt.name}`, opt.id);
        }
      }
      console.log(`Found ${this.propertyGroups.size} property groups with ${this.propertyOptions.size} options`);
      console.log('');
    }
  }

  async ensurePropertyGroup(name) {
    const shopwareName = OPTION_NAME_MAP[name] || name;

    if (this.propertyGroups.has(shopwareName)) {
      return this.propertyGroups.get(shopwareName);
    }

    if (this.dryRun) {
      console.log(`  [DRY RUN] Would create property group: ${shopwareName}`);
      return `dry-run-group-${shopwareName}`;
    }

    console.log(`  Creating property group: ${shopwareName}`);
    const result = await this.api.createPropertyGroup(shopwareName);
    const groupId = result.id;
    this.propertyGroups.set(shopwareName, groupId);
    return groupId;
  }

  async ensurePropertyOption(groupId, groupName, optionName) {
    const key = `${groupId}:${optionName}`;

    if (this.propertyOptions.has(key)) {
      return this.propertyOptions.get(key);
    }

    if (this.dryRun) {
      console.log(`  [DRY RUN] Would create option: ${optionName} in ${groupName}`);
      return `dry-run-option-${optionName}`;
    }

    console.log(`  Creating option: ${optionName} in ${groupName}`);
    const result = await this.api.createPropertyOption(groupId, optionName);
    const optionId = result.id;
    this.propertyOptions.set(key, optionId);
    return optionId;
  }

  async processProduct(product, index, total) {
    const productName = product.snigel_name;
    console.log(`\n[${index + 1}/${total}] Processing: ${productName}`);

    // Skip if already completed
    if (this.progress.completed.includes(productName)) {
      console.log('  Skipping (already completed)');
      return { status: 'skipped', reason: 'already completed' };
    }

    // Check if product has variants
    if (!product.options || Object.keys(product.options).length === 0) {
      console.log('  Skipping (no variants)');
      return { status: 'skipped', reason: 'no variants' };
    }

    try {
      // Find parent product on Shopware
      let parentProduct = null;
      if (!this.dryRun) {
        parentProduct = await this.api.findProductByName(productName);
        if (!parentProduct) {
          // Try with ravenweapon_name
          parentProduct = await this.api.findProductByName(product.ravenweapon_name);
        }
        if (!parentProduct && product.ravenweapon_sku) {
          // Try by product number
          parentProduct = await this.api.findProductByNumber(product.ravenweapon_sku);
        }
      }

      if (!parentProduct && !this.dryRun) {
        console.log(`  WARNING: Product not found on staging: ${productName}`);
        return { status: 'failed', reason: 'product not found' };
      }

      const parentId = parentProduct?.id || 'dry-run-parent';
      console.log(`  Found parent: ${parentId}`);

      // Ensure property groups and options exist
      const optionGroups = {};
      for (const [groupName, values] of Object.entries(product.options)) {
        const groupId = await this.ensurePropertyGroup(groupName);
        optionGroups[groupName] = { groupId, options: {} };

        for (const value of values) {
          const optionId = await this.ensurePropertyOption(groupId, groupName, value);
          optionGroups[groupName].options[value] = optionId;
        }
      }

      // Add configurator settings to parent
      if (!this.dryRun) {
        const existingSettings = await this.api.getConfiguratorSettings(parentId);
        const existingOptionIds = new Set(existingSettings.map(s => s.optionId));

        for (const [groupName, groupData] of Object.entries(optionGroups)) {
          for (const [optionValue, optionId] of Object.entries(groupData.options)) {
            if (!existingOptionIds.has(optionId)) {
              console.log(`  Adding configurator: ${groupName}=${optionValue}`);
              await this.api.addConfiguratorSetting(parentId, optionId);
            }
          }
        }
      }

      // Create variants from variant_images
      let variantsCreated = 0;
      let imagesLinked = 0;

      for (const variantImage of product.variant_images || []) {
        const selection = variantImage.selection;
        const imageFilename = variantImage.image;

        // Build variant name and product number
        const selectionParts = Object.entries(selection).map(([k, v]) => v);
        const variantName = `${productName} ${selectionParts.join(' ')}`;

        // Build article number based on Snigel pattern
        let variantNumber = product.snigel_article_no;
        // Append color and size codes if available
        if (selection.Colour) {
          const colorCode = { 'Black': '01', 'Grey': '09', 'Multicam': '56', 'Olive': '99', 'HighVis yellow': '70', 'White': '00', 'Navy': '02' }[selection.Colour] || 'XX';
          variantNumber = variantNumber.replace(/-$/, `-${colorCode}`);
        }
        for (const [key, value] of Object.entries(selection)) {
          if (key !== 'Colour') {
            variantNumber = `${variantNumber}-${value}`;
          }
        }

        // Check if variant already exists
        let variantProduct = null;
        if (!this.dryRun) {
          variantProduct = await this.api.findProductByNumber(variantNumber);
        }

        if (!variantProduct && !this.dryRun) {
          // Collect option IDs for this variant
          const optionIds = [];
          for (const [groupName, value] of Object.entries(selection)) {
            const optionId = optionGroups[groupName]?.options[value];
            if (optionId) {
              optionIds.push(optionId);
            }
          }

          if (optionIds.length > 0) {
            console.log(`  Creating variant: ${variantName}`);
            try {
              variantProduct = await this.api.createVariant(parentId, variantNumber, variantName, optionIds);
              variantsCreated++;
            } catch (error) {
              console.log(`  WARNING: Failed to create variant: ${error.message}`);
            }
          }
        } else if (variantProduct) {
          console.log(`  Variant exists: ${variantNumber}`);
        }

        if (this.dryRun) {
          console.log(`  [DRY RUN] Would create variant: ${variantName}`);
          variantsCreated++;
        }

        // Handle image - only link existing images, skip upload (URLs not available)
        if (imageFilename && (variantProduct || this.dryRun)) {
          const variantId = variantProduct?.id || 'dry-run-variant';

          // Check if image already exists in Shopware
          let mediaId = null;
          if (!this.dryRun) {
            const existingMedia = await this.api.findMediaByFilename(imageFilename);
            if (existingMedia) {
              console.log(`  Image found: ${existingMedia.fileName}`);
              mediaId = existingMedia.id;
            } else {
              // Skip upload - Snigel URLs not available in scraped data
              console.log(`  No image found for: ${imageFilename.replace(/-uai-\d+x\d+/i, '')}`);
            }

            // Link media to variant
            if (mediaId) {
              const existingProductMedia = await this.api.getProductMedia(variantId);
              const alreadyLinked = existingProductMedia.some(pm => pm.mediaId === mediaId);

              if (!alreadyLinked) {
                try {
                  await this.api.linkMediaToProduct(variantId, mediaId);
                  imagesLinked++;
                } catch (error) {
                  console.log(`  WARNING: Failed to link image: ${error.message}`);
                }
              }
            }
          } else {
            console.log(`  [DRY RUN] Would upload/link image: ${imageFilename}`);
            imagesLinked++;
          }
        }
      }

      console.log(`  Done: ${variantsCreated} variants, ${imagesLinked} images`);

      this.progress.completed.push(productName);
      if (!this.dryRun) {
        saveJSON(CONFIG.progressFile, this.progress);
      }

      return {
        status: 'success',
        variantsCreated,
        imagesLinked,
        variantImages: product.variant_images?.length || 0
      };

    } catch (error) {
      console.log(`  ERROR: ${error.message}`);
      this.progress.failed.push({ name: productName, error: error.message });
      return { status: 'failed', reason: error.message };
    }
  }

  async run() {
    await this.init();

    // Load scraped data
    console.log(`Loading scraped data from: ${CONFIG.scrapedDataPath}`);
    const data = loadJSON(CONFIG.scrapedDataPath);
    const products = data.snigel_products;

    // Filter products with variants
    const productsWithVariants = products.filter(p =>
      p.options && Object.keys(p.options).length > 0
    );

    console.log(`Total products: ${products.length}`);
    console.log(`Products with variants: ${productsWithVariants.length}`);
    console.log('');

    // Process each product
    for (let i = 0; i < productsWithVariants.length; i++) {
      const result = await this.processProduct(productsWithVariants[i], i, productsWithVariants.length);
      this.results.push({
        product: productsWithVariants[i].snigel_name,
        ...result
      });

      // Small delay between products to avoid rate limiting
      if (!this.dryRun && i < productsWithVariants.length - 1) {
        await sleep(500);
      }
    }

    // Save results
    const summary = {
      timestamp: new Date().toISOString(),
      mode: this.dryRun ? 'dry-run' : 'live',
      total: productsWithVariants.length,
      success: this.results.filter(r => r.status === 'success').length,
      failed: this.results.filter(r => r.status === 'failed').length,
      skipped: this.results.filter(r => r.status === 'skipped').length,
      results: this.results
    };

    if (!this.dryRun) {
      saveJSON(CONFIG.resultsFile, summary);
    }

    console.log('\n=== SUMMARY ===');
    console.log(`Total processed: ${productsWithVariants.length}`);
    console.log(`Success: ${summary.success}`);
    console.log(`Failed: ${summary.failed}`);
    console.log(`Skipped: ${summary.skipped}`);

    if (!this.dryRun) {
      console.log(`\nResults saved to: ${CONFIG.resultsFile}`);
    }
  }
}

// === MAIN ===
async function main() {
  const args = process.argv.slice(2);
  const dryRun = args.includes('--dry-run');
  const resume = args.includes('--resume');

  const sync = new VariantSync(dryRun, resume);
  await sync.run();
}

main().catch(error => {
  console.error('Fatal error:', error);
  process.exit(1);
});
