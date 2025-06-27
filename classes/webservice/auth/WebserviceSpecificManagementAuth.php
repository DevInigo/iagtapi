<?php
class WebserviceSpecificManagementAuth implements WebserviceSpecificManagementInterface
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
        // Verificar el token para métodos GET, PUT y DELETE
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $authMiddleware = new AuthenticationMiddleware();
            $decodedToken = $authMiddleware->handle();
            if (!$decodedToken) {
                return; // Autenticación fallida, respuesta ya enviada por el middleware
            }
        } else {
            $decodedToken = null;
        }

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->sendUserData($decodedToken->id_customer);
                break;
            case 'PUT':
                $this->handlePutRequest($decodedToken);
                break;
            case 'POST':
                $this->handlePostRequest();
                break;
            case 'DELETE':
                $this->handleDeleteRequest($decodedToken);
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
                break;
        }
    }

    private function handlePostRequest()
    {
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (isset($requestData['firstname']) && isset($requestData['lastname']) && isset($requestData['email']) && isset($requestData['password'])) {
            $this->createNewUser($requestData['firstname'], $requestData['lastname'], $requestData['email'], $requestData['password']);
        } else {
            $this->sendResponse(['error' => 'Debe proporcionar los datos necesarios'], 400);
        }
    }

    private function createNewUser($firstname, $lastname, $email, $password)
    {
        $existingCustomer = new Customer();
        if ($existingCustomer->getByEmail($email)) {
            $this->sendResponse(['error' => 'El correo electrónico ya está registrado'], 409);
            return;
        }

        // Hashear la contraseña usando la función de PrestaShop
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Crear nuevo cliente
        $customer = new Customer();
        $customer->firstname = $firstname;
        $customer->lastname = $lastname;
        $customer->email = $email;
        $customer->passwd = $hashedPassword;
        $customer->active = 1;

        if ($customer->add()) {
            $this->sendResponse(['success' => 'Usuario creado exitosamente'], 201);
        } else {
            $this->sendResponse(['error' => 'Error al crear el usuario'], 500);
        }
    }

    private function handlePutRequest($decodedToken)
    {
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        $customerId = $decodedToken->id_customer;
        $customer = new Customer($customerId);

        if (isset($requestData['new_password'])) {
            // Encriptar la nueva contraseña
            $encryptedPassword = password_hash($requestData['new_password'], PASSWORD_BCRYPT);
            $customer->passwd = pSQL($encryptedPassword);
        }

        if (isset($requestData['firstname'])) {
            $customer->firstname = $requestData['firstname'];
        }
        if (isset($requestData['lastname'])) {
            $customer->lastname = $requestData['lastname'];
        }
        if (isset($requestData['email'])) {
            $customer->email = $requestData['email'];
        }
        if (isset($requestData['birthday'])) {
            $customer->birthday = $requestData['birthday'];
        }
        if (isset($requestData['id_gender'])) {
            $customer->id_gender = $requestData['id_gender'];
        }
        if (isset($requestData['newsletter'])) {
            $customer->newsletter = (bool)$requestData['newsletter'];
        }

        if ($customer->update()) {
            $this->sendResponse(['success' => 'Datos del usuario actualizados correctamente']);
        } else {
            $this->sendResponse(['error' => 'Error al actualizar los datos del usuario'], 500);
        }
    }

    private function handleDeleteRequest($decodedToken)
    {
        $customerId = $decodedToken->id_customer;

        $customer = new Customer($customerId);
        if ($customer->delete()) {
            $this->sendResponse(['success' => 'Usuario eliminado correctamente']);
        } else {
            $this->sendResponse(['error' => 'Error al eliminar el usuario'], 500);
        }
    }

    private function sendUserData($userId)
    {
        $customer = new Customer($userId);
        if (Validate::isLoadedObject($customer)) {
            $this->sendResponse([
                'id' => $customer->id,
                'email' => $customer->email,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'birthday' => $customer->birthday,
                'id_gender' => $customer->id_gender,
                'newsletter' => $customer->newsletter,
                'id_cart' => $this->getActiveCartId($customer->id) // Añadir id_cart si existe un carrito no finalizado
            ]);
        } else {
            $this->sendResponse(['error' => 'User not found'], 404);
        }
    }

    private function getActiveCartId($customerId)
    {
        $sql = 'SELECT c.id_cart
                FROM ' . _DB_PREFIX_ . 'cart c
                LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON c.id_cart = o.id_cart
                WHERE c.id_customer = ' . (int)$customerId . ' AND o.id_cart IS NULL
                ORDER BY c.date_upd DESC
                LIMIT 1';

        $result = Db::getInstance()->executeS($sql);

        // Si el resultado no está vacío, devolver el id_cart
        if (!empty($result)) {
            return $result[0]['id_cart'];
        }

        return false;
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
