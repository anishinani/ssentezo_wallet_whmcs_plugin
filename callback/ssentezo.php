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

// === Display Success Page ===
$systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');

// HTML for success page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Ssentezo Wallet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out 0.2s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        .checkmark {
            color: white;
            font-size: 48px;
            font-weight: bold;
        }
        h1 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        .success-message {
            color: #718096;
            font-size: 16px;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .details-box {
            background: #f7fafc;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #718096;
            font-size: 14px;
            font-weight: 500;
        }
        .detail-value {
            color: #2d3748;
            font-size: 14px;
            font-weight: 600;
        }
        .invoice-link {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .invoice-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .powered-by {
            margin-top: 24px;
            color: #a0aec0;
            font-size: 12px;
        }
        @media (max-width: 480px) {
            .success-container {
                padding: 30px 20px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <span class="checkmark">✓</span>
        </div>
        <h1>Payment Successful!</h1>
        <p class="success-message">
            Your payment has been processed successfully. A receipt has been sent to your email.
        </p>
        <div class="details-box">
            <div class="detail-row">
                <span class="detail-label">Invoice Number:</span>
                <span class="detail-value">#<?php echo htmlspecialchars($invoiceId); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount Paid:</span>
                <span class="detail-value"><?php echo htmlspecialchars(number_format($amountPaid, 2)); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars(substr($transactionId, 0, 12)) . '...'; ?></span>
            </div>
        </div>
        <a href="<?php echo $systemUrl . 'viewinvoice.php?id=' . $invoiceId; ?>" class="invoice-link">
            View Invoice
        </a>
        <p class="powered-by">Powered by Ssentezo Wallet</p>
    </div>
</body>
</html>
<?php
exit();
?>
