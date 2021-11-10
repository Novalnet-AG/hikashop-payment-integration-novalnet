<?php
/**
* This script is used for Direct Debit SEPA with and without guarantee
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
* Script : novalnet_sepa.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_sepa' . DS . 'tmpl' . DS . 'novalnet_sepa_form.php';
require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';
require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_validation.php';

// Loads language
$lang = JFactory::getLanguage();
$lang->load('plg_hikashoppayment_novalnet_sepa', JPATH_ADMINISTRATOR);

// Loads JS
$document = JFactory::getDocument();
$document->addScript(JURI::root() . '/plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_utilities.js');

/**
 * Novalnet SEPA payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_Sepa extends hikashopPaymentPlugin
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
    public $name = 'novalnet_sepa';
    var $pluginConfig = array(
		'testmode' 				=> array('HKPAYMENT_NOVALNET_ADMIN_TEST_MODE', 'boolean','0'),
		'transactionEndStatus' 	=> array('HKPAYMENT_NOVALNET_STATUS_COMPLETE', 'orderstatus'),
		'paymentDuration' 		=> array('HKPAYMENT_NOVALNET_ADMIN_SEPA_DURATION', 'input'),
		'onholdAction' 			=> array('HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_ACTION', 'list',array('CAPTURE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_CAPTURE', 'AUTHORIZE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_AUTHORIZE')),
		'onHold' 				=> array('HKPAYMENT_NOVALNET_SEPA_ON_HOLD', 'input'),
		'shoppingType' 			=> array('HKPAYMENT_NOVALNET_SHOPPING_TYPE','list', array('' => 'HKPAYMENT_NOVALNET_NONE', 'ZERO_AMOUNT_BOOKING' => 'HKPAYMENT_NOVALNET_ZERO_AMOUNT_BOOKING', 'ONECLICK_SHOPPING' => 'HKPAYMENT_NOVALNET_ONE_CLICK_SHOPPING')),
		'notificationMsg' 		=> array('HKPAYMENT_NOVALNET_NOTIFICATION_TO_ENDUSER', 'input'),
		'pinbyCallback' 		=> array('HKPAYMENT_ADMIN_PIN_BY_CALL', 'list',array('' => 'HKPAYMENT_ADMIN_CALLBACK', 'CALLBACK' => 'HKPAYMENT_ADMIN_TEL', 'SMS' => 'HKPAYMENT_ADMIN_SMS')),
		'callbackAmount' 		=> array('HKPAYMENT_ADMIN_PIN_AMOUNT', 'input'),
		'guaranteeLabel' 		=> array('HKPAYMENT_NOVALNET_SEPA_PAYMENT_GUARANTEE_CONFIGURATION', 'label'),
		'guranteeRequirements' 	=> array('HKPAYMENT_NOVALNET_SEPA_PAYMENT_GUARANTEE_BASIC_REQUIRMENTS','html', ''),
		'guaranteeEnable' 		=> array('HKPAYMENT_NOVALNET_SEPA_GUARANTEE_ENABLE', 'boolean','0'),
		'minimumAmount' 		=> array('HKPAYMENT_NOVALNET_SEPA_MINIMUM_AMOUNT', 'input'),
		'guaranteeStatus' 		=> array('HKPAYMENT_NOVALNET_ADMIN_GUARANTEE_ORDER_STATUS','orderstatus'),
		'guaranteeMethod' 		=> array('HKPAYMENT_NOVALNET_SEPA_GUARANTEE_SELECT_METHOD', 'boolean','1'),
	);

    /**
     * To hide the payment if global configuration not configured.
     *
     * @param object $subject current subject payment params
     * @param array $config current payment method object
     */
    public function __construct(&$subject, $config)
    {
        if(!NovalnetUtilities::globalConfig())
           return false;
        $this->pluginConfig['guranteeRequirements'][2] = JText::_('HKPAYMENT_NOVALNET_SEPA_PAYMENT_GUARANTEE_BASIC_REQUIRMENTS_LIST');
        $paymentId = NovalnetUtilities::selectQuery(array('table_name' => array('#__hikashop_payment'),'column_name' => array('payment_id'),'condition' => array("payment_type='novalnet_payments'"),));

        parent::__construct($subject, $config);
    }

    /**
     * This event is fired to display the payment based on the configuration.
     *
     * @param  object $order order object
     * @param  object $methods current payment methods
     * @param  object $usable_methods current usable payment methods
     * @return string
     */
    public function onPaymentDisplay(&$order, &$methods, &$usable_methods)
    {
        if (!empty($methods))
        {
            foreach ($methods as $method)
            {
                if ($method->payment_type != 'novalnet_sepa' || !$method->enabled)
                {
                    continue;
                }

                if (!empty($method->payment_zone_namekey))
                {
                    $zone_class = hikashop_get('class.zone');
                    $zones = $zone_class->getOrderZones($order);

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
     * @param  object $method method object
     * @param  object $order order object
     * @return void
     */
    public function needCC(&$method, $order)
    {
        // Get post params.
        $postParams          = JRequest::get('post');
        // Load the user detail using loadUser function.
        $user                 = hikashop_loadUser(true);

        // Get the user Id.
        $userId           = !empty($user->user_id) ? $user->user_id : '';
        $customerDetails     = NovalnetUtilities::loadAddress('billing');
        $maskedPatterns = NovalnetUtilities::getMaskedDetails($userId, $method->payment_id);

        // Get the customer name.
        $holderName          = (isset($customerDetails->address_firstname)) ? $customerDetails->address_firstname . ' ' : '';
        $holderName         .= (isset($customerDetails->address_lastname)) ? $customerDetails->address_lastname : '';

        foreach (array('birthdate_' . $method->payment_id, 'pin_' . $method->payment_id, 'new_pin_' . $method->payment_id, 'pinby_tel_' . $method->payment_id,'telephone_field_' . $method->payment_id,'pin_field_'. $method->payment_id,'sepa_oneclick','payment_ref','check_sepa','sepa_masked_value') as $key)
        {
            if(!empty($postParams[$key]))
            NovalnetUtilities::handleSession($postParams[$key], $key, 'set');
        }
        $amount = NovalnetUtilities::doFormatAmount($order->full_total->prices[0]->price_value_with_tax);

        $displayBlock = $sepaFormHide = "";

        $nnSepaVariable = array(
            'nn_sepa_owner' => trim($postParams['nnsepa_holder_' . $method->payment_id]),
            'iban' 			=> trim($postParams['nnsepa_account_number_' . $method->payment_id]),
        );

        // Serialize sepa values and stored in session.
        if (!empty($nnSepaVariable['iban'])) {
            NovalnetUtilities::handleSession(serialize($nnSepaVariable), 'SEPAvariable', 'set');
        }

        if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING')
        {
            NovalnetUtilities::handleSession('0', 'one_click_' . $method->payment_id, 'set');
            NovalnetUtilities::handleSession($nnSepaVariable['iban'], 'iban' . $method->payment_id, 'set');
        }

        if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING')
        {
            if (!NovalnetUtilities::handleSession('iban' . $method->payment_id, '', 'get'))
            {
                NovalnetUtilities::handleSession('1', 'one_click_' . $method->payment_id, 'set');
            }
        }
        $html = '';
        $method->payment_description  = NovalnetUtilities::getPaymentNotifications($method);
        $config =& hikashop_config();

        // Check the sepa values to show the sepa form.
        if (empty($nnSepaVariable['nn_sepa_owner']) || empty($nnSepaVariable['iban']))
        {
            $displayBlock  = 'style=display:block;height:' . ((NovalnetUtilities::handleSession('tid_' . $method->payment_id, '', 'get') == '') ? '100%' : '100px');
            $sepaFormHide = false;
        }
        else
        {
            if (isset($postParams['hikashop_payment']) && ($postParams['hikashop_payment'] == 'novalnet_sepa_' . $method->payment_id))
            {
                $displayBlock  = 'style=display:none';
                $sepaFormHide = true;
                $html .= JText::_('HKPAYMENT_NOVALNET_SEPA_ORDER_MSG');
            }
        }

        // Form the sepa values in array.
        $sepaFormValues    = array(
            'payment_id'   => $method->payment_id,
            'name'         => html_entity_decode($holderName),
            'country_code' => isset($customerDetails->address_country->zone_code_2) ? $customerDetails->address_country->zone_code_2 : '',
        );
        $formDisplay = ($sepaFormHide) ? 'style=display:none' : $displayBlock;
        $html .= '<div id="sepa_form" ' . $formDisplay . '>';

        if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING' && ($maskedPatterns = NovalnetUtilities::getMaskedDetails($userId,$method->payment_id)))
        {
                $orderMsg = "";
                $block = 'style=display:block';

            // Get the payment refernce transaction id.
            $tid = NovalnetUtilities::handleSession('tid_' . $method->payment_id, '', 'get');

            // Show the masked form
            if (empty($tid))
            {
                if (empty($nnSepaVariable['iban']) && !isset($postParams['payment_ref']) || isset($postParams['hikashop_payment']))
                {
                    $html .= '<div id="masked_form"' . $block . '>' . novalnetSepaForm::renderMaskedForm($sepaFormValues, $maskedPatterns,  $amount, $method, (NovalnetUtilities::getMaskedDetails($userId, $method->payment_id, true)), $order) . '</div>' . $orderMsg;
                }
            }
            else
            {
                if (empty($nnSepaVariable['iban']))
                {
                    $html .= novalnetSepaForm::sepaFormDisplay($sepaFormValues, $method, $amount, 'block', $order);
                }
            }
        }
        else
        {
            if (empty($nnSepaVariable['iban']))
            {
                $html .= novalnetSepaForm::sepaFormDisplay($sepaFormValues, $method, $amount, 'block', $order);
            }
        }
        $display = 'style=display:block';
        if (NovalnetUtilities::handleSession('birthdate_' . $method->payment_id, '', 'get') == ''
            && $method->payment_params->guaranteeEnable)
        {
            $displayBlock = 'style=display:block;height:' . ((NovalnetUtilities::handleSession('tid_' . $method->payment_id, '', 'get') == '') ? '100%' : '100px');
        }
        else
        {
            // Get the current selected payment name.
            $paymentName = isset($postParams['hikashop_payment']) ? $postParams['hikashop_payment'] : '';
            if ($paymentName == 'novalnet_sepa_' . $method->payment_id)
            {
                // Otherwise none.
                $display = 'style=display:none';
                $method->custom_html = JText::_('HKPAYMENT_NOVALNET_SEPA_ORDER_MSG');
            }
        }

        // Check fraudmodule is enabled or not.
        if (NovalnetUtilities::handleSession('tid_' . $method->payment_id, '', 'get') != '')
        {
            NovalnetUtilities::handleSession(1, 'non_guarantee_with_fraudcheck_' . $method->payment_id, 'set');
            $html .= '<br/><div id="fraud_module_field"' . $displayBlock . '>' . NovalnetUtilities::pinByCallCheck($method, $order) . '</div>';
        }

        $html .= '</div>';
        $method->custom_html = '';
        $method->custom_html .= $html;
        $method->custom_html .= '<script type="text/javascript" src="' . HIKASHOP_LIVE . 'plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_formscript.js"></script>';
        $method->custom_html .= '<script type="text/javascript" src="' . HIKASHOP_LIVE . 'plugins/hikashoppayment/novalnet_sepa/assets/js/novalnet_sepa.js"></script>';
        $method->custom_html .= NovalnetUtilities::noScriptMessage('sepa_form');
    }

    /**
     * This event is fired onbefore order creation to validate payment params.
     *
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
        // Validate basic params.
        NovalnetValidation::doNovalnetValidation($this);

        if ($this->payment_params->guaranteeEnable
            && NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get'))
        {
            $birthDate = NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get');

			if ($birthDate)
			{
				if (NovalnetValidation::validateAge($birthDate))
				{
					NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'clear');
					JError::raiseWarning(500, JText::_('HKPAYMENT_NOVALNET_AGE_VALIDATION'));
					$this->app->redirect(NovalnetUtilities::redirectUrl());
					return false;
				}
			}
			else
			{
				NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'clear');
				JError::raiseWarning(500, JText::_('HKPAYMENT_NOVALNET_DOB'));
				$this->app->redirect(NovalnetUtilities::redirectUrl());

				return false;
			}
        }

        if (NovalnetUtilities::handleSession('tid_' . $order->order_payment_id, '', 'get') == '' && NovalnetValidation::fraudModuleCheck($order->cart->payment, NovalnetUtilities::doFormatAmount($order->cart->full_total->prices[0]->price_value_with_tax), $order->cart->billing_address->address_country->zone_code_2) && !(NovalnetUtilities::handleSession('pin_tel' . $order->order_payment_id, '', 'get')) && (($this->payment_params->guaranteeEnable != '1') || (NovalnetUtilities::handleSession('non_guarantee_with_fraudcheck_' . $order->order_payment_id, '', 'get') == '1')))
        {
             // Form fraud module and send first request call to server.
             NovalnetUtilities::fraudModuleRequest($this, $order);
        }
    }

    /**
     * This event is fired to send request to server.
     *
     * @param  object $order current order object
     * @param  object $methods current payment method object
     * @param  string $method_id current payment method id
     * @return void
     */
    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);
        $paymentParams        = NovalnetUtilities::doFormPaymentParameters($this, $order);

        // Send fraud module second call.
        if (NovalnetUtilities::handleSession('one_click_' . $order->order_payment_id, '', 'get') != 1
            && !NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get')
            && !NovalnetUtilities::handleSession('error_guarantee_' . $order->order_payment_id, '', 'get')
            && NovalnetValidation::fraudModuleCheck($this, NovalnetUtilities::doFormatAmount($order->order_full_price), $order->cart->billing_address->address_country->zone_code_2)
            && NovalnetUtilities::handleSession('tid_' . $order->order_payment_id, '', 'get') != '')
        {
            NovalnetValidation::validateAmount($order->order_full_price, $order->order_payment_id, $order->order_payment_method);
            $response = NovalnetUtilities::fraudModuleSecondCall($order, $this);
            $fraudModule = NovalnetUtilities::handleSession('fraud_module_response_'.$order->order_payment_id,'','get');
        }
        else
        {
			$sepaSessionData = unserialize(NovalnetUtilities::handleSession('SEPAvariable', '', 'get'));
			if(!empty($sepaSessionData)) {
				$paymentParams['bank_account_holder'] = html_entity_decode($sepaSessionData['nn_sepa_owner']);
				$paymentParams['iban'] = strtoupper($sepaSessionData['iban']);
			}

            if (!isset($paymentParams['payment_ref']))
            {
                $paymentParams  = array_merge($paymentParams, self::getSepaParams($paymentParams));
            }
            // Form the guarantee payment params for Invoice payment.
            if ($this->payment_params->guaranteeEnable == 1 && NovalnetUtilities::handleSession('guarantee_' . $order->order_payment_id, '', 'get') && !(empty(NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get')) || (time() < strtotime('+18 years', strtotime(NovalnetUtilities::handleSession('birthdate_' . $order->order_payment_id, '', 'get'))))))
            {
                 $paymentParams = self::getSepaParams($paymentParams, true);
            }
            // If guarantee force is disabled to show the message.
            elseif (NovalnetUtilities::handleSession('error_guarantee_' . $order->order_payment_id, '', 'get'))
            {
                NovalnetUtilities::handleSession(true, 'dob_field_' . $order->order_payment_id, 'set');
            }

            NovalnetUtilities::handleSession($paymentParams['key'], 'payment_key' . $order->order_payment_id, 'set');

            // One click payment enable for SEPA.
            if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && NovalnetUtilities::handleSession('check_sepa', '', 'get') == 'on')
            {
                $paymentParams['create_payment_ref'] = 1;
            }
            // Checks SEPA due date and sets into the payment request.
            if (!empty($methods[$method_id]->payment_params->paymentDuration))
                $paymentParams['sepa_due_date'] = date('Y-m-d', strtotime('+' . $methods[$method_id]->payment_params->paymentDuration . ' days'));

            if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && empty(NovalnetUtilities::handleSession('check_sepa', '', 'get')) && (NovalnetUtilities::handleSession('sepa_masked_value', '', 'get') == '1'))
            {
                $paymentParams['payment_ref'] = NovalnetUtilities::getMaskedDetails($order->cart->user_id, $methods[$method_id]->payment_id, true);
            }

            if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && empty(NovalnetUtilities::handleSession('check_sepa', '', 'get')) && (NovalnetUtilities::handleSession('sepa_masked_value', '', 'get') == '0'))
            {
                unset($paymentParams['payment_ref']);
            }

            if ($methods[$method_id]->payment_params->shoppingType =='ONECLICK_SHOPPING' && ($methods[$method_id]->payment_params->guaranteeEnable == 1 && $methods[$method_id]->payment_params->guaranteeMethod == 1 && !empty($methods[$method_id]->payment_params->pinbyCallback)))
            {
                unset($paymentParams['create_payment_ref']);
                unset($paymentParams['payment_ref']);
                $paymentParams = array_merge($paymentParams, NovalnetUtilities::getSepaParams($paymentParams));
            }
            if (isset($paymentParams['create_payment_ref']))
            {
                NovalnetUtilities::handleSession('1', 'create_payment_ref' . $order->order_payment_id, 'set');
            }

            // To handle the request and response with Novalnet server.
            $response = NovalnetUtilities::performHttpsRequest(http_build_query($paymentParams));
        }
        // Handle server response for SEPA payment.
         NovalnetUtilities::handleDirectPaymentCompletion($order, $response, $methods[$method_id], $this, (!empty($fraudModule) ? $fraudModule : ''));
    }

    /**
     * This event is fired to save the payment description and manage payment logo in backend.
     *
     * @param  object $element current payment element object
     * @return void
     */
    public function onPaymentConfigurationSave(&$element)
    {
        $element->payment_description = !empty($_POST['payment_description']) ? $_POST['payment_description'] : JText::_('HKPAYMENT_NOVALNET_PAYMENT_SEPA_DESC');

        // Due date validation
        if (!empty($element->payment_params->paymentDuration) && ($element->payment_params->paymentDuration < 2 || $element->payment_params->paymentDuration > 14) || $element->payment_params->paymentDuration == '0' ) {
			$message = JText::_('HKPAYMENT_NOVALNET_ADMIN_SEPA_DURATION_ERROR');
			NovalnetUtilities::backendErrorMsg($element, $message);
	    }

        // If guarantee payment is enable to validate the fields.
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
        foreach (array('payment_name' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_SEPA'),'payment_description' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_SEPA_DESC'),'payment_images' => 'novalnet_sepa' ) as $configKey => $configValue)
        {
            $element->$configKey = $configValue;
        }

        foreach (array('testmode' => false,'paymentLogo' => true, 'payment_currency' => true, 'transactionEndStatus' => "created", 'guaranteeEnable' => false, 'guaranteeStatus'=>'created', 'guaranteeMethod' => true) as $pluginKey => $pluginValue)
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
    public function getSepaParams($paymentParams, $guarantee =  false)
    {
        $paymentParams['key'] = '37';
        $paymentParams['payment_type'] = 'DIRECT_DEBIT_SEPA';
        if ($guarantee)
        {
            // Appending parameters for guarantee payment.
            $paymentParams['key'] = '40';
            $paymentParams['payment_type'] = 'GUARANTEED_DIRECT_DEBIT_SEPA';
            $birth_date = NovalnetUtilities::handleSession('birthdate_' . $this->payment->payment_id, '', 'get');
            $paymentParams['birth_date'] = date('Y-m-d', strtotime($birth_date));
        }
        return $paymentParams;
    }
}
