## SwagStripeDemoPayment

This demo app is based on the [official Shopware App Template](https://github.com/shopwareLabs/AppTemplate).

Please **do not use** this demo app in **production** environments.
It offers only basic features and does not provide a secure way to process payments.
The app is only considered a simple **proof of concept** for payments via apps in Shopware 6.

For any further development questions, please consider the [App Template](https://github.com/shopwareLabs/AppTemplate) or the [Developer Documentation](https://developer.shopware.com/docs/guides/plugins/apps).

### Shopware payment interaction

The main interaction with Shopware regarding payments can be found in the `App\Controller\PaymentController`, where the two asynchronous requests are being handled.

The first `pay` POST request starts the payment with the payment provider and is provided all necessary data from the `order`, `orderTransaction` and a `returnUrl`, where the user should be redirected to once the payment process with the payment provider has been finished.
In normal cases, this returns a redirect URL, where the user is redirected to by Shopware.
In case the payment can't be started (for example because of missing credentials for the shop), this can also return a `fail` status.

The second `finalize` POST request will be called once the user has been redirected back to the shop.
This second request is only provided with the `orderTransaction` for identification purposes.
The response `status` value determines the outcome of the payment, e.g.:
* `cancel` if the user has canceled the payment on the Payment provider
* `fail` if the payment has failed for some reason, e.g. missing funds
* `paid` for a successful immediate payment
* `authorize` for a delayed payment

In case you provide a error `message`, the payment will default to `fail` in both requests.

All responses need to be signed with the app secret (see `App\Controller\PaymentController::sign`) for protection against e.g. DNS spoofing.
You should also check the signatures on the POST requests coming from Shopware (see `App\SwagAppsystem\Authenticator`).