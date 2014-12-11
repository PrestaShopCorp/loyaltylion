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
  <div class='settings-box fluid'>
    <!-- <div class='heading'></div> -->
    <div class='content'>
      <div class='create-vouchers-async'>
        <div class='loading'>
          <p>{l s="Creating {$codes_to_generate} vouchers (each one for {$currency}{$discount_amount})" mod='loyaltylion'}
          <br>
          {l s="This may take a minute - please don't close this window" mod='loyaltylion'}</p>
          <div class='spinner'></div>
        </div>
        <div class='complete' style='display: none'>
          <p>{l s="Finished importing {$codes_to_generate} codes!" mod='loyaltylion'}
          <br><br>
          <a href='#' class='orange-btn small-btn' id='close-window-btn'>{l s='Click here to close this window' mod='loyaltylion'}</a></p>
        </div>
        <div class='error-happened' style='display: none'>
          <p>{l s="Sorry, something went wrong! Reload this window to try again" mod='loyaltylion'}
          <br><br>
          {l s="If this problem persists, contact support@loyaltylion.com and tell us the error code:" mod='loyaltylion'} <strong class='error-code'></strong></p>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  $(document).ready(function() {

    $('#close-window-btn').on('click', function(e) {
      e.preventDefault();
      window.close();
    });

    var url = "{$create_voucher_codes_url|escape:'javascript'}";

    $.ajax({
      url: url,
      type: 'post',
      success: function(resp) {
        $('.create-vouchers-async .loading').hide();
        $('.create-vouchers-async .complete').show();
        console.log(resp);
      },
      error: function(resp, txt) {
        $('.create-vouchers-async .loading').hide();
        $('.create-vouchers-async .error-happened').show().find('.error-code').text(resp.responseText || '000');
      }
    });

  });
</script>