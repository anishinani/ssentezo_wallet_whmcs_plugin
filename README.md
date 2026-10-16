# Ssentezo Wallet for WHMCS

Clients pay a WHMCS invoice with Ssentezo mobile money or with Visa / Mastercard. Card numbers are entered on Ssentezo's hosted checkout, not in WHMCS.

The module targets WHMCS 8 and 9. Payment starts in its own script instead of posting back to the invoice page, and the callback loads WHMCS gateway functions with `App::load_function()`.

## Requirements

- WHMCS 8.0 or newer (WHMCS 9 included)
- PHP 7.2 or newer, with curl and OpenSSL
- A Ssentezo Wallet API username and API key
- A public HTTPS URL for this WHMCS install

## Installation

1. Copy the files into the WHMCS root so the paths match exactly:

```
modules/gateways/ssentezo.php
modules/gateways/ssentezo/common.php
modules/gateways/ssentezo/pay.php
modules/gateways/ssentezo/return.php
modules/gateways/callback/ssentezo.php
modules/gateways/callback/check_payment.php
```

2. Log in to the WHMCS admin area.
3. Open **Configuration > System Settings > Payment Gateways**. On older WHMCS 8 layouts this is **Setup > Payments > Payment Gateways**.
4. Open **All Payment Gateways**, find **Ssentezo Wallet**, and click **Activate**.
5. Open the active gateway and set:
   - **API Username**: the username from the Ssentezo Wallet API Access page
   - **API Key**: the matching API key
   - **Sandbox**: tick this only when those credentials belong to `https://devwallet.ssentezo.com`
   - **Callback URL**: leave this blank
6. Save the gateway.
7. Confirm **Show on Order Form** is enabled if clients should choose Ssentezo at checkout.

If a Callback URL was saved by an older version of this module, clear it and save again. A blank value uses:

```
https://your-whmcs-domain/modules/gateways/callback/ssentezo.php
```

Replace `your-whmcs-domain` with the WHMCS System URL, including any subdirectory. Ssentezo's servers must be able to reach that address over HTTPS. The callback does not use a WHMCS login.

Set a Callback URL only when WHMCS is behind a proxy and **Configuration > System Settings > General Settings > General** shows the wrong System URL. The value must be the full `callback/ssentezo.php` address above.

## What the client sees

On an unpaid invoice the client can:

- Enter a mobile number and approve the prompt on the phone.
- For UGX invoices, choose **Pay with Visa or Mastercard** and finish on Ssentezo's checkout page.

Card checkout accepts UGX 500 to UGX 7,000,000. The card fee stays on the Ssentezo wallet. It is not added to the WHMCS invoice.

## How an invoice becomes paid

1. WHMCS sends the collection to Ssentezo with a unique reference and the callback URL.
2. Ssentezo posts the result to `modules/gateways/callback/ssentezo.php`.
3. The callback asks Ssentezo for the transaction status.
4. When that status is succeeded, WHMCS records the payment and marks the invoice paid.

Returning to the invoice in the browser does not by itself mark it paid. Check **Billing > Gateway Log** if a payment stays unpaid.

## Test before going live

1. Tick **Sandbox** and save the devwallet API credentials.
2. Create an unpaid UGX invoice.
3. Pay it with a sandbox mobile number, then with a sandbox card.
4. Confirm the invoice status is Paid and the Gateway Log shows Success.
5. Clear **Sandbox**, enter the live API credentials, and save.

## Upgrading an existing install

Replace all six files listed above. Deactivate and activate the gateway only if WHMCS does not show the new Sandbox setting. Clear any previously saved Callback URL so notifications hit `callback/ssentezo.php`.
