<?php

class WebserviceSpecificManagementLogin implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

    private $secret; // Utiliza la misma clave secreta que usaste para generar el token
    public function __construct()
    {
        include_once(_PS_MODULE_DIR_.'iagtapi/config.php');
        $this->secret = _IAGTAPI_JWT_SECRET_KEY;
    }

    /* ------------------------------------------------
     * GETTERS & SETTERS
     * ------------------------------------------------ */

    /**
     * @param WebserviceOutputBuilderCore $obj
     *
     * @return WebserviceSpecificManagementInterface
     */
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
        if ($this->wsObject === null) {
            $this->sendResponse(['error' => 'Webservice object is null'], 500);
            return;
        }
        // Obtener los datos del cuerpo de la solicitud
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (!isset($requestData['email']) || !isset($requestData['password'])) {
            $this->sendResponse(['error' => 'Debe proporcionar email y contraseña'], 400);
            return;
        }

        $email = $requestData['email'];
        $password = $requestData['password'];

        $this->verifyUser($email, $password);
    }
    
    public static function crypto($plaintextPassword, $passwordHash)
    {
        /** @var \PrestaShop\PrestaShop\Core\Crypto\Hashing $crypto */
        $crypto = PrestaShop\PrestaShop\Adapter\ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
        return $crypto->checkHash($plaintextPassword, $passwordHash);
    }

    private function verifyUser($email, $password)
    {
        $db = Db::getInstance();
        $sql = 'SELECT id_customer, passwd, email, firstname, lastname FROM ' . _DB_PREFIX_ . 'customer WHERE email = \'' . pSQL($email) . '\' AND active = 1 AND deleted = 0';
        $user = $db->getRow($sql);
        $hash = $user['passwd']; // Hash de la contraseña
        $crypted = password_hash($hash, PASSWORD_DEFAULT);
        $isMatch = password_verify($password, $hash);

        if ($user && $isMatch) {
            // Obtener el carrito no finalizado, si existe
            $cartId = $this->getActiveCartId($user['id_customer']);
            
            // Credenciales correctas, generar token JWT
            $payload = [
                'id_customer' => $user['id_customer'],
                'email' => $user['email'],
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'id_cart' => $cartId // Incluir el ID del carrito en el payload
                // 'exp' => time() + 3600 // Expira en una hora
            ];
            $token = $this->generateToken($payload);
            $this->sendResponse(['token' => $token]);
        } else {
            // Credenciales incorrectas
            $this->sendResponse(['error' => 'Email o contraseña incorrectos'], 401);
        }
    }

    private function getActiveCartId($customerId)
    {
        $sql = 'SELECT c.id_cart
                FROM ' . _DB_PREFIX_ . 'cart c
                LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON c.id_cart = o.id_cart
                WHERE c.id_customer = ' . (int)$customerId . ' AND o.id_cart IS NULL';

        return Db::getInstance()->getValue($sql);
    }

    private function generateToken($payload)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
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
        // Aquí puedes devolver el contenido deseado, por ejemplo, un mensaje indicando que el inicio de sesión se ha realizado correctamente
        return json_encode(['message' => 'Login successful']);
    }
}
