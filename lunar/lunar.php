<?php

defined('_JEXEC') or die('Restricted access');

include_once('vendor/autoload.php');

use Joomla\CMS\Factory;
use Joomla\CMS\Version;

use Lunar\Lunar;
use Lunar\Exception\ApiConnection;

/**
 * plgHikashoppaymentLunar class
 */
class plgHikashoppaymentLunar extends hikashopPaymentPlugin
{
    const REMOTE_URL = 'https://pay.lunar.money/?id=';
    const TEST_REMOTE_URL = 'https://hosted-checkout-git-develop-lunar-app.vercel.app/?id=';
    
    const CARD_METHOD = 'card';
    const MOBILEPAY_METHOD = 'mobilePay';

    protected string $paymentMethodCode;

    /** @var \Joomla\CMS\Application\CMSApplicationInterface $app */
    protected $app;

    protected $method;
    protected $apiClient;
    protected $currencyId;
    protected $currencyCode;
    protected $totalAmount;
    protected $emailCurrency;
    protected $check;
    protected $args = [];
    protected $errorMessage = null;
    protected $intentIdKey = '_lunar_intent_id';
    protected $testMode = false;
    protected $isMobilePay = false;

    public $name = 'lunar';
    public $multiple = true;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->app = Factory::getApplication();
        $this->testMode = !!$this->app->input->cookie->get('lunar_testmode'); // same with !!$_COOKIE['lunar_testmode']

        $lang = Factory::getLanguage();
        $plugins = "plg_hikashoppayment_lunar";
        $base_dir = JPATH_ADMINISTRATOR;
        $lang->load($plugins, $base_dir, $lang->getTag(), true);

        if ($this->app->isClient('administrator')) {
            $this->maybeAddAdminScript();
        }
        // $this->paymentMethodCode = 'card'; // temporary
    }

    /** 
     * 
     */
    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);

        $price = round(
            $order->cart->full_total->prices[0]->price_value_with_tax,
            (int) $this->currency->currency_locale['int_frac_digits']
        );
        $this->totalAmount = (string) $price;
        $this->currencyCode = $this->currency->currency_code;

        $this->method = $methods[$method_id];

        $this->modifyOrder($order->order_id, $this->getConfig('order_status'), false, false);

        $this->setArgs($order);

        $this->apiClient = new Lunar($this->getConfig('app_key'), null, $this->testMode);
        $paymentIntentId = $this->apiClient->payments()->create($this->args);

        
        $this->app->redirect(($this->testMode ? self::TEST_REMOTE_URL : self::REMOTE_URL) . $paymentIntentId, 302);


        // $price = round($order->cart->full_total->prices[0]->price_value_with_tax, (int)$this->currency->currency_locale['int_frac_digits']);
        // if (strpos($price, '.')) {
        //     $price = rtrim(rtrim($price, '0'), '.');
        // }

        // $customs = array();
        // $products = hikashop_get('class.product');
        // foreach ($order->cart->cart_products as $item) :
        //     $product = $products->get($item->product_id);
        //     $product->product_name = str_replace(array('"', "'"), array('\"', "\'"), $product->product_name);
        //     $customs[] = "{ product: '$product->product_name ($product->product_code)', quantity: $item->cart_product_quantity },";
        // endforeach;
        

        // $vars = array(
        //     "order_id" => $order->order_id,
        //     "order_number" => $order->order_number,
        //     "method_id" => $method_id,
        //     "custom" => implode("\n", $customs),
        //     "sitename" => ,

        //     // "history_url" => $this->orderHistoryURL(),
        // );


 
    }


    /**
     * SET ARGS
     */
    private function setArgs($order)
    {
        $billingInfo = $order->cart->billing_address;

        $this->args = [
            'integration' => [
                'key' => $this->getConfig('public_key'),
                'name' => $this->getConfig('shop_title') ?? $this->app->get('sitename'),
                'logo' => $this->getConfig('logo_url'),
            ],
            'amount' => [
                'currency' => $this->currencyCode,
                'decimal' => $this->totalAmount,
            ],
            'custom' => [
                'orderId' => $order->order_id,
                'products' => $this->getFormattedProducts(),
                'customer' => [
                    'name' => $billingInfo->address_firstname . ' ' . $billingInfo->address_lastname,
                    'email' => $this->user->user_email,
                    'telephone' => $billingInfo->address_telephone,
                    'address' => $billingInfo->address_street . ' ' . $billingInfo->address_city . ' ' . 
                        $billingInfo->address_post_code . ' ' . $billingInfo->address_state->zone_name . ' ' . 
                        $billingInfo->address_state->zone_code_2,
                    'ip' => $this->app->input->server->get('REMOTE_ADDR'), // @TODO check hikashop_getIP() compatibility
                ],
                'platform' => [
                    'name' => 'Joomla',
                    'version' => (new Version())->getShortVersion(),
                ],
                'ecommerce' => [
                    'name' => 'HikaShop',
                    'version' => hikashop_config()->get('version'),
                ],
                'lunarPluginVersion' => $this->getPluginVersion(),
            ],
            'redirectUrl' => JURI::root()
                            . '?option=com_hikashop&ctrl=checkout&task=notify'
                            . '&notif_payment=lunar&tmpl=component&lang=en' // get lang code 
                            . '&pm=' . $this->method->payment_id,
            'preferredPaymentMethod' => $this->getConfig('payment_method'),
        ];

        if ($this->isMobilePay) {
            $this->args['mobilePayConfiguration'] = [
                'configurationID' => $this->getConfig('configuration_id'),
                'logo' => $this->method->logo_url,
            ];
        }
    
        if ($this->testMode) {
            $this->args['test'] = $this->getTestObject();
        }
    }

    public function onPaymentNotification(&$statuses)
    {
        switch (Factory::getApplication()->input->get("pm")) {
            case "card":
                // $this->savingTransaction();
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

        $history = (object) [
            'notified' => 1,
            'amount' => $_REQUEST['amount'],
            'data' => ob_get_clean(),
        ];
        $email = (object) [
            'subject' => JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', $this->method->payment_name, 'Confirmed', $_REQUEST['order_number']),
            'body' => str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', $this->method->payment_name, 'Confirmed')) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', 'Confirmed') . "\r\n\r\n",
        ];

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
                    'amount'   => (string) $_REQUEST['amount'],
                    'currency' => $_REQUEST['currency']
                );

                $response = $this->apiClient->payments()->capture($_REQUEST['txnid'], $data);

                if ($response['transaction']['capturedAmount'] > 0) :
                    // update status to capture
                    $sql = "update #__hikashop_payment_plg_lunar set status='captured' where order_id='$order->order_id'";
                    $db->setQuery($sql);
                    $db->execute();
                endif;
            }
        }
    }

    /** */
    private function getPaymentIntentCookie()
    {
        return $this->app->input->cookie->get($this->intentIdKey);
    }

    /** */
    private function setPaymentIntentCookie($paymentIntentId = null, $expire = 0)
    {
        $this->app->input->cookie->set($this->intentIdKey, $paymentIntentId, $expire, '/', '', false, true);
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

    public function getPaymentDefaultValues(&$element)
    {
        $element->payment_images = 'VISA,MASTERCARD';
        $element->payment_params->payment_method = 'card';
        $element->payment_params->capture_mode = 'delayed';
        $element->payment_params->order_status = 'pending';
        $element->payment_params->confirmed_status = 'confirmed';
    }

    public function onPaymentConfigurationSave(&$element)
    {
        // @TODO reactivate key validation when available

        parent::onPaymentConfigurationSave($element);
        return true;
    }

        /**
     * 
     */
    private function getFormattedProducts()
    {
        $products_array = [];
        // foreach ($this->cart->products as $product) {
        //     $products_array[] = [
        //         'ID' => $product->id,
        //         'name' => $product->product_name,
        //         'quantity' => $product->quantity,
        //     ];
        // }
        return $products_array;
    }

    /**
     *
     */
    private function getTestObject(): array
    {
        return [
            "card"        => [
                "scheme"  => "supported",
                "code"    => "valid",
                "status"  => "valid",
                "limit"   => [
                    "decimal"  => "25000.99",
                    "currency" => $this->currencyCode,
                    
                ],
                "balance" => [
                    "decimal"  => "25000.99",
                    "currency" => $this->currencyCode,
                    
                ]
            ],
            "fingerprint" => "success",
            "tds"         => array(
                "fingerprint" => "success",
                "challenge"   => true,
                "status"      => "authenticated"
            ),
        ];
    }

    /**
     * 
     */
    protected function getConfig($key) 
    {
        return $this->method->payment_params->{$key} ?? '';
    }

    /**
     * @return string
     */
    protected function getPluginVersion() 
    {
        $xmlStr = file_get_contents("$this->name.xml");
        $xmlObj = simplexml_load_string($xmlStr);
        return (string) $xmlObj->version;
    }

    /**
     * 
     */
    private function maybeAddAdminScript()
    {
        Factory::getDocument()->addScriptDeclaration('
            jQuery(document).ready(function( $ ) {
                
                let radio0 = jQuery("#data_payment_payment_params_payment_methodcard");
                let radio1 = jQuery("#data_payment_payment_params_payment_methodmobilePay");

                if (radio1.is(":checked")) {
                    manageConfigIdField(radio1.val());
                } else {
                    manageConfigIdField(radio0.val());
                }
                                
                radio0.on("change", function() { manageConfigIdField($(this).val()) });
                radio1.on("change", function() { manageConfigIdField($(this).val()) });

                function manageConfigIdField(methodCode) {
                    let configIdField = $("#lunar_configuration_id").closest("tr");
                    if ("mobilePay" === methodCode) {
                        configIdField.show();
                    } else {
                        configIdField.hide();
                    }
                }
            });
        ');
    }

}
