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
  <div class='signup-box'>
    <div class='heading'>

    </div>
    <div class='content'>
      <div class='intro'>
        <!-- <div class='intro-text'>
          Quickly create your own social loyalty program. Reward customers for purchases, account creation, visits and referring friends.
        </div> -->
        <div class='video'>
          <iframe src='//player.vimeo.com/video/66563344' width='580' height='326' frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
        </div>
      </div>
      <div class='more-info'>
        <div class='sub-heading'>{l s='Create your own social loyalty program' mod='loyaltylion'}</div>
        <p>{l s='Show customers they’re valued and increase sales by rewarding referrals, purchases, signups, reviews and visits.' mod='loyaltylion'}</p>

        <p>{l s="You decide what to reward and how to reward it; for example, 100 points for signups, 5 points per {$currency_sign}1 spent and 1000 points per referral. Customers collect points and redeem them for vouchers to spend at your store." mod='loyaltylion'}</p>

        <div class='sub-heading'>{l s='Benefits' mod='loyaltylion'}</div>
        <ul>
          <li>{l s='Increase sales via repeat purchases' mod='loyaltylion'}</li>
          <li>{l s='Acquire new customers using our refer a friend feature. Reward referrals via Facebook, Twitter and email' mod='loyaltylion'}</li>
          <li>{l s='Differentiate your store from your competitors' mod='loyaltylion'}</li>
          <li>{l s='Case Study: BeefenSteak gains 2,114 new visitors worth $1,412 using LoyaltyLion.' mod='loyaltylion'} <a href='http://resources.loyaltylion.com/case-studies/2014-08-05/beefensteak-en.pdf' target='_blank'>{l s='Read more' mod='loyaltylion'}</a></li>
        </ul>

        <div class='sub-heading'>{l s='Features' mod='loyaltylion'}</div>
        <ul>
          <li>{l s='Import existing customers and their points' mod='loyaltylion'}</li>
          <li>{l s='Reward visits, signups, referrals, reviews and purchases' mod='loyaltylion'}</li>
          <li>{l s='Refer a friend on Facebook, Twitter and via email' mod='loyaltylion'}</li>
          <li>{l s='Automatically generate bulk voucher codes' mod='loyaltylion'}</li>
          <li>{l s='Name and customise the program to match your store' mod='loyaltylion'}</li>
          <li>{l s='No LoyaltyLion branding anywhere' mod='loyaltylion'}</li>
          <li>{l s='Gain customer insights: most engaged customers and top referrers' mod='loyaltylion'}</li>
          <li>{l s='Mobile friendly' mod='loyaltylion'}</li>
        </ul>

        <div class='sub-heading'>{l s='Pricing' mod='loyaltylion'}</div>

        <p>{l s="Pricing is based on the number of orders per month. We'll let you know which plan you'll be on before your free trial ends." mod='loyaltylion'}</p>

        <div class='pricing'>
          <div class='boxes'>
            <div class='pricing-box'>
              <div class='plan-box'>
                <div class='price-box'>
                  <div class='price'>
                    <div class='price'>{$pricing_sign|escape}{$pricing[0]|escape}<span>/mo</span></div>
                  </div>
                  <div class='limit'>0-200 {l s='orders /month' mod='loyaltylion'}</div>
                </div>
              </div>
            </div>
            <div class='pricing-box'>
              <div class='plan-box'>
                <div class='price-box'>
                  <div class='price'>
                    <div class='price'>{$pricing_sign|escape}{$pricing[1]|escape}<span>/mo</span></div>
                  </div>
                  <div class='limit'>201-400 {l s='orders /month' mod='loyaltylion'}</div>
                </div>
              </div>
            </div>
            <div class='pricing-box'>
              <div class='plan-box'>
                <div class='price-box'>
                  <div class='price'>
                    <div class='price'>{$pricing_sign|escape}{$pricing[2]|escape}<span>/mo</span></div>
                  </div>
                  <div class='limit'>401-800 {l s='orders /month' mod='loyaltylion'}</div>
                </div>
              </div>
            </div>
          </div>

          <p class='enterprise'>{l s='More than 800 orders a month?' mod='loyaltylion'} <a href='mailto:hello@loyaltylion.com'>{l s='Contact us' mod='loyaltylion'}</a></p>
        </div>

        <div class='signup-btn'>
          <a href='http://{$loyaltylion_host|escape}/platforms/land/prestashop?shop_details={$shop_details}' class='orange-btn' target='_blank'>{l s='Start setup' mod='loyaltylion'}</a>
        </div>
        
      </div>
      <!-- <div class='buttons'>
        <a href='http://{$loyaltylion_host|escape}/prestashop/signup' class='orange-btn' target='_blank'>{l s='Start free trial' mod='loyaltylion'}</a>
        <p class='account-already'>
          <a href='{$base_uri|escape}&amp;force_show_settings=1'>{l s='or click here if you already have a LoyaltyLion account' mod='loyaltylion'}</a>
        </p>
      </div> -->
    </div>
    <div class='sidebar'>
      <div class='box signup'>
        <a href='http://{$loyaltylion_host|escape}/platforms/land/prestashop?shop_details={$shop_details}' class='orange-btn' target='_blank'>{l s='Start setup' mod='loyaltylion'}</a>
      </div>
      <div class='box existing-account'>
        {l s='Already have a LoyaltyLion account?' mod='loyaltylion'} <a href='{$base_uri|escape}&amp;force_show_settings=1'>{l s='Click here' mod='loyaltylion'}</a>
      </div>
      <div class='box contact-info'>
        <div class='sub-heading'>{l s='Contact us' mod='loyaltylion'}</div>
        <p>&rsaquo; <a href='mailto:hello@loyaltylion.com'>hello@loyaltylion.com</a></p>
      </div>
      <div class='box screenshots'>
        <div class='sub-heading'>{l s='Screenshots' mod='loyaltylion'}</div>
        <div class='screenshot-links'>
          <a class='screenshot mobile fancybox' rel='group' href='../modules/loyaltylion/img/screenshots/mobile.jpg' target='_blank'></a>
          <a class='screenshot refer-screen fancybox' rel='group' href='../modules/loyaltylion/img/screenshots/refer-screen.jpg' target='_blank'></a>
          <a class='screenshot widget fancybox' rel='group' href='../modules/loyaltylion/img/screenshots/widget.jpg' target='_blank'></a>
          <a class='screenshot customise fancybox ' rel='group' href='../modules/loyaltylion/img/screenshots/customise.jpg' target='_blank'></a>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  $(document).ready(function () {
    $('.fancybox').fancybox();
  });
</script>