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
class WebserviceSpecificManagementHomeSlider implements WebserviceSpecificManagementInterface
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
		$id_lang = (int) $this->wsObject->urlFragments['filter']['id_lang'];

		$result = false;
		$db = \Db::getInstance();
		$query = 'SELECT id, id_lang, position, slide_text, slide_img_url, slide_img_mobile_url, link, img_class, img_alt 
				  FROM ' . _DB_PREFIX_ . 'iagthome_secondblock 
				  WHERE id_lang = ' . ((int)$id_lang) . ' 
				  ORDER BY position';

		$resultDb = $db->executeS($query);
		if (!empty($resultDb)) {
			$result = [];
			$result['slides'] = [];

			foreach ($resultDb as $slide) {
				// Extraer el número del link
				$link = $slide['link'];
				$number = null;

				if (preg_match('/\/(\d+)-/', $link, $matches)) {
					$number = $matches[1]; // El primer grupo de captura es el número
				}

				// Añadir el número extraído al resultado
				$slide['id_product'] = $number;
				$result['slides'][] = $slide;
			}
		}
		$this->output = json_encode($result);
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
