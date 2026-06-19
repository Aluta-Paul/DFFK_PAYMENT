<?php
session_start();
require_once __DIR__ . '/../app/config/paths.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$paymentId = $_GET['payment_id'] ?? null;
$redirectUrl = '/player/player_dashboard.php'; // Update with actual dashboard URL

// Check if payment was successful
if (!$paymentId) {
    header('Location: payments.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - DFFK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .success-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .success-icon {
            font-size: 80px;
            color: #4caf50;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #1a237e;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0d47a1;
        }
        .redirect-countdown {
            margin-top: 20px;
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Payment Successful!</h1>
        <p>Your membership has been renewed. You now have full access to all features.</p>
        <p style="font-size: 14px; color: #888;">
            <i class="fas fa-clock"></i> Valid until: <?= date('F j, Y', strtotime('+1 year')) ?>
        </p>
        <a href="<?= $redirectUrl ?>" class="btn">
            <i class="fas fa-arrow-right"></i> Go to Dashboard
        </a>
        <div class="redirect-countdown">
            Redirecting in <span id="countdown">5</span> seconds...
        </div>
    </div>

    <script>
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const interval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(interval);
                window.location.href = '<?= $redirectUrl ?>';
            }
        }, 1000);
    </script>
</body>
</html>