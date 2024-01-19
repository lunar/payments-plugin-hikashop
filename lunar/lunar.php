<?php

defined('_JEXEC') or die('Restricted access');

include_once('vendor/autoload.php');

use Joomla\CMS\Factory;


/**
 * plgHikashoppaymentLunar class
 */
class plgHikashoppaymentLunar extends hikashopPaymentPlugin
{
    const PLUGIN_VERSION = '1.0.0';

    public $name = 'lunar';
    public $multiple = true;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $lang = Factory::getLanguage();
        $plugins = "plg_hikashoppayment_lunar";
        $base_dir = JPATH_ADMINISTRATOR;
        $lang->load($plugins, $base_dir, $lang->getTag(), true);
    }

    public function onPaymentConfiguration(&$element)
    {
        parent::onPaymentConfiguration($element);
    }

    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);

        $input = Factory::getApplication()->input;
        $ip    = $input->server->get('REMOTE_ADDR');

        $method = &$methods[$method_id];
        $this->modifyOrder($order->order_id, $method->payment_params->order_status, false, false);

        $config = Factory::getConfig();

        $price = round($order->cart->full_total->prices[0]->price_value_with_tax, (int)$this->currency->currency_locale['int_frac_digits']);
        if (strpos($price, '.')) {
            $price = rtrim(rtrim($price, '0'), '.');
        }

        $customs = array();
        $products = hikashop_get('class.product');
        foreach ($order->cart->cart_products as $item) :
            $product = $products->get($item->product_id);
            $product->product_name = str_replace(array('"', "'"), array('\"', "\'"), $product->product_name);
            $customs[] = "{ product: '$product->product_name ($product->product_code)', quantity: $item->cart_product_quantity },";
        endforeach;

        (new JVersion())->getShortVersion();
        hikashop_config()->get('version');

        $vars = array(
            "test_mode" => $this->payment_params->test_mode,
            "currency" => $this->currency->currency_code,
            "exponent" => get_lunar_currency($this->currency->currency_code)['exponent'],
            "amount" => $price,
            "lunar_amount" => get_lunar_amount($price, $this->currency->currency_code),
            "public_key" => $method->payment_params->public_key,
            "order_id" => $order->order_id,
            "order_number" => $order->order_number,
            "method_id" => $method_id,
            "custom" => implode("\n", $customs),
            "sitename" => $config->get("sitename"),
            "customer_name" => $order->cart->billing_address->address_firstname . " " . $order->cart->billing_address->address_lastname,
            "customer_email" => $this->user->user_email,
            "customer_phone" => $order->cart->billing_address->address_telephone,
            "customer_address" => $order->cart->shipping_address->address_street . " " . $order->cart->shipping_address->address_city . " " . $order->cart->shipping_address->address_post_code . " " . $order->cart->shipping_address->address_state->zone_name . " " . $order->cart->shipping_address->address_state->zone_code_2,
            "customer_ip" => $ip,
            "history_url" => $this->orderHistoryURL(),
            "lunar_plugin_version" => self::PLUGIN_VERSION,
        );

        $this->vars = $vars;

        // redirect 

        // return $this->showPage('end');
    }

    public function getPaymentDefaultValues(&$element)
    {
        $element->payment_name = JText::_('HIKASHOP_LUNAR_NAME');
        $element->payment_description = JText::_('HIKASHOP_LUNAR_DESCRIPTION');
        $element->payment_images = 'VISA,MASTERCARD';

        $element->payment_params->order_status = 'pending';
        $element->payment_params->confirmed_status = 'confirmed';
    }

    public function onPaymentConfigurationSave(&$element)
    {
        // @TODO reactivate key validation when available

        parent::onPaymentConfigurationSave($element);
        return true;
    }

    public function onPaymentNotification(&$statuses)
    {
        switch (Factory::getApplication()->input->get("act")) {
            case "savingTransaction":
                $this->savingTransaction();
                break;
        }

        return true;
    }


    public function savingTransaction()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $columns = array(
            'order_id',
            'order_number',
            'paymentmethod_id',
            'amount',
            'created_on',
            'status',
            'currency_code',
            'transaction_id',
            'payment_method'
        );

        $values = array(
            $db->quote($_REQUEST['order_id']),
            $db->quote($_REQUEST['order_number']),
            $db->quote($_REQUEST['method_id']),
            $db->quote($_REQUEST['amount']),
            $db->quote(date('Y-m-d h:i:s')),
            $db->quote('created'),
            $db->quote($_REQUEST['currency']),
            $db->quote($_REQUEST['transaction_id']),
            $db->quote("Lunar")
        );

        $query
            ->insert($db->quoteName('#__hikashop_payment_plg_lunar'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);
        $db->execute();

        $history = new stdClass();
        $email = new stdClass();
        $history->notified = 1;
        $history->amount = $_REQUEST['amount'];
        $history->data = ob_get_clean();
        $email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'Lunar', 'Confirmed', $_REQUEST['order_number']);
        $body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'Lunar', 'Confirmed')) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', 'Confirmed') . "\r\n\r\n";
        $email->body = $body;

        $order = $this->getOrder($_REQUEST['order_id']);
        $this->loadPaymentParams($order);

        $this->modifyOrder($_REQUEST['order_id'], $this->payment_params->confirmed_status, $history, $email);

        // try to clear cart
        $class = hikashop_get('class.cart');
        $class->cleanCartFromSession();

        if ($this->payment_params->capture_mode == "delayed") {
            return;
        }

        if ($order->order_status != $this->payment_params->confirmed_status) {
            // capture payment
            if ($_REQUEST['amount'] > 0) {
                $data        = array(
                    'amount'   => get_lunar_amount($_REQUEST['amount'], $_REQUEST['currency']),
                    'currency' => $_REQUEST['currency']
                );
                \Lunar\Client::setKey($this->payment_params->private_key);
                $response = \Lunar\Transaction::capture($_REQUEST['txnid'], $data);

                if ($response['transaction']['capturedAmount'] > 0) :
                    // update status to capture
                    $sql = "update #__hikashop_payment_plg_lunar set status='captured' where order_id='$order->order_id'";
                    $db->setQuery($sql);
                    $db->execute();
                endif;
            }
        }
    }

    public function orderHistoryURL()
    {
        $db = Factory::getDBO();
        $db->setQuery("select * from #__menu where link like '%com_hikashop&view=user&layout=cpanel%'");
        $row = $db->loadObject();
        if ($row->id) {
            return JRoute::_('index.php?Itemid=' . $row->id);
        } else {
            return JRoute::_('index.php?option=com_hikashop&view=user&layout=cpanel');
        }
    }
}
