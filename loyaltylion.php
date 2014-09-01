<?php

if (!defined('_PS_VERSION_')) exit;

class LoyaltyLion extends Module
{

	public $form_values = array(
		'discount_amount' => '',
		'discount_amount_currency' => '',
		'codes' => '',
	);

	public function __construct()
	{
		$this->name = 'loyaltylion';
		$this->tab = 'pricing_promotion';
		$this->version = '1.0';
		$this->author = 'LoyaltyLion';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');

		parent::__construct();

		$this->displayName = $this->l('LoyaltyLion');
		$this->description = $this->l('LoyaltyLion Prestashop module');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		// Logger::addLog(var_export($this, true));
	}

	public function install()
	{
		return parent::install() &&
			$this->registerHook('displayHeader') &&
			$this->registerHook('displayOrderConfirmation') &&
			$this->registerHook('actionOrderStatusPostUpdate') &&
			$this->registerHook('actionValidateOrder') &&
			$this->registerHook('actionProductCancel') &&
			// $this->registerHook('actionOrderSlipAdd') &&
			$this->registerHook('actionObjectOrderSlipAddAfter') &&
			$this->registerHook('actionObjectProductCommentAddAfter') &&
			$this->registerHook('actionLoyaltyLionProductCommentAccepted') &&
			$this->registerHook('actionLoyaltyLionProductCommentDeleted') &&
			$this->registerHook('actionCustomerAccountAdd');
	}

	public function uninstall()
	{
		parent::uninstall();
	}

	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submitConfiguration'))
		{
			Configuration::updateValue('LOYALTYLION_TOKEN', Tools::getValue('loyaltylion_token'));
			Configuration::updateValue('LOYALTYLION_SECRET', Tools::getValue('loyaltylion_secret'));

			$output .= $this->displayConfirmation($this->l('Configuration saved'));
		}

		if (Tools::isSubmit('submitVoucherCodes'))
		{
			// we probs need to create some vouchers now...

			$this->form_values['discount_amount'] = Tools::getValue('discount_amount');
			$this->form_values['discount_amount_currency'] = Tools::getValue('discount_amount_currency');
			$this->form_values['codes'] = Tools::getValue('codes');

			$discount_amount = floatval($this->form_values['discount_amount']);
			$discount_amount_currency = intval($this->form_values['discount_amount']);
			$codes_str = $this->form_values['codes'];

			$codes = array_filter(array_unique(preg_split("/\r\n|\n|\r/", $codes_str)), 'strlen');

			if (!$discount_amount)
			{
				$output .= $this->displayError($this->l('Invalid discount amount'));
			} else if (empty($codes))
			{
				$output .= $this->displayError($this->l('At least one code is required'));
			} else
			{
				// reset form values
				$this->form_values['discount_amount'] = '';
				$this->form_values['discount_amount_currency'] = '';
				$this->form_values['codes'] = '';

				$problem_codes = array();

				foreach ($codes as $code)
				{

					// check if already exists, don't add it again, even though prestashop will let you (come on!)
					$existing_codes = CartRule::getCartsRuleByCode($code, (int)$this->context->language->id);

					if (!empty($existing_codes))
					{
						$problem_codes[] = $code;
						continue;
					}

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
					{
						$rule->name[$language['id_lang']] = $code;
					}

					if (!$rule->add())
					{
						$problem_codes[] = $code;
					}
				}

				$created_codes = count($codes) - count($problem_codes);

				if ($created_codes > 0)
				{
					$output .= $this->displayConfirmation("Created {$created_codes} new voucher codes");
				}

				if (!empty($problem_codes))
				{
					$output .= $this->displayError(count($problem_codes) . " codes could not be created: " . implode(', ', $problem_codes));
				}
			}
		}

		return $output . $this->displayForm();
	}

	public function displayForm()
	{
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$baseUrl = 'index.php?';
		foreach ($_GET as $k => $value)
		{
			$baseUrl .= $k . '=' . $value . '&';
		}
		$baseUrl = rtrim($baseUrl, '&');

		$this->context->smarty->assign(
			array(
				'action' => Tools::safeOutput($baseUrl),
				'token' => $this->getToken(),
				'secret' => $this->getSecret(),
				'currencies' => Currency::getCurrencies(),
				'defaultCurrency' => Configuration::get('PS_CURRENCY_DEFAULT'),
				'form_values' => $this->form_values,
			)
		);

		return $this->display(__FILE__, 'form.tpl');
	}

	public function hookDisplayHeader()
	{

		// prestashop appears to run this hook prior to starting output, so it should be safe to
		// set the referral cookie here if we have one !
		$referral_id = Tools::getValue('ll_ref_id');

		// if we have an id and we haven't already set a cookie for it (don't override existing ref cookie)
		if ($referral_id && !$this->context->cookie->loyaltylion_referral_id)
		{
			$this->context->cookie->__set('loyaltylion_referral_id', $referral_id);
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
			$auth_token = sha1($customer->id . $date . $this->getSecret());

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
	 * Fired when a customer account is created
	 *
	 * @param  [type] $params [description]
	 * @return [type]         [description]
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
				. $response->status . ', error: ' . $response->error, 3);
		}
	}

	/**
	 * Hook into `ProductComment` creates, which allows us to track when a new product comment
	 * has been created. This only supports the official Prestashop product comments module
	 *
	 * @param  [type] $params [description]
	 * @return [type]         [description]
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
				. $response->status . ', error: ' . $response->error, 3);
		}

		if (Configuration::get('PRODUCT_COMMENTS_MODERATE') !== '1')
		{
			// reviews do not require moderation, which means this one will be shown immediately and we should
			// send an update now to approve it

			$response = $this->client->activities->update('review', $comment->id, array('state' => 'approved'));

			if (!$response->success)
			{
				Logger::addLog('[LoyaltyLion] Failed to update review activity. API status: '
					. $response->status . ', error: ' . $response->error, 3);
			}
		}
	}

	/**
	 * This is fired by our AdminModulesContrller override when a comment has been accepted
	 * in the moderation view
	 *
	 * @param  [type] $params [description]
	 * @return [type]         [description]
	 */
	public function hookActionLoyaltyLionProductCommentAccepted($params)
	{

		if (!$params['id']) return;

		$this->loadLoyaltyLionClient();
		$response = $this->client->activities->update('review', $params['id'], array('state' => 'approved'));

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update review activity. API status: '
				. $response->status . ', error: ' . $response->error, 3);
		}
	}

	/**
	 * Fired by our AdminModulesController override when a comment has been rejected (i.e. not accepted)
	 * or deleted (accepted and then deleted)
	 *
	 * @param  [type] $params [description]
	 * @return [type]         [description]
	 */
	public function hookActionLoyaltyLionProductCommentDeleted($params)
	{

		if (!$params['id']) return;

		$this->loadLoyaltyLionClient();
		$response = $this->client->activities->update('review', $params['id'], array('state' => 'declined'));

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update review activity. API status: '
				. $response->status . ', error: ' . $response->error, 3);
		}
	}

	/**
	 * Fired when an order is first created and validated
	 *
	 * @param  [type] $params [description]
	 * @return [type]         [description]
	 */
	public function hookActionValidateOrder($params)
	{

		$order = $params['order'];
		$customer = new Customer((int)$order->id_customer);

		$data = array(
			// an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
			// to the reference if there are multiple orders with the same one
			'number' => (string)$order->getUniqReference(),
			'total' => (string)$order->total_paid,
			'total_shipping' => (string)$order->total_shipping,
			'customer_id' => $customer->id,
			'customer_email' => $customer->email,
			'merchant_id' => $order->id,
		);

		if (floatval($order->total_paid_real) == 0)
		{
			$data['payment_status'] = 'not_paid';
		} else if (floatval($order->total_paid_real) == floatval($order->total_paid))
		{
			$data['payment_status'] = 'paid';
		} else
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
			Logger::addLog('[LoyaltyLion] Failed to create order (' . $order->id . '). API status: '
				. $response->status . ', error: ' . $response->error, 3);
		}
	}

	/**
	 * Fired when an order's status changes, e.g. becoming paid
	 *
	 * @param  [type] $params [description]
	 * @return [type]         [description]
	 */
	public function hookActionOrderStatusPostUpdate($params)
	{
		$order = new Order((int)$params['id_order']);
		$this->sendOrderUpdate($order);
	}

	// at the moment this hook is not really used, as we only consider refunds via order/credit slips,
	// which are not even created when this hook fires (we get them with the "actionObjectOrderSlipAddAfter"
	// hook, below) - but we'll register this hook anyway for future proofing, as we might want to support
	// refunds without a credit slip
	public function hookActionProductCancel($params)
	{
		$this->sendOrderUpdate($params['order']);
	}

	// this is a more reliable way to discover when credit slips are created, as the standard orderslip
	// hook does not fire on partial refunds (surprising? no)
	public function hookActionObjectOrderSlipAddAfter($params)
	{
		$order = new Order((int)$params['object']->id_order);
		$this->sendOrderUpdate($order);
	}

	/**
	 * Given an order, pull out all needed info and send a full update to LoyaltyLion
	 *
	 * @param  [type] $order [description]
	 * @return [type]        [description]
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
		);

		if (floatval($order->total_paid_real) == 0)
		{
			$data['payment_status'] = 'not_paid';
			$data['total_paid'] = 0;
		} else if (floatval($order->total_paid_real) == floatval($order->total_paid))
		{
			$data['payment_status'] = 'paid';
			$data['total_paid'] = (string)$order->total_paid;
		} else
		{
			$data['payment_status'] = 'partially_paid';
			$data['total_paid'] = (string)$order->total_paid_real;
		}

		// cancelled?
		if ($order->getCurrentState() == Configuration::get('PS_OS_CANCELED'))
		{
			$data['cancellation_status'] = 'cancelled';
		}

		// credit slip hook
		// actionOrderSlipAdd
		// actionProductCancel

		// refunds in prestashop are a bit of a clusterfuck, so this isn't too simple and might still have bugs

		$total_refunded = 0;

		// i think we can simplify this by querying for credit (order) slips attached to this order
		$credit_slips = OrderSlip::getOrdersSlip($order->id_customer, $order->id);

		// if we have at least one credit slip that should mean we have had a refund, so let's add them
		// NOTE: the "amount" is the unit price of the product * quantity refunded, plus shipping if they opted
		//       refund the shipping cost. However PS doesn't stop you from refunding shipping cost more than
		//       once if you do multiple refunds, so the refund total could end up more than the actual total
		//       ... if this happens we will just cap it to the order total so it doesn't confuse loyaltylion

		foreach ($credit_slips as $slip)
		{
			if (!$slip['amount']) continue;

			$total_refunded += $slip['amount'];
		}

		if ($total_refunded > 0)
		{
			if ($total_refunded < floatval($order->total_paid))
			{
				$data['refund_status'] = 'partially_refunded';
				$data['total_refunded'] = $total_refunded;
			} else
			{
				// if the total refunded is equal (or, perhaps, greater than?) the total cost of the order,
				// we'll just class that as a full refund
				$data['refund_status'] = 'refunded';
				$data['total_refunded'] = floatval($order->total_paid);
			}
		}

		// refund state: PS_OS_REFUND

		$this->loadLoyaltyLionClient();
		$response = $this->client->orders->update($order->id, $data);

		if (!$response->success)
		{
			Logger::addLog('[LoyaltyLion] Failed to update order (' . $order->id . '). API status: '
				. $response->status . ', error: ' . $response->error, 3);
		}
	}

	/**
	 * Require the PHP LoyaltyLion library and initialise it
	 *
	 * @return [type] [description]
	 */
	private function loadLoyaltyLionClient()
	{
		require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR .
			'lib' . DIRECTORY_SEPARATOR . 'loyaltylion-client' . DIRECTORY_SEPARATOR . 'main.php');

		$options = array();

		if (isset($_SERVER['LOYALTYLION_API_BASE']))
		{
			$options['base_uri'] = $_SERVER['LOYALTYLION_API_BASE'];
		}

		$this->client = new LoyaltyLion_Client($this->getToken(), $this->getSecret(), $options);
	}

	private function getToken()
	{
		return Configuration::get('LOYALTYLION_TOKEN');
	}

	private function getSecret()
	{
		return Configuration::get('LOYALTYLION_SECRET');
	}

	private function getSDKUrl()
	{
		return isset($_SERVER['LOYALTYLION_SDK_URL'])
			? $_SERVER['LOYALTYLION_SDK_URL']
			: 'dg1f2pfrgjxdq.cloudfront.net/libs/ll.sdk-1.1.js';
	}

	private function getPlatformHost()
	{
		return isset($_SERVER['LOYALTYLION_PLATFORM_HOST'])
			? $_SERVER['LOYALTYLION_PLATFORM_HOST']
			: 'platform.loyaltylion.com';
	}
}