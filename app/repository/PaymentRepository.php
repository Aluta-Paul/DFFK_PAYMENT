<?php
require_once __DIR__ . '/../utilities/DatabaseConnection.php';

class PaymentRepository {
    private $db;

    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
    }

    /**
     * Create a new payment record
     */
    // public function createPayment(array $data): int {
    //     $stmt = $this->db->prepare("
    //         INSERT INTO payments 
    //         (payment_type, amount, phone_number, account_name, status, payment_method, 
    //          mpesa_receipt, checkout_request_id, merchant_request_id, payer_type, payer_id, dffk_code) 
    //         VALUES 
    //         (:payment_type, :amount, :phone_number, :account_name, :status, :payment_method, 
    //          :mpesa_receipt, :checkout_request_id, :merchant_request_id, :payer_type, :payer_id, :dffk_code)
    //     ");
        
    //     $stmt->execute([
    //         ':payment_type' => $data['payment_type'],
    //         ':amount' => $data['amount'],
    //         ':phone_number' => $data['phone_number'],
    //         ':account_name' => $data['account_name'] ?? '',
    //         ':status' => $data['status'] ?? 'pending',
    //         ':payment_method' => $data['payment_method'] ?? 'mpesa',
    //         ':mpesa_receipt' => $data['mpesa_receipt'] ?? null,
    //         ':checkout_request_id' => $data['checkout_request_id'] ?? null,
    //         ':merchant_request_id' => $data['merchant_request_id'] ?? null,
    //         ':payer_type' => $data['payer_type'] ?? null,
    //         ':payer_id' => $data['payer_id'] ?? null,
    //         ':dffk_code' => $data['dffk_code'] ?? null
    //     ]);

    //     return $this->db->lastInsertId();
    // }

    // In PaymentRepository.php - Update the createPayment method to handle appeal payments
    public function createPayment(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO payments 
            (payment_type, amount, phone_number, account_name, status, payment_method, 
            mpesa_receipt, checkout_request_id, merchant_request_id, payer_type, payer_id, 
            dffk_code, reference_id) 
            VALUES 
            (:payment_type, :amount, :phone_number, :account_name, :status, :payment_method, 
            :mpesa_receipt, :checkout_request_id, :merchant_request_id, :payer_type, :payer_id, 
            :dffk_code, :reference_id)
        ");
        
        $stmt->execute([
            ':payment_type' => $data['payment_type'],
            ':amount' => $data['amount'],
            ':phone_number' => $data['phone_number'] ?? '',
            ':account_name' => $data['account_name'] ?? '',
            ':status' => $data['status'] ?? 'pending',
            ':payment_method' => $data['payment_method'] ?? 'mpesa',
            ':mpesa_receipt' => $data['mpesa_receipt'] ?? null,
            ':checkout_request_id' => $data['checkout_request_id'] ?? null,
            ':merchant_request_id' => $data['merchant_request_id'] ?? null,
            ':payer_type' => $data['payer_type'] ?? null,
            ':payer_id' => $data['payer_id'] ?? null,
            ':dffk_code' => $data['dffk_code'] ?? null,
            ':reference_id' => $data['reference_id'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update a payment record
     */
    public function updatePayment(int $paymentId, array $data): bool {
        $fields = [];
        $params = [':id' => $paymentId];
        
        // Define valid status values
        $validStatuses = ['pending', 'completed', 'failed', 'cancelled', 'timeout'];
        
        foreach ($data as $key => $value) {
            // Validate status if it's being updated
            if ($key === 'status' && !in_array($value, $validStatuses)) {
                error_log("Invalid status value: {$value}, using 'failed' instead");
                $value = 'failed';
            }
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE payments SET 
            " . implode(', ', $fields) . ",
            updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute($params);
    }

    /**
     * Get payment by ID
     */
    public function getPaymentById(int $paymentId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = :id");
        $stmt->execute([':id' => $paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find payment by checkout request ID
     */
    public function findByCheckoutRequestId(string $checkoutRequestId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE checkout_request_id = :checkout_request_id");
        $stmt->execute([':checkout_request_id' => $checkoutRequestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all payments with details for admin dashboard
     */
    public function getAllPaymentsWithDetails(array $filters = []): array {
        $whereClause = '';
        $params = [];

        $conditions = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = "p.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['method'])) {
            $conditions[] = "p.payment_method = :method";
            $params[':method'] = $filters['method'];
        }

        // if (!empty($filters['region'])) {
        //     $conditions[] = "p.region = :region";
        //     $params[':region'] = $filters['region'];
        // }

        if (!empty($filters['region'])) {
            $conditions[] = "c.region_id = :region";
            $params[':region'] = $filters['region'];
        }


        // if (!empty($filters['search'])) {
        //     $conditions[] = "(p.account_name LIKE :search OR p.dffk_code LIKE :search OR p.mpesa_receipt LIKE :search)";
        //     $params[':search'] = '%' . $filters['search'] . '%';
        // }

        if (!empty($filters['search'])) {
            $conditions[] = "(p.account_name LIKE :search1 OR p.dffk_code LIKE :search2 OR p.mpesa_receipt LIKE :search3)";
            $params[':search1'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }

        if (!empty($conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }

        $sql = "
            SELECT 
                p.*,
                c.name as club_name,
                CONCAT(per.first_name, ' ', per.last_name) as person_name
            FROM payments p
            LEFT JOIN clubs c ON p.payer_type = 'club' AND p.payer_id = c.id
            LEFT JOIN persons per ON p.payer_type = 'person' AND p.payer_id = per.id
            $whereClause
            ORDER BY p.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Get payment statistics
     */
    public function getPaymentStats(): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_collected
            FROM payments
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Activate a player after successful payment
     */
    public function activatePlayer(string $dffkCode, string $expiryDate): bool {
        $stmt = $this->db->prepare("
            UPDATE person_roles 
            SET is_active = 1, 
                registration_date = NOW(),
                expiry_date = :expiry_date,
                updated_at = NOW()
            WHERE dffk_code = :dffk_code
        ");
        
        return $stmt->execute([
            ':dffk_code' => $dffkCode,
            ':expiry_date' => $expiryDate
        ]);
    }

    /**
     * Activate a team after successful payment
     */
    public function activateTeam(int $teamId, string $expiryDate): bool {
        $stmt = $this->db->prepare("
            UPDATE clubs 
            SET is_active = 1, 
                registration_date = NOW(),
                expiry_date = :expiry_date,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':id' => $teamId,
            ':expiry_date' => $expiryDate
        ]);
    }

    /**
     * Register a team for a league
     */
    public function registerTeamForLeague(int $teamId, string $expiryDate): bool {
        // This would depend on your league registration structure
        // For now, we'll just update the team's league registration status
        $stmt = $this->db->prepare("
            UPDATE clubs 
            SET league_registered = 1, 
                league_expiry = :expiry_date,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':id' => $teamId,
            ':expiry_date' => $expiryDate
        ]);
    }

    // Add this method to your PaymentRepository
    public function findPlayerByIdentifier(string $identifierType, string $identifierValue): ?array {
        try {
            if ($identifierType === 'national_id') {
                $stmt = $this->db->prepare("
                    SELECT p.*, pr.dffk_code 
                    FROM persons p
                    LEFT JOIN person_roles pr ON p.id = pr.person_id
                    WHERE p.national_id = :identifier
                ");
            } elseif ($identifierType === 'dffk_code') {
                $stmt = $this->db->prepare("
                    SELECT p.*, pr.dffk_code 
                    FROM persons p
                    JOIN person_roles pr ON p.id = pr.person_id
                    WHERE pr.dffk_code = :identifier
                ");
            } else {
                return null;
            }
            
            $stmt->execute([':identifier' => $identifierValue]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("Find player failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find player by identifier (DFFK code or national ID)
     */
    // public function findPlayerByIdentifier(string $identifierType, string $identifierValue): ?array {
    //     if ($identifierType === 'dffk_code') {
    //         $stmt = $this->db->prepare("
    //             SELECT p.*, pr.dffk_code, pr.expiry_date, pr.is_active
    //             FROM persons p
    //             JOIN person_roles pr ON p.id = pr.person_id
    //             WHERE pr.dffk_code = :identifier
    //         ");
    //     } else {
    //         $stmt = $this->db->prepare("
    //             SELECT p.*, pr.dffk_code, pr.expiry_date, pr.is_active
    //             FROM persons p
    //             JOIN person_roles pr ON p.id = pr.person_id
    //             WHERE p.national_id = :identifier
    //         ");
    //     }
        
    //     $stmt->execute([':identifier' => $identifierValue]);
    //     return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    // }

    /**
     * Check if a registration is active
     */
    public function isRegistrationActive(string $dffkCode): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM person_roles 
            WHERE dffk_code = :dffk_code 
            AND is_active = 1 
            AND expiry_date > NOW()
        ");
        
        $stmt->execute([':dffk_code' => $dffkCode]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get expiry date for a registration
     */
    public function getExpiryDate(string $dffkCode): ?string {
        $stmt = $this->db->prepare("
            SELECT expiry_date 
            FROM person_roles 
            WHERE dffk_code = :dffk_code
        ");
        
        $stmt->execute([':dffk_code' => $dffkCode]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get payment history for a payer
     */
    public function getPayerPaymentHistory(string $payerType, int $payerId): array {
        $stmt = $this->db->prepare("
            SELECT * 
            FROM payments 
            WHERE payer_type = :payer_type 
            AND payer_id = :payer_id
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([
            ':payer_type' => $payerType,
            ':payer_id' => $payerId
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment with detailed information
     */
    public function getPaymentWithDetails(int $paymentId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                c.name as club_name,
                CONCAT(per.first_name, ' ', per.last_name) as person_name
            FROM payments p
            LEFT JOIN clubs c ON p.payer_type = 'club' AND p.payer_id = c.id
            LEFT JOIN persons per ON p.payer_type = 'person' AND p.payer_id = per.id
            WHERE p.id = :id
        ");
        
        $stmt->execute([':id' => $paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Add these methods to your PaymentRepository.php
    /**
     * Get payments by team ID
     */
    public function getPaymentsByTeam($teamId): array {
        $sql = "SELECT p.* 
                FROM payments p 
                WHERE p.payer_id = ? AND p.payer_type = 'club' 
                ORDER BY p.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment history by team ID
     */
    public function getPaymentHistoryByTeam($teamId): array {
        $sql = "SELECT p.* 
                FROM payments p 
                WHERE p.payer_id = ? AND p.payer_type = 'club' 
                ORDER BY p.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user has paid for form access
     */
    public function hasUserPaidForForm($userId, $formId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM payments 
            WHERE payer_id = :user_id 
            AND form_id = :form_id 
            AND status = 'completed'
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':form_id' => $formId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
}
?>