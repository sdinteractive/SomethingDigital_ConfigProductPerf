<?php

class SomethingDigital_ConfigProductPerf_Helper_ConfigurableSwatches_Mediafallback extends Mage_ConfigurableSwatches_Helper_Mediafallback
{
    /**
     * Set child_attribute_label_mapping on products with attribute label -> product mapping
     * Depends on following product data:
     * - product must have children products attached
     *
     * This was modified from the base implementation to perform less processing per attribute.
     * With a large number of product attributes, the performance difference can be significant.
     *
     * @param Mage_Catalog_Model_Product[] $parentProducts
     * @param int $storeId
     * @return void
     */
    public function attachConfigurableProductChildrenAttributeMapping(array $parentProducts, $storeId)
    {
        $listSwatchAttr = Mage::helper('configurableswatches/productlist')->getSwatchAttribute();
        $listSwatchAttrId = $listSwatchAttr->getAttributeId();

        $parentProductIds = array();
        /* @var $parentProduct Mage_Catalog_Model_Product */
        foreach ($parentProducts as $parentProduct) {
            $parentProductIds[] = $parentProduct->getId();
        }

        $configAttributes = Mage::getResourceModel('configurableswatches/catalog_product_attribute_super_collection')
            ->addParentProductsFilter($parentProductIds)
            ->attachEavAttributes()
            ->setStoreId($storeId)
        ;

        $optionLabels = array();
        foreach ($configAttributes as $attribute) {
            $optionLabels += $attribute->getOptionLabels();
        }

        /*--- SD Modification from core, moved out of loop -------*/
        // normalize to all lower case before we start using them
        $optionLabels = array_map(function ($value) {
            return array_map('Mage_ConfigurableSwatches_Helper_Data::normalizeKey', $value);
        }, $optionLabels);
        /*--- SD Modification from core, moved out of loop -------*/

        foreach ($parentProducts as $parentProduct) {
            $mapping = array();
            $listSwatchValues = array();

            /* @var $attribute Mage_Catalog_Model_Product_Type_Configurable_Attribute */
            foreach ($configAttributes as $attribute) {
                $attributeCode = $attribute->getAttributeCode();
                $attributeId = $attribute->getAttributeId();
                /* @var $childProduct Mage_Catalog_Model_Product */
                if (!is_array($parentProduct->getChildrenProducts())) {
                    continue;
                }

                foreach ($parentProduct->getChildrenProducts() as $childProduct) {
                    // product has no value for attribute, we can't process it
                    if (!$childProduct->hasData($attributeCode)) {
                        continue;
                    }
                    $optionId = $childProduct->getData($attributeCode);

                    // if we don't have a default label, skip it
                    if (!isset($optionLabels[$optionId][0])) {
                        continue;
                    }

                    // using default value as key unless store-specific label is present
                    $optionLabel = $optionLabels[$optionId][0];
                    if (isset($optionLabels[$optionId][$storeId])) {
                        $optionLabel = $optionLabels[$optionId][$storeId];
                    }

                    // initialize arrays if not present
                    if (!isset($mapping[$optionLabel])) {
                        $mapping[$optionLabel] = array(
                            'product_ids' => array(),
                        );
                    }
                    $mapping[$optionLabel]['product_ids'][] = $childProduct->getId();
                    $mapping[$optionLabel]['label'] = $optionLabel;
                    $mapping[$optionLabel]['default_label'] = $optionLabels[$optionId][0];
                    $mapping[$optionLabel]['labels'] = $optionLabels[$optionId];

                    if ($attributeId == $listSwatchAttrId && !in_array($mapping[$optionLabel]['label'], $listSwatchValues)) {
                        $listSwatchValues[$optionId] = $mapping[$optionLabel]['label'];
                    }
                } // end looping child products
            } // end looping attributes


            foreach ($mapping as $key => $value) {
                $mapping[$key]['product_ids'] = array_unique($mapping[$key]['product_ids']);
            }

            /**
             * Start modification: sort the swatch values by their sort order
             * @author Robbie Averill <robbie@averill.co.nz>
             */
            $listSwatchValues = Mage::helper('attributefix')->sortOptionValues($listSwatchValues);
            /**
             * End moficiation
             * @author Robbie Averill <robbie@averill.co.nz>
             */

            $parentProduct->setChildAttributeLabelMapping($mapping)
                ->setListSwatchAttrValues($listSwatchValues);
        } // end looping parent products
    }
}
