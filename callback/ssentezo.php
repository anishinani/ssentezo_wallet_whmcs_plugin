<?php

require("init.php");
use WHMCS\Database\Capsule;

// Read raw input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Log the incoming request
logTransaction("Ssentezo Wallet Callback", $input, "Raw Data");

// === Validate required fields ===
if (
    !isset($data['external_reference']) ||
    !isset($data['amount']) ||
    !isset($data['status']) ||
    !isset($data['transaction_id'])
) {
    logTransaction("Ssentezo Wallet Callback", "Missing required fields", "Error");
    http_response_code(400);
    exit;
}

$invoiceId      = $data['external_reference'];
$amountPaid     = $data['amount'];
$status         = $data['status'];
$transactionId  = $data['transaction_id'];

// === Validate amount ===
if (!is_numeric($amountPaid)) {
    logTransaction("Ssentezo Wallet Callback", "Invalid amount: $amountPaid", "Error");
    http_response_code(400);
    exit;
}

// === Check transaction status ===
if (strtolower($status) !== "success") {
    logTransaction("Ssentezo Wallet Callback", "Transaction not successful", "Ignored");
    http_response_code(200);
    exit;
}

// === Check if invoice exists ===
$invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
if (!$invoice) {
    logTransaction("Ssentezo Wallet Callback", "Invoice not found: " . $invoiceId, "Error");
    http_response_code(404);
    exit;
}

// === Check if already paid ===
if ($invoice->status === "Paid") {
    logTransaction("Ssentezo Wallet Callback", "Invoice already paid: " . $invoiceId, "Ignored");
    http_response_code(200);
    exit;
}

// === Check for duplicate transaction ===
$existingPayment = Capsule::table('tblaccounts')
    ->where('transid', $transactionId)
    ->where('gateway', 'ssentezo')
    ->first();

if ($existingPayment) {
    logTransaction("Ssentezo Wallet Callback", "Duplicate transaction ID: " . $transactionId, "Ignored");
    http_response_code(200);
    exit;
}

// === Apply payment ===
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $amountPaid,
    0,
    "ssentezo" // Must match your gateway module filename
);

// === Final success log ===
logTransaction("Ssentezo Wallet Callback", "Payment applied to invoice ID: $invoiceId", "Success");

http_response_code(200);
echo json_encode(["status" => "ok"]);

?>
