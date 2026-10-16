<?php

require_once dirname(__DIR__) . '/ssentezo/common.php';
ssentezo_bootstrap();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$params = function_exists('getGatewayVariables') ? getGatewayVariables('ssentezo') : array();
$invoiceId = isset($_GET['invoiceid']) ? (int) $_GET['invoiceid'] : 0;
$reference = isset($_GET['reference']) ? (string) $_GET['reference'] : '';
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$apiKey = isset($params['apiKey']) ? (string) $params['apiKey'] : '';

if (empty($params['type']) || $invoiceId <= 0 || $apiKey === '' || !hash_equals(ssentezo_pollToken($apiKey, $invoiceId, $reference), $token)) {
    http_response_code(403);
    echo json_encode(array('status' => 'error'));
    exit;
}

$result = ssentezo_settleFromReference($params, $invoiceId, $reference);
$status = isset($result['status']) ? $result['status'] : 'pending';
if ($status === 'error') {
    http_response_code(404);
}
echo json_encode(array('status' => $status));
exit;
