<?php

class AdminModulesController extends AdminModulesControllerCore {

  public function postProcess() {
    parent::postProcess();
    xdebug_break();

    // this hack lets us listen for when a product has been accepted, so we can fire a custom hook and
    // track it as a valid review
    if (Tools::getValue('module_name') == 'productcomments' && Tools::getValue('action') == 'accept') {

      $product_comments = Tools::getValue('id_product_comment');
      if (!count($product_comments)) return;

      foreach ($product_comments as $id) {
        if (!$id) continue;
        
        Hook::exec('actionLoyaltyLionProductCommentAccepted', array('id' => $id));
      }
    }
  }
}