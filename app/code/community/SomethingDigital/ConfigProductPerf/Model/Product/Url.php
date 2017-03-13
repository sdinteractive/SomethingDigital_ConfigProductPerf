<?php

class SomethingDigital_ConfigProductPerf_Model_Product_Url extends Enterprise_Catalog_Model_Product_Url
{
    protected static $cachedCategoryRequestPaths = array();

    /**
     * Retrieve product URL based on requestPath param
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $requestPath
     * @param array $routeParams
     *
     * @return string
     */
    protected function _getProductUrl($product, $requestPath, $routeParams)
    {
        $categoryId = $this->_getCategoryIdForUrl($product, $routeParams);

        if (!empty($requestPath)) {
            if ($categoryId) {
                $categoryRequestPath = $this->getCategoryRequestPath($categoryId);
                if ($categoryRequestPath !== false) {
                    $requestPath = $categoryRequestPath . '/' . $requestPath;
                }
            }
            $product->setRequestPath($requestPath);

            $storeId = $this->getUrlInstance()->getStore()->getId();
            $requestPath = $this->_factory->getHelper('enterprise_catalog')
                ->getProductRequestPath($requestPath, $storeId);

            return $this->getUrlInstance()->getDirectUrl($requestPath, $routeParams);
        }

        $routeParams['id'] = $product->getId();
        $routeParams['s'] = $product->getUrlKey();
        if ($categoryId) {
            $routeParams['category'] = $categoryId;
        }
        return $this->getUrlInstance()->getUrl('catalog/product/view', $routeParams);
    }

    protected function getCategoryRequestPath($categoryId)
    {
        $cachedRequestPath = &static::$cachedCategoryRequestPaths[$categoryId];
        if (!isset($cachedRequestPath)) {
            $cachedRequestPath = false;

            $category = $this->_factory->getModel('catalog/category', array('disable_flat' => true))
                ->load($categoryId);
            if ($category->getId()) {
                $categoryRewrite = $this->_factory->getModel('enterprise_catalog/category')
                    ->loadByCategory($category);
                if ($categoryRewrite->getId()) {
                    $cachedRequestPath = $categoryRewrite->getRequestPath();
                }
            }
        }

        return $cachedRequestPath;
    }
}
