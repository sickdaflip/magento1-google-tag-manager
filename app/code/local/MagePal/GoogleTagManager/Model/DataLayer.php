<?php

/**
 * DataLayer
 * Copyright © 2016 MagePal. All rights reserved.
 * See COPYING.txt for license details.
 */
class MagePal_GoogleTagManager_Model_DataLayer extends Mage_Core_Model_Abstract {
    
    /**
     * @var Quote|null
     */
    protected $_quote = null;
    
    /**
     * Datalayer Variables
     * @var array
     */
    protected $_variables = array();

    /**
     * Customer session
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    
    /**
     * @var string
     */
    protected $_fullActionName;


    /**
     * @param MessageInterface $message
     * @param null $parameters
     */
    public function __construct() {

        $this->_customerSession = Mage::getSingleton('customer/session');
        
        $this->fullActionName = Mage::app()->getFrontController()->getAction() ? Mage::app()->getFrontController()->getAction()->getFullActionName() : 'Unknown';;

        // Switch events to GTag Referenz
        switch($this->fullActionName) {
            case 'catalog_category_view':
                $this->event = 'view_item_list';
                break;
            case 'catalog_product_view':
                $this->event = 'view_item';
                break;
            case 'checkout_index_index':
                $this->event = 'view_cart';
                break;
            case 'checkout_cart_index':
                $this->event = 'view_cart';
                break;
            case 'ajax_cart_add':
                $this->event = 'add_to_cart';
                break;
            default: //fallback
                $this->event = $this->fullActionName;
        }

        $this->addVariable('event', $this->event);
        $this->setCustomerDataLayer();
        $this->setProductDataLayer();
        $this->setCategoryDataLayer();
        $this->setCartDataLayer();

    }

    /**
     * Return Data Layer Variables
     *
     * @return array
     */
    public function getVariables() {
        return $this->_variables;
    }

    /**
     * Add Variables
     * @param string $name
     * @param mix $value
     * @return MagePal\GoogleTagManager\Model\DataLayer
     */
    public function addVariable($name, $value) {

        if (!empty($name)) {
            $this->_variables[$name] = $value;
        }

        return $this;
    }


    /**
     * Set category Data Layer
     */
    protected function setCategoryDataLayer() {
        if($this->fullActionName === 'catalog_category_view' && $_category = Mage::registry('current_category')) {

                //$category = array();

                $ecommerce = array(
                    'item_list_id' => $_category->getId(),
                    'item_list_name' => $_category->getName(),
                    //'items' => $category
                );
            $this->addVariable('ecommerce', $ecommerce);

        }
        return $this;
    }
    
    
    /**
     * Set product Data Layer
     */
    protected function setProductDataLayer() {
        if($this->fullActionName === 'catalog_product_view' && $_product = Mage::registry('current_product')) {

            $product = array();
            $product['item_id'] = $_product->getSku();
            $product['item_name'] = $_product->getName();
            $product['item_brand'] = $_product->getAttributeText('manufacturer');
            $product['price'] = $this->formatPrice($_product->getPrice());

            $ecommerce = array(
                'currency' => $_product->getStore()->getBaseCurrencyCode(),
                'items' => $product
            );

            $this->addVariable('ecommerce', $ecommerce);

        }

        return $this;
    }

    /**
     * Set Customer Data Layer
     */
    protected function setCustomerDataLayer() {
        $customer = array();
        if ($this->_customerSession->isLoggedIn()) {
            $customer['isLoggedIn'] = true;
            if (Mage::helper('googletagmanager')->sendPersonal()) {
                $customer['id'] = $this->_customerSession->getCustomerId();
                $customer['groupId'] = $this->_customerSession->getCustomerGroupId();
            }
            //$customer['groupCode'] = ;
        } else {
            $customer['isLoggedIn'] = false;
        }
        
        $this->addVariable('customer', $customer);

        return $this;
    }
    
    
    /**
     * Set cart Data Layer
     */
    protected function setCartDataLayer() {
        if($this->fullActionName === 'checkout_index_index' || $this->fullActionName === 'checkout_cart_index') {

        $quote = $this->getQuote();
        $cart = array();

        
        if ($quote->getItemsCount()) {
            $items = array();
            
            // set items
            foreach($quote->getAllVisibleItems() as $item){
                $items[] = array(
                    'item_id' => $item->getSku(),
                    'item_name' => $item->getName(),
                    'item_brand' => $item->getProduct()->getAttributeText('manufacturer'),
                    'price' => $this->formatPrice($item->getPrice()),
                    'quantity' => $item->getQty()
                );
            }

            if(count($items) > 0){
                //$cart['hasItems'] = true;
                $cart['items'] = $items;
            }

        }

            $ecommerce = array(
                'currency' => $item->getStore()->getBaseCurrencyCode(),
                'value' => $this->formatPrice($quote->getGrandTotal()),
                //'itemCount' => $quote->getItemsCount(),
                'items' => $items
            );

            $this->addVariable('ecommerce', $ecommerce);

        }
        return $this;

    }

    /**
     * Get active quote
     *
     * @return Quote
     */
    public function getQuote()
    {
        if (null === $this->_quote) {
            $this->_quote = Mage::getSingleton('checkout/cart')->getQuote();
        }
        return $this->_quote;
    }

    public function formatPrice($price){
        return number_format($price, 2, '.','');
    }

    public function formatQuantity($quantity){
        return number_format($quantity, 0);
    }

}
