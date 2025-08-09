<?php

// === File Includes ===
// These must be at the very top to initialize the WHMCS environment.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

// === Audit Trail Logging ===
// This logs key events in a structured, easy-to-read format.
$auditLogFile = __DIR__ . '/payment_audit_trail.log';

function logAuditTrail($message, $data = []) {
    global $auditLogFile;
    $logEntry = date('Y-m-d H:i:s') . ' - ';

    $logEntry .= $message;

    if (!empty($data)) {
        $logEntry .= ' | Details: ' . json_encode($data);
    }

    $logEntry .= "\n";
    file_put_contents($auditLogFile, $logEntry, FILE_APPEND);
}

// === Gateway Module Name (MUST match your module folder) ===
$gatewayModuleName = 'ssentezo';

// === Capture and Decode JSON Payload ===
$input = @file_get_contents('php://input');
$payload = json_decode($input, true);

// Log incoming request to the audit trail
logAuditTrail("Incoming Request", ['payload_length' => strlen($input)]);

// Check if the 'data' key exists before proceeding
if (!isset($payload['data'])) {
    logAuditTrail("❌ 'data' key is missing from payload", ['payload' => $payload]);
    http_response_code(400);
    die();
}

$data = $payload['data']; // Access the nested 'data' object

// === Validate Required Fields ===
if (
    !isset($data['externalReference']) ||
    !isset($data['transactionStatus']) ||
    !isset($data['financialTransactionId'])
) {
    logAuditTrail("❌ Missing Required Fields", $data);
    http_response_code(400);
    die();
}

// === Assign Variables (using correct field names from the wallet response) ===
$externalReference = $data['externalReference'];
$status            = strtolower($data['transactionStatus']);
$transactionId     = $data['financialTransactionId'];

// === Extract Invoice ID from External Reference ===
// The externalReference is in the format 'invoiceId-timestamp-random'
// We need to extract only the invoiceId part
$parts = explode('-', $externalReference, 2);
$invoiceId = $parts[0];

// Log key variables for tracking
logAuditTrail("Processing Transaction", [
    'external_reference' => $externalReference,
    'invoice_id'         => $invoiceId,
    'transaction_id'     => $transactionId,
    'status'             => $status
]);

// === Check Transaction Status ===
if ($status !== "succeeded") {
    logAuditTrail("⚠️ Transaction Not Successful", ['invoice_id' => $invoiceId, 'status' => $status]);
    http_response_code(200);
    die();
}

// === Validate Invoice ID & Transaction ID ===
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);
checkCbTransID($transactionId);

// === Get Invoice Details ===
$invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
$amountPaid = $invoice->total;

logAuditTrail("Fetched Amount from Invoice", ['invoice_id' => $invoiceId, 'amount' => $amountPaid]);

// === Record Payment ===
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $amountPaid,
    0,
    $gatewayModuleName
);

// === Log Success ===
logAuditTrail("✅ Payment Applied to Invoice", ['invoice_id' => $invoiceId, 'transaction_id' => $transactionId, 'amount_paid' => $amountPaid]);
logTransaction($gatewayModuleName, $data, "Successful");

// === Redirect Client to Invoice Page ===
$systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
$redirectUrl = $systemUrl . 'viewinvoice.php?id=' . $invoiceId;

header("Location: " . $redirectUrl);
exit();
?>
