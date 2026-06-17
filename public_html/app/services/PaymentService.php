<?php
// File: app/services/PaymentService.php
// Payment service for business logic

require_once __DIR__ . '/../repositories/PaymentRepository.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/TeamService.php';
require_once __DIR__ . '/../utilities/DatabaseConnection.php';

class PaymentService {
    private $paymentRepo;
    private $db;

    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
        $this->paymentRepo = new PaymentRepository();
    }

    /**
     * Validate DFFK code and get associated user info
     */
    public function validateDffkCode($dffkCode, $paymentType) {
        try {
            // Find person by DFFK code
            $stmt = $this->db->prepare("
                SELECT 
                    p.id as person_id,
                    p.first_name,
                    p.last_name,
                    p.email,
                    p.phone_number,
                    p.national_id,
                    pr.dffk_code,
                    pr.role_id,
                    pr.is_active,
                    pr.expiry_date,
                    pr.club_id,
                    c.name as club_name
                FROM persons p
                JOIN person_roles pr ON p.id = pr.person_id
                LEFT JOIN clubs c ON pr.club_id = c.id
                WHERE pr.dffk_code = :dffk_code
            ");
            
            $stmt->execute([':dffk_code' => $dffkCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Invalid DFFK code. Please check and try again.'
                ];
            }

            // Check if registration is valid
            if ($paymentType === 'registration') {
                // Check if already active and not expired
                if ($result['is_active'] == 1 && strtotime($result['expiry_date']) > time()) {
                    return [
                        'success' => false,
                        'message' => 'This DFFK code is already active. No registration renewal needed.',
                        'data' => [
                            'full_name' => $result['first_name'] . ' ' . $result['last_name'],
                            'expiry_date' => $result['expiry_date'],
                            'is_active' => true
                        ]
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'DFFK code validated successfully',
                'data' => [
                    'person_id' => $result['person_id'],
                    'full_name' => $result['first_name'] . ' ' . $result['last_name'],
                    'email' => $result['email'],
                    'phone_number' => $result['phone_number'],
                    'national_id' => $result['national_id'],
                    'dffk_code' => $result['dffk_code'],
                    'role_id' => $result['role_id'],
                    'is_active' => $result['is_active'],
                    'expiry_date' => $result['expiry_date'],
                    'club_id' => $result['club_id'],
                    'club_name' => $result['club_name']
                ]
            ];

        } catch (Exception $e) {
            error_log("DFFK validation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error validating DFFK code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payment details by ID
     */
    public function getPaymentDetails($paymentId) {
        return $this->paymentRepo->getPaymentWithDetails($paymentId);
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats() {
        return $this->paymentRepo->getPaymentStats();
    }

    /**
     * Get all payments with filters
     */
    public function getAllPayments($filters = []) {
        return $this->paymentRepo->getAllPaymentsWithDetails($filters);
    }

    /**
     * Get payments by team
     */
    public function getTeamPayments($teamId) {
        return $this->paymentRepo->getPaymentsByTeam($teamId);
    }

    /**
     * Get payment history for a user
     */
    public function getUserPaymentHistory($userId) {
        return $this->paymentRepo->getPayerPaymentHistory('person', $userId);
    }

    /**
     * Check if user has paid for specific form access
     */
    public function checkFormAccess($userId, $formId) {
        return $this->paymentRepo->hasUserPaidForForm($userId, $formId);
    }

    /**
     * Generate payment summary report
     */
    public function generateSummaryReport($startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT 
                payment_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as collected,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending
            FROM payments
            WHERE created_at BETWEEN :start_date AND :end_date
            GROUP BY payment_type
        ");
        
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending payments that need attention
     */
    public function getPendingPayments($hours = 24) {
        $stmt = $this->db->prepare("
            SELECT * FROM payments
            WHERE status = 'pending'
            AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([':hours' => $hours]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retry failed payment
     */
    public function retryPayment($paymentId) {
        $payment = $this->paymentRepo->getPaymentById($paymentId);
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        if ($payment['status'] !== 'failed') {
            throw new Exception('Only failed payments can be retried');
        }
        
        // Reset payment status
        $this->paymentRepo->updatePayment($paymentId, [
            'status' => 'pending',
            'failure_reason' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'success' => true,
            'message' => 'Payment queued for retry',
            'payment_id' => $paymentId
        ];
    }

    /**
     * Check if registration is active
     */
    public function isRegistrationActive($dffkCode) {
        return $this->paymentRepo->isRegistrationActive($dffkCode);
    }

    /**
     * Get registration expiry date
     */
    public function getRegistrationExpiry($dffkCode) {
        return $this->paymentRepo->getExpiryDate($dffkCode);
    }

    /**
     * Get payment by checkout request ID
     */
    public function getPaymentByCheckoutRequest($checkoutRequestId) {
        return $this->paymentRepo->findByCheckoutRequestId($checkoutRequestId);
    }
}