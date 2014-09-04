<?php

class AdminModulesController extends AdminModulesControllerCore
{

	public function postProcess()
	{
		parent::postProcess();

		/*
		this lets us listen for when a product has been accepted/deleted, so we can fire a custom hook and
		update review activities accordingly
		*/
		$action = Tools::getValue('action');
		$delete_action = Tools::getValue('delete_action');

		/* older versions of productcomments module use module_name param, newer versions use configure param instead

			NOTE: it goes without saying that being a hack, this is quite fragile and might break if there is a substantial
			change in how the productcomments module changes how it handles validation/deletes, but I'm not too concerned
			as the state of progress doesn't seem very fast for this module :)
		*/

		if ((Tools::getValue('module_name') == 'productcomments' || Tools::getValue('configure') == 'productcomments')
			&& (in_array($action, array('accept', 'delete')) || !empty($delete_action)))
		{

			$product_comments = empty($delete_action)
				? Tools::getValue('id_product_comment')
				: Tools::getValue('delete_id_product_comment');

			foreach ($product_comments as $id)
			{
				if (!$id) continue;

				switch ($action)
				{
					case 'accept':
						return Hook::exec('actionLoyaltyLionProductCommentAccepted', array('id' => $id));
					case 'delete':
						return Hook::exec('actionLoyaltyLionProductCommentDeleted', array('id' => $id));
					default:
						if (!empty($delete_action))
							return Hook::exec('actionLoyaltyLionProductCommentDeleted', array('id' => $id));

				}
			}
		}
	}
}