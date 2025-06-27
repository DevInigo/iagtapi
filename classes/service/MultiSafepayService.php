<?php

class MultiSafepayService
{
	
    public static function getMultiSafepayPayment($order)
    {
        // Obtener la API Key de configuración Test
        $apiKey = Configuration::get('MULTISAFEPAY_OFFICIAL_TEST_API_KEY');
        // Obtener la API Key de configuración Live
        //$apiKey = Configuration::get('MULTISAFEPAY_OFFICIAL_API_KEY');

        // Verificar que el API Key esté configurado
        if (empty($apiKey)) {
            self::logMessage('MultiSafepay API Key is not set.');
            return false;
        }

        // Construir la URL de la API usando el ID del pedido
        $orderReference = (string)$order->reference;
        // URL Test
        $apiUrl = "https://testapi.multisafepay.com/v1/json/orders/" . $orderReference;
        // URL Live
        //$apiUrl = "https://api.multisafepay.com/v1/json/orders/" . $orderReference;

        // Iniciar cURL para la solicitud GET
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "api_key: " . $apiKey
            ],
        ]);

        // Ejecutar la solicitud y obtener la respuesta
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // Cerrar la sesión de cURL
        curl_close($curl);

        // Manejo de errores en la solicitud cURL
        if ($err) {
            self::logMessage("cURL Error #: " . $err);
            return false;
        } else {
            // Decodificar la respuesta en formato JSON
            $responseData = json_decode($response, true);
			
            // Verificar si la respuesta contiene los datos necesarios
            if ($responseData && isset($responseData['data'])) {
                self::logMessage('Payment information retrieved successfully: ' . json_encode($responseData['data']));
                return $responseData['data'];
            } else {
                self::logMessage('Failed to retrieve payment information. Response: ' . json_encode($responseData));
                return false;
            }
        }
    }

public static function createMultiSafepayPayment($order, $cart)
    {
		$context = Context::getContext();
		$idModule = (int)Db::getInstance()->getValue('SELECT id_module FROM '._DB_PREFIX_.'module WHERE name = "multisafepayofficial"');
        // Obtener la API Key de configuración Test
        $apiKey = Configuration::get('MULTISAFEPAY_OFFICIAL_TEST_API_KEY');
        // Obtener la API Key de configuración Live
        //$apiKey = Configuration::get('MULTISAFEPAY_OFFICIAL_API_KEY');
        // URL Test
        $apiUrl = "https://testapi.multisafepay.com/v1/json/orders/" . $orderReference;
        // URL Live
        //$apiUrl = "https://api.multisafepay.com/v1/json/orders/" . $orderReference;
		$forwarded_ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;
        $forwarded_ip = self::validateIP($forwarded_ip);
        // Obtener el idioma y país del cliente
        $language = new Language((int)$cart->id_lang);
        $lang_iso = $language->iso_code;
        $address = new Address((int)$cart->id_address_delivery);
	
        if (!Validate::isLoadedObject($address)) {
            self::logMessage('Invalid delivery address associated with the cart.');
            return false;
        }

        $country_iso = Country::getIsoById($address->id_country);
        $locale = strtolower($lang_iso) . '_' . strtoupper($country_iso);

        $customer = new Customer((int)$cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            self::logMessage('Invalid customer associated with the cart.');
            return false;
        }

        $currency = new Currency((int)$cart->id_currency);
        if (!Validate::isLoadedObject($currency)) {
            self::logMessage('Invalid currency associated with the cart.');
            return false;
        }

        //Notification Url
        Context::getContext()->link->getModuleLink('multisafepayofficial', 'notification', [], true);

        $totalAmount = (int)round($cart->getOrderTotal(true, Cart::BOTH) * 100);
        $transactionId = $order->id_cart;
		$orderReference = $order->reference;		

        // Datos del cliente
        $ipAddress = Tools::getRemoteAddr();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

         // Datos del cuerpo de la solicitud
        $requestBody = [
            'type' => 'redirect',
            'order_id' => (string) $order->reference,
            'currency' => $currency->iso_code,
            'amount' => (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100),
            'description' => 'Pago para el pedido: ' . $order->reference,
            'payment_options' => [
                'notification_url' => $notificationUrl,
                'redirect_url' => $redirectUrl,
                'cancel_url' => $cancelUrl,
                'notification_method' => 'POST',
                'close_window' => true,
            ],
            'customer' => [
                'locale' => $context->language->iso_code . '_' . strtoupper($country_iso),
                'ip_address' => Tools::getRemoteAddr(),
                'first_name' => $customer->firstname,
                'last_name' => $customer->lastname,
                'email' => $customer->email,
            ],
        ];

        self::logMessage('MultiSafepay Request Body: ' . json_encode($requestBody));
        $response = self::makeMultiSafepayRequest($apiUrl, $requestBody, $apiKey);
		
        if ($response && isset($response['data']['payment_url'])) {
            self::logMessage('Payment URL created successfully: ' . $response['data']['payment_url']);
            return $response['data']['payment_url'];
        } else {
            self::logMessage('Failed to create payment link. Response: ' . json_encode($response));
            return false;
        }
    }

    private static function makeMultiSafepayRequest($url, $body, $apiKey)
    {
		
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'accept: application/json',
            'api_key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            self::logMessage('cURL Error: ' . $curlError);
            return false;
        }

        self::logMessage('MultiSafepay HTTP Code: ' . $httpCode);
        self::logMessage('MultiSafepay Response: ' . $response);

        return json_decode($response, true);
    }
	
	 private function validateIP($ipAddress)
    {
        if (isset($ipAddress)) {
            $ipList = explode(',', $ipAddress);
            $ipAddress = trim(reset($ipList));

            if (filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                return $ipAddress;
            }
        }
        return null;
    }

    private static function logMessage($message)
    {
        $logDir = _PS_MODULE_DIR_ . 'iagtapi/logs/';
        $logFile = $logDir . 'logs.txt';

        if (!file_exists($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $logDir));
            }
        }

        $formattedMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
}
