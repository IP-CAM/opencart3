<?php

/**
 * List of classes created and registered in "system registry" of e-shop
 * @property Config $config
 * @property \Cart\Currency $currency
 * @property Document $document
 * @property Language $language
 * @property Loader $load
 * @property ModelLocalisationGeoZone $model_localisation_geo_zone
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelLocalisationTaxClass $model_localisation_tax_class
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelSettingSetting model_setting_setting
 * @property ModelSettingStore model_setting_store
 * @property ModelSettingExtension model_setting_extension
 * @property Request $request
 * @property Response $response
 * @property Session $session
 * @property Url $url
 * @property \Cart\User $user
 */
class ControllerExtensionShippingPacketaSKPost extends Controller
{
	const ROUTING_BASE_PATH = 'extension/shipping/packetaskpost';

	// TODO: install, uninstall

	/**
	 * Handler for main action - main settings page.
	 */
	public function index() {
		// todo: vlastni preklady
		$this->load->language('extension/shipping/zasilkovna');

		// creation of customized common part of template data
		$data = [
			// common parts of page (header, left column with system menu, footer)
			'header' => $this->load->controller('common/header'),
			'column_left' => $this->load->controller('common/column_left'),
			'footer' => $this->load->controller('common/footer'),
			'breadcrumbs' => [
				[
					'text' => $this->language->get('text_home'),
					'href' => $this->createAdminLink('common/dashboard')
				],
				[
					'text' => $this->language->get('text_shipping'),
					'href' => $this->createAdminLink('marketplace/extension', ['type' => 'shipping'])
				],
				[
					'text' => $this->language->get('Packeta SK Post'),
					'href' => $this->createAdminLink('')
				]
			]
		];
		$data['heading_title'] = 'Packeta SK Post';

		// loads list of tax classes and geo zones defined in administration
		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		// save new values from POST request data to module settings
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->checkPermissions())) {
			$this->load->model('setting/setting');
			$existingSettings = $this->model_setting_setting->getSetting('shipping_packetaskpost');
			$this->model_setting_setting->editSetting('shipping_packetaskpost', $this->request->post + $existingSettings);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->createAdminLink('marketplace/extension', ['type' => 'shipping']));
		}

		// loads values for global settings from POST request data or from module configuration
		$configurationItems = [
			//'shipping_packetaskpost_api_key',
			'shipping_packetaskpost_tax_class_id',
			'shipping_packetaskpost_weight_max',
			'shipping_packetaskpost_default_free_shipping_limit',
			'shipping_packetaskpost_default_shipping_price',
			'shipping_packetaskpost_status',
			'shipping_packetaskpost_sort_order',
			'shipping_packetaskpost_geo_zone_id',
			//'shipping_packetaskpost_order_statuses',
			//'shipping_packetaskpost_cash_on_delivery_methods',
			//'shipping_packetaskpost_eshop_identifier_0', // default store always exists
		];

		foreach ($configurationItems as $itemName) {
			if (isset($this->request->post[$itemName])) {
				$data[$itemName] = $this->request->post[$itemName];
			} else {
				$data[$itemName] = $this->config->get($itemName);
			}
		}

		// todo: stranka nema title
		$this->response->setOutput($this->load->view('extension/shipping/packetaskpost', $data));
	}

	/**
	 * Check if user has permission to change module settings.
	 *
	 * @return bool TRUE = success, FALSE = error
	 */
	private function checkPermissions() {
		if (!$this->user->hasPermission('modify', self::ROUTING_BASE_PATH)) {
			$data['error_warning'] = $this->language->get('error_permission');
			return false;
		}

		return true;
	}

	/**
	 * Creates link to given action in administration including user token.
	 *
	 * @param string $actionName internal name of module action
	 * @param array $urlParameters additional parameters to url
	 * @return string
	 */
	private function createAdminLink($actionName, $urlParameters = [])
	{
		// empty action name => main page of module
		if ('' == $actionName) {
			$actionName = self::ROUTING_BASE_PATH;
		}

		// action name without slash (/) => action of module
		if (strpos($actionName, '/') === false) {
			$actionName = self::ROUTING_BASE_PATH . '/' . $actionName;
		}

		// otherwise action name is absolute routing path => no change in action name
		// user token must be part of any administration link
		$urlParameters['user_token']  = $this->session->data['user_token'];

		return $this->url->link($actionName, $urlParameters, true);
	}

}
