<?php

require_once _PS_MODULE_DIR_ . 'iagtapi/classes/webservice/auth/AuthenticationMiddleware.php';

class WebserviceSpecificManagementWishlist implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
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
            return; // Autenticación fallida, respuesta ya enviada por el middleware
        }

        // Procesar la solicitud según el método HTTP
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleGetRequest($decodedToken);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest($decodedToken);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $this->handleDeleteRequest($decodedToken);
        } else {
            $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }

    private function handleGetRequest($decodedToken)
    {
        $customerId = (int) $decodedToken->id_customer;
        $languageId = $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

        // Consulta SQL para obtener los productos en la wishlist del cliente con detalles del producto
        $sql = "
            SELECT
                iw.id_iqitwishlist_product,
                iw.id_product,
                iw.id_product_attribute,
                p.price AS product_price,
                pl.name AS product_name,
                i.id_image AS image_id
            FROM " . _DB_PREFIX_ . "iqitwishlist_product iw
            INNER JOIN " . _DB_PREFIX_ . "product p ON iw.id_product = p.id_product
            INNER JOIN " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = " . (int)$languageId . " AND pl.id_shop = iw.id_shop
            LEFT JOIN " . _DB_PREFIX_ . "image i ON p.id_product = i.id_product AND i.cover = 1
            WHERE iw.id_customer = " . (int)$customerId;

        $results = Db::getInstance()->executeS($sql);

        if ($results) {
            $wishlistProducts = [];
            foreach ($results as $result) {
                $imageUrl = $this->getImageUrl($result['id_product'], $result['image_id']);
				$features = $this->getProductFeatures($result['id_product'], $languageId);
                $wishlistProducts[] = [
                    'id_product' => $result['id_product'],
                    'product_name' => $result['product_name'],
                    'product_price' => $result['product_price'],
                    'image_url' => $imageUrl,
					'features' => $features
                ];
            }
            $this->sendResponse($wishlistProducts);
        } else {
            $this->sendResponse(['error' => 'No wishlist products found'], 404);
        }
    }
	private function getProductFeatures($productId, $languageId)
{
    $featureIds = [8, 11, 21]; // Especificar los IDs de las características que se desean obtener
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
    private function handlePostRequest($decodedToken)
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['id_product'])) {
            $idProduct = (int) $input['id_product'];
            $idProductAttribute = isset($input['id_product_attribute']) ? (int) $input['id_product_attribute'] : 0;
            $idCustomer = (int) $decodedToken->id_customer;
            $idShop = Context::getContext()->shop->id;

            // Verificar si el producto ya está en la wishlist del cliente
            $sqlCheck = "
                SELECT COUNT(*) as count
                FROM " . _DB_PREFIX_ . "iqitwishlist_product
                WHERE id_product = " . (int)$idProduct . "
                AND id_product_attribute = " . (int)$idProductAttribute . "
                AND id_customer = " . (int)$idCustomer . "
                AND id_shop = " . (int)$idShop;

            $resultCheck = Db::getInstance()->getValue($sqlCheck);

            if ($resultCheck > 0) {
                $this->sendResponse(['error' => 'Product already in wishlist'], 409);
                return;
            }

            // Insertar el producto en la wishlist
            $sqlInsert = "
                INSERT INTO " . _DB_PREFIX_ . "iqitwishlist_product (id_product, id_product_attribute, id_customer, id_shop)
                VALUES (" . (int)$idProduct . ", " . (int)$idProductAttribute . ", " . (int)$idCustomer . ", " . (int)$idShop . ")
            ";

            if (Db::getInstance()->execute($sqlInsert)) {
                $this->sendResponse(['success' => 'Product added to wishlist'], 201);
            } else {
                $this->sendResponse(['error' => 'Failed to add product to wishlist'], 500);
            }
        } else {
            $this->sendResponse(['error' => 'Invalid input data'], 400);
        }
    }

    private function handleDeleteRequest($decodedToken)
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['id_product'])) {
            $idProduct = (int) $input['id_product'];
            $idCustomer = (int) $decodedToken->id_customer;

            // Eliminar el producto de la wishlist
            $sqlDelete = "
                DELETE FROM " . _DB_PREFIX_ . "iqitwishlist_product
                WHERE id_product = " . (int)$idProduct . "
                AND id_customer = " . (int)$idCustomer;

            if (Db::getInstance()->execute($sqlDelete)) {
                $this->sendResponse(['success' => 'Product removed from wishlist'], 200);
            } else {
                $this->sendResponse(['error' => 'Failed to remove product from wishlist'], 500);
            }
        } else {
            $this->sendResponse(['error' => 'Invalid input data'], 400);
        }
    }

    private function getImageUrl($productId, $imageId)
    {
        if ($imageId) {
            $baseUrl = Tools::getHttpHost(true) . __PS_BASE_URI__;
            return $baseUrl . 'img/p/' . Image::getImgFolderStatic($imageId) . $imageId . '.jpg';
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

    private function getTokenFromBody()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input['token'] ?? null;
    }

    public function getContent()
    {
        return json_encode(['message' => 'Operation successful']);
    }
}
