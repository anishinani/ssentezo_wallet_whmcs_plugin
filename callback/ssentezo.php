<?php

require_once dirname(__DIR__) . '/ssentezo/common.php';
ssentezo_bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$gatewayModuleName = 'ssentezo';
$params = function_exists('getGatewayVariables') ? getGatewayVariables($gatewayModuleName) : array();
$gatewayName = isset($params['name']) && $params['name'] !== '' ? (string) $params['name'] : 'Ssentezo Wallet';

if (empty($params['type'])) {
    http_response_code(503);
    echo 'Module Not Activated';
    exit;
}

$data = ssentezo_callbackPayload();
$externalReference = ssentezo_field($data, 'externalReference');
$reportedStatus = ssentezo_statusFrom($data);

if ($externalReference === '') {
    ssentezo_log($gatewayName, $data, 'Invalid Callback');
    http_response_code(400);
    echo 'Missing reference';
    exit;
}

$invoiceId = ssentezo_invoiceIdFromReference($externalReference);
if ($invoiceId <= 0) {
    ssentezo_log($gatewayName, array('reference' => $externalReference), 'Invalid Invoice Reference');
    http_response_code(400);
    echo 'Invalid reference';
    exit;
}

if (!ssentezo_isSuccessStatus($reportedStatus)) {
    ssentezo_log($gatewayName, array(
        'reference' => $externalReference,
        'invoice' => $invoiceId,
        'status' => $reportedStatus,
    ), 'Unsuccessful');
    http_response_code(200);
    echo 'OK';
    exit;
}

$invoice = ssentezo_invoiceSnapshot($invoiceId);
if (!$invoice) {
    ssentezo_log($gatewayName, array('invoice' => $invoiceId, 'reference' => $externalReference), 'Invoice Not Found');
    http_response_code(404);
    echo 'Invoice not found';
    exit;
}

if (strtolower($invoice['status']) === 'paid' || $invoice['balance'] <= 0) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$verified = ssentezo_apiPost($params, 'get_status/' . rawurlencode($externalReference), array(), 3);
$verifiedData = array();
if (isset($verified['json']['data']) && is_array($verified['json']['data'])) {
    $verifiedData = $verified['json']['data'];
}

if (!$verified['ok']) {
    ssentezo_log($gatewayName, array(
        'reference' => $externalReference,
        'invoice' => $invoiceId,
        'http_code' => $verified['httpCode'],
        'error' => $verified['error'],
    ), 'Verification Failed');
    http_response_code(503);
    echo 'Retry';
    exit;
}

if (!ssentezo_isSuccessStatus(ssentezo_statusFrom($verifiedData))) {
    ssentezo_log($gatewayName, array(
        'reference' => $externalReference,
        'invoice' => $invoiceId,
        'status' => ssentezo_statusFrom($verifiedData),
    ), 'Not Successful');
    http_response_code(200);
    echo 'OK';
    exit;
}

$transactionId = ssentezo_field($verifiedData, 'financialTransactionId');
if ($transactionId === '') {
    $transactionId = ssentezo_field($data, 'financialTransactionId');
}
if ($transactionId === '') {
    $transactionId = ssentezo_field($verifiedData, 'ssentezoWalletReference');
}
if ($transactionId === '') {
    $transactionId = $externalReference;
}

if (ssentezo_transactionExists($transactionId)) {
    http_response_code(200);
    echo 'OK';
    exit;
}

if (function_exists('checkCbInvoiceID')) {
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);
}
if (function_exists('checkCbTransID')) {
    checkCbTransID($transactionId);
}

$applyAmount = $invoice['balance'];
$verifiedCurrency = strtoupper(ssentezo_field($verifiedData, 'currency'));
if (isset($verifiedData['amount']) && is_numeric($verifiedData['amount'])) {
    $verifiedAmount = round((float) $verifiedData['amount'], 2);
    $sameCurrency = $verifiedCurrency === '' || $invoice['currency'] === '' || $verifiedCurrency === $invoice['currency'];
    if ($sameCurrency && $verifiedAmount > 0 && ($verifiedAmount + 0.01) < $invoice['balance']) {
        $applyAmount = $verifiedAmount;
    }
}

try {
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $applyAmount,
        0,
        $gatewayModuleName
    );
} catch (Throwable $e) {
    ssentezo_log($gatewayName, array(
        'invoice' => $invoiceId,
        'transaction' => $transactionId,
        'error' => $e->getMessage(),
    ), 'Payment Apply Failed');
    http_response_code(500);
    echo 'ERROR';
    exit;
}

ssentezo_log($gatewayName, array(
    'invoice' => $invoiceId,
    'reference' => $externalReference,
    'transaction' => $transactionId,
    'amount' => $applyAmount,
), 'Success');

http_response_code(200);
echo 'OK';
exit;
