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