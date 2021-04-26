<?php

/**
 * Class ModelExtensionShippingPacketaSKPost
 *
 * @property Config $config
 * @property DB $db
 * @property Loader $load
 * @property Language $language
 * @property Request $request
 * @property Session $session
 * @property \Cart\Cart $cart
 * @property \Cart\Currency $currency
 * @property \Cart\Tax $tax
 */
class ModelExtensionShippingPacketaSKPost extends Model
{
	/**
	 * Check basic conditions if shipping through PacketaSKPost is allowed.
	 *
	 * @param int $totalWeight total weight of order
	 * @param array $targetAddress target address for order
	 * @return boolean check result (TRUE = shipping allowed)
	 */
	private function checkBasicConditions($totalWeight, $targetAddress)
	{
		// check if module for PacketaSKPost is enabled
		if (!(int)$this->config->get('shipping_packetaskpost_status')) {
			return false;
		}

		// check if total weight of order is lower than maximal allowed weight (if limit is defined)
		$maxWeight = (int)$this->config->get('shipping_packetaskpost_weight_max');
		if (!empty($maxWeight) && $totalWeight > $maxWeight) {
			return false;
		}

		// check if target customer address is in allowed geo zone (if zone limitation is defined)
		$configGeoZone = (int) $this->config->get('shipping_packetaskpost_geo_zone_id');
		if ($configGeoZone > 0) {
			// get country and zone from target address
			$cartCountry = $targetAddress['country_id'];
			$cartZone = $targetAddress['zone_id'];
			// check if given zone or whole country is part of geo zone from configuration
			$sqlQuery = sprintf('SELECT * FROM `%s` WHERE `geo_zone_id` = %s AND `country_id` = %s AND (`zone_id` = %s OR `zone_id` = 0)',
				DB_PREFIX . 'zone_to_geo_zone', $configGeoZone, $cartCountry, $cartZone);
			/** @var StdClass $queryResult */
			$queryResult = $this->db->query($sqlQuery);
			if (0 == $queryResult->num_rows) {
				return false;
			}
		}

		// all checks passed
		return true;
	}

	/**
	 * Calculation of shipping price. Returns price of shipping or -1 if price cannot be calculated.
	 *
	 * @param string $countryCode iso code of target country
	 * @param double $totalWeight total weight of order
	 * @param double $totalPrice total price of order
	 * @return array price of shipping and internal shipping service code
	 */
	private function calculatePrice($countryCode, $totalWeight, $totalPrice)
	{
		// check if price is over global limit for free shipping
		$globalFreeShippingLimit = (float)$this->config->get('shipping_packetaskpost_default_free_shipping_limit');
		if ($globalFreeShippingLimit > 0 && $totalPrice > $globalFreeShippingLimit) {
			return [
				'price' => 0,
				'service_name' => '1',
			];
		}

		// check if global price for shipping is defined
		$globalShippingPrice = (float)$this->config->get('shipping_packetaskpost_default_shipping_price');
		if ($globalShippingPrice > 0) {
			return [
				'price' => $globalShippingPrice,
				'service_name' => '1',
			];
		}

		throw new Exception('price cannot be calculated');
	}

	/**
	 * Returns parameters of available options for shipping.
	 * It is called from ControllerCheckoutShippingMethod for all registered shipping extensions.
	 *
	 * @param array $targetAddress
	 * @return array
	 */
	public function getQuote($targetAddress)
	{
		// load lang
		$cartTotalWeight = $this->cart->getWeight();
		$cartCountryCode = strtolower($this->cart->session->data["shipping_address"]["iso_code_2"]);
		$cartTotalPrice = $this->cart->getTotal();

		$checkResult = $this->checkBasicConditions($cartTotalWeight, $targetAddress);
		if (!$checkResult) {
			return [];
		}

		// calculate price of shipping (only one item can be displayed)
		$calcResult = $this->calculatePrice($cartCountryCode, $cartTotalWeight, $cartTotalPrice);
		$shippingPrice = $calcResult['price'];
		$serviceCodeName = $calcResult['service_name'];

		// preparation of properties for shipping service definition
		$taxClassId = $this->config->get('shipping_packetaskpost_tax_class_id');

		$descriptionText = 'descriptionText';

		$quote_data = [
			$serviceCodeName => [
				'code' => 'packetaskpost.' . $serviceCodeName,
				'title' => 'Metoda 1',
				'cost' => $shippingPrice,
				'tax_class_id' => $taxClassId,
				'text' => $descriptionText,
			],
			'2' => [
				'code' => 'packetaskpost.' . '2',
				'title' => 'Metoda 2',
				'cost' => $shippingPrice,
				'tax_class_id' => $taxClassId,
				'text' => $descriptionText,
			],
		];

		$method_data = [
			'code' => 'packetaskpost',
			'title' => 'Packeta SK Post',
			'quote' => $quote_data,
			'sort_order' => $this->config->get('shipping_packetaskpost_sort_order'),
			'error' => false
		];

		return $method_data;
	}

}
