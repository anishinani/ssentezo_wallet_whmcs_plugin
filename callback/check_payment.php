<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

use WHMCS\Database\Capsule;

// Get invoice ID from query parameter
$invoiceId = isset($_GET['invoiceid']) ? (int)$_GET['invoiceid'] : 0;

if ($invoiceId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice ID']);
    exit();
}

// Get invoice status from database
$invoice = Capsule::table('tblinvoices')
    ->where('id', $invoiceId)
    ->first();

if (!$invoice) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
    exit();
}

// Check if invoice is paid
$status = ($invoice->status === 'Paid') ? 'paid' : 'pending';

header('Content-Type: application/json');
echo json_encode([
    'status' => $status,
    'invoice_id' => $invoiceId,
    'amount' => $invoice->total
]);
?>

