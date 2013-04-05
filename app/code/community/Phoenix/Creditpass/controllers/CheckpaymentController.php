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
 * @copyright  Copyright (c) 2009 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Phoenix_Creditpass_CheckpaymentController extends Mage_Checkout_Controller_Action
{
	/**
	 * Check if payment is cleared
	 */
	public function processAction()
	{
		Mage::log('creditPass: checkpaymentAction()');

		$response = '';
		try {
			if (Mage::helper('creditpass')->checkPayment())
				$response = 'PASSED';
		}
		catch (Exception $e) {
			$response = $e->getMessage();
		}
		Mage::log('creditPass result: '.$response);

		if ($response != 'PASSED')
			$response = trim(Mage::helper('creditpass')->getConfig('error_message'));
		if (strlen($response) == 0)
			$response = Mage::helper('creditpass')->__('The selected payment type is currently not available. Please select another one.');

    	$this->getResponse()->setBody($response);
	}
}
?>