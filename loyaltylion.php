<?php
/**
* The MIT License (MIT)
*
* Copyright (c) 2014 LoyaltyLion
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
* @author    LoyaltyLion <support@loyaltylion.com>
* @copyright 2012-2014 LoyaltyLion
* @license   http://opensource.org/licenses/MIT  The MIT License
*/

if (!defined('_PS_VERSION_')) exit;

require(dirname(__FILE__).'/lib/loyaltylion-client/lib/connection.php');

class LoyaltyLion extends Module
{

	public $form_values = array(
		'discount_amount' => '',
		'discount_amount_currency' => '',
		'codes' => '',
	);
	private $base_uri;
	private $output = '';

	public function __construct()
	{
		$this->name = 'loyaltylion';
		$this->tab = 'pricing_promotion';
		$this->version = '1.1';
		$this->author = 'LoyaltyLion';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('LoyaltyLion');
		$this->description = $this->l('LoyaltyLion Prestashop module');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}

	public function install()
	{
		if (!function_exists('curl_init')) {
			$this->setError($this->l('LoyaltyLion needs the PHP Curl extension. Please ask your hosting ' +
				'provider to enable it before installing LoyaltyLion.'));
			return false;
		}

		return parent::install() &&
			$this->registerHook('displayHeader') &&
			$this->registerHook('displayOrderConfirmation') &&
			$this->registerHook('actionOrderStatusPostUpdate') &&
			$this->registerHook('actionValidateOrder') &&
			$this->registerHook('actionProductCancel') &&
			$this->registerHook('actionObjectOrderSlipAddAfter') &&
			$this->registerHook('actionObjectProductCommentAddAfter') &&
			$this->registerHook('actionObjectProductCommentDeleteAfter') &&
			$this->registerHook('actionObjectProductCommentValidateAfter') &&
			$this->registerHook('actionCustomerAccountAdd') &&
			$this->registerHook('displayCustomerAccount');
	}

	public function getContent()
	{
		$this->setBaseUri();

		if (isset($this->context->controller))
			$this->context->controller->addCSS($this->_path.'/css/loyaltylion.css', 'all');
		else
			echo '<link rel="stylesheet" type="text/css" href="../modules/loyaltylion-prestashop/css/loyaltylion.css" />';

		switch ($this->getConfigurationAction())
		{
			case 'signup':
				$this->displaySignupForm();
				break;
			case 'set_token_secret':
				$this->setTokenAndSecret();
				break;
			case 'add_voucher_codes':
				$this->addVoucherCodes();
				break;
			default:
				$this->displaySettingsForm();
		}

		return $this->output;
	}

	/**
	 * Sets LoyaltyLion token and secret.
	 */
	public function setTokenAndSecret() {
		$token = Tools::getValue('loyaltylion_token');
		$secret = Tools::getValue('loyaltylion_secret');

		if(!$token || !$secret)
			return $this->displaySignupForm();

		Configuration::updateValue('LOYALTYLION_TOKEN', $token);
		Configuration::updateValue('LOYALTYLION_SECRET', $secret);

		// Let LoyaltyLion know about this operation
		$this->updateSiteMetadata(array('token_and_secret_set' => true));

		$this->output .= $this->displayConfirmation($this->l('Your LoyaltyLion token and secret is updated. Please close this window.'));
	}

	/**
	 * Pulls voucher codes and rewards from LoyaltyLion merchant account and
	 * adds them to Prestashop site.
	 */
	public function addVoucherCodes() {
		$rewards_with_voucher_codes = $this->getRewardsWithVoucherCodes();

		$success = 0;

		foreach ($rewards_with_voucher_codes as $reward) {

			foreach($reward->vouchers as $voucher) {
				$result = $this->createRule($voucher, $reward->cost, $this->getCurrencyId($reward->cost_currency));

				if ($result)
					$success++;

			}

		}

		if($success > 0) {
			$this->updateSiteMetadata(array('vouchers_added' => true));
		}

		$this->output .= $this->displayConfirmation($this->l("${success} codes are imported successfuly. Please close this window."));
	}

	/**
	 * Display (and handle updates of) the settings form, where users can update their token/secret
	 * and batch import voucher codes
	 */
	public function displaySettingsForm()
	{
		$token = $this->getToken();
		$secret = $this->getSecret();

		if (Tools::isSubmit('submitConfiguration'))
		{
			$token = Tools::getValue('loyaltylion_token');
			$secret = Tools::getValue('loyaltylion_secret');

			if (empty($token) || empty($secret))
				$this->output .= $this->displayError($this->l('Token and secret cannot be empty'));
			else
			{
				Configuration::updateValue('LOYALTYLION_TOKEN', $token);
				Configuration::updateValue('LOYALTYLION_SECRET', $secret);

				$this->output .= $this->displayConfirmation($this->l('Token and secret updated'));
			}
		}

		if (Tools::isSubmit('submitVoucherCodes'))
		{
			// we probs need to create some vouchers now...

			$this->form_values['discount_amount'] = Tools::getValue('discount_amount');
			$this->form_values['discount_amount_currency'] = Tools::getValue('discount_amount_currency');
			$this->form_values['codes'] = Tools::getValue('codes');

			$discount_amount = (float)$this->form_values['discount_amount'];
			$discount_amount_currency = (int)$this->form_values['discount_amount'];
			$codes_str = $this->form_values['codes'];

			$codes = array_filter(array_unique(preg_split("/\r\n|\n|\r/", $codes_str)), 'strlen');

			if (!$discount_amount)
				$this->output .= $this->displayError($this->l('Invalid discount amount'));
			else if (empty($codes))
				$this->output .= $this->displayError($this->l('At least one code is required'));
			else
			{
				/* reset form values */
				$this->form_values['discount_amount'] = '';
				$this->form_values['discount_amount_currency'] = '';
				$this->form_values['codes'] = '';

				$problem_codes = array();

				foreach ($codes as $code)
				{
					$result = $this->createRule($code, $discount_amount, $discount_amount_currency);

					if (!$result)
						$problem_codes[] = $code;
				}

				$created_codes = count($codes) - count($problem_codes);

				if ($created_codes > 0)
					$this->output .= $this->displayConfirmation("Created {$created_codes} new voucher codes");

				if (!empty($problem_codes))
					$this->output .= $this->displayError(count($problem_codes).' codes could not be created: '.implode(', ', $problem_codes));

			}
		}

		$this->context->smarty->assign(
			array(
				'action' => $this->base_uri,
				'token' => $token,
				'secret' => $secret,
				'currencies' => Currency::getCurrencies(),
				'defaultCurrency' => Configuration::get('PS_CURRENCY_DEFAULT'),
				'form_values' => $this->form_values,
				'loyaltylion_host' => $this->getLoyaltyLionHost(),
			)
		);

		$this->output .= $this->display(__FILE__, 'views/templates/admin/settingsForm.tpl');
	}

	/**
	 * Display the "signup / marketing" page, where users who are not already using LoyaltyLion
	 * can create an account (via loyaltylion.com). When a store first installs LoyaltyLion, this
	 * is the page they'll see
	 */
	public function displaySignupForm()
	{
		$shop_details = json_encode(array(
			'shop_name' => Configuration::get('PS_SHOP_NAME'),
			'shop_domain' => Configuration::get('PS_SHOP_DOMAIN'),
			'base_uri' => $_SERVER['REQUEST_URI'] 
		));

		$this->context->smarty->assign(
			array(
				'base_uri' => $this->base_uri,
				'loyaltylion_host' => $this->getLoyaltyLionHost(),
				'shop_details' => base64_encode($shop_details)
			)
		);

		$this->output .= $this->display(__FILE__, 'views/templates/admin/signupForm.tpl');
	}

	/**
	 * Fired when the displayHeader is being processed.
	 *
	 * We use it to:
	 * 1) Stick in the JavaScript SDK snippet
	 * 2) Add a referral cookie if there's a referral ID param in the URL
	 * 
	 * @return String HTML including the LoyaltyLion SDK snippet
	 */
	public function hookDisplayHeader()
	{
		// prestashop appears to run this hook prior to starting output, so it should be safe to
		// set the referral cookie here if we have one !
		$referral_id = Tools::getValue('ll_ref_id');

		/* if we have an id and we haven't already set a cookie for it (don't override existing ref cookie) */
		if ($referral_id && !$this->context->cookie->loyaltylion_referral_id)
			$this->context->cookie->__set('loyaltylion_referral_id', $referral_id);

		$customer = $this->context->customer;

		$this->context->smarty->assign(array(
			'll_token' => $this->getToken(),
			'sdk_url' => $this->getSDKUrl(),
			'platform_host' => $this->getPlatformHost(),
		));

		if ($customer)
		{
			$date = date('c');
			$auth_token = sha1($customer->id.$date.$this->getSecret());

			$this->context->smarty->assign(array(
				'customer_id' => $customer->id,
				'customer_email' => $customer->email,
				'customer_name' => '',
				'date' => $date,
				'auth_token' => $auth_token,
			));
		}

		$html = $this->display(__FILE__, 'sdk_init.tpl');

		return $html;
	}

	/**
	 * Fired when customer opens my account page.
	 *
	 * We use this hook to add Loyalty program button to My Account page.
	 * 
	 * @return [type] [description]
	 */
	public function hookDisplayCustomerAccount() {
		$html = $this->display(__FILE__, 'my_account_button.tpl');
		return $html;	
	}

	/**
	 * Fired when a customer account is created
	 *
	 * @param  [type] $params [description]
	 */
	public function hookActionCustomerAccountAdd($params)
	{
		$customer = $params['newCustomer'];
		$data = array(
			'customer_id' => $customer->id,
			'customer_email' => $customer->email,
			'date' => date('c'),
		);
	
		if ($this->context->cookie->loyaltylion_referral_id)
			$data['referral_id'] = $this->context->cookie->loyaltylion_referral_id;

		$this->loadLoyaltyLionClient();

		$response = $this->client->activities->track('signup', $data);


		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to track signup activity. API status: '
				.$response->status.', error: '.$response->error, 3);
		}
		else Configuration::updateValue ('LOYALTYLION_CONFIGURATION_OK', true);
	}

	/**
	 * Hook into `ProductComment` creates, which allows us to track when a new product comment
	 * has been created. This only supports the official Prestashop product comments module
	 *
	 * @param  [type] $params [description]
	 */
	public function hookActionObjectProductCommentAddAfter($params)
	{
		$comment = $params['object'];
		$customer = new Customer($comment->id_customer);

		$data = array(
			'customer_id' => $customer->id,
			'customer_email' => $customer->email,
			'date' => date('c'),
			'merchant_id' => $comment->id,
		);

		$this->loadLoyaltyLionClient();

		$response = $this->client->activities->track('review', $data);

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to track review activity. API status: '
				.$response->status.', error: '.$response->error, 3);
		}

		if (Configuration::get('PRODUCT_COMMENTS_MODERATE') !== '1')
		{
			// reviews do not require moderation, which means this one will be shown immediately and we should
			// send an update now to approve it right now

			$response = $this->client->activities->update('review', $comment->id, array('state' => 'approved'));

			if (!$response->success)
			{
				Logger::addLog('[LoyaltyLion] Failed to update review activity. API status: '
					.$response->status.', error: '.$response->error, 3);
			}
		}
	}

	/**
	 * Hook into `ProductComment` deletes, which lets us track when a product comment has been
	 * deleted and tell the LoyaltyLion API so any points for that review can be removed. This
	 * only supports the official Prestashop product comments module
	 * 
	 * @param  [type] $params [description]
	 * @return [type]         [description]
	 */
	public function hookActionObjectProductCommentDeleteAfter($params)
	{
		$object = $params['object'];

		if (!$object) return;

		$this->loadLoyaltyLionClient();
		$response = $this->client->activities->update('review', $object->id, array('state' => 'declined'));

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update review activity. API status: '
				.$response->status.', error: '.$response->error, 3);
		}
	}

	/**
	 * Hook into `ProductComment` validations, which lets us track when a product comment has been
	 * moderated and tell the LoyaltyLion API so any points for that review can be approved. This
	 * only supports the official Prestashop product comments module
	 * 
	 * @param  [type] $params [description]
	 * @return [type]         [description]
	 */
	public function hookActionObjectProductCommentValidateAfter($params)
	{
		$object = $params['object'];

		if (!$object) return;

		$this->loadLoyaltyLionClient();
		$response = $this->client->activities->update('review', $object->id, array('state' => 'approved'));

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update review activity. API status: '
				.$response->status.', error: '.$response->error, 3);
		}
	}

	/**
	 * Fired when an order is first created and validated
	 *
	 * @param  [type] $params [description]
	 */
	public function hookActionValidateOrder($params)
	{
		$order = $params['order'];
		$customer = new Customer((int)$order->id_customer);

		$data = array(
			/*
			an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
			to the reference if there are multiple orders with the same one
			*/
			'number' => (string)$order->getUniqReference(),
			'total' => (string)$order->total_paid,
			'total_shipping' => (string)$order->total_shipping,
			'customer_id' => $customer->id,
			'customer_email' => $customer->email,
			'merchant_id' => $order->id,
		);

		if ((float)$order->total_paid_real == 0)
			$data['payment_status'] = 'not_paid';
		else if ((float)$order->total_paid_real == (float)$order->total_paid)
			$data['payment_status'] = 'paid';
		else
		{
			$data['payment_status'] = 'partially_paid';
			$data['total_paid'] = (string)$order->total_paid_real;
		}

		if ($this->context->cookie->loyaltylion_referral_id)
			$data['referral_id'] = $this->context->cookie->loyaltylion_referral_id;

		$this->loadLoyaltyLionClient();
		$response = $this->client->orders->create($data);

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to create order ('.$order->id.'). API status: '
				.$response->status.', error: '.$response->error, 3);
		}
	}

	/**
	 * Fired when an order's status changes, e.g. becoming paid
	 *
	 * @param  [type] $params [description]
	 */
	public function hookActionOrderStatusPostUpdate($params)
	{
		$order = new Order((int)$params['id_order']);
		$this->sendOrderUpdate($order);
	}

	/**
	 * Fired when a product is removed from an order
	 * 
	 * @param  [type] $params [description]
	 */
	public function hookActionProductCancel($params)
	{
		$this->sendOrderUpdate($params['order']);
	}

	/**
	 * Fired after an order slip has been added to an order. This hook is more useful than the
	 * standard orderslip hook, as that one does not fire on partial refunds (this one will, as it's
	 * fired when any OrderSlip record is created)
	 *
	 * We use this to determine if we need to apply refunds to an order
	 * @param  [type] $params [description]
	 */
	public function hookActionObjectOrderSlipAddAfter($params)
	{
		$order = new Order((int)$params['object']->id_order);
		$this->sendOrderUpdate($order);
	}

	/**
	 * Given an order, pull out all needed info and send a full update to LoyaltyLion
	 *
	 * This is an idempotent operation in that it sends a full copy of the order to the LoyaltyLion
	 * order update endpoint, so it's safe to call this whenever there is any change to a Prestashop order
	 * 
	 * @param  [type] $order [description]
	 */
	private function sendOrderUpdate($order)
	{
		if (!$order) return;

		$data = array(
			/*
			an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
			to the reference if there are multiple orders with the same one
			*/
			'number' => (string)$order->getUniqReference(),
			'refund_status' => 'not_refunded',
			'cancellation_status' => 'not_cancelled',
			'total_refunded' => 0,
		);

		if ((float)$order->total_paid_real == 0)
		{
			$data['payment_status'] = 'not_paid';
			$data['total_paid'] = 0;
		}
		else if ((float)$order->total_paid_real == (float)$order->total_paid)
		{
			$data['payment_status'] = 'paid';
			$data['total_paid'] = (string)$order->total_paid;
		}
		else
		{
			$data['payment_status'] = 'partially_paid';
			$data['total_paid'] = (string)$order->total_paid_real;
		}

		/* cancelled? */
		if ($order->getCurrentState() == Configuration::get('PS_OS_CANCELED'))
			$data['cancellation_status'] = 'cancelled';
		/*
		credit slip hook
		actionOrderSlipAdd
		actionProductCancel

		refunds in prestashop are a bit of a clusterfuck, so this isn't too simple and might still have bugs
		*/

		$total_refunded = 0;

		/* i think we can simplify this by querying for credit (order) slips attached to this order */
		$credit_slips = OrderSlip::getOrdersSlip($order->id_customer, $order->id);

		/*
		 * if we have at least one credit slip that should mean we have had a refund, so let's add them
		 * NOTE: the "amount" is the unit price of the product * quantity refunded, plus shipping if they opted
		 * refund the shipping cost. However PS doesn't stop you from refunding shipping cost more than
		 * once if you do multiple refunds, so the refund total could end up more than the actual total
		 * ... if this happens we will just cap it to the order total so it doesn't confuse loyaltylion
		*/

		// what the fuck
		// why can't I do this?

		foreach ($credit_slips as $slip)
		{
			if (!$slip['amount']) continue;

			$total_refunded += $slip['amount'];
		}

		if ($total_refunded > 0)
		{
			if ($total_refunded < (float)$order->total_paid)
			{
				$data['refund_status'] = 'partially_refunded';
				$data['total_refunded'] = $total_refunded;
			}
			else
			{
				/*
				if the total refunded is equal (or, perhaps, greater than?) the total cost of the order,
				we'll just class that as a full refund
				*/
				$data['refund_status'] = 'refunded';
				$data['total_refunded'] = (float)$order->total_paid;
			}
		}

		/* refund state: PS_OS_REFUND */

		$this->loadLoyaltyLionClient();
		$response = $this->client->orders->update($order->id, $data);

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update order ('.$order->id.'). API status: '
				.$response->status.', error: '.$response->error, 3);
		}
	}

	/**
	 * Determine which "state" we should render for the Configuration page
	 *
	 * The two states we have are "signup" and "settings". If a token/secret has been provided, a
	 * settings update is in process, or the `force_show_settings` param is set, we'll show the "settings" page
	 *
	 * @return String 'signup' or 'settings'
	 */
	private function getConfigurationAction()
	{
		$action = 'signup';

		if (Tools::isSubmit('gotoSettings')
				|| Tools::isSubmit('submitConfiguration')
				|| Tools::isSubmit('submitVoucherCodes')
				|| $this->getToken()
				|| $this->getSecret()
				|| Tools::getValue('force_show_settings'))
			$action = 'settings';

		if (Tools::getValue('force_show_signup'))
			$action = 'signup';

		if (Tools::getValue('set_token_secret'))
			$action = 'set_token_secret';

		if (Tools::getValue('add_voucher_codes'))
			$action = 'add_voucher_codes';

		return $action;
	}

	/**
	 * Load the PHP LoyaltyLion library and initialise it
	 */
	private function loadLoyaltyLionClient()
	{
		require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.
			'lib'.DIRECTORY_SEPARATOR.'loyaltylion-client'.DIRECTORY_SEPARATOR.'main.php');

		$options = array();

		if (isset($_SERVER['LOYALTYLION_API_BASE']))
			$options['base_uri'] = $_SERVER['LOYALTYLION_API_BASE'];


		$this->client = new LoyaltyLion_Client($this->getToken(), $this->getSecret(), $options);
	}

	/**
	 * Get this store's LoyaltyLion token from the configuration
	 * @return String LoyaltyLion token
	 */
	private function getToken()
	{
		return Configuration::get('LOYALTYLION_TOKEN');
	}

	/**
	 * Get this store's LoyaltyLion secret from the configuration
	 * @return String LoyaltyLion secret
	 */
	private function getSecret()
	{
		return Configuration::get('LOYALTYLION_SECRET');
	}

	/**
	 * Get the URL (domain & path) to the LoyaltyLion JS SDK
	 *
	 * If a server environment variable has been set for this, it will be returned, which allows
	 * configuring the module to work in different environments (e.g. development and staging); if not,
	 * this will return the default production value
	 * 
	 * @return String SDK URL (domain and path)
	 */
	private function getSDKUrl()
	{
		return isset($_SERVER['LOYALTYLION_SDK_URL'])
			? $_SERVER['LOYALTYLION_SDK_URL']
			: 'dg1f2pfrgjxdq.cloudfront.net/libs/ll.sdk-1.1.js';
	}

	/**
	 * Get the LoyaltyLion platform host (e.g. `platform.loyaltylion.com`)
	 *
	 * If a server environment variable has been set for this, it will be returned, which allows 
	 * configuring the module to work in different environments (e.g. development and staging); if not,
	 * this will return the default production value
	 * 
	 * @return String LoyaltyLion platform host
	 */
	private function getPlatformHost()
	{
		return isset($_SERVER['LOYALTYLION_PLATFORM_HOST'])
			? $_SERVER['LOYALTYLION_PLATFORM_HOST']
			: 'platform.loyaltylion.com';
	}

	/**
	 * Get the main LoyaltyLion host (e.g. `loyaltylion.com`)
	 *
	 * If a server environment variable has been set for this, it will be returned, which allows 
	 * configuring the module to work in different environments (e.g. development and staging); if not,
	 * this will return the default production value
	 * 
	 * @return String LoyaltyLion host, e.g. 'loyaltylion.com'
	 */
	private function getLoyaltyLionHost()
	{
		return isset($_SERVER['LOYALTYLION_HOST'])
			? $_SERVER['LOYALTYLION_HOST']
			: 'loyaltylion.com';
	}

	/**
	 * Set the base URI for this module page
	 */
	private function setBaseUri()
	{
		$this->base_uri = 'index.php?';

		foreach ($_GET as $k => $value)
			// don't include conf parameter, because that is passed in when app is installed
			// and isn't needed after that. we also don't want any of our own parameters because
			// we'll use those to navigate between pages (e.g. to force view the settings page)
			if (!in_array($k, array('conf', 'force_show_settings', 'force_show_signup')))
				$this->base_uri .= $k.'='.$value.'&';

		$this->base_uri = rtrim($this->base_uri, '&');
	}

	/**
	 * Gets rewards from Merchant account. Uses LoyaltyLion PHP SDK with different
	 * base url.
	 */
	private function getRewardsWithVoucherCodes() {
		$base_uri = 'http://'.$this->getLoyaltyLionHost().'/prestashop';
		$connection = new LoyaltyLion_Connection($this->getToken(), $this->getSecret(), $base_uri);

		$response = $connection->get('/rewards_with_voucher_codes');
		if (isset($response->error)) return;

		$rewards = json_decode($response->body);
		return $rewards;
	}

	/**
	 * Updates metadata information of site on LoyaltLion.
	 * 
	 * @return [type] [description]
	 */
	private function updateSiteMetadata($data) {
		$base_uri = 'http://'.$this->getLoyaltyLionHost().'/prestashop';
		$connection = new LoyaltyLion_Connection($this->getToken(), $this->getSecret(), $base_uri);
		$response = $connection->post('/metadata', array('metadata' => $data));
		
		if (isset($response->error)) return;

		$rewards = json_decode($response->body);
		return $rewards;
	}

	/**
	 * Checks if code is added (because Prestashop lets you add existing code again!) 
	 * If it's not added, creates a rule and returns the result of add operation.
	 * 
	 * @param  [type] $code                     [description]
	 * @param  [type] $discount_amount          [description]
	 * @param  [type] $discount_amount_currency [description]
	 * @return [type]                           [description]
	 */
	private function createRule($code, $discount_amount, $discount_amount_currency) {
		$existing_codes = CartRule::getCartsRuleByCode($code, (int)$this->context->language->id);

		if (!empty($existing_codes))
			return false;

		$rule = new CartRule();

		$rule->code = $code;
		$rule->description = $this->l('Generated LoyaltyLion voucher');
		$rule->quantity = 1;
		$rule->quantity_per_user = 1;

		$now = time();
		$rule->date_from = date('Y-m-d H:i:s', $now);
		$rule->date_to = date('Y-m-d H:i:s', $now + (3600 * 24 * 365 * 10)); /* 10 years */
		$rule->active = 1;

		$rule->reduction_amount = $discount_amount;
		$rule->reduction_tax = true;
		$rule->reduction_currency = $discount_amount_currency;

		foreach (Language::getLanguages() as $language)
			$rule->name[$language['id_lang']] = $code;

		return $rule->add();
	}

	/**
	 * Iterates over all currencies, if iso code of currency
	 * is same with currency code we look for, returs the id of it.
	 * 
	 * @param  [type] $code [description]
	 * @return [type]       [description]
	 */
	private function getCurrencyId($code) {
		$currencies = Currency::getCurrencies();

		foreach ($currencies as $currency) {
			if(strtolower($currency['iso_code']) == strtolower($code))
				return $currency['id_currency'];
		}

	}

}