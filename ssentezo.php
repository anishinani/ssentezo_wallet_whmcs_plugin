<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/ssentezo/common.php';

function ssentezo_MetaData()
{
    return array(
        'DisplayName' => 'Ssentezo Wallet',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function ssentezo_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Ssentezo Wallet',
        ),
        'apiUsername' => array(
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Ssentezo Wallet API username.',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Ssentezo Wallet API key.',
        ),
        'testMode' => array(
            'FriendlyName' => 'Sandbox',
            'Type' => 'yesno',
            'Description' => 'Use devwallet.ssentezo.com instead of the live wallet.',
        ),
        'callbackUrl' => array(
            'FriendlyName' => 'Callback URL',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Leave blank to use this site\'s /modules/gateways/callback/ssentezo.php. Clear any old URL after upgrading WHMCS so notifications hit that file.',
        ),
    );
}

function ssentezo_link($params)
{
    $invoiceId = (int) $params['invoiceid'];
    $amount = number_format((float) str_replace(',', '', (string) $params['amount']), 2, '.', '');
    $currency = strtoupper(trim((string) $params['currency']));
    $apiUsername = isset($params['apiUsername']) ? trim((string) $params['apiUsername']) : '';
    $apiKey = isset($params['apiKey']) ? (string) $params['apiKey'] : '';

    if ($apiUsername === '' || $apiKey === '') {
        return '<div class="alert alert-danger">Ssentezo Wallet is not configured yet. Please contact support.</div>';
    }

    $payUrl = ssentezo_systemUrl($params) . 'modules/gateways/ssentezo/pay.php';
    $signature = ssentezo_paymentSignature($apiKey, $invoiceId, $amount, $currency);
    $tokenField = ssentezo_csrfField();
    $cardAllowed = ssentezo_cardAllowed($currency);

    $hidden = $tokenField
        . '<input type="hidden" name="invoiceid" value="' . $invoiceId . '">'
        . '<input type="hidden" name="amount" value="' . ssentezo_h($amount) . '">'
        . '<input type="hidden" name="currency" value="' . ssentezo_h($currency) . '">'
        . '<input type="hidden" name="sig" value="' . ssentezo_h($signature) . '">';

    $cardBlock = '';
    if ($cardAllowed) {
        $cardBlock = '
        <form method="post" action="' . ssentezo_h($payUrl) . '" style="margin-bottom:8px;">
            ' . $hidden . '
            <input type="hidden" name="pay_method" value="card">
            <button type="submit" style="width:100%;background:#1a1a2e;color:#fff;border:none;padding:12px 20px;border-radius:4px;font-size:16px;font-weight:600;cursor:pointer;">
                Pay with Visa or Mastercard
            </button>
            <small style="color:#7f8c8d;margin-top:8px;display:block;text-align:center;">Card details are entered on Ssentezo\'s secure page.</small>
        </form>
        <div style="margin:14px 0;text-align:center;color:#95a5a6;font-size:12px;">or pay with mobile money</div>';
    }

    return '
    <!-- ssentezo-wallet-1.3 -->
    <div style="max-width:450px;margin:0 auto;padding:20px;border-radius:8px;background:#f8f9fa;border:1px solid #ddd;">
        <div style="text-align:center;margin-bottom:16px;">
            <div style="color:#2c3e50;font-size:18px;font-weight:bold;">Ssentezo Wallet</div>
            <div style="color:#7f8c8d;font-size:14px;">Visa, Mastercard, or mobile money</div>
        </div>
        <div style="text-align:center;font-size:18px;font-weight:bold;margin-bottom:18px;color:#2c3e50;padding:10px;background:#ecf0f1;border-radius:4px;">
            Amount: ' . ssentezo_h($currency . ' ' . $amount) . '
        </div>
        ' . $cardBlock . '
        <form method="post" action="' . ssentezo_h($payUrl) . '">
            ' . $hidden . '
            <input type="hidden" name="pay_method" value="mobile">
            <label for="ssentezo_phone" style="display:block;margin-bottom:8px;font-weight:600;color:#2c3e50;">Mobile number</label>
            <input id="ssentezo_phone" type="tel" name="phone_number" required placeholder="e.g. 2567XXXXXXXX" autocomplete="tel" style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
            <small style="color:#7f8c8d;margin-top:6px;display:block;">Airtel or MTN number that will approve the prompt.</small>
            <button type="submit" style="width:100%;margin-top:14px;background:#3498db;color:#fff;border:none;padding:12px 20px;border-radius:4px;font-size:16px;font-weight:600;cursor:pointer;">
                Pay with Mobile Money
            </button>
        </form>
        <div style="text-align:center;margin-top:16px;font-size:12px;color:#95a5a6;">Invoice #' . $invoiceId . '</div>
    </div>';
}
