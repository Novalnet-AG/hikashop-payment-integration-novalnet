<?php
/**
* This script is used for extension process
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
* Script : novalnet_extension.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

if (!class_exists('NovalnetUtilities'))
{
    require JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';
}
if (!class_exists('PlgHikashoppaymentnovalnet_ApiForm'))
{
    require JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'api' . DS . 'tmpl' . DS . 'novalnet_api_form.php';
}

/**
 * Novalnet extension payment class
 *
 * @package Hikashop_Payment_Plugin
 */
class NovalnetExtension
{
    /**
     * This event is triggerd to Display Novalnet API Form
     *
     * @param  integer $orderId get the order id
     * @return string
     */
    public static function getNovalnetAPI($orderId)
    {
        // Get the request values.
        $postParams = JRequest::get('request');
        
        // Get Order details in transaction detail table using order id.
        $orderDetail = self::getNovalnetDetails($orderId);
        $orderDetails = json_decode(($orderDetail->additional_data), true);
        $displayForm = '';

        // Check the results.
        if (!empty($orderDetail))
        {
            // Check the valid params for need to show the refund block.
            $displayForm.= '<table class="novalnet_api" style="table-layout: fixed;"><tr><td valign="top"><form name="novalnet_api" id="novalnet_api" action="" method="POST">';
            // Display Refund form.
            $displayForm.= ($orderDetail->amount != 0) ? ((in_array($orderDetails['payment_method'], array('novalnet_sepa', 'novalnet_paypal'))) && $orderDetail->gateway_status == 100 ? PlgHikashoppaymentnovalnet_ApiForm::displayRefundForm($orderDetail) : PlgHikashoppaymentnovalnet_ApiForm::displayRefundForm($orderDetail)) : '';
            $displayForm.= '</td><td valign="top" align="center">';
            // Check the valid params for need to show the manage transaction block.
            if ((!in_array($orderDetails['payment_method'], array('novalnet_giropay', 'novalnet_banktransfer', 'novalnet_eps','novalnet_ideal', 'novalnet_przelewy24', 'novalnet_cashpayment'))) && in_array($orderDetail->gateway_status, array('98', '99', '91', '85')))
            {
                // Display Manage transaction form.
                $displayForm.= PlgHikashoppaymentnovalnet_ApiForm::displayVoidCapture($orderDetail);
            }
            // Check the valid params for need to show the manage transaction block.
            if ($orderDetail->amount == 0 && (in_array($orderDetails['payment_method'], array('novalnet_cc', 'novalnet_sepa', 'novalnet_paypal'))) && $orderDetail->gateway_status != 103)
            {
                // Display Manage transaction form.
                $displayForm.= PlgHikashoppaymentnovalnet_ApiForm::displayZeroAmountBooking($orderDetail);
            }
            // Check the valid params for need to show the amount update block.
            if ((in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment','novalnet_cashpayment')) && $orderDetail->gateway_status == '100') || ($orderDetails['payment_method'] == 'novalnet_sepa' && $orderDetail->gateway_status == '99' && $orderDetail->amount != 0))
            {
                // Display amount update form.
                $displayForm.= PlgHikashoppaymentnovalnet_ApiForm::displayAmountUpdate($orderDetail);
            }
            $displayForm.= '</td></form></tr></table>';
        }
        // Check the required params to process Novalnet API.
        if (isset($postParams['capture']) || isset($postParams['void']) || isset($postParams['amount_update']) || isset($postParams['refund']) || isset($postParams['zero_amount_booking']))
        {
            // Based on the params perform different operations.
            self::processNovalnetAPI($postParams, $orderDetail);
        }
        // Shows the extension form based on the request.
        return $displayForm;
    }

    /**
     * Process Novalnet extension features
     *
     * @param  array $postParams get the post params
     * @param  object $orderDetail get the order detail
     * @return void
     */
    public static function processNovalnetAPI($postParams, $orderDetail)
    {
        // Get basic parameter.
        $configData = self::formConfigData($orderDetail);
        $orderDetails = json_decode(($orderDetail->additional_data), true);
        if (!empty($postParams['refund']))
        {
            // Get the request parameter values.
            $postParams = JRequest::get('post');
            // Check the amount value.
            if ($postParams['refund_amount'] != '0')
            {
                $orderDetail->amount = NovalnetUtilities::doFormatAmount($orderDetail->amount);
                $refundParam = $postParams['refund_amount'];
            }
            else
            {
                self::showMessage(JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_INVALID_ERROR'), $orderDetail->hika_order_id);
            }
            $configData['refund_request'] = 1;
            $configData['refund_param'] = $refundParam;
            // If refund reference is availble for the reference params.
            if (!empty($postParams['refund_ref']) && (preg_match('/^[a-zA-Z]+[a-zA-Z0-9._]+$/', $postParams['refund_ref'])))
            {
                $configData['refund_ref'] = $postParams['refund_ref'];
            }
        }
        elseif (isset($postParams['amount_update']))
        {
            // Check valid amount.
            if (!is_numeric($postParams['amount']) || !(trim($postParams['amount'], '0')) || empty($postParams['amount']))
            {
                self::showMessage(JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_INVALID_ERROR'), $orderDetail->hika_order_id);
            }
            else
            {
                $configData['update_inv_amount'] = 1;
                $configData['amount'] = $postParams['amount'];
            }
            if (in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment', 'novalnet_cashpayment')))
            {
                $dueDate = empty($postParams['due_date']) ? '' : date('Y-m-d', strtotime($postParams['due_date']));
                if (!empty($dueDate))
                {
                    // Check valid due date.
                    if (self::checkValidDueDate($dueDate))
                    {
                        $configData['due_date'] = $dueDate;
                    }
                    else
                    {
                        self::showMessage(JText::_('HKPAYMENT_NOVALNET_ADMIN_DUEDATE_INVALID_ERROR'), $orderDetail->hika_order_id);
                    }
                }
                else
                {
                    self::showMessage(JText::_('HKPAYMENT_NOVALNET_ADMIN_DUEDATE_EMPTY_ERROR'), $orderDetail->hika_order_id);
                }
            }
        }
        elseif (isset($postParams['zero_amount_booking']))
        {
            $paymentKey = $configData['key'];
            $configData = unserialize($orderDetail->payment_request);
            $configData['amount'] = $postParams['zero_amount_booking'];
            $configData['payment_ref'] = $orderDetail->tid;
            $configData['key'] = $paymentKey;
            $configData['remote_ip'] = $_SERVER['REMOTE_ADDR'];
            if (in_array($orderDetails['payment_method'], array('novalnet_paypal', 'novalnet_cc', 'novalnet_sepa')))
            {
                unset($configData['create_payment_ref']);
            }
        }
        // Transaction amount update or Capture or Void
        if (isset($postParams['capture']) || isset($postParams['amount_update']) || isset($postParams['void']))
        {
            $configData = array_merge($configData, array('edit_status' => 1, 'status' => (isset($postParams['capture']) && !empty($postParams['capture']) || isset($postParams['amount_update'])) ? 100 : 103));
        }
        $configData['lang'] = NovalnetUtilities::getLanguageTag();
        
        // Validate basic Parameters
        if (self::validateConfigData($configData))
        {
            // Send the API request to Novalnet server based on the Option
            $aryResponse = NovalnetUtilities::performHttpsRequest(http_build_query($configData));

            $message = NovalnetUtilities::responseMsg($aryResponse);
            if ($aryResponse['status'] == 100)
            {
                // Update the Transaction Amount and Due date for invoice and prepayment.
                if (isset($postParams['amount_update']))
                {
                    if (in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment', 'novalnet_cashpayment')))
                    {
                        self::updateAmountDuedate($aryResponse, $configData['amount'], $orderDetail, (isset($configData['due_date']) ? $configData['due_date'] : ''));
                    } else
                    {
                        // Update the Transaction Amount for sepa payment.
                        self::updateAmount($configData['amount'], $message, $orderDetail);
                    }
                }
                elseif (isset($postParams['zero_amount_booking']))
                {
                    self::zeroAmountBooking($configData['amount'], $message, $aryResponse, $orderDetail);
                }
                elseif (isset($postParams['refund']))
                {
                    // Separate the refund type based on the paid amount.
                    $postParams['refund_type'] = ($refundParam >= (string)$orderDetail->amount) ? 'FULL' : (((($orderDetail->refunded_amount * 100) + $refundParam) >= $orderDetail->amount) ? 'FULL' : 'PARTIAL');
                    self::processRefund($aryResponse, $postParams, $orderDetail);
                }
                elseif (isset($configData['status']) && $configData['status'] == '103')
                {
                    // Update the Transaction cancel in Database
                    self::cancelTransaction($message, $orderDetail);
                }
                else
                {
                    // Update the Transaction confirmation in Database
                    self::transactionConfirmation($message, $aryResponse, $orderDetail);
                }
            }
            else
            {
                // Show the status message.
                self::showMessage($message, $orderDetail->hika_order_id);
            }
        }
        else
        {
            self::showMessage(JText::_('HKPAYMENT_NOVALNET_BASIC_PARAM_ERROR'), $orderDetail->hika_order_id);
        }
    }

    /**
     * Form the merchant params
     *
     * @param  object $order get the order object
     * @return array
     */
    public static function formConfigData($order)
    {
        $configData = json_decode($order->additional_data);
        return array('vendor' => $configData->vendor, 'product' => $configData->product, 'key' => $configData->payment_key, 'tariff' => $configData->tariff, 'auth_code' => $configData->auth_code, 'tid' => $order->tid, 'remote_ip' => $_SERVER['REMOTE_ADDR']);
    }

    /**
     * This event is triggered to get the transaction details.
     *
     * @param  integer $orderId get the order id
     * @return object
     */
    public static function getNovalnetDetails($orderId)
    {
        return NovalnetUtilities::selectQuery(
        array('table_name' => array('#__novalnet_transaction_detail'),
        'column_name' => array('tid', 'payment_id', 'amount', 'refunded_amount', 'gateway_status', 'hika_order_id', 'additional_data', 'payment_specific_data', 'payment_request', 'order_amount','customer_id'), 'condition' => array("hika_order_id='$orderId'")));
    }

    /**
     * Update the transaction cancellation in database
     *
     * @param  string $message get the response message
     * @param  object $order get the order object
     * @return void
     */
    public function cancelTransaction($message, $order)
    {
        // Retrieves the merchant details.
        $config = NovalnetUtilities::getMerchantConfig();
        NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array("gateway_status='103'",), array('hika_order_id="' . $order->hika_order_id . '"'));
        // Get Language Tag.
        $language = NovalnetUtilities::getLanguageTag();
        $comments = ($language == 'EN') ? sprintf(JText::_('HKPAYMENT_NOVALNET_TRANSACTION_DEACTIVATED_MESSAGE')) . date('Y-m-d H:i:s') : sprintf(JText::_('HKPAYMENT_NOVALNET_TRANSACTION_DEACTIVATED_MESSAGE')) . date('H:i:s');
        // Update the order status in order history table.
        NovalnetUtilities::updateOrderStatus($config->transactionCancelStatus, $comments, $order);
        // Show the status message..
        self::showMessage($message, $order->hika_order_id, true);
    }

    /**
     * Update the amount for the reference order.
     *
     * @param  integer $amount get the order amount
     * @param  string $message get the response message
     * @param  array $aryResponse get the extension response
     * @param  object $order get the order object
     * @return void
     */
    public function zeroAmountBooking($amount, $message, $aryResponse, $order)
    {
        // Get currency object.
        $currencyHelper = hikashop_get('class.currency');
        $orderDetails = json_decode(($order->additional_data), true);
        // Get amount update message.
        $bookedAmount = ($amount / 100);
        $language = NovalnetUtilities::getLanguageTag();
        $comments = NovalnetUtilities::transactionComments($order->tid, $orderDetails['test_mode']).'<br>';
        $comments.= sprintf(JText::_('HKPAYMENT_NOVALNET_ZERO_AMOUNT_BOOK_MSG'), $currencyHelper->format($bookedAmount), $aryResponse['tid']);
        // Update order status in order history table.
        NovalnetUtilities::updateOrderStatus(NovalnetUtilities::getOrderStatus($order->hika_order_id), $comments, $order);
        // Update the details in transaction table.
        NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array("amount='$bookedAmount'", 'tid="' . $aryResponse['tid'] . '"', 'gateway_status="' . $aryResponse['tid_status'] . '"'), array('hika_order_id="' . $order->hika_order_id . '"'));
        // Show the status message.
        self::showMessage($message, $order->hika_order_id, true);
    }

    /**
     * This event is triggered to get the Amount Update message.
     *
     * @param  integer $amount get the order amount
     * @param  object $currencyHelper get the currency helper object
     * @return string
     */
    public static function getAmountUpdateDueDateMessage($amount, $currencyHelper, $dueDate)
    {
        return JText::_('HKPAYMENT_NOVALNET_TRANSACTION_AMOUNT_DUE_DATE_MESSAGE') . ' ' . $currencyHelper->format($amount) . ' ' . JText::_('HKPAYMENT_NOVALNET_TRANSACTION_DUE_DATE_MESSAGE') . ' ' . $dueDate;
    }

    /**
     * This event is triggered to amount update process.
     *
     * @param  integer $amount get the order amount
     * @param  string $message get the response message
     * @param  object $order get the order object
     * @return void
     */
    public function updateAmount($amount, $message, $order)
    {
        // Format amount using class.currency object.
        $currencyHelper = hikashop_get('class.currency');
        // Get amount update message.
        $updateAamount = ($amount / 100);
        $comments = self::getAmountUpdateMessage($updateAamount, $currencyHelper);
        // Update order status in order history table.
        NovalnetUtilities::updateOrderStatus(NovalnetUtilities::getOrderStatus($order->hika_order_id), $comments, $order);
        // Update the details in transaction table.
        NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array("amount='$updateAamount'",), array('hika_order_id="' . $order->hika_order_id . '"'));
        // Show the status message.
        self::showMessage($message, $order->hika_order_id, true);
    }

    /**
     * This event is triggered to update the transaction Confirmation.
     *
     * @param  string $message get the response message
     * @param  array $response get the payment response
     * @param  object $order get the order object
     * @return void
     */
    public static function transactionConfirmation($message, $response, $order)
    {
        $orderDetails = json_decode(($order->additional_data), true);
        if ($orderDetails['payment_method'] == 'novalnet_invoice')
        {
            NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array('payment_request="' . $response['due_date'] . '"',), array('hika_order_id="' . $order->hika_order_id . '"'));
        }
        $updateConfirmDetail = $response['tid_status'] ;
        if ($orderDetails['payment_method'] == 'novalnet_paypal')
        {
            $amount = ($response['tid_status'] == 100) ? NovalnetUtilities::doFormatAmount($order->amount) : 0;
            $maskingDetail = json_encode(array(
            'tid_status' => $response['tid_status'],
            'paypal_transaction_id' => $response['paypal_transaction_id'],
            'tid' => $order->tid));
            NovalnetUtilities::updateQuery(array('#__novalnet_callback_detail'), array("callback_amount='$amount'"), array('hika_order_id="' . $order->order_number . '"'));
        }
        $OrderStatus = NovalnetUtilities::getOrderPaymentStatus("payment_id='" . $order->payment_id . "'", $order->hika_order_id);
        $config = NovalnetUtilities::getMerchantConfig();
        $maskingDetail = !empty($maskingDetail) ? $maskingDetail : '';
        // Update the transaction confirmation detail in transaction table.
        NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'),
        array("payment_specific_data='$maskingDetail'","gateway_status='$updateConfirmDetail'"),
        array('hika_order_id="' . $order->hika_order_id . '"'));
        // Get the language tag.
        $language = NovalnetUtilities::getLanguageTag();
        // Get the order debit transaction comments based on the langugae.
        $comments = ($language == 'EN') ? JText::_('HKPAYMENT_NOVALNET_TRANSACTION_CONFIRM_SUCCESS_MESSAGE') . ' ' . date('Y-m-d H:i:s') : JText::_('HKPAYMENT_NOVALNET_TRANSACTION_CONFIRM_SUCCESS_MESSAGE') . ' ' . date('Y-m-d');
        if ($orderDetails['payment_method'] == 'novalnet_invoice') {
            $amount = ($response['tid_status'] == 100) ? NovalnetUtilities::doFormatAmount($order->amount) : 0;
            $comments.= self::updateAmountDuedate($response, $amount, $order, (isset($response['due_date']) ? $response['due_date'] : ''));
        }
        // Update order status in order history table.
        NovalnetUtilities::updateOrderStatus($OrderStatus['order_status'], $comments, $order);
        // Show the status message.
        self::showMessage($message, $order->hika_order_id, true);
    }

    /**
     * This event is triggered to get the Amount Update message.
     *
     * @param  integer $amount get the order amount
     * @param  object  $currencyHelper get the currency helper object
     * @return string
     */
    public static function getAmountUpdateMessage($amount, $currencyHelper)
    {
        // Get language tag.
        $language = NovalnetUtilities::getLanguageTag();
        return ($language == 'EN') ? sprintf(JText::_('HKPAYMENT_NOVALNET_TRANSACTION_AMOUNT_MESSAGE'), $currencyHelper->format($amount), date('Y-m-d, H:i:s')) : sprintf(JText::_('HKPAYMENT_NOVALNET_TRANSACTION_AMOUNT_MESSAGE'), $currencyHelper->format($amount), date('Y-m-d'), date('h:i:s'));
    }

    /**
     * This event is triggered to Update the Due date and amount process.
     *
     * @param  integer $aryResponse get the payment response
     * @param  array $amount get the amount update amount
     * @param  object $order get the order object
     * @param  date $dueDate get the due date value
     * @return void
     */
    public static function updateAmountDuedate($aryResponse, $amount, $order, $dueDate = null)
    {
        // Format amount using class.currency object.
        $currencyHelper = hikashop_get('class.currency');
        // Get configuration details.
        $config = NovalnetUtilities::getMerchantConfig();
        $orderDetails = json_decode(($order->additional_data), true);
        $dueDate = $dueDate ? $dueDate : $orderDetails['invoice_duedate'];
        $paymentResponse = json_decode($order->payment_specific_data);
        if (in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment')))
        {
            // Get amount update message.
            $transactionComments = self::getAmountUpdateDueDateMessage(($amount / 100), $currencyHelper, $dueDate) . '<br><br>';
            $transactionComments.= NovalnetUtilities::transactionComments($order->tid, $orderDetails['test_mode']).'<br>';
            $transactionComments.= PHP_EOL . JText::_('HKPAYMENT_NOVALNET_COMMENTS_MSG') . '<br>';
            if (!empty($dueDate)) {
                $transactionComments.= JText::_('HKPAYMENT_NOVALNET_DUE_DATE') . ': ' . hikashop_getDate($dueDate, '%d %B %Y ') . '<br>';
            }
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_HOLDER') . $paymentResponse->invoice_account_holder . '<br>';
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_IBAN') . ': ' . $paymentResponse->invoice_iban . '<br>';
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_BIC') . ': ' . $paymentResponse->invoice_bic . '<br>';
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_BANK') . ': ' . $paymentResponse->invoice_bankname . " " . trim($paymentResponse->invoice_bankplace) . '<br>';
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_COMMENTS_AMOUNT') . ': ' . $currencyHelper->format($amount / 100) . '<br>';
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_PAYMENT_REFERENCE_MSG_MULTIPLE') . '</br>';
            $transactionComments.= JText::_('NOVALNET_PAYMENT_REFERENCE') . '1: ' . 'BNR-' . $config->productId . '-' . $orderDetails['order_no'] . '<br>';
            $transactionComments.= JText::_('NOVALNET_PAYMENT_REFERENCE') . '2: ' . 'TID ' . $order->tid . '<br>';
            // Get Invoice/Prepayment payment reference values.
            $paymentResponse->invoice_duedate = $dueDate;
            $order->payment_specific_data = $paymentResponse;
            $updateDue = json_encode($order->payment_specific_data);
        }
        else
        {
            $paymentResponse = json_decode(json_encode($paymentResponse), true);
            $transactionComments = self::getAmountUpdateDueDateMessage(($amount / 100), $currencyHelper, $dueDate) . '<br><br>';
            $transactionComments.= NovalnetUtilities::transactionComments($order->tid, $orderDetails['test_mode']).'<br>';
            $storeCount = 1;
            foreach ($paymentResponse as $key) {
                if (strpos($key, 'nearest_store_title') !== false) {
                    $storeCount++;
                }
            }
            $transactionComments.= '<br>';
            $cashPaymentDueDate = ($dueDate) ? $dueDate : $paymentResponse['cashpayment_due_date'];
            if ($cashPaymentDueDate) {
                $transactionComments.= JText::_('HKPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_SLIP_DATE') . ': ' . $cashPaymentDueDate;
            }
            $transactionComments.= '<br><br>';
            $transactionComments.= JText::_('HKPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_STORE') . '<br><br>';
            $transactionComments.= $paymentResponse['stores'];
            $paymentResponse['cashpayment_due_date'] = $cashPaymentDueDate;
            $updateDue = json_encode($paymentResponse);
        }
        // Update amount update details in transction detail table.
        NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array("payment_specific_data='$updateDue'", "amount='" . ($amount / 100) . "'"), array('hika_order_id="' . $order->hika_order_id . '"'));
        // Update the order status in order history table.
        $orderStatus = NovalnetUtilities::getOrderPaymentStatus("payment_id='" . $order->payment_id . "'", $order->hika_order_id, $orderDetails['payment_method']);
        NovalnetUtilities::updateOrderStatus($orderStatus['transaction_before_status'], $transactionComments, $order);
        // Show the status message.
        self::showMessage(NovalnetUtilities::responseMsg($aryResponse), $order->hika_order_id, true);
    }

    /**
     * This event is triggered to process Refund response
     *
     * @param  array $aryResponse get the payment response
     * @param  array $postParams get the post params
     * @param  object $order get the order   object
     * @return void
     */
    public function processRefund($aryResponse, $postParams, $order)
    {
        $configValue = NovalnetUtilities::getMerchantConfig();
        // Get language tag.
        $language = NovalnetUtilities::getLanguageTag();
        $orderDetails = json_decode(($order->additional_data), true);
        // Format amount value using class.currency object.
        $currencyHelper = hikashop_get('class.currency');
        $refundMessage = '';
        if ($language == 'DE')
        {
            $refundMessage = JText::_('HKPAYMENT_NOVALNET_ADMIN_PARTIAL_REFUND_AMT_ONE');
        }
        // If full refund this part is processed
        if ($postParams['refund_type'] == 'FULL')
        {
            $fields = array("refunded_amount='" . $order->amount . "'", "gateway_status='" . $aryResponse['tid_status'] . "'");
            if (($orderDetails['payment_method'] == 'novalnet_cc') && !empty($order->refunded_amount))
            {
                $fields = array("refunded_amount='" . ($order->amount / 100) . "'");
            }
            NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), $fields, array('hika_order_id="' . $order->hika_order_id . '"'));
            // Form the Refund comments.
            $comments = JText::_('HKPAYMENT_NOVALNET_ADMIN_FULL_REFUND_MESSAGE') . ' ' . $order->tid . ' ' . JText::_('HKPAYMENT_NOVALNET_ADMIN_PARTIAL_REFUND_AMT_MESSAGE') . ' ' . $currencyHelper->format($postParams['refund_amount'] / 100) . $refundMessage;
            // Show child Tid comments.
            if (in_array($orderDetails['payment_method'], array('novalnet_cc', 'novalnet_przelewy24', 'novalnet_paypal')) && !empty($aryResponse['tid']))
            {
                $comments.= JText::_('HKPAYMENT_NOVALNET_ADMIN_NEW_TID_MESSAGE') . $aryResponse['tid'];
            }
            // Update the order details in order history table.
            NovalnetUtilities::updateOrderStatus((in_array($orderDetails['payment_method'], array('novalnet_cc', 'novalnet_przelewy24', 'novalnet_paypal')) && $aryResponse['tid_status'] != '103') ? NovalnetUtilities::getOrderStatus($order->hika_order_id) : $configValue->transactionCancelStatus, $comments, $order);
        }
        else
        {
            // If partial refund this part is processed.
            $amount = $postParams['refund_amount'] / 100;
            $comments = JText::_('HKPAYMENT_NOVALNET_ADMIN_PARTIAL_REFUND_MESSAGE') . ' ' . $order->tid . ' ' . JText::_('HKPAYMENT_NOVALNET_ADMIN_PARTIAL_REFUND_AMT_MESSAGE') . $currencyHelper->format($amount) . ' ' . $refundMessage;
            if (!empty($aryResponse['tid']))
            {
                $comments.= JText::_('HKPAYMENT_NOVALNET_ADMIN_NEW_TID_MESSAGE') . $aryResponse['tid'];
            }
            // Get the total amount.
            $totalAmount = $order->refunded_amount + $amount;
            // Get the refunded amount.
            NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array("tid='" . $order->tid . "'", "refunded_amount='$totalAmount'"), array('hika_order_id="' . $order->hika_order_id . '"'));
            // Update the order details in order history table.
            NovalnetUtilities::updateOrderStatus(NovalnetUtilities::getOrderStatus($order->hika_order_id), $comments, $order);
        }
        $result = NovalnetUtilities::selectQuery(array('table_name' => array('#__novalnet_transaction_detail'),'column_name' => array('amount', 'refunded_amount'),'condition' => array("hika_order_id='" . $order->hika_order_id . "'")));
        if ($orderDetails['payment_method'] == 'novalnet_cc' && ($result->amount == $result->refunded_amount))
        {
                NovalnetUtilities::updateQuery(array('#__novalnet_transaction_detail'), array("gateway_status=' 103 '"), array('hika_order_id="' . $order->hika_order_id . '"'));
        }
        // Show the status message.
        self::showMessage(NovalnetUtilities::responseMsg($aryResponse), $order->hika_order_id, true);
    }

    /**
     * Redirects to success or error page based on response
     *
     * @param  integer $orderId get the order id
     * @return void
     */
    public static function redirectUrl($orderId)
    {
        $postParams = JRequest::get('request');
        return HIKASHOP_LIVE . 'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&cid[]=' . $orderId . '&cancel_redirect=' . $postParams['cancel_redirect'];
    }

    /**
     * Validating the merchant configuration
     *
     * @param  array $configParams get the config params
     * @return boolean
     */
    public static function validateConfigData($configParams)
    {
        return (is_numeric($configParams['vendor']) && !empty($configParams['auth_code']) && is_numeric($configParams['product']) && is_numeric($configParams['tariff']));
    }

    /**
     * Validating the date format
     *
     * @param  date $dueDate get the due date value
     * @return boolean
     */
    public static function checkValidDueDate($dueDate)
    {
        return preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $dueDate) && $dueDate >= date('Y-m-d');
    }

    /**
     * Display the transaction message
     *
     * @param  string  $message get the response message
     * @param  integer $orderId get the order id
     * @param  boolean $enqueueMessage based on the message display error/warning
     * @return void
     */
    public static function showMessage($message, $orderId, $enqueueMessage = false)
    {
        // Loads the Joomla application.
        $app = JFactory::getApplication();
        (!$enqueueMessage) ? JError::raiseWarning(500, $message) : $app->enqueueMessage(JText::_($message));
        $app->redirect(self::redirectUrl($orderId));
    }
}
