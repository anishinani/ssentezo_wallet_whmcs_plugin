<?php
require '../../../init.php'; // Ensure WHMCS is initialized

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$logFile = __DIR__ . '/ssentezo_callback.log';
$timestamp = date('Y-m-d H:i:s');
$rawData = file_get_contents('php://input');

// Log the incoming callback with timestamp
file_put_contents(
    $logFile, 
    "[{$timestamp}] Callback received: {$rawData}\n", 
    FILE_APPEND
);

// Get the raw POST data and decode it
$callbackData = json_decode($rawData, true);

if (!$callbackData || !isset($callbackData['data'])) {
    file_put_contents(
        $logFile, 
        "[{$timestamp}] Invalid callback format\n", 
        FILE_APPEND
    );
    die("Invalid callback format");
}

$data = $callbackData['data'];

// Extract required parameters
$transactionStatus = $data['transactionStatus'] ?? '';
$ssentezoReference = $data['ssentezoWalletReference'] ?? '';
$externalReference = $data['externalReference'] ?? '';
$financialTransactionId = $data['financialTransactionId'] ?? '';

if (!$externalReference || !$transactionStatus) {
    file_put_contents(
        $logFile, 
        "[{$timestamp}] Missing required parameters\n", 
        FILE_APPEND
    );
    die("Missing required parameters");
}

// Parse the invoice ID from the externalReference
// The format from your code is: $invoiceId . '-' . time() . '-' . substr(md5(mt_rand()), 0, 6)
$referenceParts = explode('-', $externalReference);
$invoiceId = isset($referenceParts[0]) ? intval($referenceParts[0]) : 0;

if (!$invoiceId) {
    file_put_contents(
        $logFile, 
        "[{$timestamp}] Could not determine invoice ID from reference: {$externalReference}\n", 
        FILE_APPEND
    );
    die("Invalid invoice reference");
}

$invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);

if ($invoice['status'] === 'Paid') {
    file_put_contents(
        $logFile, 
        "[{$timestamp}] Invoice already paid\n", 
        FILE_APPEND
    );
    die("Invoice already paid");
}

if ($transactionStatus === 'SUCCEEDED') {
    $paymentParams = [
        'invoiceid' => $invoiceId,
        'transid' => $financialTransactionId,
        'amount' => $invoice['total'],
        'gateway' => 'ssentezo',
        'date' => $timestamp
    ];
    $result = localAPI('AddInvoicePayment', $paymentParams);
    file_put_contents(
        $logFile, 
        "[{$timestamp}] Payment API Response: " . json_encode($result) . "\n", 
        FILE_APPEND
    );
    echo "Payment successful";
} else {
    file_put_contents(
        $logFile, 
        "[{$timestamp}] Payment failed: {$transactionStatus}\n", 
        FILE_APPEND
    );
    echo "Payment failed";
}
