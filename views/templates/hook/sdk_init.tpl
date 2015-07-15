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
<script>{literal}(function(t, e){window.lion = e;var n, i = t.getElementsByTagName("script")[0];n = t.createElement("script"), n.type = "text/javascript", n.async = !0,n.src="//{/literal}{$sdk_url|escape}{literal}",i.parentNode.insertBefore(n, i),e.init=function(n){function i(t, e) {
    var n = e.split(".");
    2 === n.length && (t = t[n[0]], e = n[1]), t[e] = function () {
        t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
    }
}var r, o = t.getElementsByTagName("script")[0];r = t.createElement("script"), r.type = "text/javascript", r.async = !0,r.src="//{/literal}{$platform_host|escape}{literal}/sdk/configuration/"+n+".js",o.parentNode.insertBefore(r, o),e.ui = e.ui || [];for (var a = "_push configure track_pageview identify_customer auth_customer identify_product on off ui.refresh".split(" "), c = 0; a.length > c; c++)i(e, a[c]);e._token = n}})(document, window.lion || []);{/literal}

lion.init("{$ll_token|escape}");
lion.configure({ platform:'prestashop' });
{if isset($customer_id) && !$is_guest_customer}
lion.identify_customer({ id:"{$customer_id|escape}", email:"{$customer_email|escape}", name:"{$customer_name|escape}" });
lion.auth_customer({ date:"{$date|escape}", auth_token:"{$auth_token|escape}" });
{/if}
</script>
