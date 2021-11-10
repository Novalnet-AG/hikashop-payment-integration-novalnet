<?php
/**
* This script is used for handling callback details
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
* Script : novalnet_vendorscript.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';

/**
 * Novalnet vendorscript class
 *
 * @package Hikashop_Payment_Plugin
 */
class NovalnetVendorScript extends hikashopPaymentPlugin
{
    /**
     * Initial level payments - Level 0
     *
     * @var array
     */
    protected $initialLevelPayments = array('CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE', 'PAYPAL', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'ONLINE_TRANSFER', 'IDEAL', 'EPS', 'GIROPAY', 'PRZELEWY24');
    /**
     * Chargeback level payments - Level 1
     *
     * @var array
     */
    protected $chargebackLevelPayments = array('RETURN_DEBIT_SEPA', 'REVERSAL', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'CASHPAYMENT_REFUND', 'GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK');
    /**
     * CreditEntry level payments - Level 2
     *
     * @var array
     */
    protected $collectionLevelPayments = array('INVOICE_CREDIT', 'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA', 'DEBT_COLLECTION_CREDITCARD', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE');
    /**
     * @var array
     */
    protected $transactionCancellation = array('TRANSACTION_CANCELLATION');
    /**
     * @var array
     */
    protected static $techincMail = 'technic@novalnet.de';
    /**
     * @var array
     */
    protected $paymentTypes = array(
    'novalnet_invoice' 		=> array('INVOICE_CREDIT', 'INVOICE_START', 'GUARANTEED_INVOICE', 'GUARANTEED_INVOICE_BOOKBACK', 'TRANSACTION_CANCELLATION', 'REFUND_BY_BANK_TRANSFER_EU'), 'novalnet_prepayment' 	=> array('INVOICE_CREDIT', 'INVOICE_START', 'REFUND_BY_BANK_TRANSFER_EU'),
    'novalnet_cashpayment' 	=> array('CASHPAYMENT', 'CASHPAYMENT_CREDIT', 'CASHPAYMENT_REFUND'),
    'novalnet_sepa' 		=> array('DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA', 'REFUND_BY_BANK_TRANSFER_EU', 'GUARANTEED_SEPA_BOOKBACK', 'TRANSACTION_CANCELLATION'),
    'novalnet_cc' 			=> array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD'),
    'novalnet_banktransfer' => array('ONLINE_TRANSFER', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_paypal' 		=> array('PAYPAL', 'PAYPAL_BOOKBACK'),
    'novalnet_ideal' 		=> array('IDEAL', 'REVERSAL', 'ONLINE_TRANSFER_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_eps' 			=> array('EPS', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_przelewy24' 	=> array('PRZELEWY24', 'PRZELEWY24_REFUND'),
    'novalnet_giropay' 		=> array('GIROPAY', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'));

    /**
     * This event triggered to get the callback response
     *
     * @param  string $statuses get the payment statuses
     * @return void
     */
    public function onPaymentNotification(&$statuses)
    {
        // Get the server callback response.
        $response               = JRequest::get('response');
        $this->aryCaptureParams = array_map('trim', $response);
        $this->configDetails    = NovalnetUtilities::getMerchantConfig('callback');

        // Validate Ip address.
        self::validateIpAddress($this->configDetails->callbackTestmode);
        if (empty($this->aryCaptureParams))
            self::displayMessage('Novalnet callback received. No params passed over!');

        // Validate the request callback parameters.
        $this->nnVendorParams = self::validateCaptureParams();

        // Get payment type level.
        $paymentTypeLevel = self::getPaymentTypeLevel();

        // Set the currency value.
        $currency = !empty($this->nnVendorParams['currency']) ? $this->nnVendorParams['currency'] : '';

        // Loads the order object for the vendor request.
        $this->orderReference = self::getOrderByIncrementId();

        // Get the order amount.
        $orderAmount = NovalnetUtilities::doFormatAmount($this->orderReference->order_full_price);
        $paymentName = self::getPaymentMethod($this->orderReference->order_payment_id);

        // Get callback order status.
        $this->OrderStatus = NovalnetUtilities::getOrderPaymentStatus("payment_id='" . $this->orderReference->order_payment_id . "'", $this->orderReference->order_id, $paymentName);
        $result            = NovalnetUtilities::selectQuery(array(
            'table_name' => '#__novalnet_transaction_detail',
            'column_name' => array(
                'gateway_status',
                'tid'
            ),
            'condition' => array(
                "hika_order_id = '" . $this->orderReference->order_id . "'"
            ),
            'order' => ''
        ));

        if (empty($result->tid))
            self::handleCommunicationFailure();

        if (isset($this->nnVendorParams['vendor_activation']) && $this->nnVendorParams['vendor_activation'] == 1) {
            // Update affiliate account details.
            self::insertAffAccountActivationDetail($this->nnVendorParams);
            $callbackComments = 'Novalnet callback script executed successfully with Novalnet account activation information.' . PHP_EOL;

            // Send notification mail to merchant.
            self::sendMailNotification($callbackComments);

            // Displays message and stops the execution.
            self::displayMessage($callbackComments);
        }

        $callbackComments = '';
        if ($paymentTypeLevel == 3 && in_array($result->gateway_status, array('75', '91', '99', '98', '85')))
        {
            $callbackComments = sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_CANCELLATION_COMMENTS'), $this->nnVendorParams['tid'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'));
            $configDetails    = self::getPaymentParams('payment_type="novalnet_payments"');
            $OrderStatus      = $configDetails->transactionCancelStatus;
            // Update the status in transaction table.
            NovalnetUtilities::updateQuery(array(
                '#__novalnet_transaction_detail'
            ), array(
                'gateway_status="' . $this->nnVendorParams['tid_status'] . '"'
            ), array(
                'tid="' . $this->nnVendorParams['tid'] . '"'
            ));

            // Insert the callback comments in order history table.
            self::insertCallbackComments(array(
                'hk_order_id' => $this->orderReference->order_id,
                'order_status' => $OrderStatus,
                'callback_comments' => $callbackComments
            ), $this->orderReference);

            self::sendMailNotification($callbackComments);
            self::displayMessage($callbackComments, $this->aryCaptureParams['order_no']);
        }

        // Credit entry of Invoice or Prepayment.
        if ($paymentTypeLevel == 2 && $this->aryCaptureParams['status'] == 100 && $this->aryCaptureParams['tid_status'] == 100) {
            if (in_array($this->aryCaptureParams['payment_type'], array('INVOICE_CREDIT', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT')))
            {
                $result         = NovalnetUtilities::getCallbackAmount($this->orderReference->order_id, 'novalnet_invoice');
                $totalAmount    = !empty($result->total_amount) ? $result->total_amount : 0;
                $totalAmountSum = $totalAmount + $this->nnVendorParams['amount'];

                if ($totalAmount < $orderAmount)
                {
                    $callbackComments .= sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_COMMENTS'), $this->nnVendorParams['tid_payment'], NovalnetUtilities::doFormatAmount($this->nnVendorParams['amount'] / 100, true), $currency, hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'), $this->nnVendorParams['tid']);
                    $OrderStatus = $this->OrderStatus['transaction_before_status'];
                    if ($totalAmountSum >= $orderAmount)
                    {
                        $callbackComments = sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_COMMENTS'), $this->nnVendorParams['tid_payment'], NovalnetUtilities::doFormatAmount($this->nnVendorParams['amount'] / 100, true), $currency, hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'), $this->nnVendorParams['tid']);
                        $OrderStatus      = $this->OrderStatus['order_status'];
                        if ($this->nnVendorParams['payment_type'] == 'ONLINE_TRANSFER_CREDIT' && $totalAmountSum >= $orderAmount)
                        {
                            $OrderStatus = $this->OrderStatus['before_status'];
                            $callbackComments .= PHP_EOL . sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_ONLINE_TRANSFER_COMMENTS'), $totalAmountSum, $currency, $this->orderReference->order_number);
                        }
                    }
                    NovalnetUtilities::updateQuery(array(
                        '#__hikashop_history'
                    ), array(
                        "history_data='callbackComments_new'"
                    ), array(
                        'history_id="' . $this->orderReference->order_id . '"'
                    ));

                    // Insert the callback comments in order history table.
                    self::insertCallbackComments(array(
                        'hk_order_id' => $this->orderReference->order_id,
                        'order_status' => $OrderStatus,
                        'callback_comments' => $callbackComments
                    ), $this->orderReference);

                    // Updates order status into shop's db.
                    NovalnetUtilities::updateQuery(array(
                        '#__hikashop_order'
                    ), array(
                        'order_status="' . $OrderStatus . '"'
                    ), array(
                        'order_id="' . $this->orderReference->order_id . '"'
                    ));

                    // Send notification mail to merchant.
                    self::sendMailNotification($callbackComments);

                    // Insert the values into novalnet_callback table for reference.
                    self::logCallbackProcess($this->nnVendorParams, $this->orderReference->order_number, $this->orderReference->order_id);

                    // Displays message and stops the execution.
                    self::displayMessage($callbackComments);
                }
                else
                {
                    $this->displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_id);
                }
            }
            else
            {
                $callbackComments .= sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_COMMENTS'), $this->nnVendorParams['tid_payment'], NovalnetUtilities::doFormatAmount($this->nnVendorParams['amount'] / 100, true), $currency, hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'), $this->nnVendorParams['tid']);
                // Insert the callback comments in order history table.
                self::insertCallbackComments(array(
                    'hk_order_id' => $this->orderReference->order_id,
                    'order_status' => $this->OrderStatus['order_status'],
                    'callback_comments' => $callbackComments
                ), $this->orderReference);
                self::sendMailNotification($callbackComments);
                self::logCallbackProcess($this->nnVendorParams, $this->nnVendorParams['shop_tid'], $this->orderReference->order_number, $this->orderReference->order_id);
                self::displayMessage($callbackComments, $this->nnVendorParams['order_no']);
            }
        }
        // Level 1 payments - Type of charge backs.
        elseif ($paymentTypeLevel == 1 && $this->aryCaptureParams['status'] == 100 && $this->aryCaptureParams['tid_status'] == 100)
        {
            // Do the steps to update the status of the order or the user and note that the payment was reclaimed from user.
            $callbackComments = (in_array($this->nnVendorParams['payment_type'], array('PAYPAL_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDITCARD_BOOKBACK', 'PRZELEWY24_REFUND',            'CASHPAYMENT_REFUND', 'GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK'))) ? sprintf(JText::_('HKPAYMENT_NOVALNET_BOOKBACK_COMMENT'), $this->nnVendorParams['tid_payment'], NovalnetUtilities::doFormatAmount($this->nnVendorParams['amount'] / 100, true), $this->nnVendorParams['currency'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'), $this->nnVendorParams['tid']) : sprintf(JText::_('HKPAYMENT_NOVALNET_CHARGEBACK_COMMENT'), $this->nnVendorParams['tid_payment'], NovalnetUtilities::doFormatAmount($this->nnVendorParams['amount'] / 100, true), $this->nnVendorParams['currency'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'), $this->nnVendorParams['tid']);

            // Insert the callback comments in order history table.
            self::insertCallbackComments(array(
                'hk_order_id' => $this->orderReference->order_id,
                'order_status' => $this->OrderStatus['before_status'],
                'callback_comments' => $callbackComments
            ), $this->orderReference);

            // Send notification mail to merchant.
            self::sendMailNotification($callbackComments);

            // Insert the values into novalnet_callback table for reference.
            self::logCallbackProcess($this->nnVendorParams, $this->orderReference->order_number, $this->orderReference->order_id);

            // Displays message and stops the execution.
            self::displayMessage($callbackComments);

        }
        elseif ($this->nnVendorParams['payment_type'] == 'PAYPAL' && in_array($this->nnVendorParams['tid_status'], array('100', '90', '85')) || ($this->nnVendorParams['payment_type'] == 'PRZELEWY24' && ($this->nnVendorParams['tid_status'] == 100)))
        {
			if ($result->gateway_status == '90' && $this->nnVendorParams['tid_status'] == '85')
            {
				$callbackComments = sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_ONHOLD_COMMENTS'), $this->nnVendorParams['tid'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'));
				$orderStatus = $this->configDetails->transactionConfirmStatus;
			} elseif ($this->nnVendorParams['tid_status'] == '100') {
                $callbackComments = sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_CONFIRMED_COMMENTS'), $this->nnVendorParams['tid'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'));
                $orderStatus = $this->OrderStatus['order_status'];
			}
                // Update order amount in callback table.
                NovalnetUtilities::updateQuery(array(
                '#__novalnet_transaction_detail'
                ), array(
                    'gateway_status="' . $this->nnVendorParams['tid_status'] . '"'
                ), array(
                    'tid="' . $this->nnVendorParams['tid'] . '"'
                ));
				// Insert the callback comments in order history table.
				self::insertCallbackComments(array(
					'hk_order_id' => $this->orderReference->order_id,
					'order_status' => $orderStatus,
					'callback_comments' => $callbackComments
				), $this->orderReference);

                self::sendMailNotification($callbackComments);

                self::displayMessage($callbackComments);
        }
        elseif ($this->nnVendorParams['payment_type'] == 'PRZELEWY24' && $this->nnVendorParams['tid_status'] != 100)
        {
            if ($this->nnVendorParams['tid_status'] == '86')
                self::displayMessage('Novalnet Callbackscript received. Payment type ( ' . $this->nnVendorParams['payment_type'] . ' ) is not applicable for this process!');

            $cancellationMsg                   = NovalnetUtilities::responseMsg($this->nnVendorParams);
            $callbackComments                  = PHP_EOL . JText::_('HKPAYMENT_NOVALNET_CALLBACK_PRZELEWY_COMMENTS') . $cancellationMsg;
            $this->OrderStatus['order_status'] = 'cancelled';

            // Insert the callback comments in order history table.
            self::insertCallbackComments(array(
                'hk_order_id' => $this->orderReference->order_id,
                'order_status' => $this->OrderStatus['order_status'],
                'callback_comments' => $callbackComments
            ), $this->orderReference);
            self::sendMailNotification($callbackComments);
            self::displayMessage($callbackComments);

        }
        elseif (in_array($this->nnVendorParams['payment_type'], array('GUARANTEED_INVOICE', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'CREDITCARD')) && ($this->nnVendorParams['status'] == 100) && in_array($this->nnVendorParams['tid_status'], array('91', '98','99', '100')) && in_array($result->gateway_status, array('75', '91', '98', '99')))
        {
            $this->configDetails = self::getPaymentParams('payment_type="novalnet_payments"');
            $message             = NovalnetUtilities::transactionComments($this->nnVendorParams['tid'], $this->nnVendorParams['test_mode']);
            $callbackMessage     = ($this->nnVendorParams['tid_status'] == 100) ? PHP_EOL . sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_CONFIRMED_COMMENTS'), $this->nnVendorParams['tid'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S')) : PHP_EOL . sprintf(JText::_('HKPAYMENT_NOVALNET_CALLBACK_ONHOLD_COMMENTS'), $this->nnVendorParams['tid'], hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S'));
            if ($result->gateway_status == 75 && in_array($this->nnVendorParams['tid_status'], array('91', '100')) && $this->nnVendorParams['payment_type'] == 'GUARANTEED_INVOICE')
            {
                $message           = ($this->nnVendorParams['tid_status'] == 91) ? $message . $callbackMessage : $message . NovalnetUtilities::formInvoicePrepaymentReferenceComments($this->nnVendorParams, $this->orderReference) . $callbackMessage;
                $this->OrderStatus = ($this->nnVendorParams['tid_status'] == 91) ? $this->configDetails->transactionConfirmStatus : $this->OrderStatus['order_status'];
            } elseif ($result->gateway_status == 75 && in_array($this->nnVendorParams['tid_status'], array('99', '100')) && $this->nnVendorParams['payment_type'] == 'GUARANTEED_DIRECT_DEBIT_SEPA')
            {
                $message           = ($this->nnVendorParams['tid_status'] == 100) ? $message : $callbackMessage;
                $this->OrderStatus = ($this->nnVendorParams['tid_status'] == 99) ? $this->configDetails->transactionConfirmStatus : $this->OrderStatus['order_status'];
            }
            elseif ((in_array($result->gateway_status, array('91', '98', '99')) && $this->nnVendorParams['tid_status'] == 100))
            {
                $message           = ($result->gateway_status == 91) ? $message . NovalnetUtilities::formInvoicePrepaymentReferenceComments($this->nnVendorParams, $this->orderReference) . '</br>' . $callbackMessage : $callbackMessage;
                $this->OrderStatus = ($this->nnVendorParams['payment_type'] == "INVOICE_START") ? $this->OrderStatus['transaction_before_status'] : $this->OrderStatus['order_status'];

            } else {
                $this->displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_id);
            }
            if (in_array($this->nnVendorParams['tid_status'], array('91', '100')) && in_array($result->gateway_status, array('75', '91')))
                self::sendOrderMail($message);
            NovalnetUtilities::updateQuery(array(
                '#__novalnet_transaction_detail'
            ), array(
                'gateway_status="' . $this->nnVendorParams['tid_status'] . '"'
            ), array(
                'tid="' . $this->nnVendorParams['tid'] . '"'
            ));

            // Send notification mail to merchant
            self::sendMailNotification($callbackMessage);
            if ($result->gateway_status == 91)
                $callbackMessage = $message . $callbackMessage;
            self::insertCallbackComments(array(
                'hk_order_id' => $this->orderReference->order_id,
                'order_status' => $this->OrderStatus,
                'callback_comments' => $message
            ), $this->orderReference);

            // Displays message and stops the execution.
            self::displayMessage($callbackMessage, $this->nnVendorParams['order_no']);

        }
        else
        {
            $this->displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_id);

        }
        if ($this->nnVendorParams['status'] != 100 || $this->nnVendorParams['tid_status'] != 100)
        {
            $status      = ($this->nnVendorParams['status'] != 100) ? 'status' : 'tid_status';
            $statusValue = $this->nnVendorParams['status'] != 100 ? $this->nnVendorParams['status'] : $this->nnVendorParams['tid_status'];
            self::displayMessage('Novalnet callback received. ' . $status . ' (' . $statusValue . ') is not valid');

        }
        else
        {
            self::displayMessage('Novalnet Callbackscript received. Payment type ( ' . $this->nnVendorParams['payment_type'] . ' ) is not applicable for this process!');
        }
    }

    /**
     * Validating the required parameters
     *
     * @return array
     */
    public function validateCaptureParams()
    {
        $paramsRequired = array('vendor_id', 'status', 'payment_type', 'tid', 'tid_status');

        if (isset($this->aryCaptureParams['payment_type']) && in_array($this->aryCaptureParams['payment_type'], array_merge($this->chargebackLevelPayments, $this->collectionLevelPayments)))
            array_push($paramsRequired, 'tid_payment');

        if (empty($this->aryCaptureParams['vendor_activation']))
        {
            // Validate the required parameters
            foreach ($paramsRequired as $v)
            {
                if (empty($this->aryCaptureParams[$v]))
                    self::displayMessage('Required param ( ' . $v . '  ) missing!');
            }
            // Validate the tid parameter.
            if (!preg_match('/^\d{17}$/', $this->aryCaptureParams['tid']))
            {
                self::displayMessage('Novalnet callback received. Invalid TID [' . $this->aryCaptureParams['tid'] . '] for Order.');
            }

            if (in_array($this->aryCaptureParams['payment_type'], array_merge($this->chargebackLevelPayments, $this->collectionLevelPayments)))
            {
                // Level 2 added tid_payment params.
                $this->aryCaptureParams['shop_tid'] = $this->aryCaptureParams['tid_payment'];
            }
            elseif (!empty($this->aryCaptureParams['tid']))
            {
                $this->aryCaptureParams['shop_tid'] = $this->aryCaptureParams['tid'];
            }
        }
        else
        {
            $affParamsRequired = array('vendor_id', 'vendor_authcode', 'product_id', 'aff_id', 'aff_authcode', 'aff_accesskey');
            // Validate the affiliate params.
            foreach ($affParamsRequired as $v)
            {
                if (empty($this->aryCaptureParams[$v]))
                    self::displayMessage('Required param ( ' . $v . '  ) missing!');
            }
        }
        return $this->aryCaptureParams;
    }

    /**
     * Validates the IP address which processing the callback script
     *
     * @param  int $processTestmode get the testmode value
     * @return string
     */
    public function validateIpAddress($processTestmode)
    {
        $hostAddress = gethostbyname('pay-nn.de');
        if (empty($hostAddress))
            self::displayMessage('Novalnet HOST IP missing');
        if ($hostAddress != hikashop_getIP() && !$processTestmode)
           self::displayMessage('Novalnet callback received. Unauthorised access from the IP ' . hikashop_getIP());

    }

    /**
     * Display the error message
     *
     * @param  string $errorMsg get the debug message
     * @param  string $stopExecution based on the execution show the message
     * @return void
     */
    public static function displayMessage($errorMsg, $stopExecution = false)
    {
        echo $errorMsg;
        if ($stopExecution != 'show')
        {
            exit;
        }
    }

    /**
     * Insert the affiliate details in to database
     *
     * @param  array $data get the affiliate datas
     * @return void
     */
    public static function insertAffAccountActivationDetail($data)
    {
        NovalnetUtilities::insertQuery('#__novalnet_aff_account_detail', array(
            'vendor_id',
            'vendor_authcode',
            'product_id',
            'product_url',
            'activation_date',
            'aff_id',
            'aff_authcode',
            'aff_accesskey'
        ), array(
            "'" . $data['vendor_id'] . "'",
            "'" . $data['vendor_authcode'] . "'",
            "'" . $data['product_id'] . "'",
            "'" . $data['product_url'] . "'",
            "'" . (($data['activation_date'] != '') ? date('Y-m-d H:i:s', strtotime($data['activation_date'])) : '') . "'",
            "'" . $data['aff_id'] . "'",
            "'" . $data['aff_authcode'] . "'",
            "'" . $data['aff_accesskey'] . "'"
        ));
    }

    /**
     * Get the order details for the given TID or order number
     *
     * @return object
     */
    public function getOrderByIncrementId()
    {
        $configDetails = self::getPaymentParams('payment_type="novalnet_payments"');
        $order         = (!empty($this->nnVendorParams['order_no'])) ? NovalnetUtilities::selectQuery(array(
            'table_name' => '#__hikashop_order',
            'column_name' => array(
                'order_full_price',
                'order_id',
                'order_number',
                'order_payment_id',
                'order_user_id',
                'order_payment_method'
            ),
            'condition' => array(
                "order_number = '" . $this->nnVendorParams['order_no'] . "'"
            ),
            'order' => ''
        )) : (!empty($this->nnVendorParams['order_id']) ? NovalnetUtilities::selectQuery(array(
            'table_name' => '#__hikashop_order',
            'column_name' => array(
                'order_full_price',
                'order_id',
                'order_number',
                'order_payment_id',
                'order_user_id',
                'order_payment_method'
            ),
            'condition' => array(
                "order_id = '" . $this->nnVendorParams['order_id'] . "'"
            ),
            'order' => ''
        )) : '');

        if (!$order)
        {
            $hkOrderId = NovalnetUtilities::selectQuery(array(
                'table_name' => '#__hikashop_history',
                'column_name' => array(
                    'history_order_id'
                ),
                'condition' => array(
                    "history_data LIKE '%" . $this->nnVendorParams['shop_tid'] . "%'"
                ),
                'order' => ''
            ));
            $order     = NovalnetUtilities::selectQuery(array(
                'table_name' => '#__hikashop_order',
                'column_name' => array(
                    'order_full_price',
                    'order_id',
                    'order_number',
                    'order_payment_id',
                    'order_user_id',
                    'order_payment_method'
                ),
                'condition' => array(
                    "order_id = '" . $hkOrderId->history_order_id . "'"
                ),
                'order' => ''
            ));
        }

        if (!$order)
        {
            // Define some variables to assign to template
			$callbackComments  = 'Technic team,' . PHP_EOL . PHP_EOL . 'Please evaluate this transaction and contact our Technic team and Backend team at Novalnet.' . PHP_EOL . PHP_EOL ;
			$callbackComments .= 'Merchant ID: ' . $configDetails->vendor . PHP_EOL;
			$callbackComments .= 'Project ID: ' . $configDetails->productId . PHP_EOL;
			$callbackComments .= 'TID: ' . $this->nnVendorParams['tid'] . PHP_EOL;
			$callbackComments .= 'TID status: ' . $this->nnVendorParams['tid_status'] . PHP_EOL; 
			$callbackComments .= 'Order no: ' . $this->nnVendorParams['order_no'] . PHP_EOL;
			$callbackComments .= 'Payment type: ' . $this->nnVendorParams['payment_type'] . PHP_EOL;
			$callbackComments .= 'E-mail: ' . $configDetails->mailTo . PHP_EOL;
			$callbackComments .= PHP_EOL . PHP_EOL . 'Regards,' . PHP_EOL . 'Novalnet Team';
			
            $subject          = sprintf('Critical error on shop system' . JFactory::getApplication()->getCfg('sitename') . 'Order not found for TID: ' . $aryCaptureValues['tid']);

            // Send notification mail to merchant.
            self::criticalMailNotification($callbackComments, $subject);
            self::displayMessage('Novalnet callback script order number not valid');
        }
        else
        {
            if (in_array($this->aryCaptureParams['payment_type'], $this->paymentTypes) || in_array($this->aryCaptureParams['payment_type'], array($this->initialLevelPayments, $this->collectionLevelPayments, $this->chargebackLevelPayments)))
                self::displayMessage('Novalnet callback received. Payment type [' . $this->aryCaptureParams['payment_type'] . '] is mismatched!');

            // Validates and matches the requested order number.
            if (!empty($this->orderReference->order_number) && $this->orderReference->order_number != $order->order_number)
                self::displayMessage('Novalnet callback received. Order no is not valid.');
            return $order;
        }
    }

    /**
     * Handling the communication failure
     *
     * @return void
     */
    public function handleCommunicationFailure()
    {
        $configDetails    = self::getPaymentParams('payment_type="novalnet_payments"');
        $callbackComments = NovalnetUtilities::transactionComments($this->aryCaptureParams['tid'], $this->aryCaptureParams['test_mode']);
        $cancelled        = false;
        // Check the transaction status for cancellation.
        if (!in_array($this->aryCaptureParams['tid_status'], array('100', '91', '90', '98', '99', '85', '86', '75')))
        {
            $cancelled                   = true;
            $OrderStatus['order_status'] = 'cancelled';
        }
        $amount = in_array($this->aryCaptureParams['tid_status'], array('90', '86')) ? 0 : NovalnetUtilities::doFormatAmount($this->aryCaptureParams['amount'], true);
        $merchantDetails = json_encode(array(
            'vendor' => $configDetails->vendor,
            'auth_code' => $configDetails->authCode,
            'product' => $configDetails->productId,
            'tariff' => $configDetails->tariffId,
            'payment_method' => $this->orderReference->order_payment_method,
        ));
        // Insert order details in order history table.
        NovalnetUtilities::insertQuery('#__novalnet_transaction_detail', array(
            'hika_order_id',
            'tid',
            'additional_data',
            'payment_id',
            'amount',
            'gateway_status',
            'payment_request',
            'customer_id'
        ), array(
            "'" . $this->orderReference->order_id . "'",
            "'" . $this->aryCaptureParams['tid'] . "'",
            "'" . $merchantDetails . "'",
            "'" . $this->orderReference->order_payment_id . "'",
            "'" . $amount . "'",
            "'" . $this->aryCaptureParams['tid_status'] . "'",
            "'" . $merchantDetails . "'",
            "'" . $this->orderReference->order_user_id . "'"
        ));

        // Append the cancelled transaction message.
        if ($cancelled)
            $callbackComments .= '<br>'.NovalnetUtilities::responseMsg($this->nnVendorParams);
        self::insertCallbackComments(array(
            'hk_order_id' => $this->orderReference->order_id,
            'order_status' => in_array($this->aryCaptureParams['tid_status'], array('85', '86', '90')) ? $this->OrderStatus['before_status'] : $this->OrderStatus['order_status'],
            'callback_comments' => $callbackComments
        ), $this->orderReference);
        // Send notification mail to merchant.
        self::sendMailNotification($callbackComments);
        // Displays message and stops the execution.
        self::displayMessage($callbackComments);
    }

    /**
     * Get payment type Level based on the payment actions
     *
     * @return integer
     */
    public function getPaymentTypeLevel()
    {
        if (in_array($this->nnVendorParams['payment_type'], $this->initialLevelPayments))
        {
            return 0;
        }
        elseif (in_array($this->nnVendorParams['payment_type'], $this->chargebackLevelPayments))
        {
            return 1;
        }
        elseif (in_array($this->nnVendorParams['payment_type'], $this->collectionLevelPayments))
        {
            return 2;
        }
        elseif (in_array($this->nnVendorParams['payment_type'], $this->transactionCancellation))
        {
            return 3;
        }
        else
        {
            self::displayMessage('Novalnet callback received. Payment type [' . $this->nnVendorParams['payment_type'] . '] is mismatched!');
        }
    }

    /**
     * Send callback mail notification
     *
     * @param  string $mailData get the callback mail show comments
     * @return void
     */
    public static function sendMailNotification($mailData)
    {
        // Get callback details from the merchat script managment.
        $callbackDetails = NovalnetUtilities::getMerchantConfig('callback');

        // Load shop mail configuration using getMailer function.
        $mailer = JFactory::getMailer();

        // Email from address.
        $emailFrom = $mailer->From;

        // Email from name
        $emailFromName = $mailer->FromName;

        // Email data
        $emailTo = $callbackDetails->mailTo;
        $mailer->addRecipient($emailTo);
        $mailer->setSender(array($emailFrom, $emailFromName));
        $mailer->setSubject('Novalnet Callback script notification');
        $mailer->setBody($mailData);
        $mailer->Encoding = 'base64';
        $send = $mailer->Send();
        ($send) ? self::displayMessage('Mail sent successfully!', 'show') : self::displayMessage('Mail not sent!', 'show');
    }

    /**
     * Send callback critical mail notification
     *
     * @param  string $mailData get the callback mail show comments
     * @param  string $subject get the critical mail subject
     * @return void
     */
    public static function criticalMailNotification($mailData, $subject)
    {
        // Get callback details from the merchat script managment.
        $callbackDetails = NovalnetUtilities::getMerchantConfig('callback');

        // Load shop mail configuration using getMailer function.
        $mailer = JFactory::getMailer();

        // Email from address.
        $emailFrom = $mailer->From;

        // Email from name
        $emailFromName = $mailer->FromName;

        // Email data
        $mailData = str_replace('<br />', PHP_EOL, $mailData);
        $mailer->addRecipient(self::$techincMail);
        $mailer->setSender(array($emailFrom, $emailFromName));
        $mailer->setSubject($subject);
        $mailer->setBody($mailData);
        $mailer->Encoding = 'base64';
        $send = $mailer->Send();
        isset($send) ? self::displayMessage('Mail sent successfully!', 'show') : self::displayMessage('Mail not sent!', 'show');
    }

    /**
     * To send order mail
     *
     * @param  string $message get the callback comments
     * @return string
     */
    public function sendOrderMail($message)
    {
        $paymentClass = hikashop_get('class.payment');
        $payment      = $paymentClass->get($this->orderReference->order_payment_id);
        $payment->payment_name .= '</br>' . $message;
        $orderhistory             = new stdClass();
        $orderhistory->notified   = 1;
        $orderhistory->payment_id = $this->orderReference->order_payment_id;
        $this->modifyOrder($this->orderReference->order_id, $this->OrderStatus, $orderhistory, false, false);
    }

    /**
     * Insert callback comments in order history table
     *
     * @param  array $comments get the callback comments
     * @param  object $orderReference get the order reference
     * @return void
     */
    public static function insertCallbackComments($comments, $orderReference)
    {
        NovalnetUtilities::insertQuery('#__hikashop_history', array(
            'history_order_id',
            'history_notified',
            'history_new_status',
            'history_data',
            'history_created',
            'history_type',
            'history_ip',
            'history_reason',
            'history_payment_method',
            'history_user_id',
            'history_payment_id',
            'history_amount'
        ), array(
            "'" . $comments['hk_order_id'] . "'",
            1,
            "'" . $comments['order_status'] . "'",
            "'" . $comments['callback_comments'] . "'",
            "'" . time() . "'",
            "'callback'",
            "'" . hikashop_getIP() . "'",
            "'callback'",
            "'" . $orderReference->order_payment_method . "'",
            "'" . $orderReference->order_user_id . "'",
            "'" . $orderReference->order_payment_id . "'",
            "'" . $orderReference->order_full_price . "'"
        ));

        // Update the order status into shop's db.
        NovalnetUtilities::updateQuery(array(
            '#__hikashop_order'
        ), array(
            'order_status="' . $comments['order_status'] . '"'
        ), array(
            'order_id="' . $comments['hk_order_id'] . '"'
        ));
    }

    /**
     * Insert callback information for logging purpose
     *
     * @param  array  $data get the request data
     * @param  string $orderNo get the order number
     * @param  string $hkOrderId get the order id
     * @return boolean
     */
    public static function logCallbackProcess($data, $orderNo, $hkOrderId)
    {
        if (!empty($data))
        {
            NovalnetUtilities::insertQuery('#__novalnet_callback_detail', array(
                'hika_order_id',
                'callback_amount',
                'reference_tid',
                'callback_tid',
                'callback_datetime'
            ), array(
                "'" . $hkOrderId . "'",
                "'" . $data['amount'] . "'",
                "'" . $data['shop_tid'] . "'",
                "'" . $data['tid'] . "'",
                "'" . hikashop_getDate(time(), '%Y-%m-%d %H:%M:%S') . "'"
            ));
            if ($hkOrderId)
            {
                $updateField = array(
                    'history_created="' . time() . '", history_type="callback"'
                );

                // Updates order status order histroy table.
                NovalnetUtilities::updateQuery(array(
                    '#__hikashop_history'
                ), $updateField[0], array(
                    'history_order_id="' . $hkOrderId . '"'
                ), 'history_id');
            }
        }
        return false;
    }

    /**
     * Get payment method for the given payment method id
     *
     * @param   int $paymentId get the paymemnt id
     * @return string
     */
    public static function getPaymentMethod($paymentId)
    {
        $paymentMethod = NovalnetUtilities::selectQuery(array(
            'table_name' => '#__hikashop_order',
            'column_name' => array(
                'order_payment_method'
            ),
            'condition' => array(
                "order_payment_id = '" . $paymentId . "'"
            ),
            'order' => ''
        ));
        return isset($paymentMethod->order_payment_method) ? $paymentMethod->order_payment_method : '';
    }

    /**
     * Get payment order status for the hikashop payment table.
     *
     * @param  string $select_data
     * @param  int $user_id
     * @param  int $order
     * @param  string $paymentName
     * @return array
     */
    public static function getPaymentParams($select_data, $user_id = false, $order = false, $paymentName = false)
    {
        $results = NovalnetUtilities::selectQuery(array(
            'table_name' => '#__hikashop_payment',
            'column_name' => array(
                'payment_params',
                'payment_type'
            ),
            'condition' => array(
                $select_data
            ),
            'order' => ''
        ));
        $result  = unserialize($results->payment_params);
        if (isset($result->novalnetProductActivationKey))
        {
            if ($user_id)
            {
                $result->tariffType = $result->tariffType;
                $result->tariffId   = $result->tariffId;
                $result             = NovalnetUtilities::formAffiliate($user_id, $result);
            }
            return $result;
        }
        $this->OrderStatus = array(
            "order_status" => $result->transactionConfirmStatus
        );

        // Get paypal and przelewy order pending status.
        if (in_array($paymentName, array(
            'novalnet_paypal',
            'novalnet_przelewy24',
            'novalnet_invoice',
            'novalnet_prepayment',
            'novalnet_cashpayment'
        )))
            $this->OrderStatus["transaction_before_status"] = $result->transactionBeforeStatus;

        if (in_array($results->payment_type, array(
            'novalnet_invoice',
            'novalnet_prepayment',
            'novalnet_cashpayment',
            'novalnet_paypal',
            'novalnet_cc',
            'novalnet_banktransfer',
            'novalnet_sepa',
            'novalnet_przelewy24',
            'novalnet_ideal'
        )))
            $this->OrderStatus["before_status"] = NovalnetUtilities::getOrderStatus($order);

        return $this->OrderStatus;
    }
}
