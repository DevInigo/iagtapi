<?php
use GeoIp2\Database\Reader;
require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';

class WebserviceSpecificManagementCart implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

    public function __construct()
    {
        include_once(_PS_MODULE_DIR_.'iagtapi/config.php');
    }

    /* ------------------------------------------------
     * GETTERS & SETTERS
     * ------------------------------------------------ */

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
        // Verificar el token para métodos GET, POST y PUT
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

	private function handleGetRequest($decodedToken)
    {
        $headers = getallheaders();
        $cartId = $headers['Cart-Id'] ?? null;
        $carrierId = $headers['Id-Carrier'] ?? null;
        $languageId = $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

        if (!$cartId) {
            $this->sendResponse(['error' => 'Cart ID is required'], 400);
            return;
        }

        $cart = new Cart((int)$cartId);

        if (!Validate::isLoadedObject($cart)) {
            $this->sendResponse(['error' => 'Cart not found'], 404);
            return;
        }
		
		// Verificar si ya existe un pedido relacionado con el carrito
		$orderId = Order::getIdByCartId((int)$cartId);
		if ($orderId) {
			// Si existe un pedido, enviar una respuesta con un carrito vacío
			$this->sendResponse([
				'cart' => [],
				'message' => 'Cart is empty because an order is already associated with it'
			], 200);
			return;
		}


        // Calcular subtotal y obtener detalles del carrito si no está vacío
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
                'features' => $this->getProductFeatures($productId, $languageId),
                'image_url' => $this->getProductImageUrl($productId)
            ];

            $cartDetails[] = $productDetails;
        }

        // Resto del procesamiento si el carrito contiene productos
        $geoCountry = $this->getGeolocatedCountry();
		$idZone = $geoCountry->id_zone;
        if (!$geoCountry) {
            $this->sendResponse(['error' => 'Country could not be determined'], 404);
            return;
        }
		
		
        $availableCarriers = Carrier::getCarriers(
            $languageId,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS,
            null,
            $geoCountry->id_zone
        );
		
        if (empty($availableCarriers)) {
            $this->sendResponse(['error' => 'No available carriers for the selected country'], 404);
            return;
        }
        // Seleccionamos transportista basado en la cabecera
        if ($carrierId && in_array($carrierId, array_column($availableCarriers, 'id_carrier'))) {
            $shippingCost = $cart->getPackageShippingCost($carrierId, true);
        }else {
            $selectedCarrier = $availableCarriers[0]['id_carrier'];
			$shippingCost = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        }
        
        $cart->id_carrier = $selectedCarrier;
        $cart->update();

        
        $totalCartPrice = $subtotal + $shippingCost;

        $cartRules = $cart->getCartRules();
        $discountDetails = [];
        $totalDiscount = 0;

        if (!empty($cartRules)) {
            foreach ($cartRules as $rule) {
                $discountAmount = 0;
                if ($rule['reduction_percent'] > 0) {
                    $discountAmount = $subtotal * ($rule['reduction_percent'] / 100);
                } elseif ($rule['reduction_amount'] > 0) {
                    $discountAmount = $rule['reduction_amount'];
                }
                $totalDiscount += $discountAmount;

                $discountDetails[] = [
                    'code' => $rule['code'],
                    'value' => $rule['reduction_amount'] > 0 ? $rule['reduction_amount'] : ($rule['reduction_percent'] . '%')
                ];
            }
        }

        $subtotalAfterDiscount = $subtotal - $totalDiscount;

        $response = [
            'products' => $cartDetails,
            'subtotal' => number_format($subtotal, 3, '.', ''),
            'subtotal_after_discount' => number_format($subtotalAfterDiscount, 3, '.', ''),
            'shipping_cost' => number_format($shippingCost, 3, '.', ''),
            'total' => number_format($subtotalAfterDiscount + $shippingCost, 3, '.', ''),
        ];

        if (!empty($discountDetails)) {
            $response['discounts'] = $discountDetails;
        }

        $this->sendResponse($response);
    }



    private function handlePostRequest($decodedToken)
    {
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (!isset($requestData['id_currency']) || !isset($requestData['products'])) {
            $this->sendResponse(['error' => 'Missing required parameters'], 400);
            return;
        }

        $customerId = (int)$decodedToken->id_customer;

        // Obtener secure_key del cliente
        $secureKey = Db::getInstance()->getValue('SELECT secure_key FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = ' . $customerId);

        $cart = new Cart();
        $cart->id_customer = $customerId;
        $cart->id_currency = (int)$requestData['id_currency'];
        $cart->id_lang = (int)($requestData['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT'));
        $cart->id_shop_group = (int)($requestData['id_shop_group'] ?? 1);
        $cart->id_shop = (int)($requestData['id_shop'] ?? 1);
        $cart->secure_key = $secureKey;

        // Crear dirección geolocalizada ficticia
        $geoCountry = $this->getGeolocatedCountry();

        if ($geoCountry) {
            $cart->id_address_delivery = (int)$geoCountry->id;
            $cart->id_address_invoice = (int)$geoCountry->id;
        }

        if ($cart->add()) {
            foreach ($requestData['products'] as $product) {
                $cart->updateQty(
                    (int)$product['quantity'],
                    (int)$product['id_product'],
                    (int)($product['id_product_attribute'] ?? 0),
                    (int)($product['id_customization'] ?? 0),
                    'up',
                    (int)$cart->id_address_delivery,
                    new Shop((int)$cart->id_shop)
                );
            }
            $this->sendResponse([
                'success' => 'Cart created successfully',
                'id_cart' => $cart->id,
                'id_customer' => $cart->id_customer
            ], 201);
        } else {
            $this->sendResponse(['error' => 'Failed to create cart'], 500);
        }
    }

private function handlePutRequest($decodedToken)
{
    $headers = getallheaders();
    $cartId = $headers['Cart-Id'] ?? null;

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

    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->sendResponse(['error' => 'Invalid JSON format'], 400);
        return;
    }

    if (!isset($requestData['products'])) {
        $this->sendResponse(['error' => 'Products data is required'], 400);
        return;
    }

    // Borrar todos los productos actuales del carrito
    $cart->deleteAssociations();

    $subtotal = 0;

    // Agregar cada producto nuevo al carrito
    foreach ($requestData['products'] as $product) {
        // Obtener el precio con IVA
        $priceWithTax = Product::getPriceStatic(
            (int)$product['id_product'],
            true, // Con IVA
            (int)($product['id_product_attribute'] ?? 0),
            2 // Decimales
        );

        // Calcular el subtotal acumulando el precio con IVA
        $subtotal += $priceWithTax * (int)$product['quantity'];

        // Añadir producto al carrito
        $cart->updateQty(
            (int)$product['quantity'],
            (int)$product['id_product'],
            (int)($product['id_product_attribute'] ?? 0),
            (int)($product['id_customization'] ?? 0),
            'up',
            (int)$cart->id_address_delivery,
            new Shop((int)$cart->id_shop)
        );
    }

    // Verificar y aplicar el código de descuento
    if (isset($requestData['discount_code']) && $requestData['discount_code']) {
        $cartRule = new CartRule(CartRule::getIdByCode($requestData['discount_code']));
        if (Validate::isLoadedObject($cartRule)) {
            $cart->addCartRule($cartRule->id);
        } else {
            $this->sendResponse(['error' => 'Invalid discount code'], 400);
            return;
        }
    }

    // Establecer el transportista en el carrito si se proporciona
    $carrierId = $requestData['carrier_id'] ?? null;

	
    if ($carrierId) {
        $cart->id_carrier = (int)$carrierId;
    
        // Configurar la opción de entrega con el ID del transportista
        $deliveryOption = [(int)$cart->id_address_delivery => (int)$carrierId . ','];
        $cart->setDeliveryOption($deliveryOption);
    }

    // Recalcular el carrito
    $cart->update();

    // Calcular el coste de envío en función del transportista seleccionado y el total final
    $shippingCost = $cart->getPackageShippingCost((int)$carrierId, true);
    $total = $subtotal + $shippingCost;

    $this->sendResponse([
        'success' => 'Cart updated successfully',
        'id_cart' => $cart->id,
        'shipping_cost' => number_format($shippingCost, 2, '.', ''),
        'subtotal_with_tax' => number_format($subtotal, 2, '.', ''),
        'total_with_tax' => number_format($total, 2, '.', ''),
    ], 200);
}



    private function calculateShippingCostWithoutAddress($cart, $geoCountryId)
    {
        $deliveryOption = $cart->simulateCarrierSelectedOutput();
        $shippingCost = 0;

        if ($deliveryOption && isset($deliveryOption[$cart->id_carrier])) {
            $carrier = new Carrier($cart->id_carrier);
            if (Validate::isLoadedObject($carrier)) {
                // Calcula el costo de envío usando el país geolocalizado
                $shippingCost = $carrier->getDeliveryPriceByWeight($cart->getTotalWeight(), $geoCountryId);
            }
        }

        return $shippingCost;
    }

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

    private function getGeolocatedCountry()
    {
        $defaultCountryId = (int)Configuration::get('PS_COUNTRY_DEFAULT');
        $defaultCountry = new Country($defaultCountryId);

        // Obtener la IP del cliente
        $ip = Tools::getRemoteAddr();

        // Ruta al archivo GeoLite2-City.mmdb
        $geoLiteDatabasePath = _PS_ROOT_DIR_ . '/app/Resources/geoip/GeoLite2-City.mmdb';

        // Verificar si la clase Reader de GeoIp2 está disponible
        if (class_exists('GeoIp2\Database\Reader') && file_exists($geoLiteDatabasePath)) {
            try {
                // Crear un lector para la base de datos GeoLite2
                $reader = new Reader($geoLiteDatabasePath);
                
                // Obtener los datos de geolocalización para la IP
                $record = $reader->city($ip);
                $countryIso = $record->country->isoCode;
                if ($countryIso) {
                    $countryId = (int)Country::getByIso($countryIso);
                    if ($countryId) {
                        return new Country($countryId);
                    }
                }
            } catch (Exception $e) {
                // Manejar errores en caso de que la geolocalización falle
                $this->sendResponse(['error' => 'GeoIP2 error: ' . $e->getMessage()], 500);
                return null;
            }
        } else {
            $this->sendResponse(['error' => 'GeoIP2 database not found or Reader class not available'], 500);
            return null;
        }

        // Si no se puede determinar el país, devolver el país predeterminado de la tienda
        return $defaultCountry;
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