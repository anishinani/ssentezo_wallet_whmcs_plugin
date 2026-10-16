<?php

require_once dirname(__DIR__) . '/ssentezo/common.php';
ssentezo_bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$params = function_exists('getGatewayVariables') ? getGatewayVariables('ssentezo') : array();
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

$result = ssentezo_settleFromReference($params, $invoiceId, $externalReference);
$status = isset($result['status']) ? $result['status'] : 'pending';

if ($status === 'error') {
    http_response_code(404);
    echo 'Invoice not found';
    exit;
}

if ($status === 'pending') {
    http_response_code(503);
    echo 'Retry';
    exit;
}

http_response_code(200);
echo 'OK';
exit;
