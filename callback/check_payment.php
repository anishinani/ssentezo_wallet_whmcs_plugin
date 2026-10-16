<?php

require_once dirname(__DIR__) . '/ssentezo/common.php';
ssentezo_bootstrap();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$params = function_exists('getGatewayVariables') ? getGatewayVariables('ssentezo') : array();
$invoiceId = isset($_GET['invoiceid']) ? (int) $_GET['invoiceid'] : 0;
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$apiKey = isset($params['apiKey']) ? (string) $params['apiKey'] : '';

if (empty($params['type']) || $invoiceId <= 0 || $apiKey === '' || !hash_equals(ssentezo_pollToken($apiKey, $invoiceId), $token)) {
    http_response_code(403);
    echo json_encode(array('status' => 'error'));
    exit;
}

$invoice = ssentezo_invoiceSnapshot($invoiceId);
if (!$invoice) {
    http_response_code(404);
    echo json_encode(array('status' => 'error'));
    exit;
}

$paid = strtolower($invoice['status']) === 'paid' || $invoice['balance'] <= 0;
echo json_encode(array('status' => $paid ? 'paid' : 'pending'));
exit;
