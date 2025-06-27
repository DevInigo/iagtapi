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
class WebserviceSpecificManagementRelatedProducts implements WebserviceSpecificManagementInterface
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
    $productId = (int) $this->wsObject->urlFragments['filter']['product_id'];
    $languageId = (int) $this->wsObject->urlFragments['filter']['id_lang'];

    // Primero, obtenemos la categoría del producto actual
    $sqlCategory = "
        SELECT id_category_default
        FROM " . _DB_PREFIX_ . "product
        WHERE id_product = " . (int)$productId . "
    ";

    $categoryResult = Db::getInstance()->getRow($sqlCategory);

    if ($categoryResult && isset($categoryResult['id_category_default'])) {
        $idCategory = (int) $categoryResult['id_category_default'];

        // Consulta para obtener 16 productos aleatorios y sus características como array de objetos JSON
        $sqlProductsAndFeatures = "
            SELECT
                p.id_product,
                pl.name AS product_name,
                p.price AS product_price,
                CONCAT('[', GROUP_CONCAT(
                    DISTINCT CONCAT(
                        '{\"feature_id\": ', fp.id_feature, ', \"feature_value\": \"', fvl.value, '\"}'
                    ) SEPARATOR ', '
                ), ']') AS features,
                i.id_image AS image_id
            FROM
                " . _DB_PREFIX_ . "product p
            INNER JOIN
                " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = " . (int)$languageId . "
            INNER JOIN
                " . _DB_PREFIX_ . "category_product cp ON p.id_product = cp.id_product
            LEFT JOIN
                " . _DB_PREFIX_ . "image i ON p.id_product = i.id_product
            LEFT JOIN
                " . _DB_PREFIX_ . "feature_product fp ON p.id_product = fp.id_product
            LEFT JOIN
                " . _DB_PREFIX_ . "feature_value fv ON fp.id_feature_value = fv.id_feature_value
            LEFT JOIN
                " . _DB_PREFIX_ . "feature_value_lang fvl ON fv.id_feature_value = fvl.id_feature_value AND fvl.id_lang = " . (int)$languageId . "
            WHERE
                cp.id_category = " . (int)$idCategory . "
                AND p.id_product != " . (int)$productId . "
                AND fp.id_feature IN (8, 11, 21)
            GROUP BY
                p.id_product, pl.name, p.price, i.id_image
            ORDER BY
                RAND()
            LIMIT 16;
        ";

        // Ejecutar la consulta
        $results = Db::getInstance()->executeS($sqlProductsAndFeatures);
		$baseUrl = 'https://' . Tools::getShopDomainSsl() . __PS_BASE_URI__ . 'img/p/';
        if ($results) {
            // Procesar resultados y devolver como JSON
            $relatedProducts = [];
            foreach ($results as $result) {
                // Decodificar la cadena JSON de features
                $features = json_decode($result['features'], true);
				$imageId = $result['image_id'];
				$imagePath = implode('/', str_split($imageId)) . '/' . $imageId . '.jpg';
				$imageUrl = $baseUrl . $imagePath;
                $relatedProducts[] = [
                    'id_product' => $result['id_product'],
                    'product_name' => $result['product_name'],
                    'product_price' => $result['product_price'],
                    'features' => array_values($features), // Convertir a un array indexado para asegurar la unicidad
                    'image_url' => $imageUrl
                ];
            }

            // Convertir el array de resultados a formato JSON
            $this->output = json_encode($relatedProducts);
        } else {
            $this->output = json_encode(['error' => 'No se encontraron productos relacionados con las características especificadas']);
        }
    } else {
        $this->output = json_encode(['error' => 'No se encontró la categoría del producto']);
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
