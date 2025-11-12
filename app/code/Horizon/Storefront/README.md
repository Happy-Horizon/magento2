# Horizon Storefront - Href Lang Tag Functionality

This module implements href lang tag functionality for the multilingual webshop 'Its All About Christmas' in Magento 2, ensuring proper SEO support for multiple languages.

## Features

- **Href Lang Tags**: Automatically generates href lang tags for products, categories, and CMS pages
- **Canonical URLs**: Provides canonical URL support aligned with href lang tags
- **GraphQL Support**: Extends GraphQL schema with `alternate_urls` and `canonical_url` fields
- **Cache Management**: Admin interface for cleaning GraphQL cache
- **Schema Regeneration**: Automatic GraphQL schema regeneration on decode failures
- **Multilingual Support**: Properly handles page availability across multiple store views

## Installation

1. Enable the module:
```bash
php bin/magento module:enable Horizon_Storefront
php bin/magento setup:upgrade
php bin/magento cache:clean
```

2. Compile and deploy:
```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

## Configuration

The module automatically detects available store views and generates appropriate href lang tags based on:
- Store locale configuration
- Current page URL
- Available store views

## GraphQL API

The module extends the following GraphQL types:

### ProductInterface
- `alternate_urls: [AlternateUrl]` - Array of alternate URLs with language codes
- `canonical_url: String` - Canonical URL for the product

### CategoryInterface
- `alternate_urls: [AlternateUrl]` - Array of alternate URLs with language codes
- `canonical_url: String` - Canonical URL for the category

### CmsPage
- `alternate_urls: [AlternateUrl]` - Array of alternate URLs with language codes
- `canonical_url: String` - Canonical URL for the CMS page

### AlternateUrl Type
- `hreflang: String` - Language code (e.g., 'en-us', 'nl-nl', 'x-default')
- `url: String` - Alternate URL for the specified language

## Admin Features

### Clean GraphQL Cache
Navigate to: **System > Cache Management > Clean GraphQL Cache**

This action:
- Clears GraphQL schema cache
- Clears GraphQL resolver cache
- Invalidates related cache types

## Frontend Integration

Href lang tags are automatically rendered in the `<head>` section of all pages via the `Horizon\Storefront\Block\Hreflang` block.

## Recent Improvements

1. **Helper/Data.php**: Fixed `getFileContent()` method to properly return file content with error handling
2. **Plugin/Graphql/Reader.php**: Added logging and automatic schema cache clearing on decode failures
3. **Controller/Adminhtml/Cache/CleanGraphql.php**: Refactored for clearer cache invalidation logic

## Validation

To validate the implementation:

1. **Check GraphQL Schema**:
   - Query products/categories/CMS pages via GraphQL
   - Verify `alternate_urls` and `canonical_url` fields are available

2. **Check Frontend Output**:
   - View page source on any product/category/CMS page
   - Verify `<link rel="alternate" hreflang="..." href="..." />` tags are present

3. **Check Multilingual Support**:
   - Verify href lang tags include all available store views
   - Verify x-default tag is present for default store

4. **Test Cache Management**:
   - Use admin interface to clean GraphQL cache
   - Verify schema regenerates correctly after cache clear

## Compatibility

- Magento 2.4.x
- PHP 8.1+
- Compatible with Horizon Storefront frontend

## Support

For issues or questions, contact Happy Horizon Arnhem development team.
