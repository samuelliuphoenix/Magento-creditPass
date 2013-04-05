<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Phoenix
 * @package    Phoenix_Creditpass
 * @copyright  Copyright (c) 2010 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Phoenix_Creditpass_Block_Multishipping_Billing extends Mage_Checkout_Block_Multishipping_Billing
{
    /**
     * Overrides
     * Performs additional checking for creditPass
     *
     * @return bool
     */
    protected function _canUseMethod($method) {
        $allowed = parent::_canUseMethod($method);
        if ($allowed) {
            $allowed = $this->_canUseForCreditRating($method, $this->getQuote());
        }
        return $allowed;
    }

    /**
     * Credit Rating
     *
     * @return bool
     */
    protected function _canUseForCreditRating($method, $quote)
    {
        $helper = Mage::helper('creditpass');
        if ($helper->moduleActive() && !$helper->inAdmin() && $helper->filterActive())
        {
            Mage::log('creditPass: Payment filtering active');

            // only allow enabled methods
            $allowed_methods = $helper->getAllowedPaymentMethods();
            if (is_array($allowed_methods) && in_array($method->getCode(), $allowed_methods)) {
                return true;
            }
            return false;
        } else {
            Mage::log('creditPass: Payment filtering NOT active. moduleActive:'.var_export($helper->moduleActive(),true).';inAdmin:'.var_export($helper->inAdmin(),true).';filterActive:'.var_export($helper->filterActive(),true));
        }

        return true;
    }
}
