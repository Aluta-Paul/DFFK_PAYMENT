<?php
// File: app/api/mpesa_callback.php
// M-Pesa callback handler

require_once __DIR__ . '/../../../app/config/paths.php';
require_once __DIR__ . '/../../../app/services/DarajaService.php';
require_once __DIR__ . '/../../../app/services/PaymentService.php';
//require_once __DIR__ . '/../repositories/PaymentRepository.php';
require_once __DIR__ . '/../../../app/repositories/PaymentRepository.php';

// Log the callback for debugging
$callbackData = file_get_contents('php://input');
error_log("M-Pesa Callback Received: " . $callbackData);

// Set response headers
header('Content-Type: application/json');

try {
    if (empty($callbackData)) {
        throw new Exception('Empty callback data');
    }
    
    $data = json_decode($callbackData, true);
    
    if (!isset($data['Body']['stkCallback'])) {
        throw new Exception('Invalid callback structure');
    }
    
    $callback = $data['Body']['stkCallback'];
    $checkoutRequestId = $callback['CheckoutRequestID'];
    $resultCode = $callback['ResultCode'];
    $resultDesc = $callback['ResultDesc'] ?? '';
    
    $paymentRepo = new PaymentRepository();
    $payment = $paymentRepo->findByCheckoutRequestId($checkoutRequestId);
    
    if (!$payment) {
        error_log("Payment not found for checkout request: {$checkoutRequestId}");
        // Still respond with success to Daraja
        echo json_encode([
            "ResultCode" => 0,
            "ResultDesc" => "Callback received"
        ]);
        exit;
    }
    
    // Process based on result code

    if ($resultCode == 0) {
        // Payment successful
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $mpesaReceipt = extractMpesaReceipt($metadata);
        $transactionDate = extractTransactionDate($metadata);
        $amount = extractAmount($metadata);
        
        // Update payment record
        $paymentRepo->updatePayment($payment['id'], [
            'status' => 'completed',
            'mpesa_receipt' => $mpesaReceipt,
            'transaction_date' => $transactionDate,
            'failure_reason' => null,
            'failure_code' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Process successful payment
        processSuccessfulPayment($payment, $mpesaReceipt);
        
        error_log("Payment completed: {$payment['id']}, Receipt: {$mpesaReceipt}");
        
    } else {
        // Payment failed - use the new function
        $statusInfo = determinePaymentStatus($resultCode);
        
        $paymentRepo->updatePayment($payment['id'], [
            'status' => $statusInfo['status'],
            'failure_reason' => $statusInfo['failure_reason'],
            'failure_code' => $resultCode,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        error_log("Payment failed: {$payment['id']}, Code: {$resultCode}, Reason: {$statusInfo['failure_reason']}");
    }
    
    // Always respond with success to Daraja
    $response = [
        "ResultCode" => 0,
        "ResultDesc" => "Callback processed successfully"
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Callback Processing Error: " . $e->getMessage());
    
    // Still respond with success to Daraja
    $response = [
        "ResultCode" => 0,
        "ResultDesc" => "Callback received"
    ];
    
    echo json_encode($response);
}

/**
 * Extract M-Pesa receipt from callback metadata
 */
function extractMpesaReceipt($metadata) {
    foreach ($metadata as $item) {
        if ($item['Name'] === 'MpesaReceiptNumber') {
            return $item['Value'];
        }
    }
    return null;
}

/**
 * Extract transaction date from callback metadata
 */
function extractTransactionDate($metadata) {
    foreach ($metadata as $item) {
        if ($item['Name'] === 'TransactionDate') {
            return $item['Value'];
        }
    }
    return null;
}

/**
 * Extract amount from callback metadata
 */
function extractAmount($metadata) {
    foreach ($metadata as $item) {
        if ($item['Name'] === 'Amount') {
            return $item['Value'];
        }
    }
    return null;
}


/**
 * Determine payment status based on result code
 * Returns appropriate status and description
 */
function determinePaymentStatus($resultCode): array {
    // Map result codes to status and description
    $statusMap = [
        0 => ['status' => 'completed', 'desc' => 'Payment completed successfully'],
        1 => ['status' => 'failed', 'desc' => 'Insufficient funds in M-Pesa account'],
        1032 => ['status' => 'cancelled', 'desc' => 'Transaction cancelled by user'],
        1037 => ['status' => 'timeout', 'desc' => 'Transaction timed out - user did not respond'],
        2001 => ['status' => 'failed', 'desc' => 'Invalid PIN entered'],
        2002 => ['status' => 'failed', 'desc' => 'Invalid phone number'],
        2003 => ['status' => 'failed', 'desc' => 'Transaction rejected by bank'],
        2004 => ['status' => 'failed', 'desc' => 'Transaction failed - system error'],
        // Default for any other code
        'default' => ['status' => 'failed', 'desc' => 'Payment failed']
    ];
    
    $result = $statusMap[$resultCode] ?? $statusMap['default'];
    
    return [
        'status' => $result['status'],
        'failure_reason' => $result['desc']
    ];
}

/**
 * Process successful payment
 */
function processSuccessfulPayment($payment, $mpesaReceipt) {
    $paymentType = $payment['payment_type'];
    $paymentRepo = new PaymentRepository();
    
    try {
        switch ($paymentType) {
            case 'registration':
                // Activate player registration
                if (!empty($payment['dffk_code'])) {
                    $expiryDate = date('Y-m-d', strtotime('+1 year'));
                    $paymentRepo->activatePlayer($payment['dffk_code'], $expiryDate);
                }
                break;
                
            case 'team_registration':
                // Activate team
                if (!empty($payment['payer_id']) && $payment['payer_type'] === 'club') {
                    $expiryDate = date('Y-m-d', strtotime('+1 year'));
                    $paymentRepo->activateTeam($payment['payer_id'], $expiryDate);
                }
                break;
                
            case 'match_fine':
            case 'other_fine':
                // Update fine record
                if (!empty($payment['reference_id'])) {
                    updateFineStatus($payment['reference_id'], 'paid');
                }
                break;
                
            case 'donation':
                // Record donation - no action needed
                break;
                
            case 'appeal':
                // Process appeal payment
                if (!empty($payment['reference_id'])) {
                    processAppealPayment($payment['reference_id']);
                }
                break;
                
            default:
                error_log("Unknown payment type processed: {$paymentType}");
        }
        
        // Log successful payment
        error_log("Payment processed successfully: ID={$payment['id']}, Type={$paymentType}, Receipt={$mpesaReceipt}");
        
    } catch (Exception $e) {
        error_log("Error processing payment {$payment['id']}: " . $e->getMessage());
    }
}

/**
 * Update fine status
 */
function updateFineStatus($fineId, $status) {
    $db = DatabaseConnection::getInstance();
    
    $stmt = $db->prepare("
        UPDATE fines 
        SET status = :status, 
            paid_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");
    
    return $stmt->execute([
        ':status' => $status,
        ':id' => $fineId
    ]);
}

/**
 * Process appeal payment
 */
function processAppealPayment($appealId) {
    $db = DatabaseConnection::getInstance();
    
    $stmt = $db->prepare("
        UPDATE appeals 
        SET payment_status = 'paid',
            payment_date = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");
    
    return $stmt->execute([':id' => $appealId]);
}
?>