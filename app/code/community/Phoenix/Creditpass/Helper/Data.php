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
class Phoenix_Creditpass_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function checkPayment()
	{
		// if module is disabled
		if (!$this->moduleActive()) {
			return true;
		}

		$payment_array = $this->getPaymentArray();
		$session = Mage::getSingleton('core/session');
		$quote = Mage::getSingleton('checkout/session')->getQuote();
		$address = $quote->getBillingAddress();
		$payment = $quote->getPayment();
		$paymentInfo = $payment->getMethodInstance();
		$guest = ($quote->getCheckoutMethod() == Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST) ? true : false;

		$session->setCreditPassTimestamp(sprintf('%s', Mage::helper('core')->formatDate(null, 'medium', true)));
		$session->setCreditPassQuoteId($quote->getId());

		if (!$guest && $this->getConfigFlag('exclude_groups_enable') && $this->_excludeCustomerGroup($quote->getCustomer()->getGroupId())) {
			$session->setCreditPassAnswerCode(sprintf('%s', 0));
			$session->setCreditPassAnswerText(sprintf('%s', $this->__('Customer has not been checked because he is member of a trusted group.')));
			$session->setCreditPassResult('PASSED');
			return true;
		}

		Mage::log('creditPass: Checking for '.$payment->getMethod());

		if (array_key_exists($payment->getMethod(), $payment_array)) {
			// only check rating once
			if ($session->getCreditPassResult() == 'FAILED') {
				return false;
			}

			if ($this->getConfigFlag('live_mode')) {
				$ta_type = '27920';
				$p_mode = '1';
			} else {
				$ta_type = '27920';
				$p_mode = '8';
			}

			$helper = Mage::helper('core/string');

			$requesttxt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<REQUEST></REQUEST>";
			$requestobj = simplexml_load_string($requesttxt);
			$customerobj = $requestobj->addChild('CUSTOMER');
			$customerobj->addChild('AUTH_ID', $this->getConfig('auth_id'));
			$customerobj->addChild('AUTH_PW', $this->getConfig('auth_pw'));
			$customerobj->addChild('CUSTOMER_TA_ID', md5(time()));
			$processobj = $requestobj->addChild('PROCESS');
			$processobj->addChild('TA_TYPE', $ta_type);
			$processobj->addChild('PROCESSING_CODE', $p_mode);
			$processobj->addChild('REQUESTREASON', 'ABK');
			$queryobj = $requestobj->addChild('QUERY');
			$queryobj->addChild('FIRST_NAME', $helper->htmlEscape($helper->substr($address->getFirstname(), 0, 64)));
			$queryobj->addChild('LAST_NAME', $helper->htmlEscape($helper->substr($address->getLastname(), 0, 64)));

			$customerDob = '';
            if ($quote->getCustomerDob()) {
                $customerDob = Mage::app()->getLocale()->date($quote->getCustomerDob(), null, null, false)->toString('yyyy-MM-dd');
            }
            $queryobj->addChild('DOB', $customerDob);

			$queryobj->addChild('ADDR_STREET_FULL', $helper->htmlEscape($helper->substr(implode(' ', $address->getStreet()), 0, 50)));
			$queryobj->addChild('ADDR_ZIP', $helper->substr($address->getPostcode(), 0, 5));
			$queryobj->addChild('ADDR_CITY', $helper->htmlEscape($helper->substr($address->getCity(), 0, 32)));
			$queryobj->addChild('ADDR_COUNTRY', $address->getCountryId());
			$queryobj->addChild('CUSTOMERIP', ($quote->getRemoteIp() ? $quote->getRemoteIp() : '127.0.0.1'));
			$queryobj->addChild('CUSTOMEREMAIL', $helper->htmlEscape($helper->substr($address->getEmail(), 0, 128)));
			$queryobj->addChild('CUSTOMERTEL', $helper->htmlEscape($helper->substr($address->getTelephone(), 0, 64)));
			$queryobj->addChild('COMPANY_NAME', $helper->htmlEscape($helper->substr($address->getCompany(), 0, 64)));
			$queryobj->addChild('AMOUNT', round($quote->getGrandTotal() * 100));

				// add additional information for supported payment methods
			switch ($payment->getMethod()) {
				case 'debit':
					// fix decrypt but in extension
					
					// IBAN fix                                                                                                                                                                                           
					$ibanTemp = $paymentInfo->getAccountIban();                                                                                                                                                           
					                                                                                                                                                                                                      
					if(empty($ibanTemp)){                                                                                                                                                                                 
					    $blz = (strpos($paymentInfo->getAccountBLZ(), '=') !== false) ? $paymentInfo->decrypt($paymentInfo->decrypt($paymentInfo->getAccountBLZ())) : $paymentInfo->getAccountBLZ();                      
					    $konto = (strpos($paymentInfo->getAccountNumber(), '=') !== false) ? $paymentInfo->decrypt($paymentInfo->decrypt($paymentInfo->getAccountNumber())) : $paymentInfo->getAccountNumber();           
					                                                                                                                                                                                                      
					    $queryobj->addChild('BLZ', $blz);                                                                                                                                                                 
					    $queryobj->addChild('KONTONR', $konto);                                                                                                                                                           
					} else {                                                                                                                                                                                              
					    $iban = (strpos($paymentInfo->getAccountIban(), '=') !== false) ? $paymentInfo->decrypt($paymentInfo->decrypt($paymentInfo->getAccountIban())) : $paymentInfo->getAccountIban();                  
					                                                                                                                                                                                                      
					    $queryobj->addChild('IBAN', $iban);                                                                                                                                                               
					}                                                                                                                                                                                                     
					break;
				case 'ccsave':
					$queryobj->addChild('PAN', $payment->getCcNumber());
					break;
				case 'ipayment_elv':
					$queryobj->addChild('BLZ', $payment->getPoNumber());
					$queryobj->addChild('KONTONR', $payment->getCcNumber());
					break;
			}

				// add purchase type with appended group type
			$gm = ($guest) ? '1' : '2';
			$queryobj->addChild('PURCHASE_TYPE', $payment_array[$payment->getMethod()] . $gm);

				// process request
			$response = $this->_getHttpsPage($this->getConfig('cp_url'), $requestobj->asXML());

				// process response
			$responseobj = @simplexml_load_string($response);
			if ($responseobj === FALSE) {
				Mage::throwException($this->__('creditPass API failure. Response parsing error.'));
			}

			$answer_code = $responseobj->PROCESS->ANSWER_CODE;
			Mage::log('creditPass answer code: '.$answer_code);

			$session->setCreditPassAnswerCode(sprintf('%s', $answer_code));
			$session->setCreditPassAnswerText(sprintf('%s', $responseobj->PROCESS->ANSWER_TEXT));
			$session->setCreditPassAnswerDetails(sprintf('%s', $responseobj->PROCESS->ANSWER_DETAILS));
			$session->setCreditPassRequestRaw(sprintf('%s', $requestobj->asXML()));
			$session->setCreditPassResponseRaw(sprintf('%s', $response));
			if (intval($answer_code) === 0 || intval($answer_code) === 2 || (intval($answer_code) === -1 && $this->getConfigFlag('handle_error_code'))) {
				$session->setCreditPassResult('PASSED');
				return true;
			} else {
				$session->setCreditPassResult('FAILED');
				return false;
			}
		}

		return true;
	}

	/**
	 * Return the config value for the passed key (current store)
	 *
	 * @param string $key
	 * @return string
	 */
	public function getConfig($key)
	{
		$path = 'creditpass/settings/' . $key;
		return Mage::getStoreConfig($path);
	}

    public function getConfigFlag($field)
    {
        $path = 'creditpass/settings/' . $field;
        return Mage::getStoreConfigFlag($path);
    }

	/**
	 * Check if the extension has been disabled in the system configuration
	 *
	 * @return boolean
	 */
	public function moduleActive()
	{
		return $this->getConfigFlag('active');
	}

	/**
	 * Check if filtering was enabled
	 *
	 * @return boolean
	 */
	public function filterActive()
	{
		if (Mage::getSingleton('core/session')->getCreditPassResult() == 'FAILED') {
			return true;
		}

		return false;
	}

	/**
	 * Return the allowed payment method codes
	 *
	 *
	 * @return array()
	 */
	public function getAllowedPaymentMethods()
	{
		return explode(',', $this->getConfig('allowed_payment_methods'));
	}

	/**
	 * Return true if the method is called in the adminhtml interface
	 *
	 * @return boolean
	 */
	public function inAdmin()
	{
		return Mage::app()->getStore()->isAdmin();
	}

	/**
	 * Retrieve array of payment methods
	 *
	 * @return array
	 */
	public function getPaymentArray()
	{
		$purchaseTypes = array();
		foreach (Mage::getStoreConfig('creditpass') as $key => $arr) {
			if (strpos($key, 'purchase_type_') !== false) {
				if (!empty($arr['payment_method']) && !empty($arr['purchase_type_code'])) {
					$purchaseTypes[$arr['payment_method']] = $arr['purchase_type_code'];
				}
			}
		}
		return $purchaseTypes;
	}

	/**
	 * Reading a page via HTTPS and returning its content.
	 */
	protected function _getHttpsPage($host, $data)
	{
		$error = '';
		try {
			$client = new Varien_Http_Client();
			$client->setUri($host)
				->setConfig(array('timeout'=>30,))
				->setParameterPost('reqxml', $data)
				->setMethod(Zend_Http_Client::POST);

			Mage::log('creditPass request: '.$data);
			$response = $client->request();
			$responseBody = $response->getBody();

			Mage::log('creditPass response: '.$response->getStatus().$responseBody);

			if ($response->getStatus() != '200')
				Mage::throwException($this->__('creditPass API failure. Return status %s.', $response->getStatus()));

			if (empty($responseBody))
				Mage::throwException($this->__('creditPass API failure. Response can\'t be processed.'));

		} catch (Exception $e) {
			$this->_returnErrorXml($e->getMessage());
		}

		return $responseBody;
	}

	/**
	 * Return the if group id is in the list of exclude groups
	 *
	 * @return bool
	 */
	private function _excludeCustomerGroup($group)
	{
		$groups = explode(',', $this->getConfig('exclude_groups'));
		return in_array($group, $groups, true);
	}

	/**
	 * Return error -1 XML
	 *
	 * @return string
	 */
	private function _returnErrorXml($error)
	{
		return '<?xml version="1.0" encoding="UTF-8"?>
<RESPONSE>
 <PROCESS>
  <ANSWER_CODE>-1</ANSWER_CODE>
  <ANSWER_TEXT>' . $error . '</ANSWER_TEXT>
 </PROCESS>
</RESPONSE>';
	}
}
?>
