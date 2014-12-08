{*
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

* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.

* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
*  @author LoyaltyLion <support@loyaltylion.com>
*  @copyright  2012-2014 LoyaltyLion
*  @license    http://opensource.org/licenses/MIT  The MIT License
*}
<div id='loyaltylion-configuration'>
	<div class='settings-box'>
		<div class='heading'></div>
		<div class='content'>
			{if !$token || !$secret}
				<div class='no-account'>
					Don't have a LoyaltyLion account yet? <a href='{$base_uri|escape}&amp;force_show_signup=1'>Click here to get started</a></div>
				</div>
			{/if}
			<div class='token-secret'>
				<form action='{$action|escape}' method='post'>
					<div class='group token'>
						<div class='label'>{l s='Token' mod='loyaltylion'}</div>
						<input type='text' name='loyaltylion_token' id='loyaltylion_token' value='{$token|escape}' size='40'>
					</div>
					<div class='group secret'>
						<div class='label'>{l s='Secret' mod='loyaltylion'}</div>
						<input type='text' name='loyaltylion_secret' id='loyaltylion_secret' value='{$secret|escape}' size='40'>
					</div>
					<div class='submit'>
						<div class='get-token-secret'><a href='http://{$loyaltylion_host|escape}/prestashop/token-secret' id='get-token-secret-link' target='_blank'>{l s='Click here to get your LoyaltyLion token and secret' mod='loyaltylion'}</a></div>
						<input type='submit' class='orange-btn small-btn' value='{l s='Update token & secret' mod='loyaltylion'}' name='submitConfiguration'>
					</div>
				</form>
				<!-- <br style='clear: left'> -->
			</div>
			<div class='import-vouchers' style='display: none'>
				<div class='import-vouchers-heading'>{l s='Import voucher codes' mod='loyaltylion'}</div>
				<form action='{$action|escape}' method='post'>
					<div class='group'>
						<div class='label'>{l s='Discount amount' mod='loyaltylion'}</div>
						<input type='text' name='discount_amount' id='discount_amount' value='{$form_values['discount_amount']|escape}' size='15' onchange="this.value = this.value.replace(/,/g, '.');">
						<select name="discount_amount_currency">
							{foreach from=$currencies item='currency'}
								<option value="{$currency.id_currency|intval}" {if $form_values['discount_amount_currency'] == $currency.id_currency || (!$form_values['discount_amount_currency'] && $currency.id_currency == $defaultCurrency)}selected="selected"{/if}>{$currency.iso_code|escape}</option>
							{/foreach}
						</select>
					</div>
					<div class='group'>
						<div class='label'>{l s='Codes (one per line)' mod='loyaltylion'}</div>
						<textarea name='codes' id='codes' cols='80' rows='8'>{$form_values['codes']|escape}</textarea>
					</div>
					<div class='submit'>
						<input type='submit' class='orange-btn small-btn' value='{l s='Import voucher codes' mod='loyaltylion'}' name='submitVoucherCodes'>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<script>
	$(document).ready(function() {
		$('#get-token-secret-link').on('click', function(e) {
			e.preventDefault();

			var w = 600, h = 500;
     	var left = (screen.width/2) - (w/2), top = 100;
     	var options = 'height='+h+',width='+w+',left='+left+',top='+top+',toolbar=0,location=0,menubar=0,directories=0,scrollbars=0';
     	var url = $(this).attr('href');

     	window.open(url, 'loyaltyLionTokenSecretWindow', options);
		});
	});
</script> 