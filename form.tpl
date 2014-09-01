<fieldset>
    <legend>{l s='Configuration'}</legend>
    <form action="{$action}" method='post'>
        <label>{l s='Token'}</label>

        <div class='margin-form'>
            <input type='text' name='loyaltylion_token' id='loyaltylion_token' value='{$token}' size='40'>
            <sup>*</sup>
        </div>
        <div class='clear'></div>
        <label>{l s='Secret'}</label>

        <div class='margin-form'>
            <input type='text' name='loyaltylion_secret' id='loyaltylion_secret' value='{$secret}' size='40'>
            <sup>*</sup>
        </div>
        <div class='clear'></div>
        <div class='margin-form clear'>
            <input type="submit" name="submitConfiguration" value="{l s='Save'}" class="button">
        </div>
    </form>
</fieldset>
<br>
<fieldset>
    <legend>{l s='Create multiple voucher codes'}</legend>
    <form action="{$action}" method='post'>
        <label>{l s='Discount amount'}</label>

        <div class='margin-form'>
            <input type='text' name='discount_amount' id='discount_amount' value='{$form_values['discount_amount']}'
                   size='15' onchange="this.value = this.value.replace(/,/g, '.');">
            <select name="discount_amount_currency">
            {foreach from=$currencies item='currency'}
                <option value="{$currency.id_currency|intval}"
                        {if $form_values['discount_amount_currency'] == $currency.id_currency || (!$form_values['discount_amount_currency'] && $currency.id_currency == $defaultCurrency)}selected="selected"{/if}>{$currency.iso_code}</option>
            {/foreach}
            </select>
        </div>
        <div class='clear'></div>
        <label>{l s='Codes (one per line)'}</label>

        <div class='margin-form'>
            <textarea name='codes' id='codes' cols='80' rows='15'>{$form_values['codes']}</textarea>
        </div>
        <div class='clear'></div>
        <div class='margin-form clear'>
            <input type="submit" name="submitVoucherCodes" value="{l s='Create voucher codes'}" class="button">
        </div>
    </form>
</fieldset> 