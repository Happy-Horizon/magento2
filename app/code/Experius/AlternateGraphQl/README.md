# Experius AlternateGraphQl Module

This module provides `hreflang` and canonical URL support for multi-language webshops in Magento 2 GraphQL API. It extends the GraphQL schema to include `alternate_urls` fields for CMS pages, products, and categories, enabling proper SEO implementation for multi-language stores.

## Features

- **Alternate URLs Support**: Adds `alternate_urls` field to `CmsPage`, `ProductInterface`, and `CategoryTree` GraphQL types
- **Multi-language Support**: Automatically generates alternate URLs for all active store views
- **Locale to Hreflang Conversion**: Converts Magento locale codes to standard ISO 639-1 hreflang codes
- **Type Safety**: Includes validation for URLs and proper type handling
- **Empty Array Handling**: Returns empty array `[]` when no alternate URLs are available

## Installation

1. Copy the module to `app/code/Experius/AlternateGraphQl/`
2. Run the following commands:
   ```bash
   php bin/magento module:enable Experius_AlternateGraphQl
   php bin/magento setup:upgrade
   php bin/magento cache:flush
   ```

## GraphQL Schema Extension

The module extends the following GraphQL types:

### CmsPage
```graphql
type CmsPage {
    alternate_urls: [AlternateUrl]
}
```

### ProductInterface
```graphql
interface ProductInterface {
    alternate_urls: [AlternateUrl]
}
```

### CategoryTree
```graphql
type CategoryTree {
    alternate_urls: [AlternateUrl]
}
```

### AlternateUrl Type
```graphql
type AlternateUrl {
    href: String!      # The absolute URL for the alternate language version
    hreflang: String!  # The hreflang code (e.g., 'nl', 'en', 'nl-NL', 'x-default')
}
```

## Data Format

The `alternate_urls` field returns an array of objects with the following structure:

```json
[
    {
        "href": "https://example.com/nl/product.html",
        "hreflang": "nl"
    },
    {
        "href": "https://example.com/en/product.html",
        "hreflang": "en"
    },
    {
        "href": "https://example.com/de/product.html",
        "hreflang": "de"
    }
]
```

### Empty Array Response

When no alternate URLs are available (e.g., entity not available in other stores, single store setup), the resolver returns an empty array:

```json
[]
```

## Usage Examples

### Query CMS Page with Alternate URLs

```graphql
query {
    cmsPage(identifier: "about-us") {
        identifier
        title
        alternate_urls {
            href
            hreflang
        }
    }
}
```

### Query Product with Alternate URLs

```graphql
query {
    products(filter: { sku: { eq: "product-sku" } }) {
        items {
            sku
            name
            alternate_urls {
                href
                hreflang
            }
        }
    }
}
```

### Query Category with Alternate URLs

```graphql
query {
    categories(filters: { ids: { eq: "2" } }) {
        items {
            id
            name
            alternate_urls {
                href
                hreflang
            }
        }
    }
}
```

## Supported Page Types

The module supports alternate URLs for the following entity types:

1. **CMS Pages**: Pages created in the Magento CMS
2. **Products**: All product types (Simple, Configurable, Bundle, Grouped, etc.)
3. **Categories**: Category pages in the catalog

## Locale to Hreflang Mapping

The module includes a comprehensive mapping of Magento locale codes to ISO 639-1 hreflang codes. Some examples:

- `nl_NL` → `nl`
- `nl_BE` → `nl-BE`
- `en_US` → `en`
- `en_GB` → `en-GB`
- `de_DE` → `de`
- `de_AT` → `de-AT`
- `fr_FR` → `fr`
- `fr_BE` → `fr-BE`

For locales not explicitly mapped, the module extracts the language code from the locale (e.g., `nl_NL` → `nl`).

## URL Generation

The module generates URLs for each active store view:

1. **Products**: Uses the product's URL model with store-specific URL generation
2. **Categories**: Uses the category's URL with store-specific path
3. **CMS Pages**: Uses the page identifier with the store's base URL

All URLs are normalized to absolute URLs (including protocol and domain).

## Validation

The resolver includes validation to ensure:

- URLs are properly formatted
- Entities are available in the target store
- Only active stores are included
- Invalid URLs are filtered out

## Integration with Canonical URLs

This module works alongside Magento's canonical URL functionality. The `alternate_urls` field provides hreflang tags for SEO, while canonical URLs indicate the preferred version of a page.

## Technical Details

### Resolver Class
- **Class**: `Experius\AlternateGraphQl\Model\Resolver\AlternateUrls`
- **Location**: `app/code/Experius/AlternateGraphQl/Model/Resolver/AlternateUrls.php`

### Plugins
- **CMS Page Plugin**: `Experius\AlternateGraphQl\Plugin\CmsGraphQl\PagePlugin`
  - Injects page model into resolver value for proper entity access

### Dependencies
- `Magento_GraphQl`
- `Magento_CmsGraphQl`
- `Magento_CatalogGraphQl`
- `Magento_Store`

## Configuration

No additional configuration is required. The module automatically:

- Detects all active store views
- Generates URLs based on store configuration
- Converts locales based on the mapping table

## Troubleshooting

### Empty Array Returned

If `alternate_urls` returns an empty array, check:

1. Multiple store views are configured and active
2. Entities are assigned to the store views
3. Store views have proper base URLs configured
4. Locale codes are properly configured in store settings

### Missing URLs for Specific Stores

If URLs are missing for specific stores:

1. Verify the entity is assigned to that store view
2. Check that the store view is active
3. Ensure the entity is available/published in that store view
4. Verify base URLs are configured correctly

## Compatibility

- **Magento Version**: 2.4.x and later
- **PHP Version**: 8.1, 8.2, or 8.3

## License

[OSL-3.0](https://opensource.org/licenses/OSL-3.0) / [AFL-3.0](https://opensource.org/licenses/AFL-3.0)

## Support

For issues, questions, or contributions, please contact Experius or create an issue in the repository.
