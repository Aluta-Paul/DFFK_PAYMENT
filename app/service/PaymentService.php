<?php
require_once __DIR__ . '/../repositories/PaymentRepository.php';
require_once __DIR__ . '/../services/DarajaService.php';
require_once __DIR__ . '/../services/TeamService.php';
require_once __DIR__ . '/../services/AuthService.php';

class PaymentService {
    private $paymentRepository;
    private $darajaService;
    private $teamService;
    private $authService;

    public function __construct() {
        $this->paymentRepository = new PaymentRepository();
        $this->darajaService = new DarajaService();
        $this->teamService = new TeamService();
        $this->authService = new AuthService();
    }

    /**
     * Initiate a payment transaction
     */
    public function initiatePayment(array $paymentData): array {
        try {
            // Validate payment data
            if (!$this->validatePaymentData($paymentData)) {
                return ['success' => false, 'message' => 'Invalid payment data'];
            }
            
             //  Normalize phone number BEFORE using it
            $paymentData['phone_number'] = $this->normalizePhoneNumber($paymentData['phone_number']);

            // Decide how to handle the amount
            if ($paymentData['type'] === 'donation' || $paymentData['type'] === 'form_filling') {
                // Use the user-provided amount
                $amount = $paymentData['amount'];
            } else {
                // Use fixed amounts for other types
                $amount = $this->getPaymentAmount($paymentData['type']);
            }

            // Map the incoming payment types to database enum values
            $paymentTypeMap = [
                'player' => 'player',
                'team_registration' => 'team',
                'league' => 'league',
                'donation' => 'donation',
                'form_filling' => 'form_access' 
            ];

            

            // Use the mapped value or fallback to the original
            $dbPaymentType = $paymentTypeMap[$paymentData['payment_type']] ?? $paymentData['type'];
            
            // Create payment record with pending status
            $paymentId = $this->paymentRepository->createPayment([
                'payment_type' => $dbPaymentType,
                'amount' => $amount,
                'phone_number' => $paymentData['phone_number'],
                'account_name' => $paymentData['account_name'] ?? '',
                'status' => 'pending',
                'payer_type' => $paymentData['payer_type'] ?? null,
                'payer_id' => $paymentData['payer_id'] ?? null,
                'dffk_code' => $paymentData['dffk_code'] ?? null
            ]);

            // For M-Pesa payments, initiate STK push
            if ($paymentData['payment_method'] === 'mpesa') {
                $result = $this->darajaService->initiateStkPush(
                    $paymentData['phone_number'],
                    $amount,
                    $paymentData['account_name'] ?? 'Payment',
                    $paymentData['type'] . ' Payment'
                );

                if (isset($result['CheckoutRequestID'])) {
                    // Update payment with checkout request ID
                    $this->paymentRepository->updatePayment($paymentId, [
                        'checkout_request_id' => $result['CheckoutRequestID'],
                        'merchant_request_id' => $result['MerchantRequestID'] ?? null
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Payment initiated successfully',
                        'payment_id' => $paymentId,
                        'checkout_request_id' => $result['CheckoutRequestID']
                    ];
                } else {
                    throw new Exception('Failed to initiate M-Pesa payment: ' . json_encode($result));
                }
            } else {
                // For manual payments (cash, bank), mark as completed immediately
                $this->paymentRepository->updatePayment($paymentId, [
                    'status' => 'completed',
                    'payment_method' => $paymentData['payment_method']
                ]);

                // Activate account if it's a registration payment
                if (in_array($paymentData['type'], ['registration', 'league'])) {
                    $this->activateAccount($paymentId);
                }

                return [
                    'success' => true,
                    'message' => 'Payment recorded successfully',
                    'payment_id' => $paymentId
                ];
            }
        } catch (Exception $e) {
            error_log("Payment initiation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Payment initiation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Handle M-Pesa callback and update payment status
     * At this stage NO ACCESS is granted yet.
      *Only database truth is updated.
     */
    // public function handleMpesaCallback(array $callbackData): array {
    //     try {
    //         // 1. Validate callback payload
    //         $validatedData = $this->darajaService->validateCallback($callbackData);
            
    //         // Find payment by checkout request ID 2. Locate payment
    //         $payment = $this->paymentRepository->findByCheckoutRequestId($validatedData['CheckoutRequestID']);
            
    //         if (!$payment) {
    //             throw new Exception('Payment not found for checkout request: ' . $validatedData['CheckoutRequestID']);
    //         }
    //         // 3. Handle failure early

    //         if ($validatedData['ResultCode'] == 0) {
    //             // Payment successful
    //             // 4. Success → update payment only
    //             $mpesaReceipt = $this->darajaService->extractMpesaReceipt($validatedData['CallbackMetadata']);
                
    //             $this->paymentRepository->updatePayment($payment['id'], [
    //                 'status' => 'completed',
    //                 'mpesa_receipt' => $mpesaReceipt,
    //                 'result_code' => $validatedData['ResultCode'],
    //                 'result_desc' => $validatedData['ResultDesc']
    //             ]);

    //             // Activate account if it's a registration payment
    //             if (in_array($payment['payment_type'], ['registration', 'league'])) {
    //                 $this->activateAccount($payment['id']);
    //             }

    //             return ['success' => true, 'message' => 'Payment processed successfully'];
    //         } else {
    //             // Payment failed
    //             $this->paymentRepository->updatePayment($payment['id'], [
    //                 'status' => 'failed',
    //                 'result_code' => $validatedData['ResultCode'],
    //                 'result_desc' => $validatedData['ResultDesc']
    //             ]);

    //             return ['success' => false, 'message' => 'Payment failed: ' . $validatedData['ResultDesc']];
    //         }
    //     } catch (Exception $e) {
    //         error_log("Callback handling failed: " . $e->getMessage());
    //         return ['success' => false, 'message' => 'Callback processing failed: ' . $e->getMessage()];
    //     }
    // }
    
    public function handleMpesaCallback(array $callbackData): array {
        try {
            // 1. Validate callback payload
            $validated = $this->darajaService->validateCallback($callbackData);
    
            // 2. Locate payment
            $payment = $this->paymentRepository
                ->findByCheckoutRequestId($validated['CheckoutRequestID']);
    
            if (!$payment) {
                throw new Exception(
                    'Payment not found for checkout request: ' .
                    $validated['CheckoutRequestID']
                );
            }
    
            // 3. Handle failure early
            if ((int)$validated['ResultCode'] !== 0) {
                $this->paymentRepository->updatePayment($payment['id'], [
                    'status'      => 'failed',
                    'result_code' => $validated['ResultCode'],
                    'result_desc' => $validated['ResultDesc']
                ]);
    
                return [
                    'success' => false,
                    'message' => 'Payment failed: ' . $validated['ResultDesc']
                ];
            }
    
            // 4. Success → update payment only
            $mpesaReceipt = $this->darajaService
                ->extractMpesaReceipt($validated['CallbackMetadata']);
    
            $this->paymentRepository->updatePayment($payment['id'], [
                'status'        => 'completed',
                'mpesa_receipt' => $mpesaReceipt,
                'result_code'   => $validated['ResultCode'],
                'result_desc'   => $validated['ResultDesc']
            ]);
    
            // 5. Delegate business logic
            $payment['mpesa_receipt'] = $mpesaReceipt;
            $this->handleSuccessfulPayment($payment);
    
            return [
                'success' => true,
                'message' => 'Payment processed successfully'
            ];
        } catch (Exception $e) {
            error_log('Mpesa callback error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Callback processing failed'
            ];
        }
    }
    

    // Add this method to your PaymentService class
    public function validateDffkCode(string $dffkCode, string $paymentType): array {
        try {
            if ($paymentType === 'player_registration') {
                // Check if it's a national ID format
                if (strpos($dffkCode, 'NATIONAL-') === 0) {
                    $nationalId = substr($dffkCode, 9);
                    $player = $this->findPlayerByIdentifier('national_id', $nationalId);
                    
                    if (!$player) {
                        return ['success' => false, 'message' => 'No player found with this National ID'];
                    }
                    
                    return [
                        'success' => true,
                        'player_id' => $player['id'],
                        'player_name' => $player['first_name'] . ' ' . $player['last_name'],
                        'dffk_code' => $player['dffk_code']
                    ];
                } else {
                    // It's a DFFK code format
                    $player = $this->findPlayerByIdentifier('dffk_code', $dffkCode);
                    
                    if (!$player) {
                        return ['success' => false, 'message' => 'Invalid DFFK code. Please check and try again.'];
                    }
                    
                    return [
                        'success' => true,
                        'player_name' => $player['first_name'] . ' ' . $player['last_name'],
                        'dffk_code' => $dffkCode
                    ];
                }
            }
            
            return ['success' => false, 'message' => 'Invalid payment type'];
        } catch (Exception $e) {
            error_log("DFFK validation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Validation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Update expiry date after successful payment
     */
    public function updateExpiryDate(string $dffkCode, string $expiryDate = null): bool {
        try {
            if (!$expiryDate) {
                // Set expiry to 1 year from now by default
                $expiryDate = date('Y-m-d', strtotime('+1 year'));
            }
            
            $stmt = $this->db->prepare("
                UPDATE person_roles 
                SET expiry_date = :expiry_date, updated_at = NOW()
                WHERE dffk_code = :dffk_code
            ");
            
            return $stmt->execute([
                ':expiry_date' => $expiryDate,
                ':dffk_code' => $dffkCode
            ]);
        } catch (Exception $e) {
            error_log("Expiry date update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activate account after successful payment
     */
    // Modify the activateAccount method to use updateExpiryDate
    private function activateAccount(int $paymentId): bool {
        $payment = $this->paymentRepository->getPaymentById($paymentId);
        
        if (!$payment || $payment['status'] !== 'completed') {
            return false;
        }

        // Calculate expiry date (1 year from now)
        $expiryDate = date('Y-m-d', strtotime('+1 year'));
        
        if ($payment['payment_type'] === 'registration') {
            if ($payment['payer_type'] === 'person') {
                // Update player's expiry date
                return $this->updateExpiryDate(
                    $payment['dffk_code'],
                    $expiryDate
                );
            } elseif ($payment['payer_type'] === 'club') {
                // Activate team (implementation depends on your team structure)
                return $this->paymentRepository->activateTeam(
                    $payment['payer_id'],
                    $expiryDate
                );
            }
        } elseif ($payment['payment_type'] === 'league') {
            // Register team for league
            return $this->paymentRepository->registerTeamForLeague(
                $payment['payer_id'],
                $expiryDate
            );
        }

        return false;
    }
    
    /**
     * MAIN ENTRY POINT after Mpesa success to allow users in portal..
     */
    /**
     * Post-payment authority handler
     * - Activates memberships
     * - Refreshes session if owner is logged in
     */
    public function handleSuccessfulPayment(array $payment): void {
        // Guard: only completed payments
        if ($payment['status'] !== 'completed') {
            return;
        }
    
        // 1. Compute expiry (1 year default)
        $expiryDate = date('Y-m-d', strtotime('+1 year'));
    
        // 2. Activate based on payer type
        if ($payment['payer_type'] === 'person') {
            $this->paymentRepository->activatePlayer(
                $payment['dffk_code'],
                $expiryDate
            );
        }
    
        if ($payment['payer_type'] === 'club') {
            $this->paymentRepository->activateTeam(
                (int)$payment['payer_id'],
                $expiryDate
            );
        }
    
        if ($payment['payment_type'] === 'league') {
            $this->paymentRepository->registerTeamForLeague(
                (int)$payment['payer_id'],
                $expiryDate
            );
        }
    
        // 3. Refresh active session if applicable
        $this->refreshSessionIfOwner($payment, $expiryDate);
    }

    
    private function refreshSessionIfOwner(array $payment, string $expiry): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
    
        if (!isset($_SESSION['user'])) {
            return;
        }
    
        if (!$this->isCurrentSessionOwner($payment)) {
            return;
        }
    
        // === AUTHORITATIVE FLAGS ===
        $_SESSION['user']['is_active'] = 1;
        $_SESSION['user']['expiry_date'] = $expiry;
    
        // Optional but powerful
        $_SESSION['user']['membership_paid'] = true;
    }


    // Handles Appeal Payment update
    public function updatePaymentCheckoutId($paymentId, $checkoutRequestId, $merchantRequestId, $phoneNumber) {
        try {
            return $this->paymentRepository->updatePayment($paymentId, [
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $merchantRequestId,
                'phone_number' => $phoneNumber
            ]);
        } catch (Exception $e) {
            error_log("Payment update failed: " . $e->getMessage());
            throw new Exception("Failed to update payment: " . $e->getMessage());
        }
    }

    public function createPaymentRecord(array $paymentData): int {
        try {
            return $this->paymentRepository->createPayment($paymentData);
        } catch (Exception $e) {
            error_log("DEBUG PAYER_TYPE: " . json_encode($paymentData['payer_type']));
            error_log("Payment record creation failed: " . $e->getMessage());
            throw new Exception("Failed to create payment record: " . $e->getMessage());
        }
    }

    /**
     * Get all payments with details for admin dashboard
     */
    public function getAllPaymentsWithDetails(array $filters = []): array {
        return $this->paymentRepository->getAllPaymentsWithDetails($filters);
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats(): array {
        return $this->paymentRepository->getPaymentStats();
    }

    /**
     * Approve a pending payment (admin function)
     */
    public function approvePayment(int $paymentId, string $mpesaCode = null): array {
        try {
            $payment = $this->paymentRepository->getPaymentById($paymentId);
            
            if (!$payment) {
                return ['success' => false, 'message' => 'Payment not found'];
            }

            if ($payment['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Payment is not pending approval'];
            }

            $updateData = [
                'status' => 'completed',
                'payment_method' => 'manual_approval'
            ];

            if ($mpesaCode) {
                $updateData['mpesa_receipt'] = $mpesaCode;
            }

            $this->paymentRepository->updatePayment($paymentId, $updateData);

            // Activate account
            if (in_array($payment['payment_type'], ['registration', 'league'])) {
                $this->activateAccount($paymentId);
            }

            return ['success' => true, 'message' => 'Payment approved successfully'];
        } catch (Exception $e) {
            error_log("Payment approval failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Payment approval failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a pending payment (admin function)
     */
    public function rejectPayment(int $paymentId): array {
        try {
            $payment = $this->paymentRepository->getPaymentById($paymentId);
            
            if (!$payment) {
                return ['success' => false, 'message' => 'Payment not found'];
            }

            if ($payment['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Payment is not pending approval'];
            }

            $this->paymentRepository->updatePayment($paymentId, [
                'status' => 'failed',
                'result_desc' => 'Rejected by administrator'
            ]);

            return ['success' => true, 'message' => 'Payment rejected successfully'];
        } catch (Exception $e) {
            error_log("Payment rejection failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Payment rejection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Create a manual payment (admin function)
     */
    public function createManualPayment(array $paymentData): array {
        try {
            // Validate payment data
            if (empty($paymentData['payment_type']) || empty($paymentData['amount'])) {
                return ['success' => false, 'message' => 'Payment type and amount are required'];
            }
            
            // Normalize phone number if provided
            if (!empty($paymentData['phone_number'])) {
                $paymentData['phone_number'] = $this->normalizePhoneNumber($paymentData['phone_number']);
            }

            // Create payment record
            $paymentId = $this->paymentRepository->createPayment([
                'payment_type' => $paymentData['payment_type'],
                'amount' => $paymentData['amount'],
                'phone_number' => $paymentData['phone_number'] ?? '',
                'account_name' => $paymentData['account_name'] ?? '',
                'status' => 'completed',
                'payment_method' => $paymentData['payment_method'] ?? 'manual',
                'mpesa_receipt' => $paymentData['mpesa_receipt'] ?? null,
                'payer_type' => $paymentData['payer_type'] ?? null,
                'payer_id' => $paymentData['payer_id'] ?? null,
                'dffk_code' => $paymentData['dffk_code'] ?? null
            ]);
            

            // Activate account if it's a registration payment
            if (in_array($paymentData['payment_type'], ['registration', 'league'])) {
                $this->activateAccount($paymentId);
            }

            return [
                'success' => true, 
                'message' => 'Manual payment recorded successfully',
                'payment_id' => $paymentId
            ];
        } catch (Exception $e) {
            error_log("Manual payment creation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Manual payment creation failed: ' . $e->getMessage()];
        }
    }

        /**
     * Get detailed payment information
     */
    public function getPaymentDetails(int $paymentId): ?array {
        try {
            $payment = $this->paymentRepository->getPaymentWithDetails($paymentId);
            
            if (!$payment) {
                return null;
            }
            
            // Get payer name based on payer type
            if ($payment['payer_type'] === 'club') {
                $club = $this->teamService->getTeamById($payment['payer_id']);
                $payment['payer_name'] = $club['name'] ?? 'Unknown Club';
                $payment['club_name'] = $payment['payer_name'];
            } elseif ($payment['payer_type'] === 'person') {
                $player = $this->findPlayerByIdentifier('dffk_code', $payment['dffk_code']);
                if ($player) {
                    $payment['payer_name'] = $player['first_name'] . ' ' . $player['last_name'];
                    $payment['person_name'] = $payment['payer_name'];
                }
            }
            
            return $payment;
        } catch (Exception $e) {
            error_log("Error getting payment details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate payment data
     */
    // private function validatePaymentData(array $data): bool {
    //     $required = ['type', 'phone_number'];
        
    //     foreach ($required as $field) {
    //         if (empty($data[$field])) {
    //             return false;
    //         }
    //     }
        
    //     $phone = preg_replace('/\D/', '', $data['phone_number']);
    //     if (substr($phone, 0, 1) === '0') {
    //         $phone = '254' . substr($phone, 1);
    //     }
    //     $data['phone_number'] = $phone;

    //     // Validate phone number format -- this si hash as it reject all but 254 format.
    //     // if (!preg_match('/^254[0-9]{9}$/', $data['phone_number'])) {
    //     //     return false;
    //     // }

    //     return true;
    // }
    
     /**
     * Validate payment data
     */
    private function validatePaymentData(array $data): bool {
        $required = ['type', 'phone_number'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
    
        // Normalize phone number first
        $normalizedPhone = $this->normalizePhoneNumber($data['phone_number']);
        
        // Update the phone number in the data array
        $data['phone_number'] = $normalizedPhone;
        
        // Now validate the normalized phone number
        if (!preg_match('/^254[0-9]{9}$/', $normalizedPhone)) {
            return false;
        }
    
        return true;
    }
    
    /**
     * Normalize phone number to 2547XXXXXXXX format
     */
    private function normalizePhoneNumber(string $phone): string {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Handle different formats
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            // Convert 07XXXXXXXX to 2547XXXXXXXX
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
            // Convert 7XXXXXXXX to 2547XXXXXXXX
            return '254' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            // Already in correct format
            return $phone;
        } elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
            // Convert +2547XXXXXXXX to 2547XXXXXXXX
            return substr($phone, 1);
        }
        
        // Return as-is if we can't normalize (validation will catch it)
        return $phone;
    }

    /**
     * Get payment amount based on type
     */
    private function getPaymentAmount(string $type): int {
        $amounts = [
            'player_registration' => 500, // Player registration
            'team_registration' => 1000, // Team registration
            'league' => 2500, // League fees
        ];

        return $amounts[$type] ?? 0;
    }

    /**
     * Find player by DFFK code or national ID
     */
    public function findPlayerByIdentifier(string $identifierType, string $identifierValue): ?array {
        return $this->paymentRepository->findPlayerByIdentifier($identifierType, $identifierValue);
    }

    /**
     * Check if a player/team registration is active
     */
    public function isRegistrationActive(string $dffkCode): bool {
        return $this->paymentRepository->isRegistrationActive($dffkCode);
    }

    /**
     * Get expiry date for a registration
     */
    public function getExpiryDate(string $dffkCode): ?string {
        return $this->paymentRepository->getExpiryDate($dffkCode);
    }

    /**
     * Get payments by team ID
     */
    public function getPaymentsByTeam($teamId): array {
        return $this->paymentRepository->getPaymentsByTeam($teamId);
    }

    /**
     * Get payment history by team ID
     */
    public function getPaymentHistoryByTeam($teamId): array {
        return $this->paymentRepository->getPaymentHistoryByTeam($teamId);
    }

    /**
     * Check if user has paid for form access
     */
    public function hasUserPaidForForm($userId, $formId) {
        try {
            if (!$userId) return false;
            
            return $this->paymentRepository->hasUserPaidForForm($userId, $formId);
        } catch (Exception $e) {
            error_log("Payment check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process form payment
     */
    public function processFormPayment($user, $formId, $amount) {
        try {
            $paymentData = [
                'type' => 'form_access',
                'amount' => $amount,
                'phone_number' => $_POST['phone_number'] ?? '',
                'account_name' => $user['first_name'] . ' ' . $user['last_name'] ?? 'Form User',
                'payment_method' => $_POST['payment_method'] ?? 'mpesa',
                'form_id' => $formId
            ];

            return $this->initiatePayment($paymentData);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Handle M-Pesa callback for form payments
     */
    public function handleMpesaCallbackForForms(array $callbackData): array {
        try {
            $validatedData = $this->darajaService->validateCallback($callbackData);
            
            // Find payment by checkout request ID
            $payment = $this->paymentRepository->findByCheckoutRequestId($validatedData['CheckoutRequestID']);
            
            if (!$payment) {
                throw new Exception('Payment not found for checkout request: ' . $validatedData['CheckoutRequestID']);
            }

            if ($validatedData['ResultCode'] == 0) {
                // Payment successful
                $mpesaReceipt = $this->darajaService->extractMpesaReceipt($validatedData['CallbackMetadata']);
                
                // Update payment status
                $this->paymentRepository->updatePayment($payment['id'], [
                    'status' => 'completed',
                    'mpesa_receipt' => $mpesaReceipt,
                    'result_code' => $validatedData['ResultCode'],
                    'result_desc' => $validatedData['ResultDesc']
                ]);

                // Activate form access
                $documentService = new DocumentService();
                $documentService->activateFormAccess($payment['id'], $mpesaReceipt);

                return [
                    'success' => true, 
                    'message' => 'Payment processed successfully',
                    'payment_id' => $payment['id'],
                    'access_code' => $payment['reference_id'] // This is our access code
                ];
            } else {
                // Payment failed
                $this->paymentRepository->updatePayment($payment['id'], [
                    'status' => 'failed',
                    'result_code' => $validatedData['ResultCode'],
                    'result_desc' => $validatedData['ResultDesc']
                ]);

                return [
                    'success' => false, 
                    'message' => 'Payment failed: ' . $validatedData['ResultDesc']
                ];
            }
        } catch (Exception $e) {
            error_log("Form payment callback failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Callback processing failed: ' . $e->getMessage()];
        }
    }

}
?>