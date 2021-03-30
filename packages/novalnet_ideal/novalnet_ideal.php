<?php
/**
* This script is used for iDEAL payment
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
* Script : novalnet_ideal.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';

// Loads JS
$document = JFactory::getDocument();
$document->addScript(JURI::root() . '/plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_utilities.js');

/**
 * Novalnet iDEAL payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_Ideal extends hikashopPaymentPlugin
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
    public $name = 'novalnet_ideal';
    var $pluginConfig = array(
		'testmode' 				=> array('HKPAYMENT_NOVALNET_ADMIN_TEST_MODE', 'boolean', '0'),
		'notificationMsg' 		=> array('HKPAYMENT_NOVALNET_NOTIFICATION_TO_ENDUSER', 'input'),
		'transactionEndStatus' 	=> array('HKPAYMENT_NOVALNET_STATUS_COMPLETE', 'orderstatus')
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
        $lang->load('plg_hikashoppayment_novalnet_ideal', JPATH_ADMINISTRATOR);
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
        // Display the payment description in payment page.
        $method->payment_description = NovalnetUtilities::getPaymentNotifications($method);
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
        // Prepares ideal parameters for payment processing
        $this->vars = NovalnetUtilities::doFormPaymentParameters($this, $order);
        $this->vars['key'] = '49';
        $this->vars['payment_type'] = 'IDEAL';
        NovalnetUtilities::handleSession($this->vars['key'], 'payment_key' . $order->order_payment_id, 'set');
        return $this->showPage('end');
    }

    /**
     * This event is fired to save the payment description and manage payment logo in backend.
     *
     * @param  object $element current payment element object
     * @return string
     */
    public function onPaymentConfigurationSave(&$element)
    {
        $element->payment_description = !empty($_POST['payment_description']) ? $_POST['payment_description'] : JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC');
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
        foreach (array('payment_name' => JText::_('HKPAYMENT_NOVALNET_PAYMENT_IDEAL'), 'payment_description' => JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC'), 'payment_images' => 'novalnet_ideal') as $configKey => $configValue)
        {
            $element->$configKey = $configValue;
        }
        foreach (array('testmode' => false, 'payment_currency' => true, 'transactionEndStatus' => "confirmed") as $pluginKey => $pluginValue)
        {
            $element->payment_params->$pluginKey = $pluginValue;
        }
    }

    /**
     * Handle the payment response.
     *
     * @param   object $statuses current payment status
     * @return array
     */
    public function onPaymentNotification(&$statuses)
    {
        // Handle the redirect payment response.
        return NovalnetUtilities::handleRedirectPaymentCompletion($this);
    }
}
