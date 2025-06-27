<?php
/**
 * 2007-2018 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
class WebserviceSpecificManagementProductList implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

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

    /**
     * Management of search.
     */
public function manage()
{
    $languageId = $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');
    $orderBy = isset($this->wsObject->urlFragments['order_by']) ? $this->wsObject->urlFragments['order_by'] : 'price';
    $orderDirection = isset($this->wsObject->urlFragments['order_dir']) && in_array(strtoupper($this->wsObject->urlFragments['order_dir']), ['ASC', 'DESC']) ? strtoupper($this->wsObject->urlFragments['order_dir']) : 'ASC';

    // Parámetros de paginación
    $page = isset($this->wsObject->urlFragments['page']) ? (int)$this->wsObject->urlFragments['page'] : 1;
    $perPage = isset($this->wsObject->urlFragments['per_page']) ? (int)$this->wsObject->urlFragments['per_page'] : 10;

    // Calcular el desplazamiento
    $offset = ($page - 1) * $perPage;

    // Parámetros de filtro
    $categoryId = isset($this->wsObject->urlFragments['category_id']) ? (int)$this->wsObject->urlFragments['category_id'] : null;
    $minPrice = isset($this->wsObject->urlFragments['min_price']) ? (float)$this->wsObject->urlFragments['min_price'] : null;
    $maxPrice = isset($this->wsObject->urlFragments['max_price']) ? (float)$this->wsObject->urlFragments['max_price'] : null;
    $brandCode = isset($this->wsObject->urlFragments['brand_code']) ? (int)$this->wsObject->urlFragments['brand_code'] : null;
    $sizes = isset($this->wsObject->urlFragments['size']) ? explode(',', $this->wsObject->urlFragments['size'][0]) : [];
    $name = isset($this->wsObject->urlFragments['name']) ? $this->wsObject->urlFragments['name'] : null;
    $relevance = isset($this->wsObject->urlFragments['relevance']) ? (int)$this->wsObject->urlFragments['relevance'] : null;

    // Construir condiciones de filtro
    $conditions = 'p.active = 1';
    if ($categoryId) {
        $conditions .= ' AND cp.id_category = ' . (int)$categoryId;
    }
    if ($minPrice) {
        $conditions .= ' AND p.price >= ' . (float)$minPrice;
    }
    if ($maxPrice) {
        $conditions .= ' AND p.price <= ' . (float)$maxPrice;
    }
    if ($brandCode) {
        $conditions .= ' AND p.id_manufacturer = ' . (int)$brandCode;
    }
    if ($sizes) {
        $sizeConditions = [];
        foreach ($sizes as $size) {
            $sizeConditions[] = 'fvl_size.value = "' . pSQL(trim($size)) . '"';
        }
        $conditions .= ' AND (' . implode(' OR ', $sizeConditions) . ')';
    }
    if ($name) {
        $conditions .= ' AND pl.name LIKE "%' . pSQL($name) . '%"';
    }

    // Obtener el número total de productos
    $totalSql = "
        SELECT COUNT(DISTINCT p.id_product)
        FROM " . _DB_PREFIX_ . "product p
        INNER JOIN " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND pl.id_shop = 1 AND pl.id_lang = " . (int)$languageId . "
        INNER JOIN " . _DB_PREFIX_ . "category_product cp ON p.id_product = cp.id_product
        INNER JOIN " . _DB_PREFIX_ . "feature_product fp_size ON p.id_product = fp_size.id_product AND fp_size.id_feature = 11
        INNER JOIN " . _DB_PREFIX_ . "feature_value_lang fvl_size ON fp_size.id_feature_value = fvl_size.id_feature_value AND fvl_size.id_lang = " . (int)$languageId . "
        WHERE $conditions
    ";

    $totalProducts = Db::getInstance()->getValue($totalSql);
    $totalPages = ceil($totalProducts / $perPage);

    // Construir la cláusula ORDER BY correctamente
    $orderByClause = '';
    if ($orderBy === 'relevance') {
        $orderByClause = 'cp.position DESC, p.id_product DESC';
    } elseif ($orderBy === 'best_sellers') {
        $orderByClause = 'ps.quantity DESC';
    } elseif ($orderBy === 'volume') {
        $orderByClause = 'fvl_size.value ' . $orderDirection;
    } elseif ($orderBy === 'category_id') {
		$orderByClause = 'cp.id_category ' . $orderDirection . ', p.id_product DESC';
	}else {
        $orderByClause = 'p.' . pSQL($orderBy) . ' ' . $orderDirection;
    }

    // URL base de la tienda para construir la URL de la imagen
    $baseUrl = 'https://' . Tools::getShopDomainSsl() . __PS_BASE_URI__ . 'img/p/';

    // Consulta principal con paginación
    $sql = "
        SELECT DISTINCT
            p.id_product AS id_product,
            pl.name AS product_name,
            p.price AS original_price,
            IFNULL(MAX(sp.reduction), 0) AS reduction,
            IFNULL(p.price - (p.price * MAX(sp.reduction)), p.price) AS reduction_price,
            IFNULL(MAX(sp.reduction) * 100, 0) AS reduction_percent,
            i.id_image AS image_id,
            fvl_size.value AS volume,
            fvl_graduation.value AS graduation,
            fvl_age.value AS age
        FROM 
            " . _DB_PREFIX_ . "product p
        INNER JOIN 
            " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND pl.id_shop = 1 AND pl.id_lang = " . (int)$languageId . "
        LEFT JOIN 
            " . _DB_PREFIX_ . "image i ON p.id_product = i.id_product
        INNER JOIN 
            " . _DB_PREFIX_ . "category_product cp ON p.id_product = cp.id_product
        INNER JOIN 
            " . _DB_PREFIX_ . "feature_product fp_size ON p.id_product = fp_size.id_product AND fp_size.id_feature = 11
        INNER JOIN 
            " . _DB_PREFIX_ . "feature_value_lang fvl_size ON fp_size.id_feature_value = fvl_size.id_feature_value AND fvl_size.id_lang = " . (int)$languageId . "
        LEFT JOIN 
            " . _DB_PREFIX_ . "feature_product fp_graduation ON p.id_product = fp_graduation.id_product AND fp_graduation.id_feature = 21
        LEFT JOIN 
            " . _DB_PREFIX_ . "feature_value_lang fvl_graduation ON fp_graduation.id_feature_value = fvl_graduation.id_feature_value AND fvl_graduation.id_lang = " . (int)$languageId . "
        LEFT JOIN 
            " . _DB_PREFIX_ . "feature_product fp_age ON p.id_product = fp_age.id_product AND fp_age.id_feature = 8
        LEFT JOIN 
            " . _DB_PREFIX_ . "feature_value_lang fvl_age ON fp_age.id_feature_value = fvl_age.id_feature_value AND fvl_age.id_lang = " . (int)$languageId . "
        LEFT JOIN
            " . _DB_PREFIX_ . "specific_price sp ON p.id_product = sp.id_product
        LEFT JOIN
            " . _DB_PREFIX_ . "product_sale ps ON p.id_product = ps.id_product
        WHERE $conditions
        GROUP BY p.id_product
        ORDER BY $orderByClause
        LIMIT $offset, $perPage;
    ";

    $products = Db::getInstance()->executeS($sql);

    if ($products) {
        // Preparar la respuesta con paginación
        $response = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalProducts,
            'total_pages' => $totalPages,
            'data' => []
        ];

        foreach ($products as $product) {
            // Construir la URL de la imagen
            $imageId = $product['image_id'];
			$imagePath = implode('/', str_split($imageId)) . '/' . $imageId . '.jpg';
            $imageUrl = $baseUrl . $imagePath;
			 // Obtener el precio con IVA incluido
            $priceWithTax = Product::getPriceStatic($product['id_product'], true);  // true para incluir impuestos
            $priceReduction = Product::getPriceStatic($product['id_product'], true, null, 2, null, true, true); // Precio reducido con impuestos
            // Agregar el producto con la URL de la imagen a la respuesta
            $response['data'][] = [
                'id_product' => $product['id_product'],
                'product_name' => $product['product_name'],
                'original_price' => $priceWithTax,
                'reduction' => $product['reduction'],
                'reduction_price' => $priceReduction,
                'reduction_percent' => $product['reduction_percent'],
                'image_url' => $imageUrl,
                'volume' => $product['volume'],
                'graduation' => $product['graduation'],
                'age' => $product['age']
            ];
        }

        // Convertir el array de productos a formato JSON
        $json_result = json_encode($response);

        // Devolver el resultado JSON
        echo $json_result;
    } else {
        echo json_encode(['error' => 'No se encontraron productos']);
    }
}

/**
     * This must be return a string with specific values as WebserviceRequest expects.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->output;
    }

}
