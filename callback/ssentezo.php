<?php
// === Detailed Callback Logging ===
// Path to your log file (ensure it's writable by the web server)
$logFile = __DIR__ . '/callback_log.txt';

// Capture raw input
$rawPayload = file_get_contents('php://input');

// Capture all request data
$requestData = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'query' => $_GET,
    'post' => $_POST,
    'raw' => $rawPayload,
    'headers' => function_exists('getallheaders') ? getallheaders() : []
];

// Append to log file
file_put_contents($logFile, print_r($requestData, true) . "\n----------------------\n", FILE_APPEND);


require("init.php");
use WHMCS\Database\Capsule;

// === Gateway Module Name (MUST match your module folder) ===
$gatewayModuleName = 'ssentezo'; // Ensure this matches the filename e.g. modules/gateways/ssentezo.php

// === Capture and Decode JSON Payload ===
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// === Log Raw Payload ===
logTransaction($gatewayModuleName, ['input' => $input], "Incoming Request");

// === Validate Required Fields ===
if (
    !isset($data['external_reference']) ||
    !isset($data['amount']) ||
    !isset($data['status']) ||
    !isset($data['transaction_id'])
) {
    logTransaction($gatewayModuleName, $data, "❌ Missing Required Fields");
    http_response_code(400);
    exit;
}

// === Assign Variables ===
$invoiceId      = $data['external_reference'];
$amountPaid     = $data['amount'];
$status         = strtolower($data['status']);
$transactionId  = $data['transaction_id'];

// === Validate Amount ===
if (!is_numeric($amountPaid)) {
    logTransaction($gatewayModuleName, $data, "❌ Invalid Amount");
    http_response_code(400);
    exit;
}

// === Check Transaction Status ===
if ($status !== "success") {
    logTransaction($gatewayModuleName, $data, "⚠️ Transaction Not Successful");
    http_response_code(200);
    exit;
}

// === Validate Invoice Exists ===
$invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
if (!$invoice) {
    logTransaction($gatewayModuleName, $data, "❌ Invoice Not Found: $invoiceId");
    http_response_code(404);
    exit;
}

// === Check if Already Paid ===
if ($invoice->status === "Paid") {
    logTransaction($gatewayModuleName, $data, "✅ Invoice Already Paid: $invoiceId");
    http_response_code(200);
    exit;
}

// === Prevent Duplicate Transaction ===
$existing = Capsule::table('tblaccounts')
    ->where('transid', $transactionId)
    ->where('gateway', $gatewayModuleName)
    ->first();

if ($existing) {
    logTransaction($gatewayModuleName, $data, "⚠️ Duplicate Transaction ID: $transactionId");
    http_response_code(200);
    exit;
}

// === Record Payment ===
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $amountPaid,
    0,
    $gatewayModuleName
);

// === Log Success ===
logTransaction($gatewayModuleName, $data, "✅ Payment Applied to Invoice $invoiceId");

http_response_code(200);
echo json_encode(["status" => "ok"]);
?>
