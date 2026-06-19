<?php
session_start();
require_once __DIR__ . '/../app/config/paths.php';
require_once __DIR__ . '/../app/repositories/PaymentRepository.php';
require_once __DIR__ . '/../app/services/AuthService.php';

header('Content-Type: application/json');

$response = ['status' => 'pending', 'message' => ''];

try {
    if (!isset($_GET['payment_id'])) {
        throw new Exception('Payment ID required');
    }

    $paymentId = (int)$_GET['payment_id'];
    $paymentRepository = new PaymentRepository();
    $payment = $paymentRepository->getPaymentById($paymentId);
    
    if (!$payment) {
        throw new Exception('Payment not found');
    }
    
    $response['status'] = $payment['status'];
    $response['payment'] = $payment;
    
    // If payment is completed, refresh session
    if ($payment['status'] === 'completed' && isset($_SESSION['user'])) {
        $authService = new AuthService();
        $authService->refreshSessionData($_SESSION['user']['id']);
        
        // Also update session directly
        $_SESSION['user']['is_active'] = 1;
        $_SESSION['user']['membership_paid'] = true;
        
        // Get updated expiry date
        if (!empty($payment['dffk_code'])) {
            $expiry = $paymentRepository->getExpiryDate($payment['dffk_code']);
            if ($expiry) {
                $_SESSION['user']['expiry_date'] = $expiry;
            }
        }
        
        $response['session_refreshed'] = true;
        $response['redirect_url'] = '/player/player_dashboard.php'; // Update with actual dashboard URL
    }
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);