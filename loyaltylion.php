<?php

if (!defined('_PS_VERSION_')) exit;

class LoyaltyLion extends Module {

  public function __construct() {
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

  public function install() {
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

  public function uninstall() {
    parent::uninstall();
  }

  public function getContent() {
    $output = null;

    if (Tools::isSubmit('updateSubmit')) {
      Configuration::updateValue('LOYALTYLION_TOKEN', Tools::getValue('LOYALTYLION_TOKEN'));
      Configuration::updateValue('LOYALTYLION_SECRET', Tools::getValue('LOYALTYLION_SECRET'));
    }

    if (Tools::isSubmit('submit'.$this->name)) {
      $token = strval(Tools::getValue('LOYALTYLION_TOKEN'));
      $secret = strval(Tools::getValue('LOYALTYLION_SECRET'));
    }

    $output .= $this->displayConfirmation($this->l('Configuration saved'));

    return $output . $this->displayForm();
  }

  public function displayForm() {
    $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
         
        // Init Fields form array
    $fields_form[0]['form'] = array(
      'legend' => array(
        'title' => $this->l('Settings'),
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Token'),
          'name' => 'LOYALTYLION_TOKEN',
          'size' => 40,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Secret'),
          'name' => 'LOYALTYLION_SECRET',
          'size' => 40,
          'required' => true
        )
      ),
      'submit' => array(
        'title' => $this->l('Save'),
        'class' => 'button'
      )
    );
     
    $helper = new HelperForm();

    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;

    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'updateSubmit';
    $helper->toolbar_btn = array(
      'save' => array(
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
        '&token='.Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Back to list')
      )
    );

    $helper->fields_value['LOYALTYLION_TOKEN'] = Configuration::get('LOYALTYLION_TOKEN');
    $helper->fields_value['LOYALTYLION_SECRET'] = Configuration::get('LOYALTYLION_SECRET');
         
    return $helper->generateForm($fields_form);
  }

  public function hookDisplayHeader() {

    // prestashop appears to run this hook prior to starting output, so it should be safe to
    // set the referral cookie here if we have one !
    $referral_id = Tools::getValue('ll_ref_id');

    // if we have an id and we haven't already set a cookie for it (don't override existing ref cookie)
    if ($referral_id && !$this->context->cookie->loyaltylion_referral_id) {
      $this->context->cookie->__set('loyaltylion_referral_id', $referral_id);
    }

    $customer = $this->context->customer;

    $this->context->smarty->assign(array(
      'll_token' => $this->getToken(),
      'sdk_url' => $this->getSDKUrl(),
      'platform_host' => $this->getPlatformHost(),
    ));

    if ($customer) {
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
  public function hookActionCustomerAccountAdd($params) {
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

    if (!$response->success) {
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
  public function hookActionObjectProductCommentAddAfter($params) {
    
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

    if (!$response->success) {
      Logger::addLog('[LoyaltyLion] Failed to track review activity. API status: '
        . $response->status . ', error: ' . $response->error, 3);
    }

    if (Configuration::get('PRODUCT_COMMENTS_MODERATE') !== '1') {
      // reviews do not require moderation, which means this one will be shown immediately and we should
      // send an update now to approve it
      
      $response = $this->client->activities->update('review', $comment->id, array('state' => 'approved'));

      if (!$response->success) {
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
  public function hookActionLoyaltyLionProductCommentAccepted($params) {

    if (!$params['id']) return;

    $this->loadLoyaltyLionClient();
    $response = $this->client->activities->update('review', $params['id'], array('state' => 'approved'));

    if (!$response->success) {
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
  public function hookActionLoyaltyLionProductCommentDeleted($params) {

    if (!$params['id']) return;

    $this->loadLoyaltyLionClient();
    $response = $this->client->activities->update('review', $params['id'], array('state' => 'declined'));

    if (!$response->success) {
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
  public function hookActionValidateOrder($params) {

    $order = $params['order'];
    $customer = new Customer((int) $order->id_customer);

    $data = array(
      // an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
      // to the reference if there are multiple orders with the same one
      'number' => (string) $order->getUniqReference(),
      'total' => (string) $order->total_paid,
      'total_shipping' => (string) $order->total_shipping,
      'customer_id' => $customer->id,
      'customer_email' => $customer->email,
      'merchant_id' => $order->id,
    );

    if (floatval($order->total_paid_real) == 0) {
      $data['payment_status'] = 'not_paid';
    }
    else if (floatval($order->total_paid_real) == floatval($order->total_paid)) {
      $data['payment_status'] = 'paid';
    }
    else {
      $data['payment_status'] = 'partially_paid';
      $data['total_paid'] = (string) $order->total_paid_real;
    }

    if ($this->context->cookie->loyaltylion_referral_id)
      $data['referral_id'] = $this->context->cookie->loyaltylion_referral_id;

    $this->loadLoyaltyLionClient();
    $response = $this->client->orders->create($data);

    if (!$response->success) {
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
  public function hookActionOrderStatusPostUpdate($params) {
    $order = new Order((int) $params['id_order']);
    $this->sendOrderUpdate($order);
  }

  // at the moment this hook is not really used, as we only consider refunds via order/credit slips,
  // which are not even created when this hook fires (we get them with the "actionObjectOrderSlipAddAfter" 
  // hook, below) - but we'll register this hook anyway for future proofing, as we might want to support
  // refunds without a credit slip
  public function hookActionProductCancel($params) {
    $this->sendOrderUpdate($params['order']);
  }

  // this is a more reliable way to discover when credit slips are created, as the standard orderslip
  // hook does not fire on partial refunds (surprising? no)
  public function hookActionObjectOrderSlipAddAfter($params) {
    $order = new Order( (int) $params['object']->id_order );
    $this->sendOrderUpdate($order);
  }

  /**
   * Given an order, pull out all needed info and send a full update to LoyaltyLion
   * 
   * @param  [type] $order [description]
   * @return [type]        [description]
   */
  private function sendOrderUpdate($order) {

    if (!$order) return;
    
    $data = array(
      // an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
      // to the reference if there are multiple orders with the same one
      'number' => (string) $order->getUniqReference(),
      'refund_status' => 'not_refunded',
      'cancellation_status' => 'not_cancelled',
      'total_refunded' => 0,
    );

    if (floatval($order->total_paid_real) == 0) {
      $data['payment_status'] = 'not_paid';
      $data['total_paid'] = 0;
    }
    else if (floatval($order->total_paid_real) == floatval($order->total_paid)) {
      $data['payment_status'] = 'paid';
      $data['total_paid'] = (string) $order->total_paid;
    }
    else {
      $data['payment_status'] = 'partially_paid';
      $data['total_paid'] = (string) $order->total_paid_real;
    }

    // cancelled?
    if ($order->getCurrentState() == Configuration::get('PS_OS_CANCELED')) {
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
      
    foreach($credit_slips as $slip) {
      if (!$slip['amount']) continue;

      $total_refunded += $slip['amount'];
    }
    
    if ($total_refunded > 0) {
      if ($total_refunded < floatval($order->total_paid)) {
        $data['refund_status'] = 'partially_refunded';
        $data['total_refunded'] = $total_refunded;
      } else {
        // if the total refunded is equal (or, perhaps, greater than?) the total cost of the order, 
        // we'll just class that as a full refund
        $data['refund_status'] = 'refunded';
        $data['total_refunded'] = floatval($order->total_paid);
      }
    }

    // refund state: PS_OS_REFUND

    $this->loadLoyaltyLionClient();
    $response = $this->client->orders->update($order->id, $data);

    if (!$response->success) {
      Logger::addLog('[LoyaltyLion] Failed to update order (' . $order->id . '). API status: '
        . $response->status . ', error: ' . $response->error, 3);
    }
  }

  /**
   * Require the PHP LoyaltyLion library and initialise it
   * 
   * @return [type] [description]
   */
  private function loadLoyaltyLionClient() {
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 
      'lib' . DIRECTORY_SEPARATOR . 'loyaltylion-client' . DIRECTORY_SEPARATOR . 'main.php');

    $options = array();

    if (isset($_SERVER['LOYALTYLION_API_BASE'])) {
      $options['base_uri'] = $_SERVER['LOYALTYLION_API_BASE'];
    }

    $this->client = new LoyaltyLion_Client($this->getToken(), $this->getSecret(), $options);
  }

  private function getToken() {
    return Configuration::get('LOYALTYLION_TOKEN');
  }

  private function getSecret() {
    return Configuration::get('LOYALTYLION_SECRET');
  }

  private function getSDKUrl() {
    return isset($_SERVER['LOYALTYLION_SDK_URL'])
      ? $_SERVER['LOYALTYLION_SDK_URL']
      : 'dg1f2pfrgjxdq.cloudfront.net/libs/ll.sdk-1.1.js';
  }

  private function getPlatformHost() {
    return isset($_SERVER['LOYALTYLION_PLATFORM_HOST'])
      ? $_SERVER['LOYALTYLION_PLATFORM_HOST']
      : 'platform.loyaltylion.com';
  }
}