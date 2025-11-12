<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Helper;

/**
 * Interface for Storefront Helper
 */
interface DataInterface
{
    /**
     * Get file content
     *
     * @param string $filePath
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getFileContent(string $filePath): string;

    /**
     * Get alternate URLs for href lang tags
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAlternateUrls(?int $storeId = null): array;

    /**
     * Get canonical URL
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getCanonicalUrl(?int $storeId = null): ?string;
}
