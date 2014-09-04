${*
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
<fieldset>
    <legend>{l s='Configuration' mod='loyaltylion'}</legend>
    <form action="{$action|escape}" method='post'>
        <label>{l s='Token' mod='loyaltylion'}</label>

        <div class='margin-form'>
            <input type='text' name='loyaltylion_token' id='loyaltylion_token' value='{$token|escape}' size='40'>
            <sup>*</sup>
        </div>
        <div class='clear'></div>
        <label>{l s='Secret' mod='loyaltylion'}</label>

        <div class='margin-form'>
            <input type='text' name='loyaltylion_secret' id='loyaltylion_secret' value='{$secret|escape}' size='40'>
            <sup>*</sup>
        </div>
        <div class='clear'></div>
        <div class='margin-form clear'>
            <input type="submit" name="submitConfiguration" value="{l s='Save' mod='loyaltylion'}" class="button">
        </div>
    </form>
</fieldset>
<br>
<fieldset>
    <legend>{l s='Create multiple voucher codes' mod='loyaltylion'}</legend>
    <form action="{$action|escape}" method='post'>
        <label>{l s='Discount amount' mod='loyaltylion'}</label>

        <div class='margin-form'>
            <input type='text' name='discount_amount' id='discount_amount' value='{$form_values['discount_amount']|escape}'
                   size='15' onchange="this.value = this.value.replace(/,/g, '.');">
            <select name="discount_amount_currency">
            {foreach from=$currencies item='currency'}
                <option value="{$currency.id_currency|intval}"
                        {if $form_values['discount_amount_currency'] == $currency.id_currency || (!$form_values['discount_amount_currency'] && $currency.id_currency == $defaultCurrency)}selected="selected"{/if}>{$currency.iso_code|escape}</option>
            {/foreach}
            </select>
        </div>
        <div class='clear'></div>
        <label>{l s='Codes (one per line)' mod='loyaltylion'}</label>

        <div class='margin-form'>
            <textarea name='codes' id='codes' cols='80' rows='15'>{$form_values['codes']|escape}</textarea>
        </div>
        <div class='clear'></div>
        <div class='margin-form clear'>
            <input type="submit" name="submitVoucherCodes" value="{l s='Create voucher codes' mod='loyaltylion'}" class="button">
        </div>
    </form>
</fieldset> 