<?php

require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';

class WebserviceSpecificManagementOrders implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $output;
    protected $wsObject;

    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;
        return $this;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;
        return $this;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function setUrlSegment($segments)
    {
        $this->urlSegment = $segments;
        return $this;
    }

    public function getUrlSegment()
    {
        return $this->urlSegment;
    }

   public function manage()
{
    $authMiddleware = new AuthenticationMiddleware();
    $decodedToken = $authMiddleware->handle();
    if (!$decodedToken) {
        return;
    }

    $idLang = isset($this->wsObject->urlFragments['language']) ? (int)$this->wsObject->urlFragments['language'] : Configuration::get('PS_LANG_DEFAULT');
    $customerId = (int)$decodedToken->id_customer;

    // Parámetros de paginación desde el filtro
    $page = isset($this->wsObject->urlFragments['filter']['page']) ? (int)$this->wsObject->urlFragments['filter']['page'] : 1;
    $perPage = isset($this->wsObject->urlFragments['filter']['per_page']) ? (int)$this->wsObject->urlFragments['filter']['per_page'] : 10;
    $offset = ($page - 1) * $perPage;

    // Obtener los pedidos del cliente con paginación
    $orders = Order::getCustomerOrders($customerId);
    $totalOrders = count($orders);
    $orders = array_slice($orders, $offset, $perPage);
    $orderDetails = [];

    foreach ($orders as $orderData) {
        $order = new Order($orderData['id_order']);
        $products = $order->getProducts();

        $productDetails = [];
        foreach ($products as $product) {
            $imageUrl = $this->getProductImageUrl($product['product_id']);
            $productDetails[] = [
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'image_url' => $imageUrl
            ];
        }

        // Obtener el estado actual del pedido y su color
        $orderState = $order->getCurrentOrderState();
        $stateName = $orderState ? $orderState->name[$idLang] : '';
        $stateColor = $orderState ? $orderState->color : '';

        $orderDetails[] = [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'date_add' => $order->date_add,
            'total_paid' => $order->total_paid,
            'products' => $productDetails,
            'order_state' => $stateName,
            'state_color' => $stateColor
        ];
    }

    // Preparar la respuesta con paginación
    $this->output = json_encode([
        'orders' => $orderDetails,
        'page' => $page,
        'per_page' => $perPage,
        'total_orders' => $totalOrders,
        'total_pages' => ceil($totalOrders / $perPage),
    ]);
}

private function getProductImageUrl($productId)
{
    $imageId = Db::getInstance()->getValue('SELECT id_image FROM ' . _DB_PREFIX_ . 'image WHERE id_product = ' . (int)$productId . ' AND cover = 1');
    if ($imageId) {
        $baseUrl = 'https://' . Tools::getShopDomainSsl() . __PS_BASE_URI__ . 'img/p/';
        $imagePath = implode('/', str_split($imageId)) . '/' . $imageId . '.jpg';
        return $baseUrl . $imagePath;
    }
    return null;
}


	public function getContent()
	{
		return $this->output;
	}

}
