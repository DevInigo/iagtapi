<?php

require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';

class WebserviceSpecificManagementCustomerAddresses implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

    public function __construct()
    {
        include_once(_PS_MODULE_DIR_ . 'iagtapi/config.php');
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
            case 'DELETE':
                $this->handleDeleteRequest($decodedToken);
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
                break;
        }
    }

    private function handleGetRequest($decodedToken)
    {
        if (isset($this->wsObject->urlFragments['resource'])) {
            $resource = $this->wsObject->urlFragments['resource'];
            switch ($resource) {
                case 'countries':
                    $this->getCountries();
                    break;
                case 'states':
                    $this->getStates();
                    break;
                default:
                    $this->sendResponse(['error' => 'Invalid resource'], 400);
                    break;
            }
        } else {
            $this->getAddresses($decodedToken);
        }
    }
    
    private function getAddresses($decodedToken)
    {
        $customerId = (int)$decodedToken->id_customer;

        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'address WHERE id_customer = ' . (int)$customerId . ' AND deleted = 0';
        $addresses = Db::getInstance()->executeS($sql);

        if (!empty($addresses)) {
            $this->sendResponse(['addresses' => $addresses]);
        } else {
            $this->sendResponse(['error' => 'No addresses found'], 404);
        }
    }

    private function getCountries()
    {
        $countries = Country::getCountries($this->wsObject->getIdLang());
        $this->sendResponse(['countries' => $countries]);
    }

    private function getStates()
    {
        $idCountry = (int)Tools::getValue('id_country');
        if (!$idCountry) {
            $this->sendResponse(['error' => 'id_country is required'], 400);
            return;
        }
        $states = State::getStatesByIdCountry($idCountry);
        $this->sendResponse(['states' => $states]);
    }

    private function handlePostRequest($decodedToken)
    {
        $customerId = (int)$decodedToken->id_customer;

        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (!isset($requestData['lastname']) || !isset($requestData['firstname']) || !isset($requestData['address']) || !isset($requestData['postcode']) || !isset($requestData['city']) || !isset($requestData['id_country'])) {
            $this->sendResponse(['error' => 'Missing required parameters'], 400);
            return;
        }

        $address = new Address();
        $address->id_customer = $customerId;
        $address->alias = $requestData['alias'] ?? '';
        $address->lastname = $requestData['lastname'];
        $address->firstname = $requestData['firstname'];
        $address->dni = $requestData['dni'] ?? '';
        $address->company = $requestData['company'] ?? '';
        $address->vat_number = $requestData['vat_number'] ?? '';
        $address->address1 = $requestData['address'];
        $address->address2 = $requestData['address2'] ?? '';
        $address->postcode = $requestData['postcode'];
        $address->city = $requestData['city'];
        $address->id_state = $requestData['id_state'] ?? null;
        $address->id_country = (int)$requestData['id_country'];
        $address->phone = $requestData['phone'] ?? '';
        $address->phone_mobile = $requestData['phone_mobile'] ?? '';

        if ($address->add()) {
            $this->sendResponse(['success' => 'Address created successfully', 'id_address' => $address->id], 201);
        } else {
            $this->sendResponse(['error' => 'Failed to create address'], 500);
        }
    }

    private function handlePutRequest($decodedToken)
    {
        $customerId = (int)$decodedToken->id_customer;
        $headers = getallheaders();
        $addressId = isset($headers['Address-Id']) ? (int)$headers['Address-Id'] : null;

        if (!$addressId) {
            $this->sendResponse(['error' => 'Address ID is required'], 400);
            return;
        }

        $address = new Address($addressId);

        if (!Validate::isLoadedObject($address) || $address->id_customer != $customerId) {
            $this->sendResponse(['error' => 'Address not found or does not belong to the customer'], 404);
            return;
        }

        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (isset($requestData['alias'])) {
            $address->alias = $requestData['alias'];
        }
        if (isset($requestData['lastname'])) {
            $address->lastname = $requestData['lastname'];
        }
        if (isset($requestData['firstname'])) {
            $address->firstname = $requestData['firstname'];
        }
        if (isset($requestData['dni'])) {
            $address->dni = $requestData['dni'];
        }
        if (isset($requestData['company'])) {
            $address->company = $requestData['company'];
        }
        if (isset($requestData['vat_number'])) {
            $address->vat_number = $requestData['vat_number'];
        }
        if (isset($requestData['address'])) {
            $address->address1 = $requestData['address'];
        }
        if (isset($requestData['address2'])) {
            $address->address2 = $requestData['address2'];
        }
        if (isset($requestData['postcode'])) {
            $address->postcode = $requestData['postcode'];
        }
        if (isset($requestData['city'])) {
            $address->city = $requestData['city'];
        }
        if (isset($requestData['id_state'])) {
            $address->id_state = (int)$requestData['id_state'];
        }
        if (isset($requestData['id_country'])) {
            $address->id_country = (int)$requestData['id_country'];
        }
        if (isset($requestData['phone'])) {
            $address->phone = $requestData['phone'];
        }
        if (isset($requestData['phone_mobile'])) {
            $address->phone_mobile = $requestData['phone_mobile'];
        }

        if ($address->update()) {
            $this->sendResponse(['success' => 'Address updated successfully']);
        } else {
            $this->sendResponse(['error' => 'Failed to update address'], 500);
        }
    }

    private function handleDeleteRequest($decodedToken)
    {
        $customerId = (int)$decodedToken->id_customer;
        $headers = getallheaders();
        $addressId = isset($headers['Address-Id']) ? (int)$headers['Address-Id'] : null;

        if (!$addressId) {
            $this->sendResponse(['error' => 'Address ID is required'], 400);
            return;
        }

        $address = new Address($addressId);

        if (!Validate::isLoadedObject($address) || $address->id_customer != $customerId) {
            $this->sendResponse(['error' => 'Address not found or does not belong to the customer'], 404);
            return;
        }

        if ($address->delete()) {
            $this->sendResponse(['success' => 'Address deleted successfully']);
        } else {
            $this->sendResponse(['error' => 'Failed to delete address'], 500);
        }
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
