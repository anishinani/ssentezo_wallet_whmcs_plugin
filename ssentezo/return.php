<?php

require_once __DIR__ . '/common.php';
ssentezo_bootstrap();

$params = getGatewayVariables('ssentezo');
if (empty($params['type'])) {
    ssentezo_page('Ssentezo', '<h1>Unavailable</h1><p>This payment method is not active.</p>');
}

$invoiceId = isset($_GET['invoiceid']) ? (int) $_GET['invoiceid'] : 0;
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$apiKey = isset($params['apiKey']) ? (string) $params['apiKey'] : '';
$expected = ssentezo_pollToken($apiKey, $invoiceId);

if ($invoiceId <= 0 || $apiKey === '' || !hash_equals($expected, $token)) {
    ssentezo_page('Ssentezo', '<h1>Payment session expired</h1><p>Open the invoice to check whether the payment was received.</p>');
}

$invoice = ssentezo_invoiceSnapshot($invoiceId);
$invoiceUrl = ssentezo_invoiceUrl($params, $invoiceId);

if ($invoice && (strtolower($invoice['status']) === 'paid' || $invoice['balance'] <= 0)) {
    header('Location: ' . $invoiceUrl);
    exit;
}

ssentezo_waitingPage(
    $params,
    $invoiceId,
    '',
    'Confirming the card payment. You can leave this page open; it will return to the invoice when the payment is recorded.'
);
