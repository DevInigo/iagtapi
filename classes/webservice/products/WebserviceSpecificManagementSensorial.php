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
class WebserviceSpecificManagementSensorial implements WebserviceSpecificManagementInterface
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
    
    $productId = (int) $this->wsObject->urlFragments['filter']['id_product'];
    $langId = (int) $this->wsObject->urlFragments['filter']['id_lang'];

    $sql = "
        SELECT
            it.id_product,
            it.id_iqitadditionaltab,
            itl.title,
            itl.description
        FROM
            " . _DB_PREFIX_ . "iqitadditionaltab it
        INNER JOIN
            " . _DB_PREFIX_ . "iqitadditionaltab_lang itl ON it.id_iqitadditionaltab = itl.id_iqitadditionaltab
        WHERE
            it.id_product = " . (int) $productId . "
            AND itl.id_lang = " . (int) $langId;

    $results = Db::getInstance()->executeS($sql);

    if ($results && count($results) > 0) {
        $sensorialDetails = [];
        foreach ($results as $result) {
            $sensorialDetails[] = [
                'title' => $result['title'],
                'description' => $result['description']
            ];
        }
        $this->output = json_encode($sensorialDetails);
    } else {
        $this->output = json_encode(['error' => 'No se encontraron resultados']);
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
