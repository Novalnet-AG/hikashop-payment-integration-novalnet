<?php
/**
* This script is used for Novalnet Global Configuration
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
* Script : novalnet_payments.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_vendorscript.php';
require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_validation.php';
require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'api' . DS . 'novalnet_extension.php';

// Loads language
$lang = JFactory::getLanguage();
$lang->load('plg_hikashoppayment_novalnet_payments', JPATH_ADMINISTRATOR);
$document = JFactory::getDocument();
$document->addScript(JURI::root() . '/plugins/hikashoppayment/novalnet_payments/assets/js/novalnet_api.js');


/**
 * Novalnet payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_Payments extends NovalnetVendorScript
{
    /**
     * @var boolean $multiple
     */
    public $multiple = true;

	/**
     * To define the payment $name
     *
     * @var   string
     */
    public $name = '';

    var $pluginConfig = array(
		'novalnet_payment' 			=> array('', 'html', ''),
		'novalnetProductActivationKey' => array('HKPAYMENT_NOVALNET_ADMIN_PRODUCT_ACTIVATION_KEY', 'input'),
		'vendor' 					=> array('HKPAYMENT_NOVALNET_ADMIN_MERCHANT_ID', 'input'),
		'authCode' 					=> array('HKPAYMENT_NOVALNET_ADMIN_AUTH_CODE', 'input'),
		'productId' 				=> array('HKPAYMENT_NOVALNET_ADMIN_PRODUCT_ID', 'input'),
		'tariffId' 					=> array('HKPAYMENT_NOVALNET_ADMIN_TARIFF_ID', 'list', array()),
		'tariffType' 				=> array('Tariff Type', 'input'),
		'selectedTariff' 			=> array('selected Tariff Type', 'input'),
		'keyPassword' 				=> array('HKPAYMENT_NOVALNET_ADMIN_PASSWORD', 'input'),
		'orderLabel'                => array('HKPAYMENT_ADMIN_STATUS_MANAGEMENT_ONHOLD_TITLE', 'label'),
		'transactionConfirmStatus' 	=> array('HKPAYMENT_NOVALNET_STATUS_ONHOLD_CONFIRM', 'orderstatus'),
		'transactionCancelStatus' 	=> array('HKPAYMENT_NOVALNET_STATUS_ONHOLD_CANCELLED', 'orderstatus'),
		'callbackLabel'             => array('HKPAYMENT_ADMIN_CALLBACK_TITLE', 'label'),
		'callbackTestmode' 			=> array('HKPAYMENT_NOVALNET_ADMIN_TEST_MODE', 'boolean', '0'),
		'callbackMail' 				=> array('HKPAYMENT_NOVALNET_ADMIN_MAIL', 'boolean', '0'),
		'mailTo' 					=> array('HKPAYMENT_NOVALNET_ADMIN_MAIL_TO', 'input'),
		'mailBcc' 					=> array('HKPAYMENT_NOVALNET_ADMIN_MAIL_BCC', 'input'),
		'notifyUrl' 				=> array('HKPAYMENT_NOVALNET_ADMIN_NOTIFY_URL', 'input'),
		'lang' 						=> array('lang', 'input')
    );

    /**
     * To hide the payment if global configuration not configured.
     *
     * @param  object $subject current subject payment params
     * @param  array $config current payment method object
     */
    public function __construct(&$subject, $config)
    {
        $this->pluginConfig['novalnet_payment'][2] = JText::_('HKPAYMENT_NOVALNET_ADMIN_DESCRIPTION');
        parent::__construct($subject, $config);
    }

    /**
     * This event is fired to save the payment description and manage payment logo in backend.
     *
     * @param  object $element current payment element object
     * @return void
     */
    public function onPaymentConfigurationSave(&$element)
    {
        parent::onPaymentConfiguration($element);

        // Validate Merchant configuration fileds
        NovalnetValidation::validateConfigurationFields($element);

        if (empty($element->payment_params->notifyUrl))
        {
            $element->payment_params->notifyUrl = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=novalnet_payments';
        }
        $lang = JFactory::getLanguage();
        $element->payment_params->lang = strtoupper(substr($lang->getTag(), 0, 2));
    }

    /**
     * Event triggered Handler for order extensions
     *
     * @param  object $history get the order history object
     * @return void
     */
    public function onHistoryDisplay(&$history)
    {
        $postParam = JRequest::get();
        if ($postParam['layout'] == 'show')
            echo NovalnetExtension::getNovalnetAPI($history[0]->history_order_id);
    }

    /**
     * Get default payment configuration values
     *
     * @param  object $element current payment element object
     * @return void
     */
    public function getPaymentDefaultValues(&$element)
    {
        $element->payment_name = JText::_('HKPAYMENT_NOVALNET_PAYMENT_GLOBAL');
        foreach (array('callbackTestmode' => false, 'callbackMail' => false, 'transactionEndStatus' => 'created', 'transactionConfirmStatus' => "confirmed", 'transactionCancelStatus' => 'cancelled', 'notifyUrl' => HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=novalnet_payments') as $pluginKey => $pluginValue)
        {
            $element->payment_params->$pluginKey = $pluginValue;
        }
        return true;
    }
}
