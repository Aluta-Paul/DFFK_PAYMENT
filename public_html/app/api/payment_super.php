<?php
// File: app/api/payment_super.php
// Main payment API endpoint

require_once __DIR__ . '/../../../app/config/paths.php';
require_once __DIR__ . '/../../../app/services/DarajaService.php';
require_once __DIR__ . '/../../../app/services/PaymentService.php';
require_once __DIR__ . '/../../../app/services/AuthService.php';
require_once __DIR__ . '/../../../app/services/TeamService.php';
require_once __DIR__ . '/../../../app/services/PlayerService.php';
require_once __DIR__ . '/../../../app/repositories/PaymentRepository.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'initiate_payment':
            $response = initiatePayment($input);
            break;
            
        case 'query_status':
            $response = queryPaymentStatus($input);
            break;
            
        case 'validate_dffk':
            $response = validateDffkCode($input);
            break;
            
        case 'get_payment_history':
            $response = getPaymentHistory($input);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode() ?: 500
    ];
}

echo json_encode($response);
exit;

/**
 * Initiate an STK push payment
 */
function initiatePayment($data) {
    $required = ['payment_type', 'amount', 'phone_number'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    $paymentType = $data['payment_type'];
    $amount = (float)$data['amount'];
    $phone = $data['phone_number'];
    $accountReference = $data['account_reference'] ?? 'DFFK-PAY';
    $transactionDesc = $data['transaction_desc'] ?? 'DFFK Payment';

    // Validate amount based on payment type
    validatePaymentAmount($paymentType, $amount);

    // Get payer information
    $payerInfo = getPayerInfo($data);

    // Create payment record
    $paymentRepo = new PaymentRepository();
    $paymentData = [
        'payment_type' => $paymentType,
        'amount' => $amount,
        'phone_number' => $phone,
        'account_name' => $payerInfo['name'] ?? '',
        'status' => 'pending',
        'payment_method' => 'mpesa',
        'payer_type' => $payerInfo['type'] ?? 'individual',
        'payer_id' => $payerInfo['id'] ?? null,
        'dffk_code' => $data['dffk_code'] ?? null,
        'reference_id' => $data['reference_id'] ?? null
    ];

    $paymentId = $paymentRepo->createPayment($paymentData);

    // Initiate STK push
    $daraja = new DarajaService();
    $stkResponse = $daraja->initiateStkPush(
        $phone,
        $amount,
        $accountReference,
        $transactionDesc
    );

    // Update payment with checkout request ID
    if (isset($stkResponse['CheckoutRequestID'])) {
        $paymentRepo->updatePayment($paymentId, [
            'checkout_request_id' => $stkResponse['CheckoutRequestID'],
            'merchant_request_id' => $stkResponse['MerchantRequestID'] ?? null
        ]);
    }

    return [
        'success' => true,
        'message' => 'STK push initiated successfully',
        'data' => [
            'payment_id' => $paymentId,
            'checkout_request_id' => $stkResponse['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $stkResponse['MerchantRequestID'] ?? null,
            'response_code' => $stkResponse['ResponseCode'] ?? null,
            'response_description' => $stkResponse['ResponseDescription'] ?? null
        ]
    ];
}

/**
 * Query payment status
 */
function queryPaymentStatus($data) {
    if (empty($data['checkout_request_id'])) {
        throw new Exception('Checkout request ID is required');
    }

    $checkoutRequestId = $data['checkout_request_id'];
    
    $daraja = new DarajaService();
    $statusResponse = $daraja->queryStkStatus($checkoutRequestId);
    
    // Process the status response
    $paymentRepo = new PaymentRepository();
    $payment = $paymentRepo->findByCheckoutRequestId($checkoutRequestId);
    
    if (!$payment) {
        throw new Exception('Payment not found');
    }

    $resultCode = $statusResponse['ResultCode'] ?? null;
    $resultDesc = $statusResponse['ResultDesc'] ?? '';

    // Handle different result codes
    $paymentStatus = handlePaymentResult($resultCode, $resultDesc);
    
    // Update payment record
    if ($resultCode == 0) {
        // Success - extract receipt
        $metadata = $statusResponse['CallbackMetadata']['Item'] ?? [];
        $mpesaReceipt = extractMpesaReceipt($metadata);
        
        $paymentRepo->updatePayment($payment['id'], [
            'status' => 'completed',
            'mpesa_receipt' => $mpesaReceipt,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Process successful payment based on type
        processSuccessfulPayment($payment);
        
        return [
            'success' => true,
            'message' => 'Payment completed successfully',
            'data' => [
                'status' => 'completed',
                'mpesa_receipt' => $mpesaReceipt,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc
            ]
        ];
    } else {
        // Failed or pending
        $status = $resultCode == '1037' ? 'pending' : 'failed';
        
        $paymentRepo->updatePayment($payment['id'], [
            'status' => $status,
            'failure_reason' => $resultDesc,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'success' => false,
            'message' => getPaymentErrorMessage($resultCode, $resultDesc),
            'data' => [
                'status' => $status,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc
            ]
        ];
    }
}

/**
 * Validate DFFK code
 */
function validateDffkCode($data) {
    if (empty($data['dffk_code'])) {
        throw new Exception('DFFK code is required');
    }

    $dffkCode = $data['dffk_code'];
    $paymentType = $data['payment_type'] ?? 'registration';
    
    $paymentService = new PaymentService();
    $result = $paymentService->validateDffkCode($dffkCode, $paymentType);
    
    return $result;
}

/**
 * Get payment history
 */
function getPaymentHistory($data) {
    $identifier = $data['identifier'] ?? '';
    $identifierType = $data['identifier_type'] ?? 'dffk_code';
    
    if (empty($identifier)) {
        throw new Exception('Identifier is required');
    }
    
    $paymentRepo = new PaymentRepository();
    
    // Find payer
    if ($identifierType === 'dffk_code') {
        $authService = new AuthService();
        $db = $authService->getDatabase();
        
        $stmt = $db->prepare("
            SELECT p.*, pr.dffk_code 
            FROM persons p
            JOIN person_roles pr ON p.id = pr.person_id
            WHERE pr.dffk_code = :dffk_code
        ");
        $stmt->execute([':dffk_code' => $identifier]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($person) {
            $payments = $paymentRepo->getPayerPaymentHistory('person', $person['id']);
            return [
                'success' => true,
                'data' => [
                    'payer' => $person,
                    'payments' => $payments
                ]
            ];
        }
    }
    
    // Try as national ID
    if ($identifierType === 'national_id') {
        $authService = new AuthService();
        $db = $authService->getDatabase();
        
        $stmt = $db->prepare("
            SELECT p.*, pr.dffk_code 
            FROM persons p
            LEFT JOIN person_roles pr ON p.id = pr.person_id
            WHERE p.national_id = :national_id
        ");
        $stmt->execute([':national_id' => $identifier]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($person) {
            $payments = $paymentRepo->getPayerPaymentHistory('person', $person['id']);
            return [
                'success' => true,
                'data' => [
                    'payer' => $person,
                    'payments' => $payments
                ]
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'No records found for the provided identifier'
    ];
}

/**
 * Validate payment amount based on type
 */
function validatePaymentAmount($paymentType, $amount) {
    $validAmounts = [
        'registration' => ['male' => 250, 'female' => 200],
        'team_registration' => ['male' => 10000, 'female' => 5000],
        'donation' => ['min' => 10],
        'match_fine' => ['red' => 200, 'yellow' => 150],
        'other_fine' => ['min' => 10],
        'appeal' => ['min' => 100]
    ];

    if (!isset($validAmounts[$paymentType])) {
        throw new Exception("Invalid payment type: {$paymentType}");
    }

    $valid = $validAmounts[$paymentType];
    
    if (isset($valid['min']) && $amount < $valid['min']) {
        throw new Exception("Minimum amount for {$paymentType} is {$valid['min']}");
    }
    
    if (isset($valid['max']) && $amount > $valid['max']) {
        throw new Exception("Maximum amount for {$paymentType} is {$valid['max']}");
    }
    
    // Check specific amount values
    if (isset($valid['male']) && isset($valid['female'])) {
        // Allow both amounts
        if ($amount != $valid['male'] && $amount != $valid['female']) {
            throw new Exception("Amount must be {$valid['male']} (male) or {$valid['female']} (female)");
        }
    }
    
    // Check match fines
    if ($paymentType === 'match_fine') {
        if (!in_array($amount, [200, 150])) {
            throw new Exception("Match fine must be 200 (red card) or 150 (yellow card)");
        }
    }

    return true;
}

/**
 * Get payer information from request data
 */
function getPayerInfo($data) {
    $info = [
        'type' => 'individual',
        'id' => null,
        'name' => ''
    ];
    
    // Check if DFFK code provided
    if (!empty($data['dffk_code'])) {
        $paymentService = new PaymentService();
        $result = $paymentService->validateDffkCode($data['dffk_code'], $data['payment_type']);
        
        if ($result['success'] && isset($result['data'])) {
            $payerData = $result['data'];
            $info['type'] = 'person';
            $info['id'] = $payerData['person_id'] ?? null;
            $info['name'] = $payerData['full_name'] ?? '';
        }
    }
    
    // Check if club ID provided
    if (!empty($data['club_id'])) {
        $info['type'] = 'club';
        $info['id'] = $data['club_id'];
        $info['name'] = $data['club_name'] ?? '';
    }
    
    // Use provided name if available
    if (!empty($data['account_name'])) {
        $info['name'] = $data['account_name'];
    }
    
    return $info;
}

/**
 * Handle payment result code
 */
function handlePaymentResult($resultCode, $resultDesc) {
    // Success
    if ($resultCode == 0) {
        return 'completed';
    }
    
    // User cancelled (1032) or timeout (1037) - pending
    if ($resultCode == 1032 || $resultCode == 1037) {
        return 'pending';
    }
    
    // Insufficient funds (1), wrong PIN (2001), or other failures
    return 'failed';
}

/**
 * Get user-friendly error message
 */
function getPaymentErrorMessage($resultCode, $resultDesc) {
    $messages = [
        1 => 'Insufficient funds in your M-Pesa account. Please top up and try again.',
        1032 => 'Transaction cancelled by user. You can try again.',
        1037 => 'Transaction timed out. Please check your phone and try again.',
        2001 => 'Invalid PIN entered. Please try again.',
        2002 => 'Invalid phone number. Please check and try again.'
    ];
    
    return $messages[$resultCode] ?? "Payment failed: {$resultDesc}";
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
 * Process successful payment based on type
 */
function processSuccessfulPayment($payment) {
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
                // Record fine payment - mark as resolved
                if (!empty($payment['reference_id'])) {
                    // Update fine record
                    updateFineStatus($payment['reference_id'], 'paid');
                }
                break;
                
            case 'donation':
                // Just record the donation - no action needed
                break;
                
            case 'appeal':
                // Process appeal payment
                if (!empty($payment['reference_id'])) {
                    processAppealPayment($payment['reference_id']);
                }
                break;
                
            default:
                // Log unknown payment type
                error_log("Unknown payment type processed: {$paymentType}");
        }
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