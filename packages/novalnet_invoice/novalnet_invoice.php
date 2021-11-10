<?php
/**
* This script is used for Invoice payment
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
* Script : novalnet_invoice.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';
require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_validation.php';

// Loads JS
$document = JFactory::getDocument();
$document->addScript(JURI::root() . '/plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_utilities.js');

/**
 * Novalnet Invoice payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_Invoice extends hikashopPaymentPlugin
{
    /**
     * @var boolean $multiple
     */
    public $multiple = true;

    /**
     * To define the payment $name
     *
     * @var string
     */
    public $name = 'novalnet_invoice';

    var $pluginConfig = array(
		'testmode' 					=> array('HKPAYMENT_NOVALNET_ADMIN_TEST_MODE', 'boolean', '0'),
		'transactionBeforeStatus' 	=> array('HKPAYMENT_NOVALNET_STATUS_BEFORE', 'orderstatus'),
		'transactionEndStatus' 		=> array('HKPAYMENT_NOVALNET_STATUS_AFTER', 'orderstatus'),
		'paymentDuration' 			=> array('HKPAYMENT_NOVALNET_ADMIN_INVOICE_DURATION', 'input'),
		'onholdAction' 				=> array('HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_ACTION', 'list', array('CAPTURE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_CAPTURE', 'AUTHORIZE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_AUTHORIZE')),
		'onHold' 					=> array('HKPAYMENT_NOVALNET_INVOICE_ON_HOLD', 'input'),
		'notificationMsg' 			=> array('HKPAYMENT_NOVALNET_NOTIFICATION_TO_ENDUSER', 'input'),
		'pinbyCallback' 			=> array('HKPAYMENT_ADMIN_PIN_BY_CALL', 'list', array('' => 'HKPAYMENT_ADMIN_CALLBACK', 'CALLBACK' => 'HKPAYMENT_ADMIN_TEL', 'SMS' => 'HKPAYMENT_ADMIN_SMS')), 'callbackAmount' => array('HKPAYMENT_ADMIN_PIN_AMOUNT', 'input'),
		'guaranteeLabel' 			=> array('HKPAYMENT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_CONFIGURATION', 'label'),
		'guranteeRequirements' 		=> array('HKPAYMENT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_BASIC_REQUIRMENTS', 'html', ''),
		'guaranteeEnable' 			=> array('HKPAYMENT_NOVALNET_INVOICE_GUARANTEE_ENABLE', 'boolean', '0'),
		'minimumAmount' 			=> array('HKPAYMENT_NOVALNET_INVOICE_MINIMUM_AMOUNT', 'input'),
		'guaranteeStatus' 			=> array('HKPAYMENT_NOVALNET_ADMIN_GUARANTEE_ORDER_STATUS', 'orderstatus'),
		'guaranteeMethod' 			=> array('HKPAYMENT_NOVALNET_INVOICE_GUARANTEE_SELECT_METHOD', 'boolean', '1'),
    );

    /**
     * To hide the payment if global configuration not configured.
     *
     * @param object $subject current subject payment params
     * @param array $config current payment method  object
     */
    public function __construct(&$subject, $config)
    {
        if (!NovalnetUtilities::globalConfig())
            return false;
        // Loads language
        $lang = JFactory::getLanguage();
        $lang->load('plg_hikashoppayment_novalnet_invoice', JPATH_ADMINISTRATOR);
        $paymentId = NovalnetUtilities::selectQuery(array('table_name' => array('#__hikashop_payment'), 'column_name' => array('payment_id'), 'condition' => array("payment_type='novalnet_payments'"),));

        $this->pluginConfig['guranteeRequirements'][2] = JText::_('HKPAYMENT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_BASIC_REQUIRMENTS_LIST');

        parent::__construct($subject, $config);
    }

    /**
     * This event is fired to display the payment based on the configuration.
     *
     * @param  object $order current order object
     * @param  object $methods current payment method params
     * @param  object $usable_methods current usable payment params
     * @return string
     */
    public function onPaymentDisplay(&$order, &$methods, &$usable_methods)
    {
        if (!empty($methods))
        {
            foreach ($methods as $method)
            {
                if ($method->payment_type != 'novalnet_invoice' || !$method->enabled)
                {
                    continue;
                }
                if (!empty($method->payment_zone_namekey))
                {
                    $zoneClass = hikashop_get('class.zone');
                    $zones = $zoneClass->getOrderZones($order);
                    if (!in_array($method->payment_zone_namekey, $zones))
                    {
                        return true;
                    }
                }
                $hideForm = NovalnetUtilities::formHide($method->payment_id);
                if ($method->payment_published == 0 || $hideForm)
                {
                    return false;
                }
                self::needCC($method, $order);
                $usable_methods[$method->ordering] = $method;
            }
        }
        return true;
    }

    /**
     * This event is fired to display the payment description in front end.
     *
     * @param  object $method method variable
     * @param  order $order order object variable
     * @return void
     */
    public function needCC(&$method, $order)
    {
        // Get post params.
        $postParams = JRequest::get('post');
        $customerDetails = NovalnetUtilities::loadAddress('billing');
        $this->payment_params = $method->payment_params;
        // Get the country code.
        $countryCode = isset($customerDetails->address_country->zone_code_2) ? $customerDetails->address_country->zone_code_2 : '';

        foreach (array('birthdate_' . $method->payment_id, 'pin_' . $method->payment_id, 'new_pin_' . $method->payment_id, 'pinby_tel_' . $method->payment_id,'telephone_field_' . $method->payment_id, 'pin_field_'.$method->payment_id ) as $key)
        {
            if(!empty($postParams[$key]))
            NovalnetUtilities::handleSession($postParams[$key], $key, 'set');
        }
        $method->custom_html = '';
        $displayBlock = 'style=display:block';
        if (isset($postParams['hikashop_payment']))
        {
            // If guarantee is enabled to show the birth date field.
            if ((empty(NovalnetUtilities::handleSession('birthdate_' . $method->payment_id, '', 'get')) && $method->payment_params->guaranteeEnable) || (empty(NovalnetUtilities::handleSession('telephone_field_' . $method->payment_id, '', 'get')) && $method->payment_params->pinbyCallback && !$method->payment_params->guaranteeEnable))
            {
                $height = (NovalnetUtilities::handleSession('tid_' . $method->payment_id, '', 'get') == '') ? '50px' : '50px';
                $displayBlock = 'style=display:block;height:' . $height;
            }
            else
            {
                if (isset($postParams['hikashop_payment']))
                {
                    if ($method->payment_params->guaranteeEnable || $method->payment_params->pinbyCallback)
                    {
                        // Otherwise none.
                        $displayBlock = 'style=display:none';
                        $method->custom_html = JText::_('HKPAYMENT_NOVALNET_INVOICE_ORDER_MSG');
                    }
                }
            }
        }
        $amount = NovalnetUtilities::doFormatAmount($order->full_total->prices[0]->price_value_with_tax);
        if (!NovalnetUtilities::formHide($method->payment_id))
        {
            $method->payment_description = NovalnetUtilities::getPaymentNotifications($method);
            // Show the birth date field.
            NovalnetUtilities::handleSession('guarantee_' . $method->payment_id, '', 'clear');
            if ($method->payment_params->guaranteeEnable)
            {
				NovalnetUtilities::handleSession('non_guarantee_with_fraudcheck_' . $method->payment_id, '', 'clear');
                $method->custom_html.= '<div class="birthday_field_form hkform-horizontal" id="birthday_field_form"' . $displayBlock . '>' . NovalnetUtilities::guaranteePaymentImplementation($method, NovalnetUtilities::doFormatAmount($order->full_total->prices[0]->price_value_with_tax)) . '</div>';
            }

            // Check fraudmodule is enabled or not.
            if ((!NovalnetUtilities::handleSession('guarantee_' . $method->payment_id, '', 'get') && !NovalnetUtilities::handleSession('error_guarantee_' . $method->payment_id, '', 'get') && NovalnetValidation::fraudModuleCheck($method, $amount, $countryCode) && $method->payment_params->guaranteeMethod == '1') || ($method->payment_params->pinbyCallback && $method->payment_params->guaranteeMethod != '1' && $method->payment_params->guaranteeEnable != '1'))
            {
                NovalnetUtilities::handleSession(1, 'non_guarantee_with_fraudcheck_' . $method->payment_id, 'set');
                $method->custom_html.= '<div class="fraud_module hkform-horizontal" id="fraud_module_field"' . $displayBlock . '>' . NovalnetUtilities::pinByCallCheck($method, $order) . '</div>';
            }
        }
        // Check fraudmodule and show the form fields.
        if (NovalnetValidation::fraudModuleCheck($method, $amount, $countryCode) || $method->payment_params->guaranteeEnable)
        {
            $method->custom_html.= '<script type="text/javascript" src="' . HIKASHOP_LIVE . 'plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_formscript.js"></script>';
            $method->custom_html.= '<input type="hidden" id="invoice_fraud_module" value="' . $method->payment_id . '">';
            $method->custom_html.= '<script type="text/javascript">
            jQuery(document).ready(function() {
               jQuery(".hikashop_checkout_payment_submit").css({"display": "none"});
            });
            </script>';
        }
    }

    /**
     * This event is fired onbefore order creation to validate payment params.
     * @param  object $order current order object
     * @param  object $do current do object
     * @return string
     */
    public function onBeforeOrderCreate(&$order, &$do)
    {
        if (parent::onBeforeOrderCreate($order, $do))
            return true;

        // If guarantee force is disabled to show the message.
        if (!empty((NovalnetUtilities::handleSession('guarantee_error_message_'.$order->order_payment_id, '', 'get'))))
        {
           NovalnetUtilities::showMessage(trim(NovalnetUtilities::handleSession('guarantee_error_message_'.$order->order_payment_id, '', 'get')));
        }

        // Validate the birth date field.
        if ($this->payment_params->guaranteeEnable && NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get') && $this->payment_params->guaranteeMethod !='')
        {
                if (NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get'))
                {
                    if (NovalnetValidation::validateAge(NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get')))
                    {
                        NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'clear');
                        NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_AGE_VALIDATION'));
                    }
                }
                else
                {
                    NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'clear');
                    NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_DOB'));
                }
        }
        // Validate basic configuration parameters.
        NovalnetValidation::doNovalnetValidation($this);

        // Form fraud module first request call.
        if (NovalnetUtilities::handleSession('tid_' . $order->order_payment_id, '', 'get') == '' && NovalnetValidation::fraudModuleCheck($order->cart->payment, NovalnetUtilities::doFormatAmount($order->cart->full_total->prices[0]->price_value_with_tax), $order->cart->billing_address->address_country->zone_code_2) && !(NovalnetUtilities::handleSession('pin_tel' . $order->order_payment_id, '', 'get')) && (($this->payment_params->guaranteeEnable != '1') || (NovalnetUtilities::handleSession('non_guarantee_with_fraudcheck_' . $order->order_payment_id, '', 'get') == '1')))
        {
            // Form fraud module and send first request call to server.
            NovalnetUtilities::fraudModuleRequest($this, $order);
        }
    }

    /**
     * This event is fired to send request to server.
     * @param  object $order current order object
     * @param  object $methods current payment method object
     * @param  string $method_id current payment method id
     * @return void
     */
    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);
        $paymentParams = NovalnetUtilities::doFormPaymentParameters($this, $order);
        if (!NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get') && !NovalnetUtilities::handleSession('error_guarantee_' . $order->order_payment_id, '', 'get') && NovalnetValidation::fraudModuleCheck($this, NovalnetUtilities::doFormatAmount($order->order_full_price), $order->cart->billing_address->address_country->zone_code_2) && NovalnetUtilities::handleSession('tid_' . $order->order_payment_id, '', 'get') != '')
        {
            NovalnetValidation::validateAmount($order->order_full_price, $order->order_payment_id, $order->order_payment_method);
            // Send fraudmodule secondcall request payment parameters.
            $response = NovalnetUtilities::fraudModuleSecondCall($order, $this);
            $fraudModule = NovalnetUtilities::handleSession('fraud_module_response_'.$order->order_payment_id,'','get');
        }
        else
        {
            // Form server request payment parameters without enable fraudmodule.
            $paymentParams = self::getInvoiceParams($paymentParams);

            // Form the guarantee payment params for Invoice payment.
            if ($this->payment_params->guaranteeEnable == 1 && NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get') && !(empty(NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get')) || (time() < strtotime('+18 years', strtotime(NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get'))))))
            {
                 $paymentParams = self::getInvoiceParams($paymentParams, true);
            }
            // If guarantee force is disabled to show the message.
            elseif (NovalnetUtilities::handleSession('error_guarantee_' . $order->order_payment_id, '', 'get'))
            {
                NovalnetUtilities::handleSession(true, 'dob_field_' . $order->order_payment_id, 'set');
            }

            // Checks Invoice due date and sets into the payment request.
            if (!empty($this->payment_params->paymentDuration))
                $paymentParams['due_date'] = date('Y-m-d', strtotime('+' . $this->payment_params->paymentDuration . ' days'));
            if (empty($this->payment_params->pinbyCallback))
                $paymentParams['invoice_ref'] = 'BNR-' . $GLOBALS['configDetails']->productId . '-' . $paymentParams['order_no'];
            NovalnetUtilities::handleSession($paymentParams['key'], 'payment_key' . $order->order_payment_id, 'set');

            $response = NovalnetUtilities::performHttpsRequest(http_build_query($paymentParams));
        }
        // Handle server response for Invoice payment.
        NovalnetUtilities::handleDirectPaymentCompletion($order, $response, $methods[$method_id], $this, (!empty($fraudModule) ? $fraudModule : false));
    }

    /**
     * This event is fired to save the payment description and manage payment logo in backend.
     *
     * @param  object $element current payment element object
     * @return string
     */
    public function onPaymentConfigurationSave(&$element)
    {
        $element->payment_description = !empty($_POST['payment_description']) ? $_POST['payment_description'] : JText::_('HKPAYMENT_NOVALNET_PAYMENT_INVOICE_DESC');

        // Validate guarantee fields.
        if ($element->payment_params->guaranteeEnable)
        {
            NovalnetValidation::validateGuaranteeFields($element);
        }
        return true;
    }

    /**
     * Get default payment configuration values
     *
     * @param  object $element current payment element object
     * @return void
     */
    public function getPaymentDefaultValues(&$element)
    {
        foreach (array('payment_name' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_INVOICE'), 'payment_description' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_INVOICE_DESC'), 'payment_images' => 'novalnet_invoice') as $configKey => $configValue)
        {
            $element->$configKey = $configValue;
        }
        foreach (array('testmode' => false, 'payment_currency' => true, 'transactionBeforeStatus' => 'created', 'transactionEndStatus' => "confirmed", 'guaranteeEnable' => false, 'guaranteeStatus' => 'created', 'guaranteeMethod' => true) as $pluginKey => $pluginValue)
        {
            $element->payment_params->$pluginKey = $pluginValue;
        }
    }

	/**
     * Get payment parameters
     *
     * @param  array $paymentParams payment parameters
     * @param  boolean $guarantee boolean of guarantee or not
     * @return array
     */
    public function getInvoiceParams($paymentParams, $guarantee =  false)
    {
        $paymentParams['key'] = '27';
        $paymentParams['payment_type'] = 'INVOICE_START';
        $paymentParams['invoice_type'] = 'INVOICE';
        if ($guarantee)
        {
            // Appending parameters for guarantee payment.
            $paymentParams['key'] = '41';
            $paymentParams['payment_type'] = 'GUARANTEED_INVOICE';
            $birth_date = NovalnetUtilities::handleSession('birthdate_' . $this->payment->payment_id, '', 'get');
            $paymentParams['birth_date'] = date('Y-m-d', strtotime($birth_date));
        }
        return $paymentParams;
    }

}
