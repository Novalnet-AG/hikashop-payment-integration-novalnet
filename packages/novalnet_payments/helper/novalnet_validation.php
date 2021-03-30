<?php
/**
* This script is used for validating parameters
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
* Script : novalnet_validation.php
*/


defined('_JEXEC') or die('Restricted access');
/**
 * Novalnet Validation class
 *
 * @package Hikashop_Payment_Plugin
 */
class NovalnetValidation extends NovalnetUtilities
{
    /**
     * Validates the Novalnet basic configuration value
     *
     * @param  string $paymentMethod get the current payment method
     * @return string
     */
    public static function doNovalnetValidation($paymentMethod)
    {
        // Retrieves the merchant details.
        $configDetails = NovalnetUtilities::getMerchantConfig();

        // Condition to check the basic configuration and redirects throwing an error.
        if (empty($configDetails->novalnetProductActivationKey) || (!preg_match('/^\d+$/', $configDetails->vendor)) || (!preg_match('/^\d+$/', $configDetails->productId)) || (!preg_match('/^\d+$/', $configDetails->tariffId)) || empty($configDetails->authCode) || (in_array($paymentMethod->name, array('novalnet_banktransfer', 'novalnet_ideal', 'novalnet_paypal', 'novalnet_eps', 'novalnet_giropay', 'novalnet_przelewy24')) && (!$configDetails->keyPassword)))
        {
            NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_BACK_END_ERR'));
        }
        // Curl initiation not exist it shows the CURL error.
        if (!function_exists('curl_init'))
        {
            NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_CURL_ERROR'));
        }
        // Some PHP functions is not exists it shows the error.
        if (!function_exists('base64_encode') || !function_exists('base64_decode'))
        {
            NovalnetUtilities::showMessage(JText::_('NOVALNET_INVALID_PHP_PACKAGE'));
        }
        return true;
    }

    /**
     * Conditions to check the fraud module can be enabled or not
     *
     * @param  object $paymentMethod get the current payment method
     * @param  int $amount get the amount  value
     * @param  string $countryCode get the country code
     * @return boolean
     */
    public static function fraudModuleCheck($paymentMethod, $amount, $countryCode)
    {
        return ((!empty($paymentMethod->payment_params->pinbyCallback) && ($paymentMethod->payment_params->callbackAmount == 0 || $amount >= $paymentMethod->payment_params->callbackAmount)) && in_array($countryCode, array('DE', 'AT', 'CH'))) ? true : false;
    }

    /**
     * Validates the order amount
     *
     * @param  string $amount get the amount  value
     * @param  int $paymentId  get the payment id
     * @param  string $methodName get the payment name
     * @return void
     */
    public static function validateAmount($amount, $paymentId, $methodName)
    {
        // Handles the Novalnet session available in the shop.
        $total = NovalnetUtilities::handleSession('amount_' . $paymentId, '', 'get');
        // Check total amount and order amount its mismatche to display change amount error.
        if ($total != NovalnetUtilities::doFormatAmount($amount))
        {
            NovalnetUtilities::nnsessionClear($methodName, $paymentId, true);
            NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_AMOUNT_CHANGE_ERROR'));
        }
    }

    /**
     * Validate input form callback parameters
     *
     * @param  int $callback get the fraud module type
     * @param  int $pinbyTel get the mobile number
     * @return boolean
     */
    public function validateCallbackFields($callback, $pinbyTel)
    {
        $phoneNo = isset($pinbyTel) ? trim($pinbyTel) : '';
        // If pinbycallback enable to validate the telephone number.
        ($callback == 'CALLBACK' && !(preg_match('/^\d+$/', $phoneNo))) ? NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_TEL_ERROR')) : (($callback == 'SMS' && !(preg_match('/^\d+$/', $phoneNo))) ? NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_MOBILE_ERROR')) : '');
        return true;
    }

    /**
     * Validates the PIN number
     *
     * @param  int $pin get the pin value
     * @return void
     */
    public static function validatePinNumber($pin)
    {
        if (!preg_match('/^[\w]+$/', $pin))
            NovalnetUtilities::showMessage(JText::_('HKPAYMENT_NOVALNET_PIN_ERROR'));
    }

    /**
     * Validate for users over 18 only
     *
     * @param  date $birthDate get the birthdate value
     * @return boolean
     */
    public static function validateAge($birthDate)
    {
        return (empty($birthDate) || (time() < strtotime('+18 years', strtotime($birthDate))));
    }

    /**
     * To validate the guarantee configuration fields
     *
     * @param  string $element get the element params
     * @return string
     */
    public static function validateGuaranteeFields($element)
    {
        if ($element->payment_params->minimumAmount != '')
        {
            // Given mimimum amount is not satisfied to show the message.
            if ($element->payment_params->minimumAmount < 999)
            {
                $element->payment_params->minimumAmount = "";
				NovalnetUtilities::backendErrorMsg($element, JText::_('HKPAYMENT_NOVALNET_GUARANTEE_MINIMUM_AMOUNT_ERROR'));
            }
        }
        return true;
    }

    /**
     * To validate the configuration fiels
     *
     * @param  string $element get the element params
     * @return string
     */
    public static function validateConfigurationFields($element)
    {
		$message = '';

        foreach (array($element->payment_params->novalnetProductActivationKey, $element->payment_params->tariffId, $element->payment_params->authCode, $element->payment_params->productId, $element->payment_params->keyPassword) as $params) {
            if (empty($params))
            {
				$message = JText::_('HKPAYMENT_NOVALNET_BACK_END_ERR');
            }
        }
        if(!empty($message)) {
			NovalnetUtilities::backendErrorMsg($element, $message);
			return true;
		}
        return false;
    }
}
