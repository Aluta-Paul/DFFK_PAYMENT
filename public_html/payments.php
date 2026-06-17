<?php
// Starts a session so it can track payment attempts.
session_start();

// Initialize variables
$paymentSuccess = false;
$errorMessage = '';
$checkoutRequestId = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Include payment handler
        require_once __DIR__ . '/../app/services/PaymentService.php';

        // Determine payment type and prepare data
        $paymentType = $_POST['payment_type'];
        $paymentData = [
            'type' => $paymentType,
            'payment_type' => null, 
            'phone_number' => $_POST['phone_number'],
            'account_name' => $_POST['account_name'] ?? '',
            'payment_method' => 'mpesa' 
        ];

        // Handle different payment types
        if ($_POST['payment_type'] === 'player_registration') {
            $paymentData['payment_type'] = 'player';
            $paymentData['payer_type']   = 'person';
            
            // dffk_code or national_id handling
            if ($_POST['player_id_type'] === 'dffk_id') {
                $paymentData['dffk_code'] = $_POST['dffk_id_numbers'] . '/' . $_POST['dffk_id_year'];
            } else {
                $paymentData['dffk_code'] = $_POST['national_id'];
            }
        } elseif ($paymentType === 'team_registration' || $paymentType === 'league') {
            // Team registration or league fee
            $paymentData['payment_type'] = $paymentType;
            $paymentData['payer_type'] = 'club';
            $paymentData['payer_id'] = $_POST['team_id'];
            $year = date('Y');
            $paymentData['dffk_code'] = "DFFK-TM-{$_POST['team_id']}-{$year}";
        } elseif ($paymentType === 'donation') {
            // Donation - no specific payer type
            $paymentData['payment_type'] = 'donation';
            $paymentData['amount'] = $_POST['amount'];
            // Use donor name if provided, otherwise use account name
            $paymentData['account_name'] = !empty($_POST['donor_name']) ? $_POST['donor_name'] : ($_POST['account_name'] ?? 'Anonymous');
        }

        // Initiate payment
        $paymentService = new PaymentService();
        $result = $paymentService->initiatePayment($paymentData);
        
        if ($result['success']) {
            $paymentSuccess = true;
            $_SESSION['checkout_request_id'] = $result['checkout_request_id'];
            $_SESSION['payment_id'] = $result['payment_id'];
            $checkoutRequestId = $result['checkout_request_id'];
        } else {
            $errorMessage = $result['message'];
        }
    } catch (Exception $e) {
        $errorMessage = 'Payment initiation failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="icon" type="image/png" sizes="32x32" href="assets/uploads/logo/dffk_favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/uploads/logo/dffk_favicon-16x16.png">
    <title>Deaf Football Federation of Kenya - Payments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles here */
        :root {
            --primary: #2e7d32;
            --secondary: #ff9800;
            --accent: #2196f3;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-green: #e8f5e9;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: #f5f5f5;
            background-image: linear-gradient(rgba(46, 125, 50, 0.05), rgba(46, 125, 50, 0.05)), 
                              url('https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-4.0.3');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            padding: 30px 20px;
        }

        .auth-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 900px;
            overflow: hidden;
            display: flex;
            min-height: 600px;
        }

        .auth-image {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('https://images.unsplash.com/photo-1575361204480-aadea25e6e68?ixlib=rb-4.0.3');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            color: white;
            display: none;
        }

        .auth-image h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .auth-image p {
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .auth-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .form-header p {
            color: var(--gray);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            font-family: 'Open Sans', sans-serif;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .payment-option {
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-option:hover {
            border-color: var(--primary);
            background: var(--light-green);
        }

        .payment-option.selected {
            border-color: var(--primary);
            background: var(--light-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
        }

        .payment-option i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .payment-option .amount {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 10px;
            color: var(--primary);
        }

        .donation-amounts {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .donation-amount {
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .donation-amount:hover {
            border-color: var(--primary);
            background: var(--light-green);
        }

        .donation-amount.selected {
            border-color: var(--primary);
            background: var(--light-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
        }

        .custom-amount {
            margin-top: 15px;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: var(--transition);
            display: inline-block;
            cursor: pointer;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            width: 100%;
            margin-top: 10px;
            position: relative;
        }

        .btn-primary:disabled {
            background-color: var(--gray);
            cursor: not-allowed;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: #1b5e20;
            transform: translateY(-3px);
        }

        .btn-secondary {
            background-color: var(--accent);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #0b7dda;
            transform: translateY(-3px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--light-green);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .form-footer a:hover {
            color: #1b5e20;
            text-decoration: underline;
        }

        .mpesa-logo {
            text-align: center;
            margin: 20px 0;
        }

        .mpesa-logo img {
            max-width: 150px;
            height: auto;
        }

        .payment-summary {
            background: var(--light-green);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
        }

        .payment-summary h4 {
            margin-bottom: 15px;
            color: var(--primary);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid var(--primary);
            font-weight: bold;
            font-size: 1.2rem;
        }

        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* New styles for ID selection */
        .id-type-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .id-type-option {
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .id-type-option:hover {
            border-color: var(--primary);
            background: var(--light-green);
        }

        .id-type-option.selected {
            border-color: var(--primary);
            background: var(--light-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
        }

        .dffk-id-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dffk-id-input span {
            white-space: nowrap;
        }

        .dffk-id-input input {
            text-align: center;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (min-width: 992px) {
            .auth-image {
                display: flex;
            }
        }

        @media (max-width: 576px) {
            .payment-options {
                grid-template-columns: 1fr;
            }
            
            .donation-amounts {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .auth-form {
                padding: 20px;
            }
            
            .id-type-options {
                grid-template-columns: 1fr;
            }
        }

        .validation-status {
              margin-top: 8px;
              padding: 5px 10px;
              border-radius: 4px;
              font-size: 0.9rem;
              font-weight: 500;
          }

          .validation-status.valid {
              background-color: #e8f5e9;
              color: #2e7d32;
              border: 1px solid #a5d6a7;
          }

          .validation-status.invalid {
              background-color: #ffebee;
              color: #c62828;
              border: 1px solid #ef9a9a;
          }

          .validation-status.validating {
              background-color: #fff3e0;
              color: #ef6c00;
              border: 1px solid #ffcc80;
          }

          .validation-status .loading {
              display: inline-block;
              width: 12px;
              height: 12px;
              border: 2px solid rgba(0,0,0,0.2);
              border-top-color: currentColor;
              border-radius: 50%;
              animation: spin 1s linear infinite;
              vertical-align: middle;
              margin-left: 5px;
          }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-image">
                <h2>Support Football Development</h2>
                <p>Your payment helps us maintain facilities, organize tournaments, and develop talent across the region.</p>
                <div class="features">
                    <p><i class="fas fa-check-circle"></i> Secure M-Pesa payments</p>
                    <p><i class="fas fa-check-circle"></i> Instant payment confirmation</p>
                    <p><i class="fas fa-check-circle"></i> Support your favorite teams</p>
                    <p><i class="fas fa-check-circle"></i> Contribute to football development</p>
                </div>
            </div>
            
            <div class="auth-form">
                <div class="form-header">
                    <h2>Make a Payment</h2>
                    <p>Select payment type and complete your transaction</p>
                </div>
                
                <?php if ($paymentSuccess): ?>
                    <div class="alert alert-success">
                        <p>Payment initiated successfully! Please check your phone to complete the M-Pesa transaction.</p>
                        <p>Reference ID: <?php echo htmlspecialchars($checkoutRequestId); ?></p>
                    </div>
                    <div class="form-footer">
                        <a href="payments.php">Make Another Payment</a>
                        <a href="../index.php/">Go to Dashboard</a>
                    </div>
                <?php elseif (!empty($errorMessage)): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!$paymentSuccess): ?>
                <form id="paymentForm" method="POST" action="payments.php">
                    <input type="hidden" name="amount" id="form-amount" value="">
                    <input type="hidden" name="account_name" id="form-account-name" value="">
                    <input type="hidden" name="player_id_type" id="form-player-id-type" value="">
                    <input type="hidden" name="team_id" id="form-team-id" value="">
                    <input type="hidden" name="donor_name" id="form-donor-name" value="">
                    
                    <div class="form-group">
                        <label for="payment-type">Payment Type</label>
                        <div class="select-wrapper">
                            <select id="payment-type" name="payment_type" class="form-control" required>
                                <option value="">Select payment type</option>
                                <option value="player_registration" <?php echo (isset($_POST['payment_type']) && $_POST['payment_type'] == 'player_registration') ? 'selected' : ''; ?>>Player Registration (500 KSH)</option>
                                <option value="team_registration" <?php echo (isset($_POST['payment_type']) && $_POST['payment_type'] == 'team_registration') ? 'selected' : ''; ?>>Team Registration (1000 KSH)</option>
                                <option value="league" <?php echo (isset($_POST['payment_type']) && $_POST['payment_type'] == 'league') ? 'selected' : ''; ?>>League Fee (2500 KSH)</option>
                                <option value="donation" <?php echo (isset($_POST['payment_type']) && $_POST['payment_type'] == 'donation') ? 'selected' : ''; ?>>Donation</option>
                            </select>
                        </div>
                    </div>

                    <!-- Player ID Type Selection (initially hidden) -->
                    <div class="form-group" id="player-id-type-group" style="display: none;">
                        <label>Identification Type</label>
                        <div class="id-type-options">
                            <div class="id-type-option" data-type="national_id">
                                <div>National ID</div>
                            </div>
                            <div class="id-type-option" data-type="dffk_id">
                                <div>DFFK ID</div>
                            </div>
                        </div>
                    </div>

                    <!-- National ID Input (initially hidden) -->
                    <div class="form-group" id="national-id-group" style="display: none;">
                        <label for="national-id">National ID Number</label>
                        <input type="text" id="national-id" name="national_id" class="form-control" placeholder="Enter your National ID number">
                    </div>

                    <!-- DFFK ID Input (initially hidden) -->
                    <div class="form-group" id="dffk-id-group" style="display: none;">
                        <label for="dffk-id">DFFK ID</label>
                        <div class="dffk-id-input">
                            <span>DFFK-PL-</span>
                            <input type="text" id="dffk-id-numbers" name="dffk_id_numbers" class="form-control" placeholder="12345" maxlength="5" style="width: 100px;">
                            <span>-</span>
                            <input type="text" id="dffk-id-year" name="dffk_id_year" class="form-control" placeholder="2024" maxlength="4" style="width: 100px;">
                        </div>
                    </div>

                    <!-- Region and Team Selection (initially hidden) -->
                    <div class="form-group" id="region-group" style="display: none;">
                        <label for="region">Region</label>
                        <select id="region" class="form-control">
                            <option value="">Select Region</option>
                            <?php
                            // Fetch regions from database
                            require_once __DIR__ . '/../app/services/RegionService.php';
                            $regionService = new RegionService();
                            $regions = $regionService->getAllRegions();
                            
                            foreach ($regions as $region) {
                                echo '<option value="' . $region['id'] . '">' . $region['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group" id="team-group" style="display: none;">
                        <label for="team">Team</label>
                        <select id="team" class="form-control" disabled>
                            <option value="">First select a region</option>
                        </select>
                    </div>
                    
                    <!-- Player/Team Info (initially hidden) -->
                    <div id="player-team-info" style="display: none;">
                        <div class="form-group">
                            <label for="full-name">Full Name</label>
                            <input type="text" name="account_name" id="full-name" class="form-control" placeholder="Enter your full name" value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone_number" class="form-control" placeholder="Enter your phone number" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Donation Options (initially hidden) -->
                    <div id="donation-options" style="display: none;">
                        <div class="form-group">
                            <label>Select Donation Amount</label>
                            <div class="donation-amounts">
                                <div class="donation-amount" data-amount="200">
                                    <div>Ksh 200</div>
                                </div>
                                <div class="donation-amount" data-amount="500">
                                    <div>Ksh 500</div>
                                </div>
                                <div class="donation-amount" data-amount="1000">
                                    <div>Ksh 1000</div>
                                </div>
                                <div class="donation-amount" data-amount="custom">
                                    <div>Custom Amount</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group custom-amount" style="display: none;">
                            <label for="custom-amount">Enter Amount (Ksh)</label>
                            <input type="number" id="custom-amount" class="form-control" placeholder="Enter amount" min="10" value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Payment Summary (initially hidden) -->
                    <div id="payment-summary" class="payment-summary" style="display: none;">
                        <h4>Payment Summary</h4>
                        <div class="summary-item">
                            <span>Payment Type:</span>
                            <span id="summary-type"></span>
                        </div>
                        <div class="summary-item">
                            <span>Amount:</span>
                            <span id="summary-amount"></span>
                        </div>
                        <div class="summary-total">
                            <span>Total:</span>
                            <span id="summary-total"></span>
                        </div>
                    </div>
                    
                    <!-- M-Pesa Logo -->
                    <div class="mpesa-logo">
                        <img src="assets/uploads/logo/mpesa_logo.jpeg" alt="M-Pesa Logo">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="pay-now">Pay Now</button>
                    </div>
                </form>
                
                <div class="form-footer">
                    <a href="login.php">Back to Registration</a>
                    <a href="#">Need Help?</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const paymentType = document.getElementById('payment-type');
            const playerIdTypeGroup = document.getElementById('player-id-type-group');
            const nationalIdGroup = document.getElementById('national-id-group');
            const dffkIdGroup = document.getElementById('dffk-id-group');
            const regionGroup = document.getElementById('region-group');
            const teamGroup = document.getElementById('team-group');
            const teamSelect = document.getElementById('team');
            const playerTeamInfo = document.getElementById('player-team-info');
            const donationOptions = document.getElementById('donation-options');
            const donationAmounts = document.querySelectorAll('.donation-amount');
            const customAmountField = document.querySelector('.custom-amount');
            const customAmountInput = document.getElementById('custom-amount');
            const paymentSummary = document.getElementById('payment-summary');
            const summaryType = document.getElementById('summary-type');
            const summaryAmount = document.getElementById('summary-amount');
            const summaryTotal = document.getElementById('summary-total');
            const mpesaNumber = document.getElementById('phone');
            const payNowBtn = document.getElementById('pay-now');
            const paymentForm = document.getElementById('paymentForm');
            const formAmount = document.getElementById('form-amount');
            const formAccountName = document.getElementById('form-account-name');
            const formPlayerIdType = document.getElementById('form-player-id-type');
            const formTeamId = document.getElementById('form-team-id');
            const formDonorName = document.getElementById('form-donor-name');
            const idTypeOptions = document.querySelectorAll('.id-type-option');
            const regionSelect = document.getElementById('region');
            
            // Validation variables
            let validationTimeout = null;
            let currentValidation = null;

            // Set initial state based on previous selection
            if (paymentType.value) {
                paymentType.dispatchEvent(new Event('change'));
                
                if (paymentType.value === 'donation' && <?php echo isset($_POST['amount']) ? $_POST['amount'] : 'null'; ?>) {
                    const amount = <?php echo isset($_POST['amount']) ? $_POST['amount'] : 'null'; ?>;
                    setTimeout(() => {
                        if (amount == 200 || amount == 500 || amount == 1000) {
                            document.querySelector(`.donation-amount[data-amount="${amount}"]`).click();
                        } else {
                            document.querySelector('.donation-amount[data-amount="custom"]').click();
                            customAmountInput.value = amount;
                            updatePaymentSummary();
                        }
                    }, 100);
                }
            }
            
            // Payment type change handler
            paymentType.addEventListener('change', function() {
                const type = this.value;
                
                // Reset display
                playerIdTypeGroup.style.display = 'none';
                nationalIdGroup.style.display = 'none';
                dffkIdGroup.style.display = 'none';
                regionGroup.style.display = 'none';
                teamGroup.style.display = 'none';
                playerTeamInfo.style.display = 'none';
                donationOptions.style.display = 'none';
                paymentSummary.style.display = 'none';
                
                // Clear any validation messages
                clearValidationMessages();
                
                // Show relevant sections based on payment type
                if (type === 'player_registration') {
                    playerIdTypeGroup.style.display = 'block';
                    playerTeamInfo.style.display = 'block';
                } else if (type === 'team_registration' || type === 'league') {
                    regionGroup.style.display = 'block';
                    teamGroup.style.display = 'block';
                    playerTeamInfo.style.display = 'block';
                } else if (type === 'donation') {
                    donationOptions.style.display = 'block';
                    playerTeamInfo.style.display = 'block';
                }
            });
            
            // ID type selection
            idTypeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    idTypeOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    const idType = this.getAttribute('data-type');
                    formPlayerIdType.value = idType;
                    
                    // Show relevant input field
                    if (idType === 'national_id') {
                        nationalIdGroup.style.display = 'block';
                        dffkIdGroup.style.display = 'none';
                    } else if (idType === 'dffk_id') {
                        nationalIdGroup.style.display = 'none';
                        dffkIdGroup.style.display = 'block';
                    }
                    
                    updatePaymentSummary();
                });
            });
            
            // Region change handler
            regionSelect.addEventListener('change', function() {
                const regionId = this.value;
                
                if (regionId) {
                    // Show loading state
                    teamSelect.innerHTML = '<option value="">Loading teams...</option>';
                    teamSelect.disabled = true;
                    teamGroup.style.display = 'block';

                    // Fetch teams for the selected region
                    fetch(`dashboard/api/get_teams_by_region.php?region_id=${encodeURIComponent(regionId)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(teams => {
                            if (teams.error) {
                                teamSelect.innerHTML = `<option value="">Error: ${teams.error}</option>`;
                                return;
                            }
                            
                            teamSelect.innerHTML = '<option value="">Select Team</option>';
                            teams.forEach(team => {
                                const option = document.createElement('option');
                                option.value = team.id;
                                option.textContent = team.name;
                                teamSelect.appendChild(option);
                            });
                            teamSelect.disabled = false;
                        })
                        .catch(error => {
                            console.error('Error fetching teams:', error);
                            teamSelect.innerHTML = '<option value="">Error loading teams</option>';
                        });
                } else {
                    teamSelect.innerHTML = '<option value="">First select a region</option>';
                    teamSelect.disabled = true;
                    teamGroup.style.display = 'none';
                }
            });
            
            // Team selection handler
            teamSelect.addEventListener('change', function() {
                formTeamId.value = this.value;
                updatePaymentSummary();
            });
            
            // National ID input handler
            document.getElementById('national-id').addEventListener('input', function() {
                updatePaymentSummary();
                setupRealTimeValidation();
            });
            
            // DFFK ID input handler
            document.getElementById('dffk-id-numbers').addEventListener('input', function() {
                updateDffkId();
                setupRealTimeValidation();
            });
            
            document.getElementById('dffk-id-year').addEventListener('input', function() {
                updateDffkId();
                setupRealTimeValidation();
            });
            
            function updateDffkId() {
                updatePaymentSummary();
            }
            
            // Donation amount selection
            donationAmounts.forEach(amount => {
                amount.addEventListener('click', function() {
                    // Remove selected class from all options
                    donationAmounts.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    const selectedAmount = this.getAttribute('data-amount');
                    
                    // Show custom amount field if custom is selected
                    if (selectedAmount === 'custom') {
                        customAmountField.style.display = 'block';
                        customAmountInput.focus();
                    } else {
                        customAmountField.style.display = 'none';
                        updatePaymentSummary();
                    }
                });
            });
            
            // Custom amount input handler
            customAmountInput.addEventListener('input', function() {
                updatePaymentSummary();
            });

            // Real-time validation functions
            function setupRealTimeValidation() {
                const nationalIdInput = document.getElementById('national-id');
                const dffkNumbersInput = document.getElementById('dffk-id-numbers');
                const dffkYearInput = document.getElementById('dffk-id-year');
                
                // Clear previous timeout
                clearTimeout(validationTimeout);
                
                // Set up validation with delay (only for player registration)
                validationTimeout = setTimeout(() => {
                    const type = paymentType.value;
                    const idType = formPlayerIdType.value;
                    
                    if (type === 'player_registration') {
                        if (idType === 'national_id' && nationalIdInput.value.length >= 5) {
                            validateDffkCode('NATIONAL-' + nationalIdInput.value, 'player_registration', nationalIdInput);
                        } else if (idType === 'dffk_id' && dffkNumbersInput.value.length >= 3 && dffkYearInput.value.length === 4) {
                            validateDffkCode(`DFFK-PL-${dffkNumbersInput.value}-${dffkYearInput.value}`, 'player_registration', dffkNumbersInput);
                        }
                    }
                }, 800);
            }

            async function validateDffkCode(dffkCode, paymentType, inputElement) {
                try {
                    // Cancel previous validation if still running
                    if (currentValidation) {
                        currentValidation.abort();
                    }
                    
                    currentValidation = new AbortController();
                    
                    // Show loading state
                    let statusElement = inputElement.parentNode.querySelector('.validation-status');
                    if (!statusElement) {
                        statusElement = document.createElement('div');
                        statusElement.className = 'validation-status';
                        inputElement.parentNode.appendChild(statusElement);
                    }
                    
                    statusElement.innerHTML = 'Validating... <span class="loading" style="width: 12px; height: 12px;"></span>';
                    statusElement.className = 'validation-status validating';
                    
                    const response = await fetch('dashboard/api/validate_dffk.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            dffk_code: dffkCode,
                            payment_type: paymentType
                        }),
                        signal: currentValidation.signal
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        statusElement.innerHTML = '✓ Valid code';
                        statusElement.className = 'validation-status valid';
                        
                        // Auto-populate the name field if it's empty
                        const nameField = document.getElementById('full-name');
                        if (nameField && result.player_name && !nameField.value) {
                            nameField.value = result.player_name;
                            formAccountName.value = result.player_name;
                            updatePaymentSummary();
                        }
                    } else {
                        statusElement.innerHTML = '✗ ' + result.message;
                        statusElement.className = 'validation-status invalid';
                    }
                    
                    currentValidation = null;
                    return result;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        console.log('Validation aborted');
                        return;
                    }
                    
                    console.error('Validation error:', error);
                    const statusElement = inputElement.parentNode.querySelector('.validation-status');
                    if (statusElement) {
                        statusElement.innerHTML = '✗ Validation failed. Please try again.';
                        statusElement.className = 'validation-status invalid';
                    }
                    return { success: false, message: 'Validation failed' };
                }
            }

            function clearValidationMessages() {
                document.querySelectorAll('.validation-status').forEach(el => el.remove());
            }
            
            function cleanPhoneNumber(number) {
                // Remove spaces, dashes, parentheses, etc.
                let cleaned = number.replace(/\D/g, '');
            
                // Handle leading 0 -> convert to 254
                if (cleaned.startsWith('0') && cleaned.length === 10) {
                    cleaned = '254' + cleaned.substring(1);
                }
                
                // Already in 254 format, check length
                if (cleaned.startsWith('254') && cleaned.length === 12) {
                    return cleaned;
                }
            
                // If not valid, return null
                return null;
            }

            // Update the form submission handler
            paymentForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Check validation status only for player registration
                if (paymentType.value === 'player_registration') {
                    const invalidValidations = document.querySelectorAll('.validation-status.invalid');
                    if (invalidValidations.length > 0) {
                        alert('Please fix validation errors before proceeding with payment.');
                        return;
                    }
                }
                
                // Check if phone number is valid
                // if (mpesaNumber.value.trim() === '' || mpesaNumber.value.trim().length < 10) {
                //     alert('Please enter a valid phone number');
                //     mpesaNumber.focus();
                //     return;
                // }
                
                // Clean and validate phone number
                const cleanedPhone = cleanPhoneNumber(mpesaNumber.value.trim());
                if (!cleanedPhone) {
                    alert('Please enter a valid Kenyan phone number starting with 07 or 254');
                    mpesaNumber.focus();
                    return;
                }
                
                // Replace the phone input value with cleaned number
                mpesaNumber.value = cleanedPhone;

                const type = paymentType.value;
                
                if (type === 'player_registration') {
                    const idType = formPlayerIdType.value;
                    
                    if (!idType) {
                        alert('Please select an identification type');
                        return;
                    }

                    let dffkCode = '';
                    
                    if (idType === 'national_id') {
                        const nationalId = document.getElementById('national-id').value;
                        if (!nationalId) {
                            alert('Please enter your National ID number');
                            document.getElementById('national-id').focus();
                            return;
                        }
                        dffkCode = 'NATIONAL-' + nationalId;
                    } else if (idType === 'dffk_id') {
                        const numbers = document.getElementById('dffk-id-numbers').value;
                        const year = document.getElementById('dffk-id-year').value;
                        
                        if (!numbers || !year) {
                            alert('Please complete your DFFK ID');
                            return;
                        }
                        dffkCode = `DFFK-PL-${numbers}-${year}`;
                    }

                    // Final validation before submission
                    payNowBtn.disabled = true;
                    payNowBtn.innerHTML = 'Validating <span class="loading"></span>';

                    const validationResult = await validateDffkCode(dffkCode, 'player_registration', document.getElementById('national-id'));
                    
                    if (!validationResult.success) {
                        payNowBtn.disabled = false;
                        payNowBtn.innerHTML = 'Pay Now';
                        alert(validationResult.message);
                        return;
                    }

                } else if (type === 'team_registration' || type === 'league') {
                    if (!formTeamId.value) {
                        alert('Please select a team');
                        return;
                    }
                } else if (type === 'donation') {
                    if (formAmount.value <= 0) {
                        alert('Please select or enter a valid donation amount');
                        return;
                    }
                }

                // Show loading state and submit the form
                payNowBtn.disabled = true;
                payNowBtn.innerHTML = 'Processing <span class="loading"></span>';
                
                // Submit the form programmatically
                this.submit();
            });
            
            // Update payment summary function
            function updatePaymentSummary() {
                const type = paymentType.value;
                let amount = 0;
                let typeText = '';
                
                if (type === 'player_registration') {
                    amount = 500;
                    typeText = 'Player Registration';
                    formAccountName.value = document.getElementById('full-name').value;
                    formDonorName.value = document.getElementById('full-name').value;
                } else if (type === 'team_registration') {
                    amount = 1000;
                    typeText = 'Team Registration';
                    formAccountName.value = document.getElementById('full-name').value;
                    formDonorName.value = document.getElementById('full-name').value;
                } else if (type === 'league') {
                    amount = 2500;
                    typeText = 'League Fee';
                    formAccountName.value = document.getElementById('full-name').value;
                    formDonorName.value = document.getElementById('full-name').value;
                } else if (type === 'donation') {
                    const selectedDonation = document.querySelector('.donation-amount.selected');
                    if (selectedDonation) {
                        const selectedAmount = selectedDonation.getAttribute('data-amount');
                        
                        if (selectedAmount === 'custom') {
                            amount = parseFloat(customAmountInput.value) || 0;
                        } else {
                            amount = parseFloat(selectedAmount);
                        }
                        
                        typeText = 'Donation';
                        formAccountName.value = document.getElementById('full-name').value || 'Anonymous';
                        formDonorName.value = document.getElementById('full-name').value || 'Anonymous';
                    }
                }
                
                // Update form hidden fields
                formAmount.value = amount;
                
                // Update summary display
                if (amount > 0) {
                    summaryType.textContent = typeText;
                    summaryAmount.textContent = amount.toLocaleString() + ' KSH';
                    summaryTotal.textContent = amount.toLocaleString() + ' KSH';
                    paymentSummary.style.display = 'block';
                    
                    // Enable/disable pay button based on amount validity
                    payNowBtn.disabled = amount <= 0;
                } else {
                    paymentSummary.style.display = 'none';
                    payNowBtn.disabled = true;
                }
            }
            
            // Update summary when inputs change
            document.getElementById('full-name').addEventListener('input', updatePaymentSummary);
            customAmountInput.addEventListener('input', updatePaymentSummary);
        });
    </script>
</body>
</html>