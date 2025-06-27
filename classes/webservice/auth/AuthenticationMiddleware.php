<?php

// Incluyendo las clases JWT manualmente
require_once _PS_MODULE_DIR_ . 'iagtapi/lib/JWT.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/lib/Key.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/lib/ExpiredException.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/lib/SignatureInvalidException.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/lib/BeforeValidException.php';
require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class AuthenticationMiddleware
{
    private $secret;

    public function __construct()
    {
        include_once(_PS_MODULE_DIR_ . 'iagtapi/config.php');
        $this->secret = _IAGTAPI_JWT_SECRET_KEY;
    }

    public function handle()
    {
        $headers = $this->getAuthorizationHeaders();
		
        if (!isset($headers['Bearer-Token'])) {
            $this->sendResponse(['error' => 'Token not provided'], 401);
            return false;
        }

        $token = $headers['Bearer-Token'];
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
			
            return $decoded;
        } catch (Exception $e) {
            $this->sendResponse(['error' => 'Invalid token: ' . $e->getMessage()], 401);
            return false;
        }
    }

    private function getAuthorizationHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    private function sendResponse($response, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($response);
        exit();
    }
}
