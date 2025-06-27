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
class WebserviceSpecificManagementProductDetails implements WebserviceSpecificManagementInterface
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
    $languageId = $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

    $sql = "
        SELECT
            p.id_product AS product_id,
            pl.name AS product_name,
            pl.description_short AS short_description,
            p.id_manufacturer AS manufacturer_id,
            sa.quantity AS stock,
            p.price AS product_price,
            i.id_image AS image_id,
            fl.id_feature AS feature_id,
            fl.name AS feature_name,
            fvl.value AS feature_value
        FROM
            " . _DB_PREFIX_ . "product p
        INNER JOIN
            " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = " . (int)$languageId . " AND pl.id_shop = 1
        LEFT JOIN
            " . _DB_PREFIX_ . "image i ON p.id_product = i.id_product
        INNER JOIN
            " . _DB_PREFIX_ . "feature_product fp ON p.id_product = fp.id_product
        INNER JOIN
            " . _DB_PREFIX_ . "feature f ON fp.id_feature = f.id_feature
        INNER JOIN
            " . _DB_PREFIX_ . "feature_lang fl ON f.id_feature = fl.id_feature AND fl.id_lang = " . (int)$languageId . "
        INNER JOIN
            " . _DB_PREFIX_ . "feature_value fv ON fp.id_feature_value = fv.id_feature_value
        INNER JOIN
            " . _DB_PREFIX_ . "feature_value_lang fvl ON fv.id_feature_value = fvl.id_feature_value AND fvl.id_lang = " . (int)$languageId . "
        LEFT JOIN
            " . _DB_PREFIX_ . "stock_available sa ON p.id_product = sa.id_product
        WHERE
            p.id_product = " . (int)$productId . ";
    ";

    $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if ($results) {
		$productPriceWithTax = Product::getPriceStatic($productId, true);
        $productDetails = [
            'product_id' => $results[0]['product_id'],
            'product_name' => $results[0]['product_name'],
            'short_description' => $results[0]['short_description'],
            'manufacturer_id' => $results[0]['manufacturer_id'],
            'stock' => $results[0]['stock'],
            'product_price' => $productPriceWithTax,
            'image_id' => $results[0]['image_id'],
            'features' => []
        ];

        $tempFeatures = [];

        foreach ($results as $result) {
            if (!isset($tempFeatures[$result['feature_id']])) {
                $tempFeatures[$result['feature_id']] = true;
                $productDetails['features'][] = [
                    'feature_id' => $result['feature_id'],
                    'feature_name' => $result['feature_name'],
                    'feature_value' => $result['feature_value']
                ];
            }
        }

        // Obtener la URL base dependiendo del entorno
        $baseUrl = 'https://' . Tools::getShopDomainSsl() . __PS_BASE_URI__ . 'img/p/';

        // Obtener la imagen en base al image_id
        if (!empty($productDetails['image_id'])) {
            $imageId = $productDetails['image_id'];
            $imagePath = implode('/', str_split($imageId)) . '/' . $imageId . '.jpg';
            $productDetails['image'] = $baseUrl . $imagePath;
        }

        $this->output = json_encode($productDetails);
    } else {
        $this->output = json_encode(['error' => 'Product not found']);
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
