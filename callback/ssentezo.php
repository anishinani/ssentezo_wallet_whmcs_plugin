<?php
require '../../../init.php'; // Ensure WHMCS is initialized

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$logFile = __DIR__ . '/ssentezo_callback.log';
file_put_contents($logFile, "Callback received: " . json_encode($_POST) . "\n", FILE_APPEND);

$invoiceId = $_POST['invoice_id'] ?? '';
$reference = $_POST['reference'] ?? '';
$status = $_POST['status'] ?? '';
$transactionId = $_POST['transaction_id'] ?? '';

if (!$invoiceId || !$reference || !$status) {
    file_put_contents($logFile, "Invalid callback parameters\n", FILE_APPEND);
    die("Invalid callback parameters");
}

$invoiceId = intval($invoiceId);
$invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);

if ($invoice['status'] === 'Paid') {
    file_put_contents($logFile, "Invoice already paid\n", FILE_APPEND);
    die("Invoice already paid");
}

if ($status === 'success') {
    $paymentParams = [
        'invoiceid' => $invoiceId,
        'transid' => $transactionId,
        'amount' => $invoice['total'],
        'gateway' => 'ssentezo',
        'date' => date('Y-m-d H:i:s')
    ];
    $result = localAPI('AddInvoicePayment', $paymentParams);
    file_put_contents($logFile, "Payment API Response: " . json_encode($result) . "\n", FILE_APPEND);
    echo "Payment successful";
} else {
    file_put_contents($logFile, "Payment failed\n", FILE_APPEND);
    echo "Payment failed";
}
