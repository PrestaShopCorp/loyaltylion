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
      $this->registerHook('orderReturn') &&
      $this->registerHook('actionProductCancel') &&
      $this->registerHook('actionOrderSlipAdd') &&
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
   * Notify LoyaltyLion of a new order
   * 
   * @param  [type] $params [description]
   * @return [type]         [description]
   */
  // public function hookDisplayOrderConfirmation($params) {
  //   $this->loadLoyaltyLionClient();

  //   // load the actual order, as the params only contain the new order status (not the rest of the order info)
  //   $order = $params['objOrder'];
  //   $order_data = $this->getOrderData($order);

  //   $response = $this->client->orders->create($order_data);

  //   if (!$response->success) {
  //     Logger::addLog('[LoyaltyLion] Failed to track new order. API status: '
  //       . $response->status . ', error: ' . $response->error, 3);
  //   }
  // }

  public function hookActionValidateOrder($params) {

    $order = $params['order'];
    $customer = new Customer((int) $order->id_customer);

    xdebug_break();

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

    $this->loadLoyaltyLionClient();
    $response = $this->client->orders->create($data);

    if (!$response->success) {
      Logger::addLog('[LoyaltyLion] Failed to create order (' . $order->id . '). API status: '
        . $response->status . ', error: ' . $response->error, 3);
    }
  }

  public function hookActionOrderStatusPostUpdate($params) {
    $order = new Order((int) $params['id_order']);
    $this->sendOrderUpdate($order);
  }

  public function hookActionProductCancel($params) {
    $this->sendOrderUpdate($params['order']);
  }

  public function hookActionOrderSlipAdd($params) {
    $this->sendOrderUpdate($params['order']);
  }

  public function hookOrderReturn($params) {
    xdebug_break();

    $order = new Order((int) $params['id_order']);
    $details = OrderReturn::getOrdersReturnDetail((int) $params['orderReturn']->id);


  }

  public function hookActionCustomerAccountAdd($params) {
    $customer = $params['newCustomer'];

    $data = array(
      'customer_id' => $customer->id,
      'customer_email' => $customer->email,
      'date' => date('c'),
    );

    $this->loadLoyaltyLionClient();

    $response = $this->client->events->track('signup', $data);

    if (!$response->success) {
      Logger::addLog('[LoyaltyLion] Failed to track signup event. API status: '
        . $response->status . ', error: ' . $response->error, 3);
    }
  }

  /**
   * Given an order, pull out all needed info and send a full update to LoyaltyLion
   * 
   * @param  [type] $order [description]
   * @return [type]        [description]
   */
  private function sendOrderUpdate($order) {

    if (!$order) return;

    xdebug_break();
    
    $data = array(
      // an order "reference" is not unique normally, but this method will make sure it is (it adds a #2 etc)
      // to the reference if there are multiple orders with the same one
      'number' => (string) $order->getUniqReference(),
      // 'total' => (string) $order->total_paid,
      // 'total_shipping' => (string) $order->total_shipping,
      'refund_status' => 'not_refunded',
      'cancellation_status' => 'not_cancelled',
      'total_refunded' => 0,
      // 'customer_id' => $customer->id,
      // 'customer_email' => $customer->email,
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
    if ($order->getCurrentOrderState() == Configuration::get('PS_OS_CANCELED')) {
      $data['cancellation_status'] = 'cancelled';
    }

    // credit slip hook
    // actionOrderSlipAdd
    // actionProductCancel

    // refunds in prestashop are a bit of a clusterfuck, so this isn't too simple and might still have bugs

    $refund_total = 0;

    // we'll start by looking for product refunds. before an order is shipped, you can apply a refund against
    // individual products in the order. these product lines then have their 'product_quantity_refunded' updated
    // (which therefore allows refunding only 1 of a product if multiple were ordered)


    // i think we can simplify this by querying for credit (order) slips attached to this order
    $credit_slips = OrderSlip::getOrdersSlip($order->customer_id, $order->id);

    // if we have at least one credit slip that should mean we have had a refund, so let's add them
    // NOTE: the "amount" is the unit price of the product * quantity refunded, plus shipping if they opted
    //       refund the shipping cost. However PS doesn't stop you from refunding shipping cost more than 
    //       once if you do multiple refunds so the refund total could end up more than the actual total
    //       ... if this happens we will just reset it to the order total so it doesn't confuse loyaltylion
      
    foreach($credit_slips as $slip) {
      if (!$slip['amount']) continue;

      $refund_total += $slip['amount'];
    }


    $products = $order->getProducts();

    foreach ($order->getProducts() as $product) {

      xdebug_break();

      // this is a summary of all the credit slips for this product
      $resume = OrderSlip::getProductSlipResume($product['id_order_detail']);

      // this is the amount of the credit slip, i.e. the amount refunded, which
      $refund_total += floatval($resume['amount_tax_incl']);

      // we consider credit (order) slips as a 

      // $order_slips = Db::getInstance()->executeS('
      //   SELECT product_quantity, amount_tax_excl, amount_tax_incl, shipping_cost_amount, date_add
      //   FROM `'._DB_PREFIX_.'order_slip_detail` osd
      //   LEFT JOIN `'._DB_PREFIX_.'order_slip` os
      //   ON os.id_order_slip = osd.id_order_slip
      //   WHERE osd.`id_order_detail` = '.(int) $product['id_order_detail']);

      // foreach ($order_slips as $order_slip) {
      //   if ($order_slip['shipping_cost'] && !$shipping_refund_total) {
      //     // only add shipping refund total once... it seems that it might be possible to tick the box to
      //     // refund shipping more than once (if you did multiple refunds) but we are just going to use it once,
      //     // or we could end up with a refund total that is greater than what was paid... I wonder if PS would
      //     // actually let that happen... probably.
          
      //     $shipping_refund_total = floatval($order_slip['shipping_cost_amount']);
      //     break;
      //   }
      // }

      // // if an order has been paid but not yet shipped, you can "refund" it. if an order has been shipped then you 
      // // can "return" it. This is very confusing as it seemingly does not let you "refund" an order which has been 
      // // shipped, aside from internally recognising that "returned" is the same as being refunded?
      // //
      // // I don't know enough about prestashop usage to know what to do here, so for now we will make it an option -
      // // by default we will treat "returns" as if they are refunds, but you can turn this off and then we will only
      // // treat a return as a refund if it generated a credit slip (which we always consider refunds, because, well
      // // that is what they are right?)

      // // if (intval($product['product_quantity_refunded']) > 0) {

      // // when doing a refund you have the option to generate a credit (order) slip and/or a voucher, but these are
      // // NOT required to actually complete the refund, so we can't rely on the order slip to calculate the refund
      // // amount. however, the product line should have a quantity refunded which we can use instead
      
      // // that said, we also need to check if a credit slip was created because if it was, they had the option 
      // // to refund the shipping cost as well which will adjust the refund total

      // // FYI prestashop seems to fuck up itself if you don't create a credit slip as it seems to always expect one
      // // to be there, meh...

      // xdebug_break();

      // $order_slips = Db::getInstance()->executeS('
      //   SELECT product_quantity, amount_tax_excl, amount_tax_incl, shipping_cost_amount, date_add
      //   FROM `'._DB_PREFIX_.'order_slip_detail` osd
      //   LEFT JOIN `'._DB_PREFIX_.'order_slip` os
      //   ON os.id_order_slip = osd.id_order_slip
      //   WHERE osd.`id_order_detail` = '.(int) $product['id_order_detail']);

      // foreach ($order_slips as $order_slip) {
      //   if ($order_slip['shipping_cost'] && !$shipping_refund_total) {
      //     // only add shipping refund total once... it seems that it might be possible to tick the box to
      //     // refund shipping more than once (if you did multiple refunds) but we are just going to use it once,
      //     // or we could end up with a refund total that is greater than what was paid... I wonder if PS would
      //     // actually let that happen... probably.
          
      //     $shipping_refund_total = floatval($order_slip['shipping_cost_amount']);
      //     break;
      //   }
      // }

      // // now to calculate the actual refund total we'll use the unit price of this product line * the quantity refunded,
      // // which I hope will be correct
      // $refund_total += ( floatval($product['unit_price_tax_incl']) * intval($product['product_quantity_refunded']) );
      // // }
    }

    $total_refunded = $refund_total + $shipping_refund_total;
    
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

    // if ($order->getCurrentOrderState() == Configuration::get('PS_OS_REFUND')) {

    // }

    $this->loadLoyaltyLionClient();
    $response = $this->client->orders->update($order->id, $data);

    if (!$response->success) {
      Logger::addLog('[LoyaltyLion] Failed to update order (' . $order->id . '). API status: '
        . $response->status . ', error: ' . $response->error, 3);
    }
  }

  /**
   * Return an array of useful information about this Order, such as totals,
   * totals paid, etc
   * 
   * @param  [type] $order [description]
   * @return [type]        [description]
   */
  private function getOrderData($order) {
    $data = array(
      'merchant_id' => $order->id,
      'number' => (string) $order->reference,
    );

    // PS orders have a `total_paid`, which is actually total TO pay, and also `total_paid_real`, which
    // is (confusingly) the total which HAS been paid
    
    $data['total'] = (string) $order->total_paid;
    $data['total_shipping'] = (string) $order->total_shipping;

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

    $customer = new Customer((int) $order->id_customer);

    $data['customer_id'] = $customer->id;
    $data['customer_email'] = $customer->email;

    return $data;
  }

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