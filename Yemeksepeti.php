<?php
/*
 * Author: Ömer Faruk KÜÇÜK - omerfarukucuk.com
 * Github: github.com/omerfarukucuk
 */

if (!extension_loaded("soap")) {
    exit('Please install SOAP PHP extension.');
}

class Yemeksepeti
{

    // YemekSepeti API URL
    const YS_API_URL = 'http://messaging.yemeksepeti.com/messagingwebservice/integration.asmx?WSDL';

    private $username = '';
    private $password = '';
    private $catalogName = '';
    private $categoryId = '';

    // Restaurant States
    const RESTAURANT_OPEN = 'Open';
    const RESTAURANT_CLOSED = 'Closed';
    const RESTAURANT_HUGE_DEMAND = 'HugeDemand';

    // Order States
    const ORDER_ACCEPTED = 'Accepted';
    const ORDER_REJECTED = 'Rejected';
    const ORDER_CANCELLED = 'Cancelled';
    const ORDER_ON_DELIVERY = 'OnDelivery';
    const ORDER_DELIVERED = 'Delivered';
    const ORDER_TECHNICAL_REJECTED = 'TechnicalRejected';

    // SOAP Client
    private $client;

    public function __construct($requirements = array())
    {
        $this->username = $requirements['username'];
        $this->password = $requirements['password'];
        $this->catalogName = $requirements['catalogName'];
        $this->categoryId = $requirements['categoryId'];
        $this->init();
    }

    public function getMenu()
    {
        $response = $this->client->GetMenu();
        return $this->parse($response->GetMenuResult->any)['Menu'];
    }

    public function getProductOptions($productId, $options)
    {
        $arr = [];
        foreach($options as $_option) {
            if ($_option->ProductId == $productId) {
                $arr[] = $_option;
            }
        }
        return $arr;
    }

    public function getAllMessages($version = 'V1')
    {
        $resultVariableName = 'GetAllMessagesResult';
        if (strtoupper($version) == 'V1') {
            $response = $this->client->GetAllMessages();
        } else {
            $resultVariableName = 'GetAllMessages' . strtoupper($version) . 'Result';
            $response = $this->client->GetAllMessagesV2();
        }

        $parsed = $this->parse($response->$resultVariableName);
        return array_map(function($order) {
            return [
                'order' => $this->_getAttributes($order),
                'products' => array_map(function($_i){
                    $arr = $this->_getAttributes($_i);
                    if (isset($_i['promotion'])){
                        $arr['promotion'] = $this->_getAttributes($_i['promotion']);
                    }
                    return $arr;
                }, $order['product'])
            ];
        }, $parsed['order']);
    }

    public function updateOrder($orderId, $orderState, $reason = '')
    {
        $response = $this->client->UpdateOrder([
            'orderId' => $orderId,
            'orderState' => $orderState,
            'reason' => $reason
        ]);
        return $response->UpdateOrderResult == 'OK';
    }

    public function updateRestaurantState($state)
    {
        $response = $this->client->UpdateRestaurantState([
            'catalogName' => $this->catalogName,
            'categoryName' => $this->categoryId,
            'restaurantState' => $state
        ]);
        return $response->UpdateRestaurantStateResult == 'OK';
    }

    public function getMessage($version = 'V1')
    {
        $resultVariableName = 'GetMessageResult';
        if (strtoupper($version) == 'V1') {
            $response = $this->client->GetMessage();
        } else {
            $resultVariableName = 'GetMessage' . strtoupper($version) . 'Result';
            $response = $this->client->GetMessageV2();
        }

        $parsed = $this->parse($response->$resultVariableName);
        return [
            'message' => $this->_getAttributes($parsed),
            'product' => array_map(function($element) {
                return $this->_getAttributes($element);
            }, $parsed['product'])
        ];
    }

    public function getPaymentTypes()
    {
        $response = $this->client->GetPaymentTypes();
        return $this->parse($response->GetPaymentTypesResult->any)['NewDataSet']['PaymentMethods'];
    }

    public function getRestaurantDeliveryAreas()
    {
        $response = $this->client->GetRestaurantDeliveryAreas([
            'catalogName' => $this->catalogName,
            'categoryName' => $this->categoryId
        ]);
        return $this->parse($response->GetRestaurantDeliveryAreasResult->any)['NewDataSet'];
    }

    public function getRestaurantList()
    {
        $response = $this->client->GetRestaurantList();
        return $this->parse($response->GetRestaurantListResult->any)['RestaurantList'];
    }

    public function getRestaurantStatus()
    {
        $response = $this->client->GetRestaurantStatus([
            'catalogName' => $this->catalogName,
            'categoryName' => $this->categoryId
        ]);
        return $response->GetRestaurantStatusResult;
    }

    public function isRestaurantOpen()
    {
        $response = $this->client->IsRestaurantOpen([
            'catalogName' => $this->catalogName,
            'categoryName' => $this->categoryId
        ]);
        return $response->IsRestaurantOpenResult;
    }

    public function setMessageSuccessful($message_id)
    {
        $response = $this->client->MessageSuccessful([
            'messageId' => $message_id
        ]);
        return $response;
    }

    private function init()
    {
        try {
            $header = new SoapHeader('http://tempuri.org/', 'AuthHeader', [
                'UserName' => $this->username,
                'Password' => $this->password
            ]);
            $this->client = new SoapClient(self::YS_API_URL, [
                'login' => $this->username,
                'password' => $this->password,
                'soap_version' => '1.2'
            ]);
            $this->client->__setSoapHeaders($header);
            //print_r($this->client->__getFunctions());
            //exit;
        } catch(SoapFault $e) {
            echo $e->getMessage();
        }
    }

    private function parse($result)
    {
        return json_decode(json_encode(simplexml_load_string($result)), true);
    }

    private function _getAttributes($arr)
    {
        return $arr['@attributes'];
    }

}