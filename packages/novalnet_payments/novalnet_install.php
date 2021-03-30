<?php
/**
* This script is used for Creating Novalnet tables
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
* Script : novalnet_install.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_BASE . '/includes/framework.php';

// Initialize object.
new NovalnetPaymentInstall;

/**
 * Novalnet plugin installation class
 *
 * @package Hikashop_Payment_Plugin
 */
class NovalnetPaymentInstall
{
    /**
     * Instance of a class
     *
     */
    public function __construct()
    {
        // Calling the function to create Novalnet tables
        if ($_REQUEST['task'] == 'install' || $_REQUEST['task'] == 'ajax_upload')
        {
            self::createNovalnetCustomTable();
        }
        else
        {
            // Calling the function to remove all Novalnet custom tables
            self::removeNovalnetCustomTable();
        }
        return true;
    }

    /**
     * Create and insert the Novalnet tables to install the Novalnet payments
     *
     * @return void
     */
    public function createNovalnetCustomTable()
    {
        // Create callback table to store callback details.
        self::getDbObject("CREATE TABLE IF NOT EXISTS #__novalnet_callback_detail (
                        id serial COMMENT 'Auto Increment ID',
                        hika_order_id int(11) unsigned COMMENT 'Hikashop order ID',
                        callback_amount int(11) unsigned COMMENT 'Callback paid amount details',
                        reference_tid bigint(20) COMMENT 'Reference Transaction ID',
                        callback_datetime datetime COMMENT 'Callback execution Date and time ',
                        callback_tid bigint(20) COMMENT 'Callback Transaction ID',
                        PRIMARY KEY (id) ,
                        KEY order_no (hika_order_id))");

        // Create affliate table to store affliate account details.
        self::getDbObject("CREATE TABLE IF NOT EXISTS #__novalnet_aff_account_detail (
                        id serial COMMENT 'Auto Increment ID',
                        vendor_id int(11) COMMENT 'Vendor ID',
                        vendor_authcode varchar(255) COMMENT 'Vendor Authentication Code',
                        product_id int(11) unsigned COMMENT 'Product ID',
                        product_url varchar(255) COMMENT 'Product Url',
                        activation_date datetime COMMENT 'Affiliate activation date',
                        aff_id int(11) unsigned COMMENT 'Affiliate ID',
                        aff_authcode varchar(255) COMMENT 'Affiliate Authentication Code',
                        aff_accesskey varchar(255) COMMENT 'Affiliate acesskey',
                        PRIMARY KEY (id) ,
                        KEY vendor_id (vendor_id),
                        KEY product_id (product_id),
                        KEY  aff_id (aff_id))
                        COMMENT='Novalnet merchant / affiliate account information'");

        // Create Novalnet transaction detail table.
        self::getDbObject("CREATE TABLE IF NOT EXISTS #__novalnet_transaction_detail (
                        id serial COMMENT 'Auto Increment ID',
                        tid bigint(20) COMMENT 'Novalnet Transaction Reference ID',
                        gateway_status int(11) COMMENT 'Novalnet transaction status',
                        payment_id int(11) unsigned COMMENT 'Payment ID',
                        hika_order_id int(11) unsigned COMMENT 'Hikashop order ID',
                        order_amount decimal(15,2) DEFAULT '0.00',
                        amount decimal(17,5) COMMENT 'Transaction amount',
                        refunded_amount decimal(17,5) COMMENT 'Refund amount',
                        customer_id char(128)  COMMENT 'Customer ID from shop',
                        additional_data Blob COMMENT 'reference details',
                        payment_specific_data Blob COMMENT 'masked details',
                        payment_request Blob COMMENT 'Payment request details',
                        PRIMARY KEY (id),
                        KEY order_number (hika_order_id)
                        ) COMMENT='Novalnet Transaction History'");

        // Create Affiliate user detail table.
        self::getDbObject("CREATE TABLE IF NOT EXISTS #__novalnet_aff_user_detail (
                        id serial COMMENT 'Auto Increment ID',
                        aff_id int(11) unsigned COMMENT 'Order No from shop',
                        customer_id int(10) COMMENT 'Customer Id from shop',
                        aff_order_no varchar(255) COMMENT 'Affiliate Order no',
                        PRIMARY KEY (id),
                        KEY customer_id (customer_id)
                        ) COMMENT='Novalnet affiliate customer account information'");
    }

    /**
     * Drop Novalnet custom tables and delete configuration details while uninstalling the Novalnet payment plugin
     *
     * @return void
     */
    public function removeNovalnetCustomTable()
    {
        // Delete all Novalnet configuration details from hikashop_payment table.
        self::getDbObject("DELETE FROM #__hikashop_payment WHERE payment_type like 'novalnet_%'");
    }

    /**
     * Returns the shop's database object
     *
     * @param  string $query get the sql query
     * @return object
     */
    public function getDbObject($query)
    {
        // Get the database connector.
        $dbObject = JFactory::getDBO();
        // Passed the query to the database connecter.
        $dbObject->setQuery($query);
        // Execute query using database connecter.
        $dbObject->query();
        return $dbObject;
    }
}
