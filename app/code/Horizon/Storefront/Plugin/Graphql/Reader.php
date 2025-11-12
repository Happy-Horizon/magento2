<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Plugin\Graphql;

use Magento\Framework\GraphQlSchemaStitching\Common\Reader as GraphQlReader;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\FrontendInterface;

/**
 * Plugin for GraphQL Reader to handle schema regeneration on decode failure
 */
class Reader
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FrontendInterface
     */
    private $cache;

    /**
     * @param LoggerInterface $logger
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(
        LoggerInterface $logger,
        FrontendPool $cacheFrontendPool
    ) {
        $this->logger = $logger;
        $this->cache = $cacheFrontendPool->get('graphql_schema');
    }

    /**
     * After plugin to handle decode failures and regenerate schema
     *
     * @param GraphQlReader $subject
     * @param array $result
     * @param string|null $scope
     * @return array
     */
    public function afterRead(GraphQlReader $subject, array $result, $scope = null): array
    {
        try {
            // Validate the result structure
            if (empty($result)) {
                $this->logger->warning('GraphQL schema read returned empty result');
                $this->clearSchemaCache();
                return $result;
            }

            // Check if alternate_urls and canonical_url are present in the schema
            $hasAlternateUrls = $this->hasFieldInSchema($result, 'alternate_urls');
            $hasCanonicalUrl = $this->hasFieldInSchema($result, 'canonical_url');

            if (!$hasAlternateUrls || !$hasCanonicalUrl) {
                $this->logger->info(
                    'GraphQL schema missing alternate_urls or canonical_url fields. Clearing cache for regeneration.'
                );
                $this->clearSchemaCache();
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error in GraphQL Reader plugin: %s', $e->getMessage()),
                ['exception' => $e, 'scope' => $scope]
            );
            
            // Clear cache on error but return original result to avoid breaking the request
            $this->clearSchemaCache();
            return $result;
        }
    }

    /**
     * Clear GraphQL schema cache
     *
     * @return void
     */
    private function clearSchemaCache(): void
    {
        try {
            $this->logger->info('Clearing GraphQL schema cache...');
            $this->cache->clean();
            $this->logger->info('GraphQL schema cache cleared. Schema will be regenerated on next request.');
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to clear GraphQL schema cache: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    /**
     * Check if field exists in schema
     *
     * @param array $schema
     * @param string $fieldName
     * @return bool
     */
    private function hasFieldInSchema(array $schema, string $fieldName): bool
    {
        $searchArray = function ($array, $fieldName) use (&$searchArray) {
            foreach ($array as $key => $value) {
                if ($key === $fieldName) {
                    return true;
                }
                if (is_array($value) && $searchArray($value, $fieldName)) {
                    return true;
                }
            }
            return false;
        };

        return $searchArray($schema, $fieldName);
    }
}
