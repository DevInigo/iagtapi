<?php

require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/classes/service/MultiSafepayService.php';

class WebserviceSpecificManagementCheckout implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $output;
    protected $wsObject;
    protected $module;
    protected $context;

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
        // Verificar el token para todos los métodos
        $authMiddleware = new AuthenticationMiddleware();
        $decodedToken = $authMiddleware->handle();
        if (!$decodedToken) {
            return; // Autenticación fallida, respuesta ya enviada por el middleware
        }

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->handleGetRequest($decodedToken);
                break;
            case 'POST':
                $this->handlePostRequest($decodedToken);
                break;
            case 'PUT':
                $this->handlePutRequest($decodedToken);
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
                break;
        }
    }

    /**
     * Manejar la solicitud GET para visualizar un pedido desde un carrito
     */
    private function handleGetRequest($decodedToken)
	{
		$headers = getallheaders();
		$cartId = $headers['Cart-Id'] ?? null;

		// Verificar si el Cart-Id se proporcionó
		if (!$cartId) {
			$this->sendResponse(['error' => 'Cart ID is required'], 400);
			return;
		}

		// Obtener el carrito actual
		$cart = new Cart((int)$cartId);

		// Validar que el carrito existe
		if (!Validate::isLoadedObject($cart)) {
			$this->sendResponse(['error' => 'Cart not found'], 404);
			return;
		}

		$idAddressDelivery = $headers['Id-Address-Delivery'] ?? null;

		// Obtener la dirección de entrega
		$address = new Address($idAddressDelivery);
		if (!Validate::isLoadedObject($address)) {
			$this->sendResponse(['error' => 'Invalid delivery address'], 400);
			return;
		}

		// Información de depuración
		$idCountry = $address->id_country;
		$idZone = Address::getZoneById($address->id);

		$debugData = [
			'id_address_delivery' => $idAddressDelivery,
			'id_country' => $idCountry,
			'id_zone' => $idZone,
			'available_carriers_initial' => [],
			'shipping_costs_debug' => [],
			'cart_weight' => 0
		];

		// Calcular el peso total del carrito
		$totalWeight = $cart->getTotalWeight();
		$debugData['cart_weight'] = $totalWeight;

		// Obtener los productos y el subtotal del carrito
		$products = $cart->getProducts(true);
		$subtotal = 0;
		$cartDetails = [];

		foreach ($products as $product) {
			$productId = $product['id_product'];
			$quantity = $product['cart_quantity'];
			$priceWithTax = Product::getPriceStatic($productId, true, $product['id_product_attribute'], 2, null, false, true, 1);
			$totalProductPrice = $quantity * $priceWithTax;
			$subtotal += $totalProductPrice;

			$productDetails = [
				'id_product' => $productId,
				'product_name' => $product['name'],
				'quantity' => $quantity,
				'product_price' => $priceWithTax,
				'total_price' => $totalProductPrice,
				'features' => $this->getProductFeatures($productId, $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT')),
				'image_url' => $this->getProductImageUrl($productId)
			];

			$cartDetails[] = $productDetails;
		}

		// Obtener transportistas disponibles para el país y zona
		$availableCarriers = Carrier::getCarriers(
			$this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT'),
			true,  // Only active carriers
			false, // No deleted carriers
			false, // Only include carriers available for the customer's address
			null,
			Carrier::ALL_CARRIERS,
			null,
			$idZone
		);
		
		$carriers = [];
		$id_shop = $cart->id_shop;
		$languageId = Context::getContext()->language->id;
		$baseUrl = Tools::getShopDomainSsl(true, true);

		foreach ($availableCarriers as $carrier) {
			$zoneSupported = Carrier::checkCarrierZone($carrier['id_carrier'], $idZone);

			if ($zoneSupported) {
				$shippingCost = $cart->getPackageShippingCost((int)$carrier['id_carrier'], true);
				$debugData['shipping_costs_debug'][] = [
					'carrier_id' => $carrier['id_carrier'],
					'carrier_name' => $carrier['name'],
					'zone_supported' => $zoneSupported,
					'computed_shipping_cost' => $shippingCost,
					'cart_weight' => $totalWeight,
					'free_shipping' => $carrier['is_free'],
				];

				$delay = Db::getInstance()->getValue(
					'
				SELECT delay
				FROM ' . _DB_PREFIX_ . 'carrier_lang
				WHERE id_carrier = ' . (int)$carrier['id_carrier'] . ' AND id_lang = ' . (int)$languageId . ' AND id_shop = ' . (int)$id_shop
				);

				$carriers[] = [
					'id_carrier' => $carrier['id_carrier'],
					'name' => $carrier['name'],
					'delay' => $delay,
					'shipping_cost' => number_format($shippingCost, 2, '.', ''),
					'logo_url' => $baseUrl . _THEME_SHIP_DIR_ . $carrier['id_carrier'] . '.jpg'
				];
			}
		}

		if (empty($carriers)) {
			$this->sendResponse(['error' => 'No available carriers for the selected delivery address', 'debug' => $debugData], 404);
			return;
		}
		
		// Intentar obtener el enlace de pago de MultiSafepay si hay un pedido asociado al carrito
        $orderId = Order::getIdByCartId((int)$cartId);
        if ($orderId) {
            $order = new Order($orderId);
            
            // Usar el servicio para crear el enlace de pago
            $multisafepayResponse = MultiSafepayService::getMultiSafepayPayment($order, $cart);
			
            if ($multisafepayResponse) {
                $paymentStatus = $multisafepayResponse['payment_methods'][0]['status'];
            } else {
                $debugData['multisafepay_error'] = 'No se pudo obtener el enlace de pago';
            }
        }
		

		// Respuesta final con los detalles del carrito, transportistas permitidos, el enlace de pago y el estado del pedido
		$this->sendResponse([
			'cart' => [
				'products' => $cartDetails,
				'subtotal' => number_format($subtotal, 2, '.', ''),
				'total_weight' => number_format($totalWeight, 2, '.', '')
			],
			'carriers' => $carriers,
			'multisafepay_payment_status' => $paymentStatus,
			'id_customer' => $cart->id_customer,
		]);
	}

    /**
     * Manejar la solicitud POST para crear un pedido desde un carrito
     */
    private function handlePostRequest($decodedToken)
    {
        $headers = getallheaders();
        $cartId = $headers['Cart-Id'] ?? null;
        $idAddressDelivery = $headers['Id-Address-Delivery'] ?? null;

        if (!$cartId) {
            $this->sendResponse(['error' => 'Cart ID is required'], 400);
            return;
        }

        $cart = new Cart((int)$cartId);

        if (!Validate::isLoadedObject($cart)) {
            $this->sendResponse(['error' => 'Cart not found'], 404);
            return;
        }

        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (!isset($requestData['id_carrier']) || !$idAddressDelivery) {
            $this->sendResponse(['error' => 'Address and carrier are required'], 400);
            return;
        }

        $cart->id_address_delivery = (int)$idAddressDelivery;
        $cart->id_address_invoice = (int)$idAddressDelivery;
        $cart->id_carrier = (int)$requestData['id_carrier'];

        if (!$cart->update()) {
            $this->sendResponse(['error' => 'Failed to update cart with address and carrier'], 500);
            return;
        }

        $customer = new Customer((int)$cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->sendResponse(['error' => 'Customer not found'], 404);
            return;
        }

        $carrier = new Carrier((int)$cart->id_carrier);
        if (!Validate::isLoadedObject($carrier)) {
            $this->sendResponse(['error' => 'Carrier not found'], 404);
            return;
        }

        $this->module = Module::getInstanceByName('multisafepayofficial');
        if (!$this->module || !Validate::isLoadedObject($this->module)) {
            $this->logMessage('Module "multisafepayofficial" not found or not loaded.');
            $this->sendResponse(['error' => 'Failed to load MultiSafepayofficial module'], 500);
            return;
        }

        try {
            
			$initialStatus = Configuration::get('MULTISAFEPAY_OFFICIAL_OS_INITIALIZED');
            $this->module->validateOrder(
                (int)$cart->id,
                $initialStatus,
                $cart->getOrderTotal(true, Cart::BOTH),
                'MultiSafepay',
                null,
                [],
                (int)$cart->id_currency,
                false,
                $customer->secure_key
            );


            $orderId = $this->module->currentOrder;
            $order = new Order($orderId);

            if (Validate::isLoadedObject($order)) {
				$order->setCurrentState($initialStatus);
                // Actualiza el campo `source` a 'app' en la base de datos
				Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'orders SET source = "app" WHERE id_order = ' . (int)$order->id);
                // Usar el servicio para crear el enlace de pago
                $paymentLink = MultiSafepayService::createMultiSafepayPayment($order, $cart);
				// Consultar el nombre del estado en la tabla order_state_lang
                $sql = 'SELECT name FROM ' . _DB_PREFIX_ . 'order_state_lang WHERE id_order_state = ' . (int)$initialStatus . ' AND id_lang = ' . (int)$order->id_lang;
                $orderStatusName = Db::getInstance()->getValue($sql);
                if ($paymentLink) {
                    $this->sendResponse([
                        'success' => 'Order created',
                        'order_id' => $orderId,
						'reference' => $order->reference,
						'order_status' => $initialStatus,
						'order_status_name' => $orderStatusName,
                        'payment_link' => $paymentLink
                    ], 201);
                } else {
                    $this->sendResponse(['error' => 'Failed to create payment link'], 500);
                }
            } else {
                $this->sendResponse(['error' => 'Failed to load order after validation'], 500);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener las características del producto
     */
    private function getProductFeatures($productId, $languageId)
    {
        $featureIds = [11, 21, 8];
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
	
	private function setStatus($transaction)
	{
		// Lógica condicional para devolver 'initialized' para ciertos estados
		if ($transaction->status === 'new' || $transaction->status === 'pending') {
			return Configuration::get('MULTISAFEPAY_OFFICIAL_OS_INITIALIZED');
		}

		// Devolver otros estados normalmente
		$orderStatus = [
			'initialized' => Configuration::get('MULTISAFEPAY_OFFICIAL_OS_INITIALIZED'),
			'completed' => Configuration::get('PS_OS_PAYMENT'),
			'uncleared' => Configuration::get('MULTISAFEPAY_OFFICIAL_OS_UNCLEARED'),
			'refunded' => Configuration::get('PS_OS_REFUND'),
			'partial_refunded' => Configuration::get('MULTISAFEPAY_OFFICIAL_OS_PARTIAL_REFUNDED'),
			'chargeback' => Configuration::get('MULTISAFEPAY_OFFICIAL_OS_CHARGEBACK'),
			'shipped' => Configuration::get('PS_OS_SHIPPING'),
			'cancelled' => Configuration::get('PS_OS_CANCELED'),
			'declined' => Configuration::get('PS_OS_CANCELED'),
			'expired' => Configuration::get('PS_OS_CANCELED')
		];

		return isset($orderStatus[$transaction->status]) ? $orderStatus[$transaction->status] : Configuration::get('PS_OS_ERROR');
	}

    /**
     * Obtener la URL de la imagen del producto
     */
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

    private function sendResponse($response, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($response);
        exit();
    }

    public function getContent()
    {
        return json_encode(['message' => 'Operation successful']);
    }
}
