<?php

require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/classes/service/MultiSafepayService.php';

class WebserviceSpecificManagementDetailedOrders implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $output;
    protected $wsObject;
    protected $urlSegment;

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

    if (!isset($this->wsObject->urlFragments['filter']) && (!isset($this->wsObject->urlFragments['filter']["date_add"]) || !isset($this->wsObject->urlFragments['filter']['date_upd']) || !isset($this->wsObject->urlFragments["filter"]["id"]))) {
        throw new WebserviceException('You have to set an order \'filter[id]\' or \'filter[date_upd]\' or \'filter[date_add]\'', [100, 400]);
    }

    if (isset($this->wsObject->urlFragments['filter']['date_upd'])) {
        $dates = explode(",", str_replace(["[", "]"], "", $this->wsObject->urlFragments['filter']['date_upd']));
        $ids = Order::getOrdersIdByDate($dates[0], $dates[1]);
    } elseif (isset($this->wsObject->urlFragments['filter']['date_add'])) {
        $dates = explode(",", str_replace(["[", "]"], "", $this->wsObject->urlFragments['filter']['date_add']));
        $ids = $this->getOrdersIdByAddDate($dates[0], $dates[1]);
    } else {
        $ids = explode("|", str_replace(["[", "]"], "", $this->wsObject->urlFragments['filter']['id']));
    }

    $orders = [];
    foreach ($ids as $order_id) {
        $order = new Order($order_id);
        $order->items = $order->getCartProducts();

        $order->address_delivery = new Address($order->id_address_delivery);
        if (!is_null($order->address_delivery->id_state)) {
            $order->address_delivery->state = new State($order->address_delivery->id_state);
        }

        $order->invoice_delivery = new Address($order->id_address_invoice);
        if (!is_null($order->invoice_delivery->id_state)) {
            $order->invoice_delivery->state = new State($order->invoice_delivery->id_state);
        }

        $cart = new Cart($order->id_cart);
        $order->cart_rules = $cart->getCartRules();

        $products = [];
        $subtotal = 0;

        foreach ($order->items as $product) {
            $features = $this->getProductFeatures($product['product_id'], $order->id_lang);
		// Obtener el precio del producto con IVA
			$priceWithTax = Product::getPriceStatic(
				$product['product_id'], // ID del producto
				true,                    // Con IVA
				$product['product_attribute_id'] ?? null, // Atributo del producto (si aplica)
				2                        // Decimales
			);

			// Calcular el precio total de acuerdo a la cantidad
			$totalProductPrice = $product['product_quantity'] * $priceWithTax;

			$products[] = [
				'id_product' => $product['product_id'],
				'product_name' => $product['product_name'],
				'quantity' => $product['product_quantity'],
				'product_price' => $priceWithTax,
				'features' => $features,
				'total_price' => $totalProductPrice,
				'image_url' => $this->getProductImageUrl($product['product_id'])
			];

			$subtotal += $totalProductPrice;
        }

        $shippingCost = $order->total_shipping;
        $total = $subtotal + $shippingCost;

        // Obtener el estado actual del pedido y su color
        $orderState = $order->getCurrentOrderState();
        $stateName = $orderState ? $orderState->name[$order->id_lang] : '';
        $stateColor = $orderState ? $orderState->color : '';
		$orderStatus = MultiSafepayService::getMultisafepayPayment($order);
	
		if ($orderStatus['status'] === "initialized") {
        	$paymentLink = MultiSafepayService::createMultiSafepayPayment($order, $cart);
		} else {
			$paymentLink = "Order completed";
		}
        $orderDetails = [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total' => $total,
            'date_add' => $order->date_add,
            'date_upd' => $order->date_upd,
            'order_state' => $stateName,
            'state_color' => $stateColor,
            'products' => $products,
            'payment_link' => $paymentLink
        ];

        $orders[] = $orderDetails;
    }

    $this->output = json_encode(['orders' => $orders]);
}
    private function handleInvoiceDownload()
    {
        $orderId = $this->wsObject->urlFragments['id_order'] ?? null;

        if ($orderId) {
            $order = new Order((int)$orderId);
            if (!Validate::isLoadedObject($order)) {
                $this->sendResponse(['error' => 'Order not found'], 404);
                return;
            }

            if ($order->invoice_number) {
                $invoiceFilePath = $this->getInvoiceFilePath($order);
                if (file_exists($invoiceFilePath)) {
                    $this->downloadInvoice($invoiceFilePath, $order);
                } else {
                    $this->sendResponse(['error' => 'Invoice file not found'], 404);
                }
            } else {
                $this->sendResponse(['error' => 'No invoice available for this order'], 400);
            }
        } else {
            $this->sendResponse(['error' => 'Order ID is required'], 400);
        }
    }

    private function getInvoiceFilePath($order)
    {
        $invoicePrefix = Configuration::get('PS_INVOICE_PREFIX', $order->id_lang);
        $invoiceFileName = sprintf('%s%06d.pdf', $invoicePrefix, $order->invoice_number);
        $invoiceDir = _PS_ROOT_DIR_ . '/pdf/';
        return $invoiceDir . $invoiceFileName;
    }

    private function downloadInvoice($filePath, $order)
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice_' . $order->id . '.pdf"');
        readfile($filePath);
        exit;
    }

    private function generateInvoiceUrl($order)
    {
        if ($order->invoice_number) {
            return _PS_BASE_URL_ . __PS_BASE_URI__ . 'pdf/invoice.php?id_order=' . (int)$order->id;
        }
        return null;
    }

    private function getProductFeatures($productId, $languageId)
    {
        $featureIds = [8, 11, 21];
        $sql = "
            SELECT
                fl.id_feature AS feature_id,
                fl.name AS feature_name,
                fvl.value AS feature_value
            FROM
                " . _DB_PREFIX_ . "feature_product fp
            INNER JOIN
                " . _DB_PREFIX_ . "feature_lang fl ON fp.id_feature = fl.id_feature AND fl.id_lang = " . (int)$languageId . "
            INNER JOIN
                " . _DB_PREFIX_ . "feature_value fv ON fp.id_feature_value = fv.id_feature_value
            INNER JOIN
                " . _DB_PREFIX_ . "feature_value_lang fvl ON fv.id_feature_value = fvl.id_feature_value AND fvl.id_lang = " . (int)$languageId . "
            WHERE
                fp.id_product = " . (int)$productId . "
                AND fp.id_feature IN (" . implode(',', $featureIds) . ");
        ";

        $results = Db::getInstance()->executeS($sql);
        $features = [];
        foreach ($results as $result) {
            $features[] = [
                'feature_id' => $result['feature_id'],
                'feature_name' => $result['feature_name'],
                'feature_value' => $result['feature_value']
            ];
        }
        return $features;
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

    private function sendResponse($response, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($response);
        exit();
    }
}