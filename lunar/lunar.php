<?php

defined('_JEXEC') or die('Restricted access');

include_once('vendor/autoload.php');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Version;

use Lunar\Lunar;
use Lunar\Exception\ApiException;

/**
 * plgHikashoppaymentLunar class
 */
class plgHikashoppaymentLunar extends hikashopPaymentPlugin
{
    const REMOTE_URL = 'https://pay.lunar.money/?id=';
    const TEST_REMOTE_URL = 'https://hosted-checkout-git-develop-lunar-app.vercel.app/?id=';
    
    const CARD_METHOD = 'card';
    const MOBILEPAY_METHOD = 'mobilePay';
    const LUNAR_METHODS = [
        self::CARD_METHOD,
        self::MOBILEPAY_METHOD,
    ];

    protected string $paymentMethodCode;

    /** @var \Joomla\CMS\Application\CMSApplicationInterface $app */
    protected $app;

    protected $apiClient;
    protected $currencyCode;
    protected $totalAmount;
    protected $args = [];
    protected $intentIdKey = '_lunar_intent_id';
    protected $testMode = false;
    protected $isMobilePay = false;

    public $name = 'lunar';
    public $multiple = true;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->testMode = !!$this->app->input->cookie->get('lunar_testmode'); // same with !!$_COOKIE['lunar_testmode']

        $lang = $this->app->getLanguage();
        $lang->load('plg_hikashoppayment_lunar', JPATH_ADMINISTRATOR, $lang->getTag(), true);

        if ($this->app->isClient('administrator')) {
            $this->maybeAddAdminScript();
        }
    }

    /** 
     * 
     */
    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);

        // $this->order is set on parent, so we use it instead of $order passed here
        
        $price = round(
            $this->order->order_full_price,
            (int) $this->currency->currency_locale['int_frac_digits']
        );
        $this->totalAmount = (string) $price;
        $this->currencyCode = $this->currency->currency_code;
        $this->isMobilePay = $this->getConfig('payment_method') === self::MOBILEPAY_METHOD;

        $this->apiClient = new Lunar($this->getConfig('app_key'), null, $this->testMode);

        $this->setArgs($this->order);

        try {
            $paymentIntentId = $this->apiClient->payments()->create($this->args);
        } catch (ApiException $e) {
            $this->writeToLog('LUNAR EXCEPTION (create intent): ' . $e->getMessage());
            $this->redirectBackWithNotification($e->getMessage());
        }

        $this->modifyOrder(
            $this->order->order_id,
            $this->getConfig('order_status'),
            null, null,
            (object) [
                $this->intentIdKey => $paymentIntentId
            ]
        );
        
        $this->app->redirect(($this->testMode ? self::TEST_REMOTE_URL : self::REMOTE_URL) . $paymentIntentId, 302);
    }

    /**
     * 
     */
    public function onPaymentNotification(&$statuses)
    {
        $orderId = $this->app->input->get("order_id");

        $this->order = $dbOrder = $this->getOrder((int) $orderId);
        $this->loadPaymentParams($dbOrder);
        
        if(empty($dbOrder)) {
            $errorMessage = 'Lunar: could not load any order with ID: ' . $orderId;
            $this->writeToLog($errorMessage);
            return $this->redirectBackWithNotification($errorMessage);
        }

        $this->loadOrderData($dbOrder);

        if (in_array($this->getConfig('payment_method'), self::LUNAR_METHODS)) {
            $this->saveLunarTransaction();
        }

        return true;
    }

    /**
     * SET ARGS
     */
    private function setArgs()
    {
        // order is set in parent::onAfterOrderConfirm
        $billingInfo = $this->order->cart->billing_address;

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
                'orderId' => $this->order->order_id,
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
            'redirectUrl' => JURI::root() . '?option=com_hikashop&ctrl=checkout&task=notify'
                            . '&notif_payment=lunar&tmpl=component&lang=' . $this->locale
                            . '&order_id=' . $this->order->order_id,
            'preferredPaymentMethod' => $this->getConfig('payment_method'),
        ];

        if ($this->isMobilePay) {
            $this->args['mobilePayConfiguration'] = [
                'configurationID' => $this->getConfig('configuration_id'),
                'logo' => $this->getConfig('logo_url'),
            ];
        }

        if ($this->testMode) {
            $this->args['test'] = $this->getTestObject();
        }
    }
    
    /** */
    private function redirectBackWithNotification($errorMessage, $redirectUrl = null)
    {
		$redirectUrl = $redirectUrl ?? Route::_('index.php?option=com_hikashop&ctrl=checkout');

		$this->app->enqueueMessage($errorMessage, 'error');
		$this->app->redirect($redirectUrl, 302);
    }

    /**  */
    private function saveLunarTransaction()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $columns = array(
            'order_id',
            'order_number',
            'paymentmethod_id',
            'payment_name',
            'amount',
            'currency_code',
            'transaction_id',
            'created_by',
        );

        $order_id = $this->order->order_id;
        $order_full_price = $this->order->order_full_price;
        $payment_intent_id = $this->order->order_payment_params->{$this->intentIdKey} ?? null;
        $values = [
            $db->quote($order_id),
            $db->quote($this->order->order_number),
            $db->quote($this->plugin_data->payment_id),
            $db->quote($this->getConfig('payment_method')),
            $db->quote($order_full_price),
            $db->quote(unserialize($this->order->order_currency_info)->currency_code),
            $db->quote($payment_intent_id),
            $db->quote($this->user->id),
        ];

        $query
            ->insert($db->quoteName('#__lunar_transactions'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query)->execute();

        $history = (object) [
            'notified' => 1,
            'amount' => $order_full_price,
            'data' => ob_get_clean(),
        ];
        $email = (object) [
            'subject' => JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', $this->plugin_data->payment_name, 'Confirmed', $this->order->order_number),
            'body' => str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', $this->plugin_data->payment_name, 'Confirmed')) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', 'Confirmed') . "\r\n\r\n",
        ];

        $order = $this->getOrder($order_id);
        $this->loadPaymentParams($order);

        $this->modifyOrder($order_id, $this->getConfig('confirmed_status'), $history, $email);

        // try to clear cart
        $cart = hikashop_get('class.cart');
        $cart->cleanCartFromSession();

        $this->maybeCapturePayment($payment_intent_id);

        $completeUrl = hikashop_completeLink('checkout&task=after_end&order_id='.$order->order_id.$this->url_itemid);

        return $this->app->redirect($completeUrl);
    }

    /**  */
    private function maybeCapturePayment($payment_intent_id)
    {
        if ('instant' !== $this->getConfig('capture_mode')) {
            return;
        }

        if ($this->order->order_status != $this->getConfig('confirmed_status')) {
            try {
                $apiClient = new Lunar($this->getConfig('app_key'), null, $this->testMode);

                $apiResponse = $apiClient->payments()->capture($payment_intent_id, [
                    'amount' => [
                        'currency' => $this->currency->currency_code,
                        'decimal'   => (string) $this->order->order_full_price,
                    ]
                ]);
            } catch (ApiException $e) {
                // silence for customers
                $this->writeToLog($e->getMessage());
            }

            if ('completed' === $apiResponse['captureStatus']) {
                $sql = "UPDATE #__lunar_transactions SET status='captured' WHERE order_id='".$this->order->order_id."'";
                Factory::getDbo()->setQuery($sql)->execute();
            }
        }
    }

    public function getPaymentDefaultValues(&$element)
    {
        $element->payment_description = JText::_('LUNAR_DESCRIPTION');
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
        $productsClass = hikashop_get('class.product');
        foreach ($this->order->cart->cart_products as $item) {
            $product = $productsClass->get($item->product_id);
            $products_array[] = [
                'ID' => $product->product_code,
                'name' => $product->product_name,
                'quantity' => $item->cart_product_quantity,
            ];
        }
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
        // payment_params is set in parent::onAfterOrderConfirm
        return $this->payment_params->{$key} ?? '';
    }

    /**
     * @return string
     */
    protected function getPluginVersion() 
    {
        $xmlStr = file_get_contents(dirname(__FILE__) . "/$this->name.xml");
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
