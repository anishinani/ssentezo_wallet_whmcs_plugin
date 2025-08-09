<?php
// === Detailed Callback Logging (Raw Payload) ===
// This logs the full, raw request for debugging purposes.
$rawLogFile = __DIR__ . '/raw_callback_log.txt';
$rawPayload = file_get_contents('php://input');

$requestData = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'query' => $_GET,
    'post' => $_POST,
    'raw' => $rawPayload,
    'headers' => function_exists('getallheaders') ? getallheaders() : []
];

file_put_contents($rawLogFile, print_r($requestData, true) . "\n----------------------\n", FILE_APPEND);

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

require("../../../init.php");
use WHMCS\Database\Capsule;

// === Gateway Module Name (MUST match your module folder) ===
$gatewayModuleName = 'ssentezo';

// === Capture and Decode JSON Payload ===
$input = file_get_contents("php://input");
$payload = json_decode($input, true);

// Log incoming request to the audit trail
logAuditTrail("Incoming Request", ['payload_length' => strlen($input)]);

// Check if the 'data' key exists before proceeding
if (!isset($payload['data'])) {
    logAuditTrail("❌ 'data' key is missing from payload", ['payload' => $payload]);
    http_response_code(400);
    exit;
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
    exit;
}

// === Assign Variables (using correct field names from the wallet response) ===
$invoiceId      = $data['externalReference'];
$status         = strtolower($data['transactionStatus']);
$transactionId  = $data['financialTransactionId'];

// Log key variables for tracking
logAuditTrail("Processing Transaction", [
    'invoice_id' => $invoiceId,
    'transaction_id' => $transactionId,
    'status' => $status
]);

// === Check Transaction Status ===
if ($status !== "succeeded") { // Note: status is 'SUCCEEDED' in the response
    logAuditTrail("⚠️ Transaction Not Successful", ['invoice_id' => $invoiceId, 'status' => $status]);
    http_response_code(200);
    exit;
}

// === Validate Invoice Exists & Get Amount ===
$invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
if (!$invoice) {
    logAuditTrail("❌ Invoice Not Found", ['invoice_id' => $invoiceId]);
    http_response_code(404);
    exit;
}

$amountPaid = $invoice->total; // Get the total amount from the invoice
logAuditTrail("Fetched Amount from Invoice", ['invoice_id' => $invoiceId, 'amount' => $amountPaid]);

// === Check if Already Paid ===
if ($invoice->status === "Paid") {
    logAuditTrail("✅ Invoice Already Paid", ['invoice_id' => $invoiceId]);
    http_response_code(200);
    exit;
}

// === Prevent Duplicate Transaction ===
$existing = Capsule::table('tblaccounts')
    ->where('transid', $transactionId)
    ->where('gateway', $gatewayModuleName)
    ->first();

if ($existing) {
    logAuditTrail("⚠️ Duplicate Transaction ID", ['invoice_id' => $invoiceId, 'transaction_id' => $transactionId]);
    http_response_code(200);
    exit;
}

// === Record Payment ===
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $amountPaid,
    0, // Replace 0 with the actual fee if the wallet provides it
    $gatewayModuleName
);

// === Log Success ===
logAuditTrail("✅ Payment Applied to Invoice", ['invoice_id' => $invoiceId, 'transaction_id' => $transactionId, 'amount_paid' => $amountPaid]);

http_response_code(200);
echo json_encode(["status" => "ok"]);
?>
