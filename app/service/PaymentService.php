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
    private $db;

    public function __construct() {
        $this->paymentRepository = new PaymentRepository();
        $this->darajaService = new DarajaService();
        $this->teamService = new TeamService();
        $this->authService = new AuthService();
        $this->db = DatabaseConnection::getInstance();
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
            
            // Normalize phone number BEFORE using it
            $paymentData['phone_number'] = $this->normalizePhoneNumber($paymentData['phone_number']);

            // Decide how to handle the amount
            if ($paymentData['type'] === 'donation' || $paymentData['type'] === 'form_filling') {
                $amount = $paymentData['amount'];
            } else {
                $amount = $this->getPaymentAmount($paymentData['type']);
            }

            // Map the incoming payment types to database enum values
            $paymentTypeMap = [
                'player' => 'player',
                'team_registration' => 'team',
                'league' => 'league',
                'donation' => 'donation',
                'form_filling' => 'form_access',
                'membership_renewal' => 'registration'
            ];

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
                'dffk_code' => $paymentData['dffk_code'] ?? null,
                'reference_id' => $paymentData['reference_id'] ?? null
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
                if (in_array($paymentData['type'], ['registration', 'league', 'membership_renewal'])) {
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
     */
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

    /**
     * MAIN ENTRY POINT after Mpesa success to allow users in portal.
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
            // Activate player by DFFK code
            if (!empty($payment['dffk_code'])) {
                $this->paymentRepository->activatePlayer(
                    $payment['dffk_code'],
                    $expiryDate
                );
            }
            
            // Also update the person_roles table directly
            $this->updatePersonRolesExpiry($payment['dffk_code'], $expiryDate);
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

    /**
     * Update person_roles expiry date directly
     */
    private function updatePersonRolesExpiry(string $dffkCode, string $expiryDate): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE person_roles 
                SET is_active = 1,
                    expiry_date = :expiry_date,
                    updated_at = NOW()
                WHERE dffk_code = :dffk_code
            ");
            
            return $stmt->execute([
                ':expiry_date' => $expiryDate,
                ':dffk_code' => $dffkCode
            ]);
        } catch (Exception $e) {
            error_log("Update person_roles expiry failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh session if the current user is the payer
     */
    private function refreshSessionIfOwner(array $payment, string $expiry): void {
        // Check if session is active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
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
        $_SESSION['user']['membership_paid'] = true;
        
        // Also refresh the user data from database to get all updated fields
        $this->refreshSessionUserData($_SESSION['user']['id']);
    }

    /**
     * Check if current session user is the owner of this payment
     */
    private function isCurrentSessionOwner(array $payment): bool {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        $sessionUser = $_SESSION['user'];

        // If payment has DFFK code, check against session
        if (!empty($payment['dffk_code']) && isset($sessionUser['dffk_code'])) {
            return $payment['dffk_code'] === $sessionUser['dffk_code'];
        }

        // If payment has payer_id, check against session
        if (!empty($payment['payer_id']) && isset($sessionUser['id'])) {
            return (int)$payment['payer_id'] === (int)$sessionUser['id'];
        }

        return false;
    }

    /**
     * Refresh session user data from database
     */
    private function refreshSessionUserData(int $userId): void {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id, p.username, p.email, p.first_name, p.last_name, p.profile_picture,
                    pr.role_id, pr.dffk_code, pr.club_id, pr.is_active, pr.expiry_date
                FROM persons p
                JOIN person_roles pr ON p.id = pr.person_id
                WHERE p.id = :id
            ");
            
            $stmt->execute([':id' => $userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData) {
                $_SESSION['user'] = [
                    'id' => $userData['id'],
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'profile_picture' => $userData['profile_picture'],
                    'role_id' => $userData['role_id'],
                    'role_name' => $this->getRoleName($userData['role_id']),
                    'dffk_code' => $userData['dffk_code'],
                    'club_id' => $userData['club_id'],
                    'is_active' => (int)$userData['is_active'],
                    'expiry_date' => $userData['expiry_date']
                ];
            }
        } catch (Exception $e) {
            error_log("Session refresh failed: " . $e->getMessage());
        }
    }

    /**
     * Get role name by role ID
     */
    private function getRoleName(int $roleId): string {
        try {
            $stmt = $this->db->prepare("SELECT name FROM roles WHERE id = :id");
            $stmt->execute([':id' => $roleId]);
            return $stmt->fetchColumn() ?: 'unknown';
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Activate account after successful payment
     */
    private function activateAccount(int $paymentId): bool {
        $payment = $this->paymentRepository->getPaymentById($paymentId);
        
        if (!$payment || $payment['status'] !== 'completed') {
            return false;
        }

        // Calculate expiry date (1 year from now)
        $expiryDate = date('Y-m-d', strtotime('+1 year'));
        
        if ($payment['payment_type'] === 'registration' || $payment['payment_type'] === 'membership_renewal') {
            if ($payment['payer_type'] === 'person') {
                // Update player's expiry date
                return $this->updateExpiryDate(
                    $payment['dffk_code'],
                    $expiryDate
                );
            } elseif ($payment['payer_type'] === 'club') {
                // Activate team
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
                SET expiry_date = :expiry_date, 
                    is_active = 1,
                    updated_at = NOW()
                WHERE dffk_code = :dffk_code
            ");
            
            $result = $stmt->execute([
                ':expiry_date' => $expiryDate,
                ':dffk_code' => $dffkCode
            ]);

            // Also refresh session if user is logged in
            if ($result && isset($_SESSION['user']) && $_SESSION['user']['dffk_code'] === $dffkCode) {
                $_SESSION['user']['is_active'] = 1;
                $_SESSION['user']['expiry_date'] = $expiryDate;
                $_SESSION['user']['membership_paid'] = true;
            }

            return $result;
        } catch (Exception $e) {
            error_log("Expiry date update failed: " . $e->getMessage());
            return false;
        }
    }

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
            'player_registration' => 500,
            'team_registration' => 1000,
            'league' => 2500,
            'membership_renewal' => 500,
        ];

        return $amounts[$type] ?? 0;
    }

    /**
     * Validate DFFK code
     */
    public function validateDffkCode(string $dffkCode, string $paymentType): array {
        try {
            if ($paymentType === 'player_registration' || $paymentType === 'membership_renewal') {
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
     * Find player by identifier
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
     * Get all payments with details
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
            if (in_array($payment['payment_type'], ['registration', 'league', 'membership_renewal'])) {
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
     * Update payment with checkout ID
     */
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

    /**
     * Create payment record
     */
    public function createPaymentRecord(array $paymentData): int {
        try {
            return $this->paymentRepository->createPayment($paymentData);
        } catch (Exception $e) {
            error_log("Payment record creation failed: " . $e->getMessage());
            throw new Exception("Failed to create payment record: " . $e->getMessage());
        }
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails(int $paymentId): ?array {
        return $this->paymentRepository->getPaymentWithDetails($paymentId);
    }

    /**
     * Get payments by team
     */
    public function getPaymentsByTeam($teamId): array {
        return $this->paymentRepository->getPaymentsByTeam($teamId);
    }

    /**
     * Get payment history by team
     */
    public function getPaymentHistoryByTeam($teamId): array {
        return $this->paymentRepository->getPaymentHistoryByTeam($teamId);
    }

    /**
     * Check if user has paid for form
     */
    public function hasUserPaidForForm($userId, $formId) {
        return $this->paymentRepository->hasUserPaidForForm($userId, $formId);
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
     * Handle M-Pesa callback for forms
     */
    public function handleMpesaCallbackForForms(array $callbackData): array {
        try {
            $validatedData = $this->darajaService->validateCallback($callbackData);
            
            $payment = $this->paymentRepository->findByCheckoutRequestId($validatedData['CheckoutRequestID']);
            
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if ($validatedData['ResultCode'] == 0) {
                $mpesaReceipt = $this->darajaService->extractMpesaReceipt($validatedData['CallbackMetadata']);
                
                $this->paymentRepository->updatePayment($payment['id'], [
                    'status' => 'completed',
                    'mpesa_receipt' => $mpesaReceipt,
                    'result_code' => $validatedData['ResultCode'],
                    'result_desc' => $validatedData['ResultDesc']
                ]);

                return [
                    'success' => true, 
                    'message' => 'Payment processed successfully',
                    'payment_id' => $payment['id']
                ];
            } else {
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
            return ['success' => false, 'message' => 'Callback processing failed'];
        }
    }
}