<?php
/**
* This script is used for PayPal payment
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
* Script : novalnet_paypal.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';

// Loads JS
$document = JFactory::getDocument();
$document->addScript(JURI::root() . '/plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_utilities.js');

/**
 * Novalnet PayPal payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_Paypal extends hikashopPaymentPlugin
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
    public $name = 'novalnet_paypal';
    var $pluginConfig = array(
		'testmode' 					=> array('HKPAYMENT_NOVALNET_ADMIN_TEST_MODE', 'boolean', '0'),
		'shoppingType' 				=> array('HKPAYMENT_NOVALNET_SHOPPING_TYPE', 'list', array('' => 'HKPAYMENT_NOVALNET_NONE',
		'ZERO_AMOUNT_BOOKING' 		=> 'HKPAYMENT_NOVALNET_ZERO_AMOUNT_BOOKING', 'ONECLICK_SHOPPING' => 'HKPAYMENT_NOVALNET_ONE_CLICK_SHOPPING')),
		'notificationMsg' 			=> array('HKPAYMENT_NOVALNET_NOTIFICATION_TO_ENDUSER', 'input'),
		'onholdAction' 				=> array('HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_ACTION', 'list', array('CAPTURE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_CAPTURE', 'AUTHORIZE' => 'HKPAYMENT_NOVALNET_ADMIN_ON_HOLD_AUTHORIZE')), 'onHold' => array('HKPAYMENT_NOVALNET_PAYPAL_ON_HOLD', 'input'),
		'transactionBeforeStatus' 	=> array('HKPAYMENT_NOVALNET_PAYPAL_STATUS', 'orderstatus'),
		'transactionEndStatus' 		=> array('HKPAYMENT_NOVALNET_STATUS_COMPLETE', 'orderstatus'),
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
        $lang->load('plg_hikashoppayment_novalnet_paypal', JPATH_ADMINISTRATOR);
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
        // Get the post params.
        $postParams = JRequest::get('post');

       // Get the user details using loadUser function.
        $user   = hikashop_loadUser(true);

        // Display the payment description in payment page.
        $method->payment_description = NovalnetUtilities::getPaymentNotifications($method);

        foreach(array('paypal_masked_value', 'oneclick_paypal', 'paypal_oneclick', 'payment_ref_'. $method->payment_id) as $key)
        {
            if(!empty($postParams[$key]))
            NovalnetUtilities::handleSession($postParams[$key], $key ,'set');
        }
        $displayBlock = "";
        $displayBlock = 'style=display:block';
        $html = "";
        $html.= '<input type="hidden" id="paypal_paymentid" name="paypal_paymentid" value="' . $method->payment_id . '" >';
        $html.= '<input type="hidden" id="paypal_onclick_value" name="paypal_onclick_value" value="' . $method->payment_params->shoppingType . '"/>   ';
        $html.= '<input type="checkbox" id="oneclick_paypal" name="oneclick_paypal" >';
        $html.= '<p class="check_paypal">' . JText::_('HKPAYMENT_NOVALNET_PAYMENT_PAYPAL_SAVE_CARD') . '</p>';
        $html.= '<script type="text/javascript">
                    jQuery(document).ready(function() {
                        var payment_id = jQuery("#paypal_paymentid").val();
                        jQuery("#oneclick_paypal,.check_paypal").hide();
                        if (jQuery("#paypal_onclick_value").val() == "ONECLICK_SHOPPING") {
                        jQuery("#oneclick_paypal,.check_paypal").show();
                        }
                        if(jQuery("#novalnet_paypal_oneclick").val() == 1){
                            jQuery("#oneclick_paypal,.check_paypal").hide();
                        }
                        if(jQuery("input[class=hikashop_checkout_payment_radio]:checked").val() == payment_id) {
                            jQuery(".hikashop_checkout_payment_submit").hide();
                        }
            });
            </script>';

        // Hide the form details in checkout page.
        $paymentName = isset($postParams['hikashop_payment']) ? $postParams['hikashop_payment'] : '';
        if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING' && NovalnetUtilities::handleSession('payment_ref_'.$method->payment_id ,'', 'get') && $paymentName == 'novalnet_paypal_'.$method->payment_id )
        {
                $displayBlock = 'style=display:none';
                $html.= JText::_('HKPAYMENT_NOVALNET_PAYPAL_ORDER_MSG');
        }
        if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING')
        {
            $html.= '<div id="paypal_form" ' . $displayBlock . '>' . self::renderMaskedPaypalForm(NovalnetUtilities::getMaskedDetails($user->user_id, $method->payment_id), $method) . '</div>';
        }
        $method->custom_html = $html;
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

        $this->vars = NovalnetUtilities::doFormPaymentParameters($this, $order);
        $this->vars['key'] = '34';
        $this->vars['payment_type'] = 'PAYPAL';
        
        $shippingDetails = NovalnetUtilities::getShippingDetails($order);
        
        if(!empty($shippingDetails))
         $this->vars = array_merge(array_filter($shippingDetails), $this->vars);
        
        // One click payment enable for Paypal.
        $checkPaypal = NovalnetUtilities::handleSession('oneclick_paypal', '', 'get');
        $paypalMaskedValue = NovalnetUtilities::handleSession('paypal_masked_value', '', 'get');
        $paymentRef = NovalnetUtilities::getMaskedDetails($order->cart->user_id, $methods[$method_id]->payment_id, true);

        if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && $checkPaypal == 'on')
        {
            $this->vars['create_payment_ref'] = 1;
        }
        if ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && empty($checkPaypal) && $paypalMaskedValue== '1')
        {
            $this->vars['payment_ref'] = $paymentRef;
        }
        elseif ($methods[$method_id]->payment_params->shoppingType == 'ONECLICK_SHOPPING' && empty($checkPaypal) && ($paypalMaskedValue == '0'))
        {
                unset($this->vars['payment_ref']);
                unset($this->vars['create_payment_ref']);
        }

        NovalnetUtilities::handleSession($this->vars['key'], 'payment_key' . $order->order_payment_id, 'set');
        if (isset($this->vars['create_payment_ref']))
        {
                NovalnetUtilities::handleSession('1', 'create_payment_ref' . $order->order_payment_id, 'set');
        }
        if (!isset($this->vars['payment_ref']))
        {
            return $this->showPage('end');
        }
        // Get the request params if oneclick shopping is enabled.
        $paymentParams = NovalnetUtilities::handleSession('oneclick_params_' . $method_id, '', 'get');
        $paymentParams['key'] = '34';
        $paymentParams['payment_type'] = 'PAYPAL';
        $paymentParams['payment_ref'] = $paymentRef;

        $response = NovalnetUtilities::performHttpsRequest(http_build_query($paymentParams));

        // Handle server response for Paypal.
        NovalnetUtilities::handleDirectPaymentCompletion($order, $response, $methods[$method_id], $this);
    }

    /**
     * This event is fired to save the payment description and manage payment logo in backend.
     *
     * @param  object $element current payment element object
     * @return void
     */
    public function onPaymentConfigurationSave(&$element)
    {
        // Get the joomla JFactory application.
        $app = JFactory::getApplication();
        $element->payment_description = !empty($_POST['payment_description']) ? $_POST['payment_description'] : JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC');

        // If shopping type is enabled to show the notification message in back end.
        if ($element->payment_params->shoppingType != "" && ($element->payment_params->shoppingType == "ZERO_AMOUNT_BOOKING" || $element->payment_params->shoppingType == "ONECLICK_SHOPPING"))
        {
            $app->enqueueMessage(JText::_(JText::_('HKPAYMENT_NOVALNET_PAYPAL_ONCLICK_NOTIFICATION')));
        }
    }

    /**
     * Get default payment configuration values
     *
     * @param  object $element current payment element object
     * @return void
     */
    public function getPaymentDefaultValues(&$element)
    {
        foreach (array('payment_name' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_PAYPAL'), 'payment_description' => JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC'), 'payment_images' => 'novalnet_paypal') as $configKey => $configValue)
        {
            $element->$configKey = $configValue;
        }
        foreach (array('testmode' => false, 'payment_currency' => true, 'transactionEndStatus' => "confirmed", 'transactionBeforeStatus' => "created") as $pluginKey => $pluginValue)
        {
            $element->payment_params->$pluginKey = $pluginValue;
        }
    }

    /**
     * Show the masked form details if oneclick is enabled
     *
     * @param  array $maskedPatterns get the masked values
     * @param  array $method current payment params
     * @return array
     */
    public function renderMaskedPaypalForm($maskedPatterns, $method)
    {
        $displayBlock = empty($maskedPatterns['paypal_transaction_id']) ? 'display:none;' : ' ';
        if (!empty($maskedPatterns['paypal_transaction_id'])) {
        $html = '<div id = "paypal_oneclick" class = "paypal_oneclick" onclick = oneclick_paypal_process();><br/><a id="novalnet_paypal_new_acc" style="color: #095197; text-decoration: underline; cursor: pointer;font-weight: bold;">' . JTEXT::_('HKPAYMENT_NOVALNET_NEW_PAYPAL_ACCOUNT_TEXT') . '</a><br/>
        <br/><div id="novalnet_paypal_maskedform">
        <div class="table-responsive-lg">
        <table class="table table-striped" align="left"  style="width: 100%;">
            <tr>
                <td><label>' . JTEXT::_('HKPAYMENT_NOVALNET_PAYPAL_TRANSACTION_ID') . '</label></td>
                <td><input type="text" readonly value="' . $maskedPatterns['paypal_transaction_id'] . '"></td>
            </tr>
            <tr>
                <td><label>' . JTEXT::_('HKPAYMENT_NOVALNET_TRANSACTION_ID') . '</label></td>
                <td><input type="text" readonly value="' . $maskedPatterns['tid'] . '"></td>
            </tr>
            </table></div>';
        foreach (array('given_card_msg' => JText::_('HKPAYMENT_NOVALNET_GIVEN_PAYPAL_ACCOUNT_TEXT'), 'paypal_paymentid' => $method->payment_id, 'novalnet_paypal_oneclick' => 1, 'paypal_masked_value'=>1, 'paypal_oneclick' => '', 'payment_ref_'.$method->payment_id  => $maskedPatterns['tid'], 'new_account_msg' => JText::_('HKPAYMENT_NOVALNET_NEW_PAYPAL_ACCOUNT_TEXT')) as $key => $value)
        {
            $html.= '<input type="hidden" value="' . $value . '" id="' . $key . '" name="' . $key . '">';
        }
        // Load Paypal js file.
        $html.= '<script type="text/javascript" src="' . HIKASHOP_LIVE . 'plugins/hikashoppayment/novalnet_paypal/assets/js/novalnet_paypal.js"></script>';
        $html.= '<script type=text/javascript>

            if (jQuery("input[class=hikashop_checkout_payment_radio]:checked").val() == jQuery("#paypal_paymentid").val())
                jQuery(".hikashop_checkout_payment_submit").hide();
            </script>';
        }
        return $html;
    }

    /**
     * Handle the payment response.
     *
     * @param  object $statuses current payment status
     * @return array
     */
    public function onPaymentNotification(&$statuses)
    {
        // Handle the redirect payment response for Paypal.
        return NovalnetUtilities::handleRedirectPaymentCompletion($this);
    }
}
