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
		$this->tab = 'advertising_marketing';
		$this->version = '1.2.4';
		$this->author = 'LoyaltyLion';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('LoyaltyLion');
		$this->description = $this->l('Add a loyalty program to your store in minutes. Increase customer loyalty'.
			' and happiness by rewarding referrals, purchases, signups, reviews and visits.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}

	public function install()
	{
		if (!function_exists('curl_init'))
		{
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
			$this->registerHook('actionCustomerAccountAdd');
	}

	public function getContent()
	{
		$this->base_uri = $this->getBaseUri();

		if (isset($this->context->controller))
			$this->context->controller->addCSS($this->_path.'/css/loyaltylion.min.css', 'all');
		else
			echo '<link rel="stylesheet" type="text/css" href="../modules/loyaltylion-prestashop/css/loyaltylion.min.css" />';

		switch ($this->getConfigurationAction())
		{
			case 'signup':
				$this->displaySignupForm();
				break;
			case 'set_token_secret':
				$this->setTokenAndSecret();
				break;
			case 'create_reward':
				$this->displayCreateVouchersAsync();
				break;
			case 'create_reward_async':
				$this->createReward();
				break;
			case 'reset_configuration':
				$this->resetConfiguration();
				$this->displaySignupForm();
				break;
			default:
				$this->displaySettingsForm();
		}

		return $this->output;
	}

	/**
	 * Set the `LOYALTYLION_TOKEN` and `LOYALTYLION_SECRET` configuration values using the values
	 * provided in the URL
	 *
	 * This is typically initiated during the setup procedure at loyaltylion.com, where we open this
	 * page in a popup window and pass it the token & secret, which are generated during the initial
	 * signup. This allows us to set the token and secret automatically, without the store owner having
	 * to manually copy & paste them in
	 */
	private function setTokenAndSecret()
	{
		$token = Tools::getValue('loyaltylion_token');
		$secret = Tools::getValue('loyaltylion_secret');

		if (!$token || !$secret)
			return $this->displaySignupForm();

		Configuration::updateValue('LOYALTYLION_TOKEN', $token);
		Configuration::updateValue('LOYALTYLION_SECRET', $secret);

		// let LoyaltyLion know that we've set the token & secret, so setup can proceed over at loyaltylion.com
		$this->updateSiteMetadata(array('token_and_secret_set' => true));

		$this->output .= $this->display(__FILE__, 'views/templates/admin/setTokenAndSecret.tpl');
	}

	/**
	 * Reset all configuration values set for the LoyaltyLion module
	 *
	 * This will remove everything (e.g. token and secret), and is useful for testing or fixing issues
	 */
	private function resetConfiguration()
	{
		Configuration::updateValue('LOYALTYLION_TOKEN', null);
		Configuration::updateValue('LOYALTYLION_SECRET', null);
	}

	/**
	 * Create a new LoyaltyLion reward
	 *
	 * This should be called in a pop-up window from loyaltylion.com, and currently only supports creating
	 * discounts, but could perhaps be extended in future to automatically create other types of reward
	 *
	 * For rewards of type discount:
	 * 	
	 * This method will send an API request to loyaltylion.com to create (or find) an reward with this
	 * discount amount on the server and generate (and then retrieve) the requested amount of codes
	 *
	 * It will then create the generated codes in PrestaShop so they're actually real, and then finally report
	 * this back to loyaltylion.com so it knows the codes are valid and can be given out as rewards
	 * 
	 * @return [type] [description]
	 */
	private function createReward()
	{
		$reward_data = Tools::getValue('ll_create_reward_async');

		if (!empty($reward_data))
			$reward_data = Tools::jsonDecode(urldecode($reward_data));

		if (!$reward_data || $reward_data->type != 'discount' || !$reward_data->discount_amount || !$reward_data->codes_to_generate)
			$this->render('582', 422);

		$connection = $this->getWebConnection();

		// tell our server to automatically create this reward; this will return an array of codes we should then add to PS
		$response = $connection->post('/prestashop/auto_create_reward', $reward_data);

		// if anything goes wrong here (or anywhere else in this method) we'll return an error code so
		// we at least have some idea where to start looking if a merchant calls this in
		if (isset($response->error) || !$response->body)
			$this->render('583', 500);

		$body = Tools::jsonDecode($response->body);
		$codes = $body->generated_codes;
		$batch_id = $body->batch_id;

		if (empty($codes) || empty($batch_id))
			$this->render('584', 500);

		$amount = $reward_data->discount_amount;
		$currency_id = $this->getCurrencyId($reward_data->discount_currency);

		if (!$currency_id)
			$currency_id = Currency::getDefaultCurrency()->id_currency;

		$problem_codes = array();

		foreach ($codes as $code)
		{
			$result = $this->createRule($code, $amount, $currency_id);
			if (!$result)
				$problem_codes[] = $code;
		}

		// almost done - now we need to tell loyaltylion again that we've successfully imported these codes, and
		// can therefore finalise the reward over there -- if there were any problem codes, we'll send these so
		// they can be exluded and not given out to customers
		$reward_data->batch_id = $batch_id;
		$reward_data->problem_codes = $problem_codes;

		$response = $connection->post('/prestashop/auto_create_reward', $reward_data);

		if (isset($response->error))
			// TODO: if something bad happened over at LL, we could clean up the CartRules here
			$this->render('584', 500);
		else
			$this->render('', 200);
	}

	/**
	 * Display a page which will immediately trigger an ajax request back here, to create
	 * a LoyaltyLion reward, get a list of generated codes, import said codes into PS, and
	 * finally tell LoyaltyLion when its safe to finalise the voucher
	 *
	 * This operation can take some time, so for a more pleasant user experience, during
	 * setup we open a popup window to this page so the operation occurs in the background,
	 * so the user doesn't have to sit looking at a loading page wondering if something
	 * went wrong
	 * 
	 * @return [type] [description]
	 */
	private function displayCreateVouchersAsync()
	{
		$reward_data = Tools::getValue('ll_create_reward');

		if (empty($reward_data))
			return;

		$reward_data_decoded = Tools::jsonDecode(urldecode($reward_data));

		if (!$reward_data_decoded
			|| $reward_data_decoded->type != 'discount'
			|| !$reward_data_decoded->discount_amount
			|| !$reward_data_decoded->codes_to_generate)
		{
			$this->output .= $this->displayError($this->l('Sorry - something went wrong'));
			return;
		}

		$currency = $this->getCurrency($reward_data_decoded->discount_currency);

		$this->context->smarty->assign(array(
			'create_voucher_codes_url' => str_replace('ll_create_reward', 'll_create_reward_async', $_SERVER['REQUEST_URI']),
			'reward_data' => $reward_data,
			'discount_amount' => $reward_data_decoded->discount_amount,
			'codes_to_generate' => $reward_data_decoded->codes_to_generate,
			'currency' => $currency ? $currency['sign'] : '',
		));

		$this->output .= $this->display(__FILE__, 'views/templates/admin/createVouchersAsync.tpl');
	}

	/**
	 * Display (and handle updates of) the settings form, where users can update their token/secret
	 * and batch import voucher codes
	 */
	private function displaySettingsForm()
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

		$this->context->smarty->assign(array(
			'base_uri' => $this->base_uri,
			'action' => $this->base_uri,
			'token' => $token,
			'secret' => $secret,
			'currencies' => Currency::getCurrencies(),
			'defaultCurrency' => Configuration::get('PS_CURRENCY_DEFAULT'),
			'form_values' => $this->form_values,
			'loyaltylion_host' => $this->getLoyaltyLionHost(),
		));

		$this->output .= $this->display(__FILE__, 'views/templates/admin/settingsForm.tpl');
	}

	/**
	 * Display the "signup / marketing" page, where users who are not already using LoyaltyLion
	 * can create an account (via loyaltylion.com). When a store first installs LoyaltyLion, this
	 * is the page they'll see
	 */
	private function displaySignupForm()
	{
		$shop_details = array(
			'name' => Configuration::get('PS_SHOP_NAME'),
			'url' => $this->context->shop->getBaseURL(),
			'version' => _PS_VERSION_,
			'currencies' => array(),
			'languages' => array(),
		);

		// construct a url for this (loyaltylion) module page, so we can direct merchants to this
		// page from pages on loyaltylion.com
		$module_url = str_replace($_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
		$module_url = (Tools::usingSecureMode() || Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://')
			.Configuration::get('PS_SHOP_DOMAIN').$module_url;

		$shop_details['module_base_url'] = $this->getBaseUri($module_url);

		$default_currency = Currency::getDefaultCurrency();
		$default_language = $this->context->language;

		$shop_details['default_currency'] = $default_currency->iso_code;
		$shop_details['default_language'] = array(
			'iso_code' => $default_language->iso_code,
			'language_code' => $default_language->language_code,
		);

		// add currencies to shop details packet, so we can try to set the default currency during setup
		foreach (Currency::getCurrencies() as $currency)
			if ($currency['iso_code'] != $shop_details['default_currency'])
				$shop_details['currencies'][] = $currency['iso_code'];

		// same thing for languages (we'll send both iso code and language code, the latter we could use
		// to automatically set the right locale settings)
		foreach (Language::getLanguages() as $language)
			if ($language['iso_code'] != $shop_details['default_language']['iso_code'])
				$shop_details['languages'][] = array(
					'iso_code' => $language['iso_code'],
					'language_code' => $language['language_code'],
				);

		switch ($default_currency->iso_code)
		{
			case 'GBP':
				$pricing = array(25, 49, 99, 249);
				$pricing_sign = '£';
				break;
			case 'EUR':
				$pricing = array(29, 59, 119, 299);
				$pricing_sign = '€';
				break;
			default:
				$pricing = array(39, 79, 159, 399);
				$pricing_sign = '$';
		}

		$this->context->smarty->assign(array(
			'base_uri' => $this->base_uri,
			'loyaltylion_host' => $this->getLoyaltyLionHost(),
			'shop_details' => urlencode(Tools::jsonEncode($shop_details)),
			'currency_code' => $default_currency->iso_code,
			'currency_sign' => $default_currency->sign,
			'pricing' => $pricing,
			'pricing_sign' => $pricing_sign,
		));

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
		if (!$this->isLoyaltyLionConfigured()) return;

		// prestashop appears to run this hook prior to starting output, so it should be safe to
		// set the referral cookie here if we have one !
		$referral_id = Tools::getValue('ll_ref_id');

		// if we have an id and we haven't already set a cookie for it (don't override existing ref cookie)
		if ($referral_id && !$this->context->cookie->loyaltylion_referral_id)
			$this->context->cookie->__set('loyaltylion_referral_id', $referral_id);

		$tracking_id = Tools::getValue('ll_eid');

		if ($tracking_id) {
			// I don't trust using $_SESSION as it's never used anywhere else in PrestaShop, so I'm
			// concerned it might randomly break other installs. So instead we'll use the standard cookie
			// store, but just in case it ends up being persisted forever, we'll prefix the tracking id
			// with a timestamp so we can only send it when tracking if it's less than 24 hrs old
			$value = time().':::'.$tracking_id;
			$this->context->cookie->__set('loyaltylion_tracking_id', $value);
		}

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
				'is_guest_customer' => $customer->is_guest,
				'date' => $date,
				'auth_token' => $auth_token,
			));
		}

		$html = $this->display(__FILE__, 'sdk_init.tpl');

		return $html;
	}

	/**
	 * Fired when a customer account is created
	 *
	 * @param  [type] $params [description]
	 */
	public function hookActionCustomerAccountAdd($params)
	{
		if (!$this->isLoyaltyLionConfigured()) return;

		$customer = $params['newCustomer'];
		$data = array(
			'customer_id' => $customer->id,
			'customer_email' => $customer->email,
			'date' => date('c'),
			'guest' => $customer->is_guest,
			'ip_address' => Tools::getRemoteAddr(),
			'user_agent' => $_SERVER['HTTP_USER_AGENT']
		);

		if ($this->context->cookie->loyaltylion_referral_id)
			$data['referral_id'] = $this->context->cookie->loyaltylion_referral_id;

		$tracking_id = $this->getTrackingIdFromCookie();

		if ($tracking_id)
			$data['tracking_id'] = $tracking_id;

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
		if (!$this->isLoyaltyLionConfigured()) return;

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
		if (!$this->isLoyaltyLionConfigured()) return;

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
		if (!$this->isLoyaltyLionConfigured()) return;

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
		if (!$this->isLoyaltyLionConfigured()) return;

		$order = $params['order'];
		$customer = new Customer((int)$order->id_customer);

		$data = array(
			// an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
			// to the reference if there are multiple orders with the same one
			'number' => (string)$order->getUniqReference(),
			'total' => $this->convertPrice($order->total_paid, $order->conversion_rate),
			'total_shipping' => $this->convertPrice($order->total_shipping, $order->conversion_rate),
			'customer_id' => $customer->id,
			'customer_email' => $customer->email,
			'guest' => $customer->is_guest == '1',
			'merchant_id' => $order->id,
			'ip_address' => Tools::getRemoteAddr(),
			'user_agent' => $_SERVER['HTTP_USER_AGENT']
		);

		if ((float)$order->total_paid_real == 0)
			$data['payment_status'] = 'not_paid';
		else if ((float)$order->total_paid_real == (float)$order->total_paid)
			$data['payment_status'] = 'paid';
		else
		{
			$data['payment_status'] = 'partially_paid';
			$data['total_paid'] = $this->convertPrice($order->total_paid_real, $order->conversion_rate);
		}

		if ($this->context->cookie->loyaltylion_referral_id)
			$data['referral_id'] = $this->context->cookie->loyaltylion_referral_id;

		$tracking_id = $this->getTrackingIdFromCookie();

		if ($tracking_id)
			$data['tracking_id'] = $tracking_id;

		$cart_rules = $order->getCartRules();

		if (!empty($cart_rules)) {
			$data['discount_codes'] = array();

			foreach ($cart_rules as $cart_rule) {
				if (!$cart_rule['name'] || !$cart_rule['value'])
					continue;

				$data['discount_codes'][] = array(
					'code' => $cart_rule['name'],
					'amount' => $cart_rule['value'],
				);
			}
		}

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
		if (!$this->isLoyaltyLionConfigured()) return;

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
		if (!$this->isLoyaltyLionConfigured()) return;

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
		if (!$this->isLoyaltyLionConfigured()) return;

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
			// an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
			// to the reference if there are multiple orders with the same one
			'number' => (string)$order->getUniqReference(),
			'refund_status' => 'not_refunded',
			'cancellation_status' => 'not_cancelled',
			'total_refunded' => 0,
			'ip_address' => Tools::getRemoteAddr(),
			'user_agent' => $_SERVER['HTTP_USER_AGENT']
		);

		if ((float)$order->total_paid_real == 0)
		{
			$data['payment_status'] = 'not_paid';
			$data['total_paid'] = 0;
		}
		else if ((float)$order->total_paid_real == (float)$order->total_paid)
		{
			$data['payment_status'] = 'paid';
			$data['total_paid'] = $this->convertPrice($order->total_paid, $order->conversion_rate);
		}
		else
		{
			$data['payment_status'] = 'partially_paid';
			$data['total_paid'] = $this->convertPrice($order->total_paid_real, $order->conversion_rate);
		}

		// cancelled?
		if ($order->getCurrentState() == Configuration::get('PS_OS_CANCELED'))
			$data['cancellation_status'] = 'cancelled';

		// credit slip hook
		// actionOrderSlipAdd
		// actionProductCancel
		// refunds in prestashop are a bit of a clusterfuck, so this isn't too simple and might still have bugs

		$total_refunded = 0;

		// i think we can simplify this by querying for credit (order) slips attached to this order
		$credit_slips = OrderSlip::getOrdersSlip($order->id_customer, $order->id);

		// if we have at least one credit slip that should mean we have had a refund, so let's add them
		// NOTE: the "amount" is the unit price of the product * quantity refunded, plus shipping if they opted
		// refund the shipping cost. However PS doesn't stop you from refunding shipping cost more than
		// once if you do multiple refunds, so the refund total could end up more than the actual total
		// ... if this happens we will just cap it to the order total so it doesn't confuse loyaltylion

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
				// if the total refunded is equal (or, perhaps, greater than?) the total cost of the order,
				// we'll just class that as a full refund
				$data['refund_status'] = 'refunded';
				$data['total_refunded'] = (float)$order->total_paid;
			}
		}

		// order slips have their own exchange rate, but because LL is treating this order in the
		// default currency, we're going to use the order's original rate, not the order slips. this
		// might lead to some minor inaccuracies if there is a long period between the order & refunds,
		// based on exchange rate fluctuations, but this seems the most reasonable and robust way to
		// deal with them for now

		if ($data['total_refunded'])
			$data['total_refunded'] = $this->convertPrice($data['total_refunded'], $order->conversion_rate);

		// refund state: PS_OS_REFUND

		$this->loadLoyaltyLionClient();
		$response = $this->client->orders->update($order->id, $data);

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update order ('.$order->id.'). API status: '
				.$response->status.', error: '.$response->error, 3);
		}
	}

	/**
	 * Convert a price using the given exchange rate
	 *
	 * PrestaShop orders can be placed in any currency. If they are placed in a currency that is not
	 * the default, their `conversion_rate` field will be set. I believe this rate is based on the
	 * conversion rate between the currency and the default at the time of the order.
	 *
	 * @param  [type] $amount
	 * @param  [type] $rate
	 * @return String The converted (if applicable) amount, as a string, e.g. '12.97'
	 */
	private function convertPrice($price, $rate)
	{
		$rate = (float)$rate;
		$price = (float)$price;

		if ($rate == 1 || $rate == 0)
			return number_format($price, 2);

		return number_format($price / $rate, 2);
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

		// force the display of the signup page
		if (Tools::getValue('force_show_signup'))
			$action = 'signup';

		// automatically set a token and secret (given as url parameters)
		if (Tools::getValue('ll_set_token_secret'))
			$action = 'set_token_secret';

		// display the create reward async page (which will trigger an ajax req)
		if (Tools::getValue('ll_create_reward'))
			$action = 'create_reward';

		// create the reward (designed to be called via ajax for a nice UX)
		if (Tools::getValue('ll_create_reward_async'))
			$action = 'create_reward_async';

		// reset all loyaltylion configuration values (e.g. token/secret)
		if (Tools::getValue('ll_reset'))
			$action = 'reset_configuration';

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
	 * Create and return a new Connection to make requests to the LoyaltyLion "web" server, i.e.
	 * our front-end server used for integration with platforms (NOT for tracking events, etc)
	 * 
	 * @return [type] [description]
	 */
	private function getWebConnection()
	{
		require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.
			'lib'.DIRECTORY_SEPARATOR.'loyaltylion-client'.DIRECTORY_SEPARATOR.'main.php');

		$base_uri = ($this->getLoyaltyLionSslEnabled() ? 'https://' : 'http://').$this->getLoyaltyLionHost();
		$connection = new LoyaltyLion_Connection($this->getToken(), $this->getSecret(), $base_uri);

		return $connection;
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
	 * Determine if we should be using SSL for any HTTP requests we make to LoyaltyLion's servers
	 *
	 * Unless this is explicitly set server environment variable, this will default to true. For example,
	 * in development you might want to set the 'LOYALTYLION_SSL_ENABLED' variable to '0'
	 * 
	 * @return [type] [description]
	 */
	private function getLoyaltyLionSslEnabled()
	{
		return isset($_SERVER['LOYALTYLION_SSL_ENABLED'])
			? !!$_SERVER['LOYALTYLION_SSL_ENABLED']
			: true;
	}

	/**
	 * Set the base URI for this module page
	 */
	private function getBaseUri($base = 'index.php?')
	{
		foreach ($_GET as $k => $value)
			// don't include conf parameter, because that is passed in when app is installed
			// and isn't needed after that. we also don't want any of our own parameters because
			// we'll use those to navigate between pages (e.g. to force view the settings page)
			if (!in_array($k, array('conf', 'force_show_settings', 'force_show_signup')) && Tools::substr($k, 0, 3) != 'll_')
				$base .= $k.'='.$value.'&';

		return rtrim($base, '&');
	}

	/**
	 * Updates metadata information of site on LoyaltLion.
	 * 
	 * @return [type] [description]
	 */
	private function updateSiteMetadata($data)
	{
		$connection = $this->getWebConnection();
		$response = $connection->post('/prestashop/metadata', array('metadata' => $data));

		return isset($response->error);
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
	private function createRule($code, $discount_amount, $discount_amount_currency)
	{
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
		$rule->date_to = date('Y-m-d H:i:s', $now + (3600 * 24 * 365 * 10)); // 10 years
		$rule->active = 1;

		$rule->reduction_amount = $discount_amount;
		$rule->reduction_tax = true;
		$rule->reduction_currency = $discount_amount_currency;

		foreach (Language::getLanguages() as $language)
			$rule->name[$language['id_lang']] = $code;

		return $rule->add();
	}

	/**
	 * Check the current cookie for a LoyaltyLion `tracking_id`
	 *
	 * If this id exists, and has not expired, it will be returned
	 *
	 * @return [type] Tracking id or null if it doesn't exist or has expired
	 */
	private function getTrackingIdFromCookie() {
		if (!$this->context->cookie->loyaltylion_tracking_id)
			return null;

		$values = explode(':::', $this->context->cookie->loyaltylion_tracking_id);

		if (empty($values))
			return null;

		if (count($values) != 2)
			return $values[0];

		// for now, let's have a 24 hour expiration time on the timestamp
		if (time() - (int)$values[0] > 86400)
			return null;

		return $values[1];
	}

	/**
	 * Get a PrestaShop currency by looking it up with an `iso_code`
	 *
	 * If a currency with this code exists, it will be returned (as an associative array, not
	 * an Object). If no such currency exists null will be returned
	 *
	 * @param  [type] $code [description]
	 * @return [type]       [description]
	 */
	private function getCurrency($code)
	{
		$currencies = Currency::getCurrencies();

		foreach ($currencies as $currency)
			if (Tools::strtolower($currency['iso_code']) == Tools::strtolower($code))
				return $currency;

		return null;
	}

	/**
	 * Iterates over all currencies, if iso code of currency
	 * is same with currency code we look for, returs the id of it.
	 *
	 * @param  [type] $code [description]
	 * @return [type]       [description]
	 */
	private function getCurrencyId($code)
	{
		$currency = $this->getCurrency($code);

		if ($currency)
			return $currency['id_currency'];
		else
			return null;
	}

	/**
	 * Render immediately (i.e. for ajax requests)
	 *
	 * If an array is provided as $body this will render a JSON response
	 *
	 * @param  [type]  $body        [description]
	 * @param  integer $status_code [description]
	 * @return [type]               [description]
	 */
	private function render($body, $status_code = 200)
	{
		header('X-PHP-Response-Code: '.$status_code, true, (int)$status_code);

		if (!empty($body) && is_array($body))
		{
			header('Content-Type: application/json');
			$body = Tools::jsonEncode($body);
		}

		die($body);
	}

	/**
	 * Checks if token and secret are set in configuration
	 * 
	 * @return boolean [description]
	 */
	private function isLoyaltyLionConfigured()
	{
		$token = $this->getToken();
		$secret = $this->getSecret();
		return !empty($token) && !empty($secret);
	}
}
