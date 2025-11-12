# HappyHorizon Hreflang GraphQL Module

This module provides multi-language support using `hreflang` tags for products and categories in the Magento GraphQL API. It enables the Horizon Storefront to implement proper SEO-compliant hreflang tags for multi-store/multi-language setups.

## Features

- **Product Hreflang Support**: Adds `hreflang` and `alternate_urls` fields to `ProductInterface` in GraphQL
- **Category Hreflang Support**: Adds `hreflang` and `alternate_urls` fields to `CategoryInterface` in GraphQL
- **Automatic Locale Normalization**: Converts Magento locale codes (e.g., `en_US`) to hreflang format (e.g., `en-us`)
- **Absolute URL Generation**: Generates absolute URLs for all alternate store views
- **Store Availability Detection**: Automatically detects which stores have the product/category available

## Installation

1. Ensure the module files are in place:
   ```
   app/code/HappyHorizon/HreflangGraphQl/
   ```

2. Enable the module:
   ```bash
   php bin/magento module:enable HappyHorizon_HreflangGraphQl
   php bin/magento setup:upgrade
   php bin/magento cache:flush
   ```

3. Compile GraphQL schema:
   ```bash
   php bin/magento setup:graphql:generate-cache
   ```

## GraphQL Schema

### ProductInterface Extensions

```graphql
extend interface ProductInterface {
    hreflang: String
    alternate_urls: String
}
```

### CategoryInterface Extensions

```graphql
extend interface CategoryInterface {
    hreflang: String
    alternate_urls: String
}
```

## Usage Examples

### Query Product with Hreflang

```graphql
query {
  products(filter: { sku: { eq: "product-sku" } }) {
    items {
      sku
      name
      hreflang
      alternate_urls
      canonical_url
    }
  }
}
```

### Query Category with Hreflang

```graphql
query {
  categories(filters: { ids: { eq: "2" } }) {
    items {
      id
      name
      hreflang
      alternate_urls
      canonical_url
    }
  }
}
```

## Field Descriptions

### `hreflang: String`
Returns the normalized locale code for the current store view (e.g., `en-us`, `nl-nl`). This corresponds to the `hreflang` attribute value for the current page.

### `alternate_urls: String`
Returns a JSON string containing alternate URLs for all store views where the product/category is available. Format:
```json
[
  {"locale": "en-us", "url": "https://example.com/product"},
  {"locale": "nl-nl", "url": "https://example.nl/product"}
]
```

## Integration with Canonical URLs

This module works alongside Magento's built-in canonical URL functionality:
- `canonical_url` field: Returns the canonical URL for the current store (if enabled in Magento settings)
- `hreflang` field: Returns the locale code for the current store
- `alternate_urls` field: Returns all alternate URLs with their locales

When implementing hreflang tags in the frontend:
1. Use `canonical_url` for the `<link rel="canonical">` tag
2. Use `hreflang` for the current page's `<link rel="alternate" hreflang="...">` tag
3. Parse `alternate_urls` JSON to generate additional `<link rel="alternate" hreflang="...">` tags for all alternate store views

## Configuration

### Store Locale Configuration

Ensure each store view has a proper locale code configured:
1. Go to **Stores > Configuration > General > Locale Options**
2. Set **Locale** for each store view (e.g., `English (United States)`, `Dutch (Netherlands)`)
3. The module will automatically normalize these to hreflang format

### Store Availability

Products and categories are automatically filtered based on:
- Store/website assignment
- Product status (enabled/disabled)
- Category active status
- Store active status

## Backend Integration Notes

For backend application integration:

1. **Store Configuration**: Ensure all store views are properly configured with locale codes
2. **Product Assignment**: Products must be assigned to the appropriate websites/stores
3. **Category Assignment**: Categories must be active in the stores where they should appear
4. **URL Generation**: The module generates absolute URLs based on each store's base URL configuration

## Validation Checklist

When validating with Happy Horizon Arnhem, ensure:

- [ ] All store views have locale codes configured
- [ ] Products are assigned to correct websites/stores
- [ ] Categories are active in appropriate stores
- [ ] Base URLs are configured correctly for each store
- [ ] GraphQL queries return `hreflang` and `alternate_urls` fields
- [ ] Alternate URLs are absolute (not relative)
- [ ] Locale codes are properly normalized (e.g., `en-us`, not `en_US`)
- [ ] Canonical URLs work correctly alongside hreflang tags
- [ ] Only active stores/products/categories are included in alternate URLs

## Technical Details

### Module Structure

```
HappyHorizon/HreflangGraphQl/
├── Model/
│   ├── HreflangUrlGenerator.php          # Core URL generation and locale normalization
│   └── Resolver/
│       ├── Product/
│       │   ├── Hreflang.php              # Product hreflang resolver
│       │   └── AlternateUrls.php         # Product alternate URLs resolver
│       └── Category/
│           ├── Hreflang.php              # Category hreflang resolver
│           └── AlternateUrls.php         # Category alternate URLs resolver
├── etc/
│   ├── module.xml                         # Module declaration
│   └── schema.graphqls                    # GraphQL schema extensions
├── registration.php                      # Module registration
└── composer.json                          # Composer configuration
```

### Dependencies

- Magento_Catalog
- Magento_CatalogGraphQl
- Magento_Store
- Magento_StoreGraphQl
- Magento_GraphQl

## Support

For issues or questions regarding this module, please contact the development team.
