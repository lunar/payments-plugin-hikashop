<?php

defined('_JEXEC') or die('Restricted access');

include_once(JPATH_SITE . DS . 'plugins/hikashoppayment/lunar/vendor/autoload.php');

use Joomla\CMS\Factory;

use Lunar\Lunar;
use Lunar\Exception\ApiException;

/**
 * 
 */
class plgHikashopLunarStatus extends JPlugin
{
    private $db;
    private $user;
    private $order;
    private $apiClient;
    private $error = null;


    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->db = Factory::getDbo();
        $this->user = Factory::getUser();
    }

    /**
     * 
     */
    public function onBeforeOrderUpdate(&$order, &$do)
    {
        $this->order = $order;

        if (!empty($order->old)) {
            if (! $row = $this->getLunarTransaction()) {
                return;
            }
    
            $this->setApiClient($row->paymentmethod_id); // or $order->order_payment_id

            $data = [
                'id' => $row->transaction_id,
                'amount' => [
                    'currency' => $row->currency_code,
                    'decimal' => (string) $row->amount,
                ]
            ];

            try {
                if ($order->old->order_status != $order->order_status && $order->order_status == "shipped") {
                    if ('created' == $row->status) {
                        $apiResponse = $this->captureTransaction($data);
                    }
                }
                if ($order->old->order_status != $order->order_status && $order->order_status == "refunded") {
                    if ('captured' == $row->status) {
                        $apiResponse = $this->refundTransaction($data);
                    }
                    if ('created' == $row->status) {
                        $apiResponse = $this->voidTransaction($data);
                    }
                }
            } catch (ApiException $e) {
                $this->error = $e->getMessage();
            } catch (Exception $e) {
                $this->error = $e->getMessage();
            }
        }

        if (isset($apiResponse) && true !== $apiResponse) {
            $this->error = $this->getResponseError($apiResponse);
        }

        if ($this->error) {
            hikaInput::get()->set('fail', 1);
            Factory::getApplication()->enqueueMessage($this->error, 'error');
            $do = false;
        }

    }

    /**
     * 
     */
    private function captureTransaction($data)
    {
        $apiResponse = $this->apiClient->payments()->capture($data['id'], $data);

        $sql = "UPDATE #__lunar_transactions SET status='captured',modified_by=".$this->user->id." WHERE order_id='".$this->order->order_id."'";

        if (isset($apiResponse['captureState']) && 'completed' === $apiResponse['captureState']) {
            $this->db->setQuery($sql)->execute();
            return true;
        }

        return $apiResponse;
    }

    /**
     * 
     */
    private function refundTransaction($data)
    {
        $apiResponse = $this->apiClient->payments()->refund($data['id'], $data);

        $sql = "UPDATE #__lunar_transactions SET status='refunded',modified_by=".$this->user->id." WHERE order_id='".$this->order->order_id."'";

        if (isset($apiResponse['refundState']) && 'completed' === $apiResponse['refundState']) {
            $this->db->setQuery($sql)->execute();
            return true;
        }

        return $apiResponse;
    }

    /**
     * 
     */
    private function voidTransaction($data)
    {
        $apiResponse = $this->apiClient->payments()->cancel($data['id'], $data);

        $sql = "UPDATE #__lunar_transactions SET status='voided',modified_by=".$this->user->id." WHERE order_id='".$this->order->order_id."'";

        if (isset($apiResponse['cancelState']) && 'completed' === $apiResponse['cancelState']) {
            $this->db->setQuery($sql)->execute();
            return true;
        }

        return $apiResponse;
    }

    /**
     * 
     */
    private function getLunarTransaction()
    {
        $sql = "SELECT * FROM #__lunar_transactions WHERE order_id='".$this->order->order_id."' LIMIT 1";
        $this->db->setQuery($sql);
        return $this->db->loadObject();
    }

    /**
     * 
     */
    private function setApiClient($method_id)
    {
        $this->db->setQuery("SELECT * FROM #__hikashop_payment WHERE payment_id=" . (int)$method_id);
        $paymentParams = hikashop_unserialize($this->db->loadObject()->payment_params);

        $this->apiClient = new Lunar($paymentParams->app_key, null, !! $_COOKIE['lunar_testmode']);
    }

    /**
     * Gets errors from a failed api request
     * @param array $result The result returned by the api wrapper.
     */
    private function getResponseError($result)
    {
        $error = [];
        // if this is just one error
        if (isset($result['text'])) {
            return $result['text'];
        }

        if (isset($result['code']) && isset($result['error'])) {
            return $result['code'] . '-' . $result['error'];
        }

        // otherwise this is a multi field error
        if ($result) {
			if (isset($result['declinedReason'])) {
				return $result['declinedReason']['error'];
			}

            foreach ($result as $fieldError) {
				if (isset($fieldError['field']) && isset($fieldError['message'])) {
					$error[] = $fieldError['field'] . ':' . $fieldError['message'];
				} else {
					$error = $fieldError;
				}
            }
        }

        return implode(' ', $error);
    }
}
