# LoyaltyLion Prestashop module

This module is a fairly thin layer between the Prestashop store and the LoyaltyLion API (https://api.loyaltylion.com). It hooks in to various events such as account creation and orders and makes the appropriate notifications to the LoyaltyLion API. It also adds the client-side LoyaltyLion JavaScript SDK to each page.

This module does not create or manage any database tables; the only configuration it stores are a merchant's unique LoyaltyLion token and secret.

## Items of interest

### lib/loyaltylion-client

This is a bundled version of our PHP client library used for communication with the LoyaltyLion API, as found here: https://github.com/loyaltylion/php-client

It uses cURL to make HTTPs requests to `api.loyaltylion.com`

## Global hooks used by this module

### displayHeader

We use this to do two things:

1) We add the LoyaltyLion JavaScript SDK snippet to the `<head>` section of each page. The snippet that is inserted is `views/templates/hook/sdk_init.tpl`. This snippet in turn loads the SDK from our CDN and allows all the customer-facing aspects of LoyaltyLion to work (e.g. notifications, the Loyalty Panel, etc)

2) We check if a query parameter called `ll_ref_id` exists, and if so we save it to a cookie. This allows our refer-a-friend functionality to operate. It seemed reasonable to do this here as no output has been sent when this hook executes, so it's safe to set a cookie.

### hookActionValidateOrder

We use this hook to track a new order to LoyaltyLion when one is placed and validated with the store. This is tracked to the LoyaltyLion API using the LoyaltyLion PHP Client (see above, *Items of interest*, for an explanation of this library).

### hookActionOrderStatusPostUpdate, hookActionObjectOrderSlipAddAfter, hookActionProductCancel

For all "order update" type hooks, we send a full, idempotent copy of the order to the LoyaltyLion API. This is more robust than trying to send individual updates whenever something happens (i.e. "$20 was partially paid"). Instead, if an order was partially paid, one of the above hooks would fire, and we would get the current complete state of the order (which would include the fact that $20 has now been paid) and send the whole lot to our API.

### hookActionCustomerAccountAdd

We use this hook to send a "sign up" event to the LoyaltyLion API when a customer signs up. If they have a referral cookie, we'll send this too so we know that this new customer was referred.

### hookActionObjectProductCommentAddAfter, hookActionObjectProductCommentDeleteAfter, hookActionObjectProductCommentValidateAfter

We use these to hook into the lifecycle of `ProductComments` (part of the `productcomments` module), so we can be notified when reviews are posted, deleted and validated by a moderator.

## The Configuration page

This page has two states: a "sign up / marketing" state, and a "configuration / settings" state. When someone first installs the LoyaltyLion module they'll see the "sign up" state, which will encourage them to create an account (this will take them off to `loyaltylion.com` to sign up, if they decide to continue).

The second state is visible when they click the "click here if you already have an account" link, or if they have already added a token/secret. So, one they complete the sign up (which is also includes instructions on adding their LoyaltyLion token/secret into Prestashop) they'll only ever see the "configuration" state.

Aside from updating the token and secret, the "configuration" state also allows the merchant to bulk import voucher codes. This is a necessary feature as LoyaltyLion's rewards work by providing voucher codes. Creating these on-demand would be impossible for a larger store, so as part of the LoyaltyLion setup we generate a list of voucher codes for each reward, and the merchant is then instructed to use the "Import voucher codes" tool on the configuration page to add them to Prestashop.

Internally, we simply batch create new `CartRule` records with the appropriate discount amount, currency and code.