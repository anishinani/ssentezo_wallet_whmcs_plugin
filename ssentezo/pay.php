<?php

require_once __DIR__ . '/common.php';
ssentezo_bootstrap();

$params = getGatewayVariables('ssentezo');
if (empty($params['type'])) {
    ssentezo_page('Ssentezo', '<h1>Unavailable</h1><p>This payment method is not active.</p>');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . ssentezo_systemUrl($params) . 'clientarea.php?action=invoices');
    exit;
}

if (function_exists('check_token')) {
    check_token('WHMCS.default');
}

$invoiceId = isset($_POST['invoiceid']) ? (int) $_POST['invoiceid'] : 0;
$amount = isset($_POST['amount']) ? (string) $_POST['amount'] : '';
$currency = isset($_POST['currency']) ? strtoupper((string) $_POST['currency']) : '';
$signature = isset($_POST['sig']) ? (string) $_POST['sig'] : '';
$payMethod = isset($_POST['pay_method']) ? (string) $_POST['pay_method'] : '';
$apiKey = isset($params['apiKey']) ? (string) $params['apiKey'] : '';
$apiUsername = isset($params['apiUsername']) ? trim((string) $params['apiUsername']) : '';
$gatewayName = isset($params['name']) ? (string) $params['name'] : 'Ssentezo Wallet';

$invoiceUrl = ssentezo_invoiceUrl($params, $invoiceId);
$backLink = '<a class="btn" href="' . ssentezo_h($invoiceUrl) . '">Back to invoice</a>';

if ($apiUsername === '' || $apiKey === '' || $invoiceId <= 0 || !hash_equals(ssentezo_paymentSignature($apiKey, $invoiceId, $amount, $currency), $signature)) {
    ssentezo_page('Ssentezo', '<h1>Payment could not start</h1><p>Open the invoice and try again.</p>' . $backLink);
}

$invoice = ssentezo_invoiceSnapshot($invoiceId);
if (!$invoice) {
    ssentezo_page('Ssentezo', '<h1>Invoice not found</h1><p>This invoice is no longer available.</p>' . $backLink);
}

$status = strtolower($invoice['status']);
if ($status === 'paid' || $invoice['balance'] <= 0) {
    header('Location: ' . $invoiceUrl);
    exit;
}
if (!in_array($status, array('unpaid', 'payment pending'), true)) {
    ssentezo_page('Ssentezo', '<h1>Invoice cannot be paid</h1><p>This invoice is ' . ssentezo_h($invoice['status']) . '.</p>' . $backLink);
}

if ($invoice['currency'] !== '' && $invoice['currency'] === $currency && abs($invoice['balance'] - (float) $amount) > 0.05) {
    ssentezo_page('Ssentezo', '<h1>Amount changed</h1><p>The invoice total changed. Refresh the invoice and start the payment again.</p>' . $backLink);
}

$chargeAmount = round((float) $amount, 2);
if ($chargeAmount <= 0) {
    ssentezo_page('Ssentezo', '<h1>Nothing to pay</h1><p>This invoice has no balance due.</p>' . $backLink);
}

$reference = ssentezo_generateReference($invoiceId);
$callbackUrl = ssentezo_callbackUrl($params);
$reason = 'Invoice Payment #' . $invoiceId;
if ($invoice['name'] !== '') {
    $reason .= ' by ' . $invoice['name'];
}

if ($payMethod === 'card') {
    if (!ssentezo_cardAllowed($currency)) {
        ssentezo_page('Ssentezo', '<h1>Card payment unavailable</h1><p>Visa and Mastercard checkout is available for UGX invoices.</p>' . $backLink);
    }
    if ($invoice['email'] === '' || $invoice['name'] === '') {
        ssentezo_page('Ssentezo', '<h1>Billing details required</h1><p>Add a name and email address to the client profile, then try the card payment again.</p>' . $backLink);
    }

    $result = ssentezo_apiPost($params, 'card/collect', array(
        'externalReference' => $reference,
        'amount' => $chargeAmount,
        'currency' => $currency,
        'reason' => $reason,
        'name' => $invoice['name'],
        'email' => $invoice['email'],
        'success_callback' => $callbackUrl,
        'failure_callback' => $callbackUrl,
        'redirect_url' => ssentezo_returnUrl($params, $invoiceId, $reference),
    ));

    $checkoutUrl = '';
    if (isset($result['json']['data']) && is_array($result['json']['data'])) {
        if (!empty($result['json']['data']['checkoutUrl'])) {
            $checkoutUrl = (string) $result['json']['data']['checkoutUrl'];
        } elseif (!empty($result['json']['data']['checkout_url'])) {
            $checkoutUrl = (string) $result['json']['data']['checkout_url'];
        }
    }

    ssentezo_log($gatewayName, array(
        'invoice' => $invoiceId,
        'reference' => $reference,
        'method' => 'card',
        'http_code' => $result['httpCode'],
        'response' => $result['json'] !== array() ? $result['json'] : $result['body'],
    ), $result['ok'] && ssentezo_isCheckoutUrl($checkoutUrl) ? 'Card Checkout Started' : 'Error');

    if (!$result['ok'] || !ssentezo_isCheckoutUrl($checkoutUrl)) {
        $message = $result['error'] !== '' ? 'Unable to reach Ssentezo. Please try again.' : ssentezo_apiErrorMessage($result['json']);
        ssentezo_page('Ssentezo', '<h1>Card payment failed</h1><p>' . ssentezo_h($message) . '</p>' . $backLink);
    }

    $safeUrl = ssentezo_h($checkoutUrl);
    ssentezo_page(
        'Redirecting to card checkout',
        '<h1>Redirecting to secure card checkout</h1><p>You will enter the card on Ssentezo\'s page.</p><a class="btn" href="' . $safeUrl . '">Continue</a>'
        . '<script>window.location.href=' . json_encode($checkoutUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
    );
}

$phone = isset($_POST['phone_number']) ? preg_replace('/\s+/', '', (string) $_POST['phone_number']) : '';
if (is_string($phone) && strpos($phone, '+') === 0) {
    $phone = substr($phone, 1);
}
if ($phone === '') {
    ssentezo_page('Ssentezo', '<h1>Phone number required</h1><p>Enter the mobile money number that should approve the payment.</p>' . $backLink);
}

$result = ssentezo_apiPost($params, 'deposit', array(
    'externalReference' => $reference,
    'msisdn' => $phone,
    'amount' => $chargeAmount,
    'currency' => $currency,
    'reason' => $reason,
    'name' => $invoice['name'],
    'success_callback' => $callbackUrl,
    'failure_callback' => $callbackUrl,
));

ssentezo_log($gatewayName, array(
    'invoice' => $invoiceId,
    'reference' => $reference,
    'method' => 'mobile',
    'http_code' => $result['httpCode'],
    'response' => $result['json'] !== array() ? $result['json'] : $result['body'],
), $result['ok'] ? 'Payment Request' : 'Error');

if (!$result['ok']) {
    $message = $result['error'] !== '' ? 'Unable to reach Ssentezo. Please try again.' : ssentezo_apiErrorMessage($result['json']);
    ssentezo_page('Ssentezo', '<h1>Payment request failed</h1><p>' . ssentezo_h($message) . '</p>' . $backLink);
}

ssentezo_waitingPage($params, $invoiceId, $reference, 'Approve the payment request on your phone. This page will update when the payment is confirmed.');
