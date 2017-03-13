<?php

class SomethingDigital_ConfigProductPerf_Model_Resource_Product extends Mage_Catalog_Model_Resource_Product
{
    protected $_sortingSetInfoPath = null;

    protected $_sortedSetAttributesCache = [];

    /**
     * Retrieve sorted attributes
     *
     * @param int $setId
     * @return array
     */
    public function getSortedAttributes($setId = null)
    {
        /** @var Mage_Eav_Model_Entity_Attribute_Abstract[] $attributes */
        $attributes = $this->getAttributesByCode();
        if ($setId === null) {
            $setId = $this->getEntityType()->getDefaultAttributeSetId();
        }

        // initialize set info
        Mage::getSingleton('eav/entity_attribute_set')
            ->addSetInfo($this->getEntityType(), $attributes, $setId);

        $entityType = $this->getEntityType();
        $cachedSortedCodes = &$this->_sortedSetAttributesCache[$entityType->getId() . '-' . $setId];

        if (isset($cachedSortedCodes)) {
            $attributes = $this->sortAttributesUsingCache($attributes, $cachedSortedCodes);
        } else {
            $attributes = $this->sortAttributesWithoutCache($attributes, $setId);

            // Remember the sort order for next time.  This is a reference.
            $cachedSortedCodes = array_keys($attributes);
        }
        unset($cachedSortedCodes);

        return $attributes;
    }

    protected function sortAttributesUsingCache($attributes, $sortedCodes)
    {
        $sorted = [];
        // This is easy: we can rely on sortedCodes to have the only codes in the set, too.
        foreach ($sortedCodes as $code) {
            $sorted[$code] = $attributes[$code];
        }
        return $sorted;
    }

    protected function sortAttributesWithoutCache($attributes, $setId)
    {
        // Strip any attributes that aren't supposed to be in this set.
        foreach ($attributes as $code => $attribute) {
            if (!$attribute->isInSet($setId)) {
                unset($attributes[$code]);
            }
        }

        // Now sort the remainder.
        $this->_sortingSetId = $setId;
        $this->_sortingSetInfoPath = sprintf('attribute_set_info/%s', $this->_sortingSetId);
        uasort($attributes, array($this, 'attributesCompare'));

        return $attributes;
    }

    /**
     * Compare attributes
     *
     * @param Mage_Eav_Model_Entity_Attribute $attribute1
     * @param Mage_Eav_Model_Entity_Attribute $attribute2
     * @return int
     */
    public function attributesCompare($attribute1, $attribute2)
    {
        // We spend a LOT of time in this function, so optimizing it makes a lot of sense.
        // The below implementation is functionally equivalent to the default, but takes about 45% as long.

        $attr1Info = $attribute1->getData($this->_sortingSetInfoPath);
        $attr2Info = $attribute2->getData($this->_sortingSetInfoPath);

        $attr1GroupSort = isset($attr1Info['group_sort']) ? $attr1Info['group_sort'] : 0;
        $attr2GroupSort = isset($attr2Info['group_sort']) ? $attr2Info['group_sort'] : 0;
        if ($attr1GroupSort > $attr2GroupSort) {
            return 1;
        } elseif ($attr1GroupSort < $attr2GroupSort) {
            return -1;
        }

        $attr1Sort = isset($attr1Info['sort']) ? $attr1Info['sort'] : 0;
        $attr2Sort = isset($attr2Info['sort']) ? $attr2Info['sort'] : 0;
        if ($attr1Sort > $attr2Sort) {
            return 1;
        } elseif ($attr1Sort < $attr2Sort) {
            return -1;
        }

        return 0;
    }
}
