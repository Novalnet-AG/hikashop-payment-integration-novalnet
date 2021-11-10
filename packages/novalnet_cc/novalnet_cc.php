<?php
/**
* This script is used for Credit Card
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
* Script : novalnet_cc.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_cc' . DS . 'tmpl' . DS . 'novalnet_cc_form.php';
require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';

// Loads language
$lang = JFactory::getLanguage();
$lang->load('plg_hikashoppayment_novalnet_cc', JPATH_ADMINISTRATOR);

// Loads JS
$document = JFactory::getDocument();
$document->addScript(JURI::root() . '/plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_utilities.js');

/**
 * Credit Card payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_CC extends hikashopPaymentPlugin
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
    public $name = 'novalnet_cc';

    var $pluginConfig = array(
		'testmode' 				=> array('HKPAYMENT_NOVALNET_ADMIN_TEST_MODE', 'boolean', '0'),
		'shoppingType' 			=> array('HKPAYMENT_NOVALNET_SHOPPING_TYPE', 'list',array('' => 'HKPAYMENT_NOVALNET_NONE', 'ZERO_AMOUNT_BOOKING' => 'HKPAYMENT_NOVALNET_ZERO_AMOUNT_BOOKING', 'ONECLICK_SHOPPING' => 'HKPAYMENT_NOVALNET_ONE_CLICK_SHOPPING')),
		'cc3d' 					=> array('HKPAYMENT_NOVALNET_ADMIN_CC_3D', 'boolean', '0'),
		'cc3d_force' 			=> array('HKPAYMENT_NOVALNET_ADMIN_CC_3D_FORCE', 'boolean', '0'),
		'transactionEndStatus' 	=> array('HKPAYMENT_NOVALNET_STATUS_COMPLETE', 'orderstatus'),
		'onholdAction' 			=> array('HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_ACTION', 'list', array('CAPTURE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_CAPTURE', 'AUTHORIZE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_AUTHORIZE')),
		'onHold' 				=> array('HKPAYMENT_NOVALNET_CC_ON_HOLD', 'input'),
		'notificationMsg' 		=> array('HKPAYMENT_NOVALNET_NOTIFICATION_TO_ENDUSER', 'input'),
		'guaranteeLabel' 		=> array('HKPAYMENT_NOVALNET_CC_HOLDER_CSS_IFRAME', 'label'),
		'inputLabel' 			=> array('HKPAYMENT_NOVALNET_CC_INPUT_LABEL', 'input'),
		'inputStyle' 			=> array('HKPAYMENT_NOVALNET_CSS_INPUT', 'input'),
		'cssText' 				=> array('HKPAYMENT_NOVALNET_CSS_TEXT', 'input'),
    );

    /**
     * To hide the payment if global configuration not configured.
     *
     * @param object $subject current subject payment params
     * @param array  $config  current payment method  object
     */
    public function __construct(&$subject, $config)
    {
        if (!NovalnetUtilities::globalConfig())
            return false;
        $paymentId = NovalnetUtilities::selectQuery(array('table_name' => array('#__hikashop_payment'), 'column_name' => array('payment_id'), 'condition' => array("payment_type='novalnet_payments'"),));

        parent::__construct($subject, $config);
    }

    /**
     * This event is fired to display the payment description in front end.
     *
     * @param  object $method method variable
     * @return void
     */
    public function needCC(&$method)
    {
        // Get post values
        $postParams = JRequest::get('post');

        // Get the user details
        $user   = hikashop_loadUser(true);
        $configDetails = NovalnetUtilities::getMerchantConfig();
        foreach (array('payment_ref_' . $method->payment_id, 'pan_hash', 'nn_cc_uniqueid','cc_masked_value', 'oneclick_cc') as $key)
        {
            if(!empty($postParams[$key])) {
				NovalnetUtilities::handleSession($postParams[$key], '', 'clear');
				NovalnetUtilities::handleSession($postParams[$key], $key, 'set');
			}
        }

        $html = '';
        $method->payment_description = NovalnetUtilities::getPaymentNotifications($method);

        $displayBlock = '';
        // If pan hash value is present
        if (empty($postParams['pan_hash']) || empty($postParams['nn_cc_uniqueid']))
        {
            $displayBlock = 'style="width:71%;"';
        }
        else
        {
            if (isset($postParams['hikashop_payment']) && ($postParams['hikashop_payment'] == 'novalnet_cc_' . $method->payment_id))
            {
                $displayBlock = 'style=display:none';
                $html.= JText::_('HKPAYMENT_NOVALNET_CC_ORDER_MSG');
            }
        }
        $html.= '<div id="cc_form" ' . $displayBlock . '>';
        $orderMsg = '';
        $block = 'style=display:block;width:71%';
        $maskedPatterns = NovalnetUtilities::getMaskedDetails($user->user_id, $method->payment_id);

        if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING' && $maskedPatterns && !$method->payment_params->cc3d && !$method->payment_params->cc3d_force)
        {
            // Show the masking form
            $html = '<div id="masked_form"' . $block . '>' . NovalnetCreditCardForm::renderMaskedForm($maskedPatterns, $method, $configDetails, NovalnetUtilities::getMaskedDetails($user->user_id, $method->payment_id, true)) . '</div>' . $orderMsg;
        }
        else
        {
			$html .= '<input type="hidden" id="maskedDetails" value="0">';
            $html .= NovalnetCreditCardForm::renderIframeForm($method, $configDetails, '');
            
        }
        $html.= '</div>';
        $html.= '<script type="text/javascript">
                    jQuery(document).ready(function() {
                        if(jQuery("input[class=hikashop_checkout_payment_radio]:checked").val() == '.$method->payment_id.') {
                            jQuery(".hikashop_checkout_payment_submit").hide();
                        }
            });
            </script>';

        $method->custom_html = '';
        $method->custom_html .= $html;
        $method->custom_html .= '<script type="text/javascript" src="' . HIKASHOP_LIVE . 'plugins/hikashoppayment/novalnet_cc/assets/js/novalnet_cc.js"></script>';
        $method->custom_html .= '<script type="text/javascript" src="' . HIKASHOP_LIVE . 'plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_formscript.js"></script>';
        $method->custom_html .= NovalnetUtilities::noScriptMessage('cc_form');
    }

    /**
     * This event is fired onbefore order creation to validate payment params.
     *
     * @param object $order current order object
     * @param object $do current do object
     * @return string
     */
    public function onBeforeOrderCreate(&$order, &$do)
    {
        if (parent::onBeforeOrderCreate($order, $do))
            return true;
        // Validate basic configuration parameters.
        NovalnetValidation::doNovalnetValidation($this);
    }

    /**
     * This event is fired to send request to server.
     *
     * @param  object $order current order object
     * @param  object $methods current payment method object
     * @param  string $method_id current payment method id
     * @return string
     */
    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);

        // Form the payment parameter.
        $paymentParams = NovalnetUtilities::doFormPaymentParameters($this, $order);
        $paymentParams['key'] = '6';
        $paymentParams['payment_type'] = 'CREDITCARD';
        $panHash = NovalnetUtilities::handleSession('pan_hash', '', 'get');

        // If payment ref not set, form pan_hash and unique_id params
        if (!empty($panHash))
        {
            $paymentParams['pan_hash'] = $panHash;
            $paymentParams['unique_id'] = NovalnetUtilities::handleSession('nn_cc_uniqueid', '', 'get');
        }
        $paymentParams['nn_it'] = 'iframe';
        NovalnetUtilities::handleSession($paymentParams['key'], 'payment_key' . $order->order_payment_id, 'set');

        if (!$this->payment_params->cc3d && !$this->payment_params->cc3d_force) {

			// One click payment enable for Credit Card.
			$paymentRef = NovalnetUtilities::getMaskedDetails($order->cart->user_id, $methods[$method_id]->payment_id, true);
			if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && NovalnetUtilities::handleSession('oneclick_cc', '', 'get') == 'on')
			{
				$paymentParams['create_payment_ref'] = 1;
			}
			if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && !$paymentParams['pan_hash'] && empty(NovalnetUtilities::handleSession('oneclick_cc', '', 'get')) &&  NovalnetUtilities::handleSession('cc_masked_value', '', 'get') == '1') {
				$paymentParams['payment_ref'] = $paymentRef;
			}
		}
        elseif ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && empty(NovalnetUtilities::handleSession('oneclick_cc', '', 'get')) && (NovalnetUtilities::handleSession('cc_masked_value', '', 'get')  == '0'))
        {
                unset($paymentParams['payment_ref']);
                unset($paymentParams['create_payment_ref']);
        }
        if (isset($paymentParams['create_payment_ref']))
        {
                NovalnetUtilities::handleSession('1', 'create_payment_ref' . $order->order_payment_id, 'set');
        }

        // If cc3d mode is enabled
        if ($this->payment_params->cc3d || $this->payment_params->cc3d_force)
        {
            unset($paymentParams['user_variable_0']);
            $this->vars = $paymentParams;
            if ($this->payment_params->cc3d)
            $this->vars['cc_3d'] = 1;
            return $this->showPage('end');
        }
        $response = NovalnetUtilities::performHttpsRequest(http_build_query($paymentParams));
        // Handle the paygate response
         NovalnetUtilities::handleDirectPaymentCompletion($order, $response, $methods[$method_id], $this);
    }

    /**
     * This event is fired to save the payment description and manage payment logo in backend.
     *
     * @param  object $element current payment element object
     * @return string
     */
    public function onPaymentConfigurationSave(&$element)
    {
        $element->payment_description = !empty($_POST['payment_description']) ? $_POST['payment_description'] : JText::_('HKPAYMENT_NOVALNET_CC_DESC');
        return true;
    }

    /**
     * Get default payment configuration values
     *
     * @param  object $element current payment element object
     * @return none
     */
    public function getPaymentDefaultValues(&$element)
    {
        foreach (array('payment_name' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_CREDIT_CARD'), 'payment_description' => JText::_('HKPAYMENT_NOVALNET_CC_DESC'), 'payment_images' => 'novalnet_cc') as $configKey => $configValue)
        {
            $element->$configKey = $configValue;
        }
        foreach (array('testmode' => false, 'payment_currency' => true, 'transactionEndStatus' => "confirmed", "cssText" => ".label-group{font-size:13px;padding-top:9px;font-family: Helvetica Neue, Helvetica, Arial, sans-serif;color: #333;}.input-group input{font-size: 13px;border: 1px solid #ccc;border-radius:3px;box-shadow: inset 0 1px 1px rgba(0,0,0,0.075);color: #555;}.input-group input:focus{border-color: rgba(82,168,236,0.8);outline:0;box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(82,168,236,.6);}") as $pluginKey => $pluginValue)
        {
            $element->payment_params->$pluginKey = $pluginValue;
        }
        return true;
    }

    /**
     * Handle the payment response.
     *
     * @param object $statuses current payment status
     * @return array
     */
    public function onPaymentNotification(&$statuses)
    {
        // Handle the redirect payment response for cc3d mode is enabled.
        return NovalnetUtilities::handleRedirectPaymentCompletion($this);
    }
}
