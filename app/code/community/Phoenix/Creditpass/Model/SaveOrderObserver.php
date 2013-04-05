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
class Phoenix_Creditpass_Model_SaveOrderObserver
{
	/**
	* Saves Creditpass status to order
	* @param   Varien_Event_Observer $observer
	* @return  Phoenix_Creditpass_Model_SaveOrderObserver
	*/
	public function saveCreditpassStatus($observer)
	{
		$event = $observer->getEvent();
		$session = Mage::getSingleton('core/session');
		$helper = Mage::helper('creditpass');

		if ($helper->moduleActive() && $session->getCreditPassQuoteId() == $event->getQuote()->getId() &&
			$session->getCreditPassAnswerText() != '') {
			$order = $event->getOrder();
			$status = 'creditPass (' . $session->getCreditPassTimestamp() . ") \n";
			
			$newOrderStatus = $order->getStatus();
			
			switch (intval($session->getCreditPassAnswerCode())) {
				case -1:
					$status .= $helper->__('An error occured during check. creditPassAnswerText:');
					break;
				case 0:
					$status .= $helper->__('Autorized');
					break;
				case 1:
					$status .= $helper->__('Not autorized');
					break;
				case 2:
					$status .= $helper->__('Manual check is needed.');
					break;
			}

			$status .= "\n\n" . $helper->__('creditPass answer text:') . ' ' . $session->getCreditPassAnswerText();
			$status .= "\n\n" . $helper->__('creditPass answer details:') . ' ' . $session->getCreditPassAnswerDetails();

			if ($helper->getConfigFlag('show_xml')) {
				$status .= "\n\n" . $helper->__('XML request:') . ' ' . $session->getCreditPassRequestRaw();
				$status .= "\n\n" . $helper->__('XML response:') . ' ' . $session->getCreditPassResponseRaw();
			}

			$order->addStatusToHistory($order->getStatus(), nl2br($status));
			$order->setCreditPassResponseRaw($session->getCreditPassResponseRaw());
			
				// clear all session values
//			$session->unsetData('credit_pass_answer_code');
			$session->unsetData('credit_pass_answer_details');
			$session->unsetData('credit_pass_answer_text');
			$session->unsetData('credit_pass_timestamp');
			$session->unsetData('credit_pass_quote_id');
			$session->unsetData('credit_pass_result');
			$session->unsetData('credit_pass_request_raw');
			$session->unsetData('credit_pass_response_raw');
		}

		return $this;
	}
	
	public function sendManualCheckNotification($observer)
	{
			// send merchant notification on manual checking
		if (intval(Mage::getSingleton('core/session')->getCreditPassAnswerCode()) == 2) {
			$helper = Mage::helper('creditpass');
			$order = $observer->getEvent()->getOrder();
			$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_HOLDED, $helper->__('Manual check is needed.'));
			$order->save();
			
			$mail = new Zend_Mail('UTF-8');
			$mail->setFrom(Mage::getStoreConfig('trans_email/ident_general/email'), Mage::getStoreConfig('trans_email/ident_general/name'))
	            ->addTo(Mage::getStoreConfig('trans_email/ident_sales/email'), Mage::getStoreConfig('trans_email/ident_sales/name'))
	            ->setSubject($helper->__('creditPass notification for order #%s', $order->getIncrementId()))
	            ->setBodyText($helper->__('Manual checking for order #%s required.', $order->getIncrementId()))
				->send();
		}
	}
}
?>