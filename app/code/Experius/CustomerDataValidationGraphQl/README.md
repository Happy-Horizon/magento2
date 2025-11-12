# Experius Customer Data Validation GraphQL Module

## Overview

This module prevents cross-customer data exposure in Magento 2 GraphQL API by validating customer IDs, customer objects, cart ownership, and address ownership against the authenticated user. It addresses critical privacy concerns where a customer logged in as customer A might be identified as customer B during checkout or other operations.

## Features

- **Customer ID Validation**: Validates customer IDs against the authenticated user
- **Customer Object Validation**: Validates customer objects against the authenticated user
- **Cart Ownership Validation**: Ensures carts belong to the authenticated customer
- **Address Ownership Validation**: Ensures addresses belong to the authenticated customer
- **Comprehensive Logging**: Logs all validation mismatches for security monitoring
- **GraphQL Plugins**: Automatic validation for critical GraphQL operations
- **GraphQL Resolvers**: New resolvers for explicit frontend validation

## Installation

1. Copy the module to `app/code/Experius/CustomerDataValidationGraphQl/`
2. Run `bin/magento module:enable Experius_CustomerDataValidationGraphQl`
3. Run `bin/magento setup:upgrade`
4. Run `bin/magento cache:flush`

## Module Structure

```
Experius/CustomerDataValidationGraphQl/
├── etc/
│   ├── di.xml              # Dependency injection configuration for plugins
│   ├── module.xml          # Module declaration
│   └── schema.graphqls     # GraphQL schema definitions
├── Model/
│   ├── Resolver/
│   │   ├── CurrentCustomer.php      # Resolver for currentCustomer query
│   │   └── ValidateCustomer.php     # Resolver for validateCustomer query
│   └── Service/
│       └── CustomerDataValidator.php # Core validation service
├── Plugin/
│   ├── CustomerGraphQl/
│   │   └── Model/
│   │       ├── Customer/
│   │       │   └── Address/
│   │       │       └── GetCustomerAddressPlugin.php
│   │       └── Resolver/
│   │           └── CustomerPlugin.php
│   └── QuoteGraphQl/
│       └── Model/
│           ├── Cart/
│           │   └── GetCartForUserPlugin.php
│           └── Resolver/
│               └── PlaceOrderPlugin.php
├── composer.json
├── registration.php
└── README.md
```

## GraphQL Plugins

The module includes plugins that automatically validate customer data for the following operations:

1. **GetCartForUser**: Validates cart ownership when retrieving carts
2. **Customer Resolver**: Validates customer data when resolving customer queries
3. **GetCustomerAddress**: Validates address ownership when retrieving addresses
4. **PlaceOrder**: Validates cart ownership before placing orders

## GraphQL Queries

### validateCustomer

Validates that a customer ID matches the authenticated user.

```graphql
query {
  validateCustomer(customer_id: 123) {
    is_valid
    message
  }
}
```

### currentCustomer

Returns the currently authenticated customer's data.

```graphql
query {
  currentCustomer {
    id
    email
    firstname
    lastname
  }
}
```

## Validation Service

The `CustomerDataValidator` service provides the following validation methods:

- `validateCustomerId()`: Validates a customer ID against the authenticated user
- `validateCustomer()`: Validates a customer object against the authenticated user
- `validateCartOwnership()`: Validates that a cart belongs to the authenticated customer
- `validateAddressOwnership()`: Validates that an address belongs to the authenticated customer

All mismatches are logged with detailed context information for security monitoring.

## Security

- All validation mismatches are logged to the Magento log system
- Validation occurs automatically through plugins, preventing unauthorized data access
- Explicit validation queries are available for frontend validation
- Guest carts are handled appropriately (customer_id = 0)

## Logging

Validation mismatches are logged with the following information:
- Type of mismatch (Customer ID, Cart ownership, Address ownership)
- Expected customer ID
- Actual customer ID
- Context information (operation, IDs, etc.)
- Timestamp

Logs can be found in `var/log/system.log` or `var/log/exception.log` depending on the log level.

## Testing

After installation, test the module by:

1. Logging in as customer A
2. Attempting to access customer B's cart
3. Attempting to access customer B's address
4. Verifying that appropriate errors are thrown and logged

## Support

For issues or questions, please contact Experius B.V.

## License

Copyright © Experius B.V. All rights reserved.
