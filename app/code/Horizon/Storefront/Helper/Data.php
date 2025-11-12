<?php
/**
 * Copyright © Horizon Storefront. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Horizon\Storefront\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * Storefront Helper Class
 */
class Data extends AbstractHelper implements DataInterface
{
    /**
     * @var File
     */
    private $fileDriver;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param File $fileDriver
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        File $fileDriver,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->fileDriver = $fileDriver;
        $this->storeManager = $storeManager;
        $this->logger = $context->getLogger();
    }

    /**
     * Get file content
     *
     * @param string $filePath
     * @return string
     * @throws FileSystemException
     */
    public function getFileContent(string $filePath): string
    {
        try {
            if (!$this->fileDriver->isExists($filePath)) {
                $this->logger->warning(
                    sprintf('File does not exist: %s', $filePath)
                );
                return '';
            }

            if (!$this->fileDriver->isFile($filePath)) {
                $this->logger->warning(
                    sprintf('Path is not a file: %s', $filePath)
                );
                return '';
            }

            $content = $this->fileDriver->fileGetContents($filePath);
            
            if ($content === false) {
                $this->logger->error(
                    sprintf('Failed to read file content: %s', $filePath)
                );
                return '';
            }

            return $content;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error reading file %s: %s', $filePath, $e->getMessage())
            );
            throw new FileSystemException(
                __('Unable to read file: %1', $filePath),
                $e
            );
        }
    }

    /**
     * Get alternate URLs for href lang tags
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAlternateUrls(?int $storeId = null): array
    {
        $alternateUrls = [];
        
        try {
            $currentStore = $this->storeManager->getStore($storeId);
            $stores = $this->storeManager->getStores();
            
            foreach ($stores as $store) {
                if ($store->getId() == $currentStore->getId()) {
                    continue;
                }
                
                $storeCode = $store->getCode();
                $localeCode = $this->getLocaleCode($store);
                
                if ($localeCode) {
                    $alternateUrls[] = [
                        'hreflang' => $localeCode,
                        'url' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK)
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error getting alternate URLs: %s', $e->getMessage())
            );
        }
        
        return $alternateUrls;
    }

    /**
     * Get canonical URL
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getCanonicalUrl(?int $storeId = null): ?string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            return $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error getting canonical URL: %s', $e->getMessage())
            );
            return null;
        }
    }

    /**
     * Get locale code for store
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return string|null
     */
    private function getLocaleCode($store): ?string
    {
        try {
            $locale = $store->getConfig(\Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE);
            return str_replace('_', '-', strtolower($locale));
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('Could not determine locale for store %s: %s', $store->getId(), $e->getMessage())
            );
            return null;
        }
    }
}
