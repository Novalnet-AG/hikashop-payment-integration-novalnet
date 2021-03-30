<?php
/**
* This script contains helper functions
*
* @author Novalnet AG
* @copyright Copyright (c) Novalnet
* @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz
* @link https://www.novalnet.de
*
* This free contribution made by request.
*
* If you have found this script useful a small
* recommendation as well as a comment on merchant
*
* Script : novalnet_utilities.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\Registry\Registry;

/**
 * Novalnet Utility class
 *
 * @package Hikashop_Payment_Plugin
 */
class NovalnetUtilities extends hikashopPaymentPlugin
{
    public static $redirectPayments =  array('novalnet_banktransfer', 'novalnet_paypal', 'novalnet_ideal', 'novalnet_eps', 'novalnet_giropay', 'novalnet_przelewy24');

    public static $dataParams = array('test_mode', 'auth_code', 'amount', 'product', 'tariff');

    /**
     * Retrieves the Novalnet merchant details
     *
     * @param  string $type Based the type get the configuration
     * @param  string $userId get the user id
     * @return array
     */
    public static function getMerchantConfig($type = '', $userId = '')
    {
        $pluginClass = hikashop_get('class.plugins');
        $configParams = '';
        // Loads payment methods to get the payment configuration params.
        $paymentMethods = $pluginClass->getMethods('payment');
        foreach ($paymentMethods as $key => $pluginParams)
        {
            if ($pluginParams->payment_type == 'novalnet_payments')
            {
                $configParams = $pluginParams->payment_params;
                $configParams->payment_published = $pluginParams->payment_published;
            }
        }
        if ($type == 'callback' && $userId)
        {
            $userId = $userId;
        }
        else
        {
            $userId = hikashop_loadUser(true);
            $userId = !empty($userId->user_id) ? $userId->user_id : '';
        }
        if ($configParams)
        {
            $configParams->tariffType = $configParams->tariffType;
            $configParams->tariffId = $configParams->tariffId;
            return self::formAffiliate($userId, $configParams);
        }
    }

    /**
     * Retrieves the payment order status
     *
     * @param  string $selectData based on the condition select data
     * @param  boolean $orderId get the order id
     * @param  string $paymentName get the payment name
     * @return array
     */
    public static function getOrderPaymentStatus($selectData, $orderId = false, $paymentName = '')
    {
        $results = self::selectQuery(array(
        'table_name' => '#__hikashop_payment',
        'column_name' => array('payment_params', 'payment_type'),
        'condition' => array($selectData),
        'order' => ''));
        $result = unserialize($results->payment_params);
        $orderStatus = array("order_status" => $result->transactionEndStatus);

        // Get paypal and przelewy order pending status.
        if (in_array($paymentName, array(
        'novalnet_paypal',
        'novalnet_przelewy24',
        'novalnet_invoice',
        'novalnet_prepayment',
        'novalnet_cashpayment')))
        {
            $orderStatus["transaction_before_status"] = $result->transactionBeforeStatus;
        }
        if (in_array($results->payment_type, array(
        'novalnet_invoice',
        'novalnet_prepayment',
        'novalnet_cashpayment',
        'novalnet_paypal',
        'novalnet_cc',
        'novalnet_banktransfer',
        'novalnet_sepa',
        'novalnet_przelewy24',
        'novalnet_ideal')))
        {
            $orderStatus["before_status"] = self::getOrderStatus($orderId);
        }
        return $orderStatus;
    }

    /**
     * This event is triggerd to display payment notifications
     *
     * @param  object $method get the payment method params
     * @return array
     */
    public static function getPaymentNotifications($method)
    {
        if (isset($method->payment_params->shoppingType) && $method->payment_params->shoppingType == 'ONECLICK_SHOPPING' && $method->payment_type == 'novalnet_paypal')
        {
            $method->payment_description = JText::_('HKPAYMENT_NOVALNET_PAYPAL_ONECLICK_DESC');
            $method->payment_description.= JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC');
        }
        if (isset($method->payment_params->shoppingType) && $method->payment_params->shoppingType == 'ZERO_AMOUNT_BOOKING' && in_array($method->payment_type, array(
        'novalnet_cc', 'novalnet_paypal', 'novalnet_sepa'))) {
            $method->payment_description .= '<span id ="novalnet_description_zero" >' . JText::_('HKPAYMENT_NOVALNET_ZEROAMOUNT_DESC') . '<br>' . '</span>';
        }
        if ($method->payment_type =='novalnet_cc' && !empty($method->payment_params->cc3d) || !empty($method->payment_params->cc3d_force)) {
            $method->payment_description = JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC');
        }
        $method->payment_description = !empty($method->payment_description) ? $method->payment_description : JText::_('HKPAYMENT_NOVALNET_PAYMENT_' . $method->payment_type . '_DESC') . '<br />';
        $method->payment_description.= ($method->payment_params->testmode == 1) ? JText::_('HKPAYMENT_NOVALNET_TEST_ORDER_DESC') : '';
        $method->payment_description.= (!empty($method->payment_params->notificationMsg)) ? trim($method->payment_params->notificationMsg) : '';
        return $method->payment_description;
    }

    /**
     * To get the stored patterns from database.
     *
     * @param  int $customerId get the customer id
     * @param  string  $paymentName get the payment name
     * @param  boolean $tid transaction id
     * @return mixed
     */
    public static function getMaskedDetails($customerId, $paymentId, $tid = false)
    {
        if ($customerId)
        {
            $getMaskedDetails = self::selectQuery(array(
            'table_name' => array('#__novalnet_transaction_detail'),
            'column_name' => array('payment_specific_data', 'tid'),
            'condition' => array(
            "customer_id='" . $customerId . "'",
             "payment_id='" . $paymentId . "'",
            "payment_request='1'"),
            'order' => 'hika_order_id DESC LIMIT 1'));
            if (!empty($getMaskedDetails))
                $maskedDetails = json_decode(($getMaskedDetails->payment_specific_data), true);
            if (!empty($maskedDetails))
            {
                if ($tid)
                {
                    return isset($maskedDetails['tid']) ? $maskedDetails['tid'] : $getMaskedDetails->tid;
                }
                return ($maskedDetails);
            }
            return false;
        }
        return false;
    }

    /**
     * This event is triggerd to display the noscript error message
     *
     * @param  string $form get the form type
     * @return array
     */
    public static function noScriptMessage($form)
    {
        return '<noscript><span>
            <style>
                #' . $form . '
                {
                    display:none !important;
                }
            </style>
        <br>' . JText::_('HKPAYMENT_NOVALNET_NOSCRIPT_ERROR') . '</span></noscript>';
    }

    /**
     * Converts the order amount into cents
     *
     * @param  string $amount get the order amount
     * @param  string $callback geth the callback order amount
     * @return string
     */
    public static function doFormatAmount($amount, $callback = false)
    {
       return ($callback) ? str_replace('.', ',', number_format($amount, 2)) : str_replace(',', '.', number_format($amount, 2)) * 100;
    }

    /**
     * Update the order status in history table.
     *
     * @param  string $orderStatus get the order status
     * @param  string $historyData get the order history details
     * @param  object $order order object
     * @return void
     */
    public static function updateOrderStatus($orderStatus, $historyData, $order)
    {
        $orderDetails = json_decode(($order->additional_data), true);
        $userId = !empty($orderDetails['customer_id']) ? $orderDetails['customer_id'] : $order->customer_id ;
        self::insertQuery('#__hikashop_history', array(
        'history_order_id',
        'history_new_status',
        'history_created',
        'history_payment_id',
        'history_payment_method',
        'history_data',
        'history_user_id',
        'history_type',
        'history_ip',
        'history_notified'), array(
        $order->hika_order_id, "'" . $orderStatus . "'",
        "'" . time() . "'", "'" . $order->payment_id . "'",
        "'" . $orderDetails['payment_method'] . "'",
        "'" . $historyData . "'",
        "'" . $userId . "'",
        "'" . 'payment' . "'",
        "'" . hikashop_getIP() . "'",
        1));
        self::updateQuery(array('#__hikashop_order'),
        array('order_status="' . $orderStatus . '"'),
        array('order_id="' . $order->hika_order_id . '"'));
    }

    /**
     * Retrieves the cancelled order message.
     *
     * @param  array $response get the payment response
     * @return string
     */
    public static function responseMsg($response)
    {
        return (isset($response['status_desc']) ? $response['status_desc'] : (isset($response['status_text']) ? $response['status_text'] : (isset($response['status_message']) ? $response['status_message'] : JText::_('HKPAYMENT_NOVALNET_ORDER_DESC'))));
    }

    /**
     * Forms the request payment parameters for all payments
     *
     * @param  object $paymentMethod get the payment name
     * @param  object $order get the order object
     * @return array
     */
    public static function doFormPaymentParameters($paymentMethod, $order)
    {
        // Get the formated amount.
        $amount = self::doFormatAmount($order->order_full_price);

        // Get shop languange.
        $shopLanguage = self::getLanguageTag();

        // Get the shop version.
        $version = new JVersion;
        $billingAddress = $order->cart->billing_address;
        $email = !empty($paymentMethod->user->email) ? $paymentMethod->user->email : $paymentMethod->user->user_email;

        // Check the basic user details.
        if ((empty($billingAddress->address_firstname) && empty($billingAddress->address_lastname)) || empty($email))
        {
            self::showMessage(JText::_('HKPAYMENT_NOVALNET_CUSTOMER_ERR'));
        }

        // Loads Hikashop configuration to fetch system version.
        $hikashopConfig = hikashop_config();
        $requestParameters = array(
			'vendor' 			=> $GLOBALS['configDetails']->vendor,
			'product' 			=> $GLOBALS['configDetails']->productId,
			'tariff' 			=> $GLOBALS['configDetails']->tariffId,
			'auth_code' 		=> $GLOBALS['configDetails']->authCode,
			'amount' 			=> $amount,
			'test_mode' 		=> ($paymentMethod->payment_params->testmode != '') ? $paymentMethod->payment_params->testmode : 0,
			'currency' 			=> $paymentMethod->currency->currency_code,
			'gender' 			=> 'u',
			'first_name' 		=> html_entity_decode($billingAddress->address_firstname),
			'last_name' 		=> html_entity_decode($billingAddress->address_lastname),
			'email' 			=> $email,
			'street' 			=> html_entity_decode($billingAddress->address_street),
			'search_in_street' 	=> 1,
			'city' 				=> html_entity_decode($billingAddress->address_city),
			'zip' 				=> $billingAddress->address_post_code,
			'country' 			=> $billingAddress->address_country->zone_code_2,
			'country_code' 		=> $billingAddress->address_country->zone_code_2,
			'remote_ip' 		=> $_SERVER['REMOTE_ADDR'],
			'tel' 				=> $billingAddress->address_telephone,
			'lang' 				=> $shopLanguage,
			'customer_no' 		=> $billingAddress->address_user_id > 0 ? $billingAddress->address_user_id : 'guest',
			'system_name' 		=> 'joomla-hikashop',
			'system_version' 	=> $version->getShortVersion() . ' - ' . $hikashopConfig->get('version') . ' - NN 11.2.1',
			'system_url' 		=> JURI::root(),
			'system_ip' 		=> hikashop_getIP(),
			'order_no' 			=> isset($order->cart->order_number) ? $order->cart->order_number : (isset($order->order_number) ? ($order->order_number) : '')
        );

        // Adds company paramter only if it's not empty.
        if (!empty($billingAddress->address_company) || !empty($shippingAddress->address_company))
        {
            $requestParameters['company'] = ($billingAddress->address_company) ? $billingAddress->address_company : $shippingAddress->address_company;
        }
        if (!empty($billingAddress->address_vat) || !empty($shippingAddress->address_vat))
        {
            $requestParameters['vat_id'] = ($billingAddress->address_vat) ? $billingAddress->address_vat : $shippingAddress->address_vat;
        }
        if (!empty($order->cart->order_number) || !empty($order->order_number))
        {
            $requestParameters['input1'] = 'order_id';
            $requestParameters['inputval1'] = isset($order->cart->order_id) ? $order->cart->order_id : $order->order_id;
            $requestParameters['order_id'] = isset($order->cart->order_id) ? $order->cart->order_id : $order->order_id;
        }

        // Adds on-hold parameter to the payment request based on manual check limit.
        if (in_array($paymentMethod->name, array('novalnet_invoice', 'novalnet_cc', 'novalnet_sepa', 'novalnet_paypal')) && $amount >= $paymentMethod->payment_params->onHold && $paymentMethod->payment_params->onholdAction == "AUTHORIZE")
        {
            $requestParameters['on_hold'] = 1;
        }

        // Adds notification url to the payment request.
        if (!empty($GLOBALS['configDetails']->notifyUrl))
        {
            $requestParameters['notify_url'] = htmlspecialchars($GLOBALS['configDetails']->notifyUrl);
        }
        // Adds Payment parameters if Zero amount booking is enabled.
        if ((in_array($paymentMethod->name, array('novalnet_cc', 'novalnet_paypal')) || ($paymentMethod->name == 'novalnet_sepa' && ($paymentMethod->payment_params->guaranteeEnable == 0))) && (isset($paymentMethod->payment_params->shoppingType) && $paymentMethod->payment_params->shoppingType == 'ZERO_AMOUNT_BOOKING'))
        {
            $amount = 0;
            $requestParameters['amount'] = 0;
            $requestParameters['create_payment_ref'] = 1;
            if (isset($requestParameters['on_hold']))
              unset($requestParameters['on_hold']);
            self::handleSession(serialize($requestParameters), 'payment_request' . $order->order_payment_id, 'set');
        }
        self::handleSession($requestParameters, 'oneclick_params_' . $order->order_payment_id, 'set');
        // Adds redirect payment parameters.
        if (in_array($paymentMethod->name, self::$redirectPayments) || ($paymentMethod->name == 'novalnet_cc' && (!empty($paymentMethod->payment_params->cc3d) || !empty($paymentMethod->payment_params->cc3d_force))))
        {
            $requestParameters['uniqid'] = self::get_uniqueid();
            $requestParameters = self::encode($requestParameters, $GLOBALS['configDetails']->keyPassword);
            $requestParameters['hash'] = self::hash($requestParameters, $GLOBALS['configDetails']->keyPassword);
            $requestParameters['return_url'] = htmlspecialchars(HIKASHOP_LIVE . 'index.php/' . self::redirectUrl(true) . '/checkout?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=' . $paymentMethod->name . '&tmpl=component&lang=' . $paymentMethod->locale . $paymentMethod->url_itemid);
            $requestParameters['return_method'] = 'POST';
            $requestParameters['error_return_url'] = htmlspecialchars(HIKASHOP_LIVE . 'index.php/' . self::redirectUrl(true) . '/checkout?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=' . $paymentMethod->name . '&tmpl=component&lang=' . $paymentMethod->locale . $paymentMethod->url_itemid);
            $requestParameters['error_return_method'] = 'POST';
            $requestParameters['user_variable_0'] = HIKASHOP_LIVE;
            $requestParameters['implementation'] = 'ENC';
        }
        return $requestParameters;
    }
    
    /**
	 * Get shipping details from order object for paypal payment to process.
	 *
	 * @param object $order order object.
	 * 	 
	 * @return $shipping_address
	 */
    public static function getShippingDetails($order)
    {
		$billingAddress = $order->cart->billing_address;
		$shippingAddress = $order->cart->shipping_address;
		
		$shipping_address= array(
			's_first_name'     => $shippingAddress->address_firstname,
			's_last_name'      => $shippingAddress->address_lastname,
			's_street'         => $shippingAddress->address_street,
			's_house_no'       => $shippingAddress->address_street2,
			's_city'           => $shippingAddress->address_city,
			's_zip'            => $shippingAddress->address_post_code,
			's_country_code'   => $shippingAddress->address_country->zone_code_2,
			's_company'        => $shippingAddress->address_company,
		);

		$billing_address = array(
			'country'   => $billingAddress->address_country->zone_code_2,
			'post_code' => $billingAddress->address_post_code,
			'city'      => $billingAddress->address_city,
			'address'   => $billingAddress->address_street,
			'address2'  => $billingAddress->address_street2,
		);
		$shipping = array(
			'country'   => $shippingAddress->address_country->zone_code_2,
			'post_code' => $shippingAddress->address_post_code,
			'city'      => $shippingAddress->address_city,
			'address'   => $shippingAddress->address_street,
			'address2'  => $shippingAddress->address_street2,
		);
		if($billing_address == $shipping){
			$shipping_address['ship_add_sab']=1;
		}
		return $shipping_address;
	}

    /**
     * Check the user affiliate or not using customer no.
     *
     * @param  integer $customerId get the customer id
     * @param  array $configParams get the configuration params
     * @return void
     */
    public static function formAffiliate($customerId, $configParams)
    {
        // Get affiliate id.
        $affiliateId = self::handleSession('nn_aff_id', '', 'get');

        // Get the details for affiliate user detail table.
        if (!empty($customerId) && (empty($affiliateId)))
        {
            $affUserDetails = self::selectQuery( array('table_name' => array('#__novalnet_aff_user_detail'),
            'column_name' => array('aff_id'),
            'condition' => array("customer_id='" . $customerId . "'"),
            'order' => 'customer_id DESC LIMIT 1'));
            if (!empty($affUserDetails->aff_id))
            {
                $affiliateId = $affUserDetails->aff_id;
                self::handleSession($affUserDetails->aff_id, 'nn_aff_id', 'set');
            }
        } // Get the Affiliate vendor details for aff_account_detail table.
        if (isset($affiliateId) && is_numeric($affiliateId))
        {
            $affDetails = self::selectQuery(array(
            'table_name' => array('#__novalnet_aff_account_detail'),
            'column_name' => array('aff_authcode', 'aff_accesskey'),
            'condition' => array("aff_id='" . $affiliateId . "'",
            "vendor_id='" . $configParams->vendor . "'"),
            'order' => 'aff_id DESC LIMIT 1'));
            if (!empty($affDetails))
            {
                $configParams->vendor = $affiliateId;
                $configParams->authCode = $affDetails->aff_authcode;
                $configParams->keyPassword = $affDetails->aff_accesskey;
            }
            return $configParams;
        }
        return $configParams;
    }

    /**
     * Form Invoice/Prepayment reference comments
     *
     * @param  array $gatewayResponse get the payment response
     * @param  object $order get the order details
     *
     * @return string
     */
    public static function formInvoicePrepaymentReferenceComments($gatewayResponse, $order)
    {
        // Get currency to load shop default currency object.
        $configDetails = self::getMerchantConfig();
        $currencyHelper = hikashop_get('class.currency');
        $transactionComments = '<div>' . JText::_('HKPAYMENT_NOVALNET_COMMENTS_MSG') . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_DUE_DATE') . ': ' . hikashop_getDate($gatewayResponse['due_date'], '%d %B %Y ') . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_HOLDER') . $gatewayResponse['invoice_account_holder'] . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_IBAN') . ': ' . $gatewayResponse['invoice_iban'] . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_BIC') . ': ' . $gatewayResponse['invoice_bic'] . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_BANK') . ': ' . $gatewayResponse['invoice_bankname'] . " " . $gatewayResponse['invoice_bankplace'] . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_AMOUNT') . ': ' . $currencyHelper->format($order->order_full_price) . '</br>';
        $transactionComments.= JText::_('HKPAYMENT_NOVALNET_PAYMENT_REFERENCE_MSG_MULTIPLE') . '</br>';
        $transactionComments.= JText::_('NOVALNET_PAYMENT_REFERENCE') . '1: ' . 'BNR-' . $configDetails->productId . '-' . $order->order_number . '<br>';
        $transactionComments.= JText::_('NOVALNET_PAYMENT_REFERENCE') . '2: ' . 'TID ' . $gatewayResponse['tid'] . '<br>';
        $transactionComments = html_entity_decode($transactionComments, ENT_QUOTES, 'UTF-8');
        return $transactionComments;
    }

    /**
     * Process data after paygate response for direct payment methods
     *
     * @param  object $order get the order details
     * @param  array $response get the payment response
     * @param  object $paymentParams get the payment params
     * @param  object $data get the current object
     * @param  boolean $fraudModule get the fraudenable param
     * @return string
     */
    public static function handleDirectPaymentCompletion($order, $response, $paymentParams, $data, $fraudModule = false)
    {
        $gatewayResponse = !empty($fraudModule) ? $fraudModule : $response;
        $orderHistory = new stdClass;
        $config = NovalnetUtilities::getMerchantConfig();
        $transactionComments = '';

        // Handles success scenario.
        if ($gatewayResponse['status'] == 100)
        {
            // Prepare transaction comments.
            $transactionComments .= self::transactionComments($gatewayResponse['tid'], $gatewayResponse['test_mode']);
            
            $transactionComments .= (in_array($gatewayResponse['payment_id'], array('41','40'))) ? '<br>'. JText::_('HKPAYMENT_NOVALNET_GUARANTEE_TEXT') : '';
            if($gatewayResponse['tid_status'] == '75') {
				$transactionComments.= ($gatewayResponse['payment_id'] == '41') ? ('<br><br>' . JText::_('HKPAYMENT_NOVALNET_GUARANTEE_INVOICE_TEXT')) : (($gatewayResponse['payment_id'] == '40') ?  '<br><br>'. JText::_('HKPAYMENT_NOVALNET_GUARANTEE_SEPA_TEXT') : '');
			}

            // Form Invoice/Prepayment order comments.
            if (in_array($paymentParams->payment_type ,array('novalnet_invoice','novalnet_prepayment')))
                $transactionComments .= ($gatewayResponse['tid_status'] != '75') ? self::formInvoicePrepaymentReferenceComments($gatewayResponse, $order) : ' ';
                $transactionComments.= ($paymentParams->payment_type == 'novalnet_cashpayment') ? self::cashpaymentOrderComments($gatewayResponse) : '';

            $callbackEnabled = !empty($paymentParams->payment_params->pinbyCallback) ? true : false;
            // If callback enabled send postback call to update order number in server.
            if (!empty($paymentParams->payment_params->pinbyCallback) && empty($paymentParams->payment_params->guaranteeEnable))
                self::doPostbackcall($gatewayResponse, $order);

            $orderStatus = in_array($paymentParams->payment_type, array('novalnet_sepa', 'novalnet_cc')) ? $data->payment_params->transactionEndStatus : $data->payment_params->transactionBeforeStatus;

            if ($paymentParams->payment_type == 'novalnet_paypal' && in_array($gatewayResponse['tid_status'], array(90, 85)))
                $orderStatus = $data->payment_params->transactionBeforeStatus;

            if (in_array($paymentParams->payment_type, array('novalnet_invoice', 'novalnet_cc', 'novalnet_sepa', 'novalnet_paypal')) && in_array($gatewayResponse['tid_status'], array('91', '99', '98', '85'))) {
                $orderStatus = $config->transactionConfirmStatus;
			}
            $orderStatus = ($gatewayResponse['tid_status'] == 75) ? $data->payment_params->guaranteeStatus : $orderStatus;
            
            if ( $gatewayResponse['tid_status'] == 100 && $gatewayResponse['payment_id'] == '41' )
				$orderStatus = $data->payment_params->transactionEndStatus;

            // To display the comments in order confirmation mail.
            $paymentClass = hikashop_get('class.payment');
            $payment = $paymentClass->get($order->order_payment_id);
            $orderComments = $payment->payment_name . '</br>' . $transactionComments;
            $payment->payment_name.= '</br>' . $transactionComments;

            // Order history standard object.
            $orderHistory->notified = 1;

            // Sets the Novalnet comments into the shop's session.
            self::handleSession($orderComments, 'transaction_comments', 'set');

            // Store novalnet comments in order history table.
            $orderHistory->data = $transactionComments;
            $dueDate = isset($gatewayResponse['cp_due_date']) ? date('Y-m-d', strtotime($gatewayResponse['cp_due_date'])) : (isset($gatewayResponse['due_date']) ? date('Y-m-d', strtotime($gatewayResponse['due_date'])) : '');

            // Insert gateway response in transaction detail table.
            self::getTransactionSuccess($gatewayResponse, $order, $paymentParams);

            // Insert order details in novalnet callback table.
            self::insertCallbackdata($order->order_id, 0, $gatewayResponse['tid']);

            // Remove cart value.
            $data->removeCart = true;

            // Clears Novalnet session.
            self::nnSessionClear($paymentParams->payment_type, $order->order_payment_id, $callbackEnabled);

            // Update the order details.
            $data->modifyOrder($order->order_id, $orderStatus, $orderHistory, false, false);

            // Loads the Joomla application.
            $app = JFactory::getApplication();
            if ($paymentParams->payment_type == 'novalnet_cashpayment')
            {
                $url = ($gatewayResponse['test_mode'] == '1') ? "https://cdn.barzahlen.de/js/v2/checkout-sandbox.js" : "https://cdn.barzahlen.de/js/v2/checkout.js";
                $document = JFactory::getDocument();
                $document->addScript($url . '" class="bz-checkout" data-token="' . $gatewayResponse['cp_checkout_token'] . '"');
                $orderComments.= '<a href="javascript:bzCheckout.display();">' . JText::_('HKPAYMENT_PAYNOW_BARZHALEN') . '</a>';
                $document->addStyleDeclaration('#bz-checkout-modal { position: fixed !important; }');
            }
            $app->enqueueMessage($orderComments);
            return true;
        } else {

            // Handle the failure scenario.
            self::updateCancelledOrder($order, $gatewayResponse, $orderHistory, true, $data);
        }
    }

    /**
     * Completes the orders placed in Novalnet redirect payments
     *
     * @param  object $data get the current object
     * @return void
     */
    public function handleRedirectPaymentCompletion($data)
    {
        $configDetails = self::getMerchantConfig();

        // Loads the Joomla post parameters.
        $responseParams = JRequest::get('post');

        // Get the order id.
        $orderId = isset($responseParams['order_id']) ? $responseParams['order_id'] : $responseParams['inputval3'];

        // Loads the Joomla application.
        $app = JFactory::getApplication();

        // Gets order class.
        $orderClass = hikashop_get('class.order');

        // Loads order object using the order id.
        $order = $orderClass->loadFullOrder($orderId, false, false);

        // Gets plugin class.
        $pluginClass = hikashop_get('class.plugins');

        // Loads payment methods to get the payment configuration params.
        $paymentMethods = $pluginClass->getMethods('payment');
        $paymentMethod = $paymentMethods[$order->order_payment_id];
        $data->payment_params = $paymentMethod->payment_params;

        // Order history standard object.
        $orderHistory = new stdClass;

        // Condition to check the payment success status.
        if ($responseParams['status'] == 100 || ($order->order_payment_method == 'novalnet_paypal' && $responseParams['status'] == 90))
        {
            if (!empty($responseParams['hash2']) && self::checkHash($responseParams, $GLOBALS['configDetails']->keyPassword))
            {
                $orderHistory->data.= "<br>" . JText::_('HKPAYMENT_NOVALNET_CHECK_HASH_FAILED_ERROR');
                $data->modifyOrder($orderId, 'cancelled', $orderHistory, false, false);
                self::showMessage($responseParams['status_text'] . ' - ' . JText::_('HKPAYMENT_NOVALNET_CHECK_HASH_FAILED_ERROR'));
            }
            $responseParams =  self::decode($responseParams, $GLOBALS['configDetails']->keyPassword);
            $amount = ($responseParams['status'] == 100 && $responseParams['tid_status'] != 86) ? self::doFormatAmount($order->order_full_price) : 0;
            // Insert callback data.
            self::insertCallbackdata($order->order_id, $amount, $responseParams['tid']);

            // Form the transaction comments.
            $transactionComments = self::transactionComments($responseParams['tid'], $responseParams['test_mode']);

            // To display the comments in order confirmation mail.
            $paymentClass = hikashop_get('class.payment');
            $payment = $paymentClass->get($order->order_payment_id);
            $payment->payment_name.= '</br>' . $transactionComments;
            $orderHistory->notified = 1;
            $orderHistory->payment_id = $order->order_payment_id;

            // Sets the Novalnet transaction comments in the session.
            self::handleSession($transactionComments, 'transaction_comments', 'set');

            // Store novalnet comments in order history table.
            $orderHistory->data = $transactionComments;

            // Decodes the payment response to fetch the amount value.
            $orderHistory->amount = $responseParams['amount'];

            // Sets the order status with respect to the resultant status.
            $orderStatus = (($order->order_payment_method == 'novalnet_paypal' && $responseParams['tid_status'] == 90) || ($order->order_payment_method == 'novalnet_przelewy24' && $responseParams['tid_status'] == 86)) ? $data->payment_params->transactionBeforeStatus : $data->payment_params->transactionEndStatus;
            if ($order->order_payment_method == 'novalnet_paypal' && $responseParams['tid_status'] == '85') {
                $orderStatus = $configDetails->transactionConfirmStatus;
			}
            // Insert the gateway response in transaction detail table.
            self::getTransactionSuccess($responseParams, $order, $data);
            $data->modifyOrder($orderId, $orderStatus, $orderHistory, false, false);

            // Clears the Novalnet session.
            self::nnSessionClear($order->order_payment_method, $order->order_payment_id, false);

            if ($paymentMethod)
               $cartClass = hikashop_get('class.cart');

            // Clear cart session value.
            $cartClass->cleanCartFromSession();
            $returnUrl = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=' . $orderId;

            // Appends the transaction comments.
            $app->enqueueMessage($paymentMethod->payment_name . '</br>' . $transactionComments);

            // Redirects to the success page which has been set.
            $app->redirect($returnUrl);
            return true;
        }
        else
        {
            // Handle the failure scenario.
            self::updateCancelledOrder($order, $responseParams, $orderHistory, true, $data);
        }
    }

    /**
     * To update the order details in transaction detail table.
     *
     * @param  array $gatewayResponse get the payment response
     * @param  object $order get the order
     * @param  object $paymentParams get the payment object
     *
     * @return void
     */
    public static function getTransactionSuccess($gatewayResponse, $order, $paymentParams)
    {
        $currency = (!empty($paymentParams->payment_params->pinbyCallback) && (!NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get'))) ? self::handleSession('currency_' . $order->order_payment_id, '', 'get') : $gatewayResponse['currency'];
        $gatewayStatus = (!empty($paymentParams->payment_params->pinbyCallback) && (!self::handleSession('guarantee_' . $order->order_payment_id, '', 'get'))) ? self::handleSession('callback_tid_status_' . $order->order_payment_id, '', 'get') : $gatewayResponse['tid_status'];
        if (in_array($order->order_payment_method, array('novalnet_invoice', 'novalnet_prepayment', 'novalnet_cashpayment')))
        {
            $payments = self::updateNovalnetTransactionTable($gatewayResponse, $order);
        }
        // Get the payment request.
        $paymentRequest = self::handleSession('payment_request' . $order->order_payment_id, '', 'get');
        $zeroAmountBookingEnabled = isset($paymentRequest) ? 1 : 0;
        $merchantDetails = array(
        'vendor' => $GLOBALS['configDetails']->vendor,
        'product' => $GLOBALS['configDetails']->productId,
        'tariff' => $GLOBALS['configDetails']->tariffId,
        'auth_code' => $GLOBALS['configDetails']->authCode,
        'test_mode' => !in_array($gatewayResponse['test_mode'], array(0, 1)) ? self::decode($gatewayResponse['test_mode'], $GLOBALS['configDetails']->keyPassword, $gatewayResponse['uniqid']) : (!empty($paymentParams->payment_params->testmode) || !empty($gatewayResponse['test_mode']) ? 1 : 0),
        'payment_key' => self::handleSession('payment_key' . $order->order_payment_id, '', 'get'),
        'payment_method' => $order->order_payment_method,
        'order_no' => $order->order_number);

        // Store the masking details for CC SEPA and Paypal payment.
        if (in_array($order->order_payment_method, array('novalnet_sepa','novalnet_cc','novalnet_paypal')) && $paymentParams->payment_params->shoppingType == 'ONECLICK_SHOPPING' )
        {
            $createPaymentRef = self::handleSession('create_payment_ref' . $order->order_payment_id, '', 'get');
            $sepaMaskingParams = self::handleSession('sepa_masking_params' . $order->order_payment_id, '', 'get');

            if ($createPaymentRef)
            {
                $maskedDetails = (($order->order_payment_method == 'novalnet_sepa') ? (($sepaMaskingParams) ? $sepaMaskingParams : (array(
                    'iban' => $gatewayResponse['iban'],
                    'bic' => $gatewayResponse['bic'],
                    'bankaccount_holder' => $gatewayResponse['bankaccount_holder']
                    )
                ) ) : ($order->order_payment_method == 'novalnet_cc' ? (array(
                    'cc_holder' => $gatewayResponse['cc_holder'],
                    'cc_type'  => $gatewayResponse['cc_card_type'],
                    'cc_no' => $gatewayResponse['cc_no'],
                    'cc_exp_month' => $gatewayResponse['cc_exp_month'],
                    'cc_exp_year' => $gatewayResponse['cc_exp_year']
                    )
                ) : (array(
                    'paypal_transaction_id' => $gatewayResponse['paypal_transaction_id'],
                    'tid' => $gatewayResponse['tid'],
                    'tid_status' =>$gatewayResponse['tid_status'],
                    )
                )));
                $paymentRequest = 1;
            }
        }

        // Insert datas in transaction detail table.
        $configDetails = self::getMerchantConfig();
       self::insertQuery('#__novalnet_transaction_detail',
        array(
        'tid',
        'gateway_status',
        'hika_order_id',
        'payment_id',
        'order_amount',
        'amount',
        'customer_id',
        'additional_data',
        'payment_specific_data',
        'payment_request'),
        array(
        "'" . $gatewayResponse['tid'] . "'",
        "'" . $gatewayStatus . "'",
        "'" . $order->order_id . "'",
        "'" . $order->order_payment_id . "'",
        "'" . $order->order_full_price . "'",
        "'" . (($zeroAmountBookingEnabled) ? 0 : sprintf('%0.2f', $order->order_full_price)) . "'",
        "'" . $order->customer->user_id . "'",
        "'" . json_encode($merchantDetails) . "'",
        "'" . (!empty($payments) ? json_encode($payments) : (!empty($maskedDetails) ?  json_encode($maskedDetails) : '')) . "'",
        "'" . $paymentRequest . "'"));

        // Check the affiliate id.
        if (!empty(self::handleSession('nn_aff_id', '', 'get')) && is_numeric(self::handleSession('nn_aff_id', '', 'get')))
        {
            $orderNo = ($order->cart->order_number) ? $order->cart->order_number : $order->order_number;
            // Insert affiliate details.
            self::insertAffiliateDetails($orderNo, $order->customer->user_id);
        }
    }

    /**
     * Insert affiliate information into the database
     *
     * @param  string $orderNo get the order number
     * @param  integer $customerNo get the customer number
     * @return void
     */
    public static function insertAffiliateDetails($orderNo, $customerNo)
    {
        self::insertQuery('
        #__novalnet_aff_user_detail',
        array(
        'aff_id',
        'customer_id',
        'aff_order_no'),
        array(
        "'" . $GLOBALS['configDetails']->vendor . "'",
        "'" . $customerNo . "'",
        "'" . $orderNo . "'"));
    }

    /**
     * Clears the Novalnet session variables
     *
     * @param  string $paymentName get the current  payment name
     * @param  int $paymentId get the currente payment id
     * @param  boolean $fraudmoduleEnabled get the fraud module enabled details
     * @return void
     */
    public static function nnSessionClear($paymentName, $paymentId, $fraudmoduleEnabled)
    {
        // If callback enabled to clear the invoice bank account details.
        if ($fraudmoduleEnabled)
        {
            foreach (array('tid_', 'amount_', 'non_guarantee_with_fraudcheck_') as $params)
            {
                self::handleSession($params . $paymentId, '', 'clear');
            }
        }

		foreach(array('oneclick_sepa','oneclick_paypal','oneclick_cc','paypal_masked_value','sepa_masked_value','cc_masked_value','check_sepa','sepa_oneclick', 'nn_cc_uniqueid', 'pan_hash', 'SEPAvariable') as $params)
		{
			self::handleSession($params , '', 'clear');
		}
        foreach (array('non_guarantee_with_fraudcheck_', 'fraud_module_response_', 'create_payment_ref', 'payment_ref_', 'payment_request', 'one_click_', 'birthdate_', 'telephone_field_', 'nn_cc_hash', 'paypal_normal', 'telephone_field_', 'pin_','guarantee_error_message_') as $params)
        {
            self::handleSession($params . $paymentId, '', 'clear');
        }
    }

    /**
     * Get country list using hikashop_zone table
     *
     * @return object
     */
    public static function getCountryList()
    {
        return self::selectQuery(array(
        'table_name' => '#__hikashop_zone',
        'column_name' => array('zone_name', 'zone_name_english', 'zone_code_2')));
    }

    /**
     * Calculates the invalid pin count and show the form based on the count
     *
     * @param   int $paymentId get the payment id params
     * @return boolean
     */
     public static function formHide($paymentId)
     {
        // Check the time limit to display the payment.
        return (date('H:i:s', strtotime('now')) <= self::handleSession('callback_time_' . $paymentId, '', 'get') && self::handleSession('invalid_pin_count_' . $paymentId, '', 'get') == 1) ? 1 : 0;
     }

    /**
     * To safely open a connection to a server, post data when needed and read the result
     *
     * @param   string $url               get the server request url
     * @param   array  $requestParameters get the server request parameter
     * @return  array
     */
    public static function performHttpsRequest($requestParameters, $type = false)
    {
        $url = ($type) ? 'https://payport.novalnet.de/nn_infoport.xml' : 'https://payport.novalnet.de/paygate.jsp';
        $options = new Registry;
        $transport = JHttpFactory::getAvailableDriver($options);
        $response = $transport->request('POST', new JUri($url), $requestParameters, null, 240, '');
        $data = isset($response->body) ? $response->body : '';
        ($type === false) ? parse_str($data, $result) : $result =  $data;
        return $result;
    }

    /**
     * Update the order status for cancelled order
     *
     * @param   object  $orderObject     get the order    object
     * @param   array   $gatewayResponse get the payment  response
     * @param   object  $orderHistory    get the order    history
     * @param   boolean $redirectMethods get the redirect methods
     * @param   object  $data get the current object
     * @return  void
     */
    public function updateCancelledOrder($orderObject, $gatewayResponse, $orderHistory, $redirectMethods = false, $data)
    {
        // On failure / error / cancellation.
        $orderHistory->notified = 1;
        $orderHistory->orderhistory_order_id = $orderObject->order_id;

        // Update cancelled order comments in order history table.
        $orderHistory->data = self::responseMsg($gatewayResponse) . '</br>' . JText::_('HKPAYMENT_NOVALNET_TRANSACTION_ID') . ': ' . $gatewayResponse['tid'] ;
        $paymentClass = hikashop_get('class.payment');
        $payment = $paymentClass->get($orderObject->order_payment_id);
        $payment->payment_name.= '</br>' . $orderHistory->data;

        // Update the cancelled order details.
        $data->modifyOrder($orderObject->order_id, 'cancelled', $orderHistory, false, false);

        // Store the cancelled order details in callback and transaction detail table.
        self::insertCallbackdata($orderObject->order_id, 0, $gatewayResponse['tid']);
        self::getTransactionSuccess($gatewayResponse, $orderObject, $data);

        // Clear the session value.
		self::nnSessionClear($orderObject->order_payment_method, $orderObject->order_payment_id, false);

        ($redirectMethods) ? self::showMessage($gatewayResponse['status_text']) : self::showMessage($gatewayResponse['status_desc']);
    }

    /**
     * Generate hash value using sha256 algorithm
     *
     * @param   array  $hash           get the hash    value
     * @param   string $keyPassword    get the payment access key
     * @return string
     */
    public static function hash($params, $keyPassword)
    {
        return hash('sha256', ($params['auth_code'] . $params['product'] . $params['tariff'] . $params['amount'] . $params['test_mode'] . $params['uniqid'] . strrev($keyPassword)));
    }

    /**
     * Checks the returned response hash value
     *
     * @param   array  $hash              get the hash    value
     * @param   string $paymentAccesskey  get the payment access key
     * @return boolean
     */
    public static function checkHash($hash, $paymentAccesskey)
    {
        return ($hash['hash2'] != self::hash($hash, $paymentAccesskey));
    }

    /**
     * Encodes the datas using base64_encode function
     *
     * @param   array  $data            get the ecncode data
     * @param   string $acesskey get the payment access key
     * @return array
     */
    public static function encode($data, $acesskey)
    {
        foreach (self::$dataParams as $key => $value)
        {
            try
            {
                if (isset($data[$value]))
                {
                   $data[$value] = htmlentities(base64_encode(openssl_encrypt($data[$value], "aes-256-cbc", $acesskey, true, $data['uniqid'])));
                }
            }
            catch (Exception $e)
            {
                echo ('Error: ' . $e);
            }
        }
        return $data;
    }

    /**
     * Get the unique id
     *
     * @return string
     */
    public static function get_uniqueid()
    {
        $randomwordarray = explode(',', '8,7,6,5,4,3,2,1,9,0,9,7,6,1,2,3,4,5,6,7,8,9,0');
        shuffle($randomwordarray);
        return substr(implode($randomwordarray, ''), 0, 16);
    }

    /**
     * Decodes the encoded datas using base64_decode function
     *
     * @param   array  $data                    get the decoded data
     * @param   string $paymentAccesskey    get the payment access key
     * @return  string
     */
    public static function decode($data, $paymentAccesskey)
    {
        foreach (self::$dataParams as $key => $value)
        {
            try
            {
                if (isset($data[$value]))
                {
                   $data[$value] = openssl_decrypt(base64_decode($data[$value]), "aes-256-cbc", $paymentAccesskey, true, $data['uniqid']);
                }

            }
            catch (Exception $e)
            {
                echo ('Error: ' . $e);
            }
        }
        return $data;
    }

    /**
     * Postback call are send to the server
     *
     * @param   array  $gatewayResponse get the payment response
     * @param   object $order           get the order   object
     * @return  void
     */
    public static function doPostbackcall($gatewayResponse, $order)
    {
        $orderNo = isset($order->cart->order_number) ? $order->cart->order_number : $order->order_number;
        // Prepare postbackcall parameter.
        $postBackParams = array(
        'vendor' => $GLOBALS['configDetails']->vendor,
        'auth_code' => $GLOBALS['configDetails']->authCode,
        'product' => $GLOBALS['configDetails']->productId,
        'tariff' => $GLOBALS['configDetails']->tariffId,
        'order_no' => $orderNo,
        'key' => $gatewayResponse['payment_id'],
        'tid' => $gatewayResponse['tid'],
        'status' => 100);
        if ($order->order_payment_method == 'novalnet_invoice')
        {
            $postBackParams['invoice_ref'] = 'BNR-' . $GLOBALS['configDetails']->productId . '-' . $orderNo;
        }
        parse_str(http_build_query($postBackParams), $postBackRequest);

        // Send params to the server using CURL request.
        self::performHttpsRequest($postBackRequest);
    }
    
     /**
     * Get order comments in order history table.
     *
     * @param   int $order_id get the order id
     * @return void
     */
    public static function novalnetOrderComments($order_id)
    {
        // Get the order comments in order history table.
        $result = self::selectQuery(array(
        'table_name' => '#__hikashop_history',
        'column_name' => 'history_data',
        'condition' => array('history_notified="1" AND  history_order_id=' . $order_id . ''),
        'order' => ''), true);

        // Show the order comments in order history table.
        for ($i = 0;$i < count($result);$i++)
        {
            echo '</br>' . $result[$i]->history_data . '<br/>';
        }
    }

    /**
     * Get the shop url for payments has been redirect
     *
     * @param   boolean $shopUrl get the shop url
     * @return string
     */
    public static function redirectUrl($shopUrl = false)
    {
        $url = self::getBetweenString($_SERVER['PHP_SELF'], 'index.php/', '/checkout');
        return ($shopUrl) ? $url . '/checkout' : HIKASHOP_LIVE . 'index.php/' . $url . '/checkout';
    }

    /**
     * Get inbetween string content for the url
     *
     * @param   string $content string get      the       shop url  content
     * @param   string $start   starting inbetween string
     * @param   string $end     starting end       string
     * @return string
     */
    public static function getBetweenString($content, $start, $end)
    {
        $url = explode($start, $content);
        $url = explode($end, $url[1]);
        return $url[0];
    }

    /**
     * Retrives shop language using Joomla getLanguage property
     *
     * @return string
     */
    public static function getLanguageTag()
    {
        $shopLanguage = JFactory::getLanguage();
        return strtoupper(substr($shopLanguage->getTag(), 0, 2));
    }

    /**
     * Handles the Novalnet session available in the shop
     *
     * @param   string      $value get the session value
     * @param   string|null $key   get the session key
     * @param   string      $type  get the session type
     * @return none
     */
    public static function handleSession($value, $key, $type)
    {
        $session = JFactory::getSession();
        // Set session value.
        if ($type == 'set')
        {
            $session->set($key, $value);
        }
        // Get session value.
        elseif ($type == 'get')
        {
            return $session->get($value);
        }
        // Clear session value.
        elseif ($type == 'clear')
        {
            $session->clear($value);
        }
    }

    /**
     * Shows the pin by callback template
     *
     * @param   object $paymentMethod get the payment method
     * @param   object $order get the order   object
     * @return string
     */
    public static function pinByCallCheck($paymentMethod, $order)
    {
        $display = '';
        if (self::handleSession('tid_' . $paymentMethod->payment_id, '', 'get') == '')
        {
            $number = JText::_('HKPAYMENT_NOVALNET_SMS_NO');
            $errMsg = JText::_('HKPAYMENT_NOVALNET_MOBILE_ERROR');
            if ($paymentMethod->payment_params->pinbyCallback == 'CALLBACK')
            {
                $number = JText::_('HKPAYMENT_NOVALNET_TEL_NO');
                $errMsg = JText::_('HKPAYMENT_NOVALNET_TEL_ERROR');
            }
            
            $orderAmount = self::doFormatAmount($order->full_total->prices[0]->price_value_with_tax);
       
            if( $paymentMethod->payment_params->callbackAmount <= $orderAmount ) {
				// Display fraudmodule form field.
				$display .= '<div class="hkform-group control-group"><label for="pinby_tel_' . $paymentMethod->payment_id . '" class="hkc-sm-3 hkcontrol-label">' . $number . '<span class="hikashop_field_required_label">*</span></label><div class="hkc-sm-4">
					<input class="inputbox hkform-control" type="text" autocomplete="off" name="pinby_tel_' . $paymentMethod->payment_id . '" id="pinby_tel_' . $paymentMethod->payment_id . '" value="'.$order->billing_address->address_telephone.'" ><input type="hidden" id="tel_error" value="' . $errMsg . '" /></div></div>';
			}
        }
        else
        {
            // Display fraudmodule pin field.
            $display .= '<div class="hkform-group control-group"><label for="pin_field_' . $paymentMethod->payment_id . '" class="hkc-sm-3 hkcontrol-label">' . JText::_('HKPAYMENT_NOVALNET_TEL_PIN') . '<span class="hikashop_field_required_label">*</span></label><div class="hkc-sm-4">
				<input class="inputbox hkform-control" autocomplete="off" type="text"  name="pin_field_' . $paymentMethod->payment_id . '" id="pin_field_' . $paymentMethod->payment_id . '" value="" ><br /><input type="hidden" id="sepa_paymentid" name="sepa_paymentid" value="' . $paymentMethod->payment_id . '"/><input type="checkbox" style="" name="new_pin_' . $paymentMethod->payment_id . '" value="1" />&nbsp;&nbsp;' . JText::_('HKPAYMENT_NOVALNET_NEW_PIN') .'</div></div>';
        }
        return $display;
    }

    /**
     * Send fraud module request to Novalnet server
     *
     * @param   object $paymentMethod get the payment method
     * @param   object $order         get the order   object
     *
     * @return  void
     */
    public static function fraudModuleRequest($paymentMethod, $order)
    {
        // Get the post value using JRequest.
        $postParam = JRequest::get('post');
        if (!NovalnetValidation::validateCallbackFields($paymentMethod->payment_params->pinbyCallback, self::handleSession('pinby_tel_'.$order->order_payment_id, '', 'get')))
             return false;
        // Form payment params to send the firstcall server request if enable the fraud module.
        $paymentParams = self::doFormPaymentParameters($paymentMethod, $order);
        $paymentParams = ($paymentMethod->name == 'novalnet_invoice') ? PlgHikashoppaymentnovalnet_Invoice::getInvoiceParams($paymentParams)  : PlgHikashoppaymentnovalnet_sepa::getSepaParams($paymentParams);

        if ($paymentMethod->name == 'novalnet_sepa') {
			$sepaSessionData = unserialize(NovalnetUtilities::handleSession('SEPAvariable', '', 'get'));
			if(!empty($sepaSessionData)) {
				$paymentParams['bank_account_holder'] = html_entity_decode($sepaSessionData['nn_sepa_owner']);
				$paymentParams['iban'] = strtoupper($sepaSessionData['iban']);
			}
		}

        // If pinbycallback is enable added pin_by_callback param.
        if ($paymentMethod->payment_params->pinbyCallback == 'CALLBACK')
        {
            $paymentParams['pin_by_callback'] = 1;
            $paymentParams['tel'] = (self::handleSession('pinby_tel_' . $order->order_payment_id, '', 'get') != '') ? self::handleSession('pinby_tel_' . $order->order_payment_id, '', 'get') : trim($postParam['pinby_tel_' . $order->order_payment_id]);
        }
        else
        {
        // If pinbysms is enable added pin_by_sms param.
        $paymentParams['pin_by_sms'] = 1;
        $paymentParams['mobile'] = (self::handleSession('pinby_tel_' . $order->order_payment_id, '', 'get') != '') ? self::handleSession('pinby_tel_' . $order->order_payment_id, '', 'get') : trim($postParam['pinby_tel_' . $order->order_payment_id]);
        }
        // Send to the payment params using curl.
        $aryResponse = self::performHttpsRequest(http_build_query($paymentParams));
        // Handle the first call server response and show the message.
        if ($aryResponse['status'] == 100)
        {
            self::handleSession($aryResponse['tid'], 'tid_' . $order->order_payment_id, 'set');
            self::handleSession($aryResponse, 'fraud_module_response_'.$order->order_payment_id, 'set');
            // If zero amount booking is enabled to set the order amount.
            (isset($paymentMethod->payment_params->shoppingType) && $paymentMethod->payment_params->shoppingType == "ZERO_AMOUNT_BOOKING") ? self::handleSession(self::doFormatAmount($order->order_full_price), 'amount_' . $order->order_payment_id, 'set') : self::handleSession($paymentParams['amount'], 'amount_' . $order->order_payment_id, 'set');

            if (isset($aryResponse['currency']))
            {
                self::handleSession($aryResponse['currency'], 'currency_' . $order->order_payment_id, 'set');
            }

            // Set the SEPA masking value in session field if oneclick is enabled.
            if (self::handleSession('create_payment_ref' . $order->order_payment_id, '', 'get'))
            {
                $sepaMaskingPatterns = serialize(array('iban' => $aryResponse['iban'], 'bankaccount_holder' => $aryResponse['bankaccount_holder']));
                self::handleSession($sepaMaskingPatterns, 'sepa_masking_params' . $order->order_payment_id, 'set');
            }
            self::handleSession($paymentParams['key'], 'payment_key' . $order->order_payment_id, 'set');

            // Show the message for sms and callback.
            ($paymentMethod->payment_params->pinbyCallback == 'SMS') ? self::showMessage(JText::_('HKPAYMENT_NOVALNET_MSG_ERR'), 'success') : self::showMessage(JText::_('HKPAYMENT_NOVALNET_TEL_ERR'), 'success');
        }
        else
        {
            // If failure show the message.
            self::showMessage($aryResponse['status_desc']);
        }
    }

    /**
     * Validates PIN, amount in fraudmodule second call
     *
     * @param   objcect   $order     get the order object
     * @param   string $paymentMethod get the payment  name
     * @return array
     */
    public static function fraudModuleSecondCall($order, $paymentMethod)
    {
        // Get the post params value using JRequest.
        $postParams = JRequest::get('post');
        // Handle the pin session values
        $pinValue = (!empty($postParams['new_pin_' . $order->order_payment_id])) ? $postParams['new_pin_' . $order->order_payment_id] : self::handleSession('pin_field_' . $order->order_payment_id, '', 'get');

        // Validate Pin number.
        if (!(self::handleSession('pin_field_' . $order->order_payment_id, '', 'get')))
            NovalnetValidation::validatePinNumber(trim($pinValue));

        // Send the fraudmodule xml request for second call.
        return self::fraudModuleXmlRequest(array(
        'payment_id' => $order->order_payment_id,
        'callback' => $paymentMethod->payment_params->pinbyCallback,
        'pin' => trim($pinValue),
        'method_name' => $paymentMethod->name,
        'new_pin' => self::handleSession('new_pin_' . $order->order_payment_id, '', 'get'),
        'order_id' => (!empty($order->cart->order_id) ? $order->cart->order_id : '')),
        $paymentMethod);
    }

    /**
     * Sends Xml request to Novalnet server for fraud module
     *
     * @param   int         $callbackValues get the callback values
     * @param   object|null $paymentMethod  get the payment name
     * @return array
     */
    public static function fraudModuleXmlRequest($callbackValues, $paymentMethod = null)
    {
        // Set the request type.
        if (!empty($callbackValues['new_pin']))
        {
            $requestType = 'TRANSMIT_PIN_AGAIN';
            self::handleSession('new_pin_' . $callbackValues['payment_id'], '', 'clear');
        }
        else
        {
            $requestType = 'PIN_STATUS';
        }
        $urlParam = '<nnxml><info_request><vendor_id>' . $GLOBALS['configDetails']->vendor . '</vendor_id>';
        $urlParam.= '<vendor_authcode>' . $GLOBALS['configDetails']->authCode . '</vendor_authcode>';
        $urlParam.= '<request_type>' . $requestType . '</request_type>';
        $urlParam.= '<tid>' . self::handleSession('tid_' . $callbackValues['payment_id'], '', 'get') . '</tid>';
        $urlParam.= '<remote_ip>' . $_SERVER['REMOTE_ADDR'] . '</remote_ip>';
        if ($requestType == 'PIN_STATUS')
            $urlParam.= '<pin>' . $callbackValues['pin'] . '</pin>';
        $urlParam.= '<lang>' . self::getLanguageTag() . '</lang></info_request></nnxml>';

        // Send the Xml request to server.
        $response = self::performHttpsRequest($urlParam, true);

        $response = simplexml_load_string($response);
        $response = json_decode(json_encode($response), true);

        self::handleSession($response['tid_status'], 'callback_tid_status_' . $callbackValues['payment_id'], 'set');
        // If status other than 100.
        if ($response['status'] != '100')
        {
            self::handleSession('pin_' . $callbackValues['payment_id'], '', 'clear');
            if ($response['status'] == '0529006')
            {
                self::handleSession('1', 'invalid_pin_count_' . $callbackValues['payment_id'], 'set');
                self::handleSession('1', 'hide_payment_' . $callbackValues['method_name'], 'set');
                self::handleSession(date('H:i:s', strtotime('+30 minutes')), 'callback_time_' . $callbackValues['payment_id'], 'set');
                self::nnSessionClear($callbackValues['method_name'], $callbackValues['payment_id'], true);
            }

            // Handle the server response to cancel other than status 0529006.
            self::novalnetServerCancel($response['status_message'], $callbackValues['order_id'], $paymentMethod);
            return false;
        }
        else
        {
            // Status equal to 100.
            $response = array(
            'status' => $response['status'],
            'tid' => self::handleSession('tid_' . $callbackValues['payment_id'], '', 'get'),
            );
            return $response;
        }
    }

    /**
     * To cancel the transaction
     *
     * @param   string $errorResponse get the server error message
     * @param   string $orderId       get the order  id
     * @param   int    $paymentMethod get the payment method name
     * @return  void
     */
    public static function novalnetServerCancel($errorResponse, $orderId = null,  $paymentMethod = null)
    {
        if (!empty($orderId))
        {
            // Create OrderHistory object to update the order details.
            $history = new stdClass;
            $orderStatus = 'cancelled';
            $history->notified = 1;
            $history->history_order_id = $orderId;
            $history->data = $errorResponse;

            // Update the order details.
            $paymentMethod->modifyOrder($orderId, $orderStatus, $history, false, false);
        }
        // Show the order message.
        self::showMessage($errorResponse);
    }

    /**
     * Select the query from the database
     *
     * @param   array   $sql          get   the select sql    query
     * @param   boolean $loadObject   based on  this   return the datas
     * @return  object
     */
    public static function selectQuery($sql, $loadObject = false)
    {
        // Get the database connector.
        $dbObj = JFactory::getDBO();
        $query = $dbObj->getQuery(true)->select($sql['column_name'])->from($sql['table_name']);
        if (!empty($sql['condition']))
        {
            $query->where($sql['condition'], 'AND');
        }
        // If order number is present process this one.
        if (!empty($sql['order']))
        {
            $query->order($sql['order']);
        }
        // Passed the query to the database connecter.
        $dbObj->setQuery($query);
        // Get the country list.
        return ($sql['table_name'] == '#__hikashop_zone' || $loadObject == true) ? $dbObj->loadObjectList() : $dbObj->loadObject();
    }

    /**
     * This event is triggerd to update the values
     *
     * @param   array   $tableName   get   the update datas table name
     * @param   array   $fields      get   the update fields
     * @param   array   $conditions  based on  the    condition select data
     * @param   boolean $order          get   the order  number
     * @return  boolean
     */
    public static function updateQuery($tableName, $fields, $conditions, $order = false)
    {
        // Get the database connector.
        $dbObj = JFactory::getDBO();
        $query = $dbObj->getQuery(true);
        $query->update($tableName)->set($fields)->where($conditions, 'AND');
        if ($order)
           $query->order($order);
        // Passed the query to the database connecter.
        $dbObj->setQuery($query);
        // Execute query using database connecter.
        $result = $dbObj->query();
        return $result;
    }

    /**
     * This event is triggerd to insert the values
     *
     * @param   string $tableName     get the insert   data  table  name
     * @param   array  $insertColumns get the inserted data  column name
     * @param   array  $insertValues  get the inserted datas
     * @return boolean
     */
    public static function insertQuery($tableName, $insertColumns, $insertValues)
    {
        // Get the database connector.
        $dbObj = JFactory::getDBO();
        $query = $dbObj->getQuery(true);
        $values = implode(',', $insertValues);
        $query->insert($dbObj->quoteName($tableName))->columns($dbObj->quoteName($insertColumns))->values($values);
        $dbObj->setQuery($query);
        // Execute the db query.
        $result = $dbObj->query();
        return $result;
    }

    /**
     * Insert gateway response in callback table
     *
     * @param   int         $orderNo  get the order number
     * @param   string|null $amount   get the order amount
     * @param   string      $tid      get the transaction id
     * @return  void
     */
    public static function insertCallbackdata($orderNo, $amount, $tid)
    {
        self::insertQuery('#__novalnet_callback_detail',
        array(
        'hika_order_id',
        'callback_amount',
        'reference_tid',
        'callback_datetime'),
        array(
        "'" . $orderNo . "'",
        "'" .$amount . "'",
        "'" . $tid . "'",
        "NOW()"));
    }

    /**
     * Display the message
     *
     * @param   string $message  get the response message
     * @param   string $type     based on the message display
     * @return  void
     */
    public static function showMessage($message, $type = 'error')
    {
        // Loads the Joomla application.
        $app = JFactory::getApplication();
        switch($type)
        {
        case 'error':
            JError::raiseWarning(500, $message);
            break;
        case 'success':
            JFactory::getApplication()->enqueueMessage(JText::_($message));
            break;
        case 'warning':
            JError::raiseWarning( 100, $message);
            break;
        case 'notice':
            JError::raiseNotice( 100, $message);
            break;
        }
         $app->redirect(self::redirectUrl());
         return false;
    }

     /**
     * Display the message at shop backend
     *
     * @param   string $paymentType     payment type
     * @param   string $message  		get the response message
     * @return  void
     */
    public static function backendErrorMsg($element, $message)
    {
        // Loads the Joomla application.
		$app = JFactory::getApplication();
		JError::raiseWarning(500, $message);
		$app->redirect(JRoute::_('index.php?option=com_hikashop&ctrl=plugins&plugin_type=payment&task=edit&name=' . $element->payment_type . '&subtask=payment_edit&payment_id=' . $element->payment_id, false));
		return false;
    }

    /**
     * Get payment order status
     *
     * @param   int $orderId get the order id
     * @return string
     */
    public static function getOrderStatus($orderId)
    {
        $beforeStatus = self::selectQuery(array(
        'table_name' => '#__hikashop_history',
        'column_name' => array('history_new_status'),
        'condition' => array("history_order_id ='" . $orderId . "'"),
        'order' => 'history_id DESC LIMIT 1'));
        return $beforeStatus->history_new_status;
    }

    /**
     * Render the birth date field
     *
     * @param   string $paymentId get the payment id
     * @return string
     */
    public static function renderDobField($paymentId)
    {
		$display = '<div class="hkform-group control-group">
				<label for="birthdate_' . $paymentId . '" class="hkc-sm-3 hkcontrol-label">' . JText::_('NOVALNET_BIRTH_DATE') . '<span class="hikashop_field_required_label">*</span></label><div class="hkc-sm-4">
				<input class="inputbox hkform-control" type="text"  name="birthdate_' . $paymentId . '" id="birthdate_' . $paymentId . '" autocomplete="off" maxlength="10" onblur="getAge(' . $paymentId . ')" placeholder="DD-MM-YYYY" value="" ><input type="hidden" id="date_error_' . $paymentId . '" value="' . JText::_('HKPAYMENT_NOVALNET_DOB') . '" /><input type="hidden" id="date_format" value="' . JText::_('HKPAYMENT_NOVALNET_AGE_DATE_FORMAT_VALIDATION') . '" /><input type="hidden" id="age_validation_' . $paymentId . '" value="' . JText::_('HKPAYMENT_NOVALNET_AGE_VALIDATION') . '" /></div></div>';
        return $display;
    }

    /**
     * Guarantee check condition implementation
     *
     * @param   string $paymentMethod get the current payment method
     * @param   int    $amount        get the order   amount
     * @return integer
     */
    public static function guaranteePaymentImplementation($paymentMethod, $amount)
    {
		self::handleSession('guarantee_error_message_'.$paymentMethod->payment_id, '', 'clear');
        if ($paymentMethod->payment_params->guaranteeEnable)
        {
            self::handleSession('error_guarantee_' . $paymentMethod->payment_id, '', 'clear');
            $billingAddress = self::loadAddress('billing');
            $shippingAddress = self::loadAddress('shipping');
            $address = array(
            'first_name' => $billingAddress->address_firstname,
            'last_name' => $billingAddress->address_lastname,
            'post_code' => $billingAddress->address_post_code,
            'city' => $billingAddress->address_city,
            'country_code' => $billingAddress->address_country->zone_code_2,
            'street' => $billingAddress->address_street) === array(
            'first_name' => $shippingAddress->address_firstname,
            'last_name' => $shippingAddress->address_lastname,
            'post_code' => $shippingAddress->address_post_code,
            'city' => $shippingAddress->address_city,
            'country_code' => $shippingAddress->address_country->zone_code_2,
            'street' => $shippingAddress->address_street);
            $config = & hikashop_config();
            $currency = hikashop_get('class.currency');
            $currencyCode = $currency->get($config->get('main_currency', 1))->currency_code;

            // If minimum amount is empty to assign the default amount.
            if (empty($paymentMethod->payment_params->minimumAmount))
            {
                $paymentMethod->payment_params->minimumAmount = "999";
            }
           
            // Get the billing and shipping for compare the address fields.
            if (((int)$amount >= (int)$paymentMethod->payment_params->minimumAmount) && in_array($billingAddress->address_country->zone_code_2, array('DE', 'AT', 'CH')) && $currencyCode == 'EUR' && $address)
            {
                self::handleSession('1', 'guarantee_' . $paymentMethod->payment_id, 'set');
                if (empty($billingAddress->address_company) || empty($shippingAddress->address_company))
                {
                      NovalnetUtilities::handleSession('guarantee_error_message_'.$paymentMethod->payment_id, '', 'clear');
                      return self::renderDobField($paymentMethod->payment_id);
                }
            }
            elseif (!$paymentMethod->payment_params->guaranteeMethod)
            {
                self::handleSession('guarantee_' . $paymentMethod->payment_id, '', 'clear');
                self::handleSession('1', 'error_guarantee_' . $paymentMethod->payment_id, 'set');
                $errorMessage = '';
                if (!in_array($billingAddress->address_country->zone_code_2, array('DE', 'AT', 'CH')))
                {
                    $errorMessage .= '<ul><li>' . JText::_('HKPAYMENT_NOVALNET_GUARANTEE_COUNTRY_ERROR') . '</li></ul>';
                }
                if ($currencyCode != 'EUR')
                {
                    $errorMessage = '<ul><li>' . JText::_('HKPAYMENT_NOVALNET_GUARANTEE_CURRENCY_ERROR') . '</li></ul>';
                }
                if ($address != 1) {
                    $errorMessage .= '<ul><li>' . JText::_('HKPAYMENT_NOVALNET_GUARANTEE_ADDRESS_ERROR') . '</li></ul>';
                }
                if ($amount < $paymentMethod->payment_params->minimumAmount)
                {
                    $errorMessage .= '<ul><li>' . JText::_('HKPAYMENT_NOVALNET_GUARANTEE_AMOUNT_ERROR') . ($paymentMethod->payment_params->minimumAmount / 100) . ' EUR' . '</li></ul>';
                }
                if(!empty($errorMessage))
                {
                   self::handleSession($errorMessage, 'guarantee_error_message_'.$paymentMethod->payment_id, 'set');
                }
            }
        }
        self::handleSession('guarantee_' . $paymentMethod->payment_id, '', 'clear');
        self::handleSession('error_guarantee_' . $paymentMethod->payment_id, '', 'clear');
    }

    /**
     * This event triggered to load the address based on the address type
     *
     * @param   string $type get the address type
     * @return object
     */
    public static function loadAddress($type)
    {
        $app = JFactory::getApplication();
        // Get the cart value
        $cart = hikashop_get('class.cart');
        $address = $app->getUserState(HIKASHOP_COMPONENT . '.' . $type . '_address');
        if (!empty($address))
        {
            // Load the address based on the type.
            $cart->loadAddress($order->cart, $address, 'object', $type);
            if ($type == 'billing')
            {
                return $order->cart->billing_address;
            }
            else
            {
                return $order->cart->shipping_address;
            }
        }
    }

    /**
     * To get the order comments for cashpayment
     *
     * @param   string $response get the  payment response
     * @param   date   $dueDate  get the  amount update due date
     * @return string
     */
    public static function cashpaymentOrderComments($response)
    {
        $storeCount = 1;
        foreach ($response as $key => $value)
        {
            if (strpos($key, 'nearest_store_title') !== false)
            {
                $storeCount++;
            }
        }
        $comments = '<br />';
        if ($response['cp_due_date'])
        {
            $comments.= JText::_('HKPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_SLIP_DATE') . ': ' . $response['cp_due_date'];
        }
        $comments.= '<br /><br />';
        $comments.= JText::_('HKPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_STORE') . '<br /><br />';
        for ($i = 1;$i < $storeCount;$i++) {
            $comments.= $response['nearest_store_title_' . $i] . '<br />';
            $comments.= $response['nearest_store_street_' . $i] . '<br />';
            $comments.= $response['nearest_store_city_' . $i] . '<br />';
            $comments.= $response['nearest_store_zipcode_' . $i] . '<br />';
            $zoneName = (self::getLanguageTag() == 'DE') ? 'zone_name' : 'zone_name_english';
            foreach (self::getCountryList() as $countryVal)
            {
                if ($countryVal->zone_code_2 == $response['nearest_store_country_' . $i])
                {
                    $comments.= $countryVal->$zoneName . '<br /><br />';
                }
            }
        }
        return $comments;
    }

    /**
     *  To hide the payment if global configuration not configured.
     *
     */
    public static function globalConfig()
    {
        $GLOBALS['configDetails'] = self::getMerchantConfig();
        if (empty($GLOBALS['configDetails']->novalnetProductActivationKey) || empty($GLOBALS['configDetails']->tariffId) || $GLOBALS['configDetails']->payment_published == '0')
        {
            return false;
        }
        return true;
    }
    /**
     * Insert the response to table
     *
     * @param  string $gatewayResponse get the  payment response
     * @param  string $order get the  order details
     * return array
     */
    public static function updateNovalnetTransactionTable($gatewayResponse, $order)
    {
        switch ($order->order_payment_method)
        {
            case 'novalnet_invoice':
            case 'novalnet_prepayment':
                $paymentDetails = array(
                'payment_type' => $order->order_payment_method,
                'due_date' => isset($gatewayResponse['due_date']) ? $gatewayResponse['due_date'] : '', 'invoice_iban' => $gatewayResponse['invoice_iban'], 'invoice_bic' => $gatewayResponse['invoice_bic'],
                'invoice_bankname' => $gatewayResponse['invoice_bankname'],
                'invoice_bankplace' => $gatewayResponse['invoice_bankplace'],
                'invoice_ref' => $gatewayResponse['invoice_ref'],
                'invoice_account_holder' => $gatewayResponse['invoice_account_holder']);
            break;
            case 'novalnet_cashpayment':
                if (isset($gatewayResponse['cp_due_date']))
                {
                    $paymentDetails['due_date'] = $gatewayResponse['cp_due_date'];
                    $paymentDetails['payment_type'] = $order->order_payment_method;
                    $storeCount = 1;
                    foreach ($gatewayResponse as $key => $value)
                    {
                        if (strpos($key, 'nearest_store_title') !== false)
                        {
                            $storeCount++;
                        }
                    }
                    $paymentDetails['stores'] = '';
                    for ($i = 1;$i < $storeCount;$i++)
                    {
                        $paymentDetails['stores'].= $gatewayResponse['nearest_store_title_' . $i] . ': ' . '<br>' . (isset($gatewayResponse['nearest_store_street_' . $i]) ? $gatewayResponse['nearest_store_street_' . $i] . ', ' : '') . '<br>' . (isset($gatewayResponse['nearest_store_city_' . $i]) ? $gatewayResponse['nearest_store_city_' . $i] . ', ' : '') . '<br>' . (isset($gatewayResponse['nearest_store_country_' . $i]) ? $gatewayResponse['nearest_store_country_' . $i] . ', ' : '') . '<br>' . (isset($gatewayResponse['nearest_store_zipcode_' . $i]) ? $gatewayResponse['nearest_store_zipcode_' . $i] : '' . '<br>') . '<br>';
                        $paymentDetails['stores'].= '<br>';
                    }
                }
            break;
        }
        if (isset($gatewayResponse['birth_date']))
        {
            $paymentDetails['birth_date'] = $gatewayResponse['birth_date'];
        }
        return $paymentDetails;
    }

    /**
     * This event triggered to get the callback total amount in callback table.
     *
     * @param   int    $orderNo     get the order   number
     * @param   string $paymentType get the current payment type
     * @return string
     */
    public static function getCallbackAmount($orderNo, $paymentType)
    {
        return NovalnetUtilities::selectQuery(array(
            'table_name' => '#__novalnet_callback_detail',
            'column_name' => in_array($paymentType, array(
                'novalnet_instant_banktransfer',
                'novalnet_invoice',
                'novalnet_prepayment',
                'novalnet_cashpayment'
            )) ? array(
                "SUM(callback_amount) AS total_amount"
            ) : array(
                'callback_amount'
            ),
            'condition' => array(
                "hika_order_id='" . $orderNo . "'"
            ),
            'order' => ''
        ));
    }

    /**
     * Novalnet transaction comments
     *
     * @param   array   $tid Get the transaction id
     * @param   array   $testmode Get the testmode value
     *
     * @return  array
     */
    public static function transactionComments($tid, $testmode)
    {
        $transactionComments = JText::_('HKPAYMENT_NOVALNET_TRANSACTION_ID') . ': ' . $tid.'<br>';
        $transactionComments.= ($testmode == '1') ? JText::_('HKPAYMENT_NOVALNET_TEST_ORDER_MESSAGE') : '';
        return $transactionComments;
    }
}
