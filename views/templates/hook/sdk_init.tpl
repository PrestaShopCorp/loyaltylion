<script>{literal}(function(t, e){window.lion = e;var n, i = t.getElementsByTagName("script")[0];n = t.createElement("script"), n.type = "text/javascript", n.async = !0,n.src="//{/literal}{$sdk_url}{literal}",i.parentNode.insertBefore(n, i),e.init=function(n){function i(t, e) {
    var n = e.split(".");
    2 === n.length && (t = t[n[0]], e = n[1]), t[e] = function () {
        t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
    }
}var r, o = t.getElementsByTagName("script")[0];r = t.createElement("script"), r.type = "text/javascript", r.async = !0,r.src="//{/literal}{$platform_host}{literal}/sdk/configuration/"+n+".js",o.parentNode.insertBefore(r, o),e.ui = e.ui || [];for (var a = "_push configure track_pageview identify_customer auth_customer identify_product on off ui.refresh".split(" "), c = 0; a.length > c; c++)i(e, a[c]);e._token = n}})(document, window.lion || []);{/literal}

lion.init("{$ll_token}");
lion.configure({ platform:'prestashop' });
{if isset($customer_id)}
lion.identify_customer({ id:"{$customer_id}", email:"{$customer_email}", name:"{$customer_name}" });
lion.auth_customer({ date:"{$date}", auth_token:"{$auth_token}" });
{/if}
</script>