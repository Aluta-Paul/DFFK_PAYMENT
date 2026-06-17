<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DFFK – Payments</title>
    <!-- Font Awesome 6 (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Montserrat + Open Sans (matching register) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background: #f4f7fc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-header {
            padding: 15px 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .app-header .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .app-header a {
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            color: #1e3a8a;
        }
        .app-header a i {
            margin-right: 8px;
        }
        .app-header .brand {
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .payment-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            flex: 1;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .page-title h1 {
            font-weight: 700;
            font-size: 1.9rem;
            color: #0f172a;
        }
        .page-title i {
            font-size: 2.2rem;
            color: #1e3a8a;
        }

        .mpesa-brand-card {
            background: linear-gradient(145deg, #1a7a3a, #0f5c2a);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 40px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 25px rgba(0,80,20,0.25);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .mpesa-brand-card .pay-icon {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .mpesa-brand-card .pay-icon i {
            font-size: 2.4rem;
            color: #b7e4c7;
        }
        .mpesa-brand-card .pay-details {
            display: flex;
            flex-wrap: wrap;
            gap: 24px 40px;
            font-weight: 500;
        }
        .mpesa-brand-card .pay-details span {
            background: rgba(255,255,255,0.1);
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 0.95rem;
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .mpesa-brand-card .pay-details i {
            margin-right: 8px;
            color: #b7e4c7;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .payment-card {
            background: white;
            border-radius: 24px;
            padding: 22px 18px 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid #e9edf2;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .payment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(0,0,0,0.07);
            border-color: #b9d0e8;
        }
        .payment-card .card-icon {
            font-size: 2.2rem;
            color: #1e3a8a;
            margin-bottom: 10px;
        }
        .payment-card h3 {
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 6px;
            color: #0f172a;
        }
        .payment-card .desc {
            font-size: 0.95rem;
            color: #475569;
            margin-bottom: 12px;
            line-height: 1.4;
            flex: 1;
        }
        .payment-card .badge {
            background: #eef2f6;
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #1e293b;
            align-self: flex-start;
            margin-top: 6px;
        }
        .payment-card .badge i {
            margin-right: 6px;
            font-size: 0.7rem;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: white;
            max-width: 520px;
            width: 100%;
            border-radius: 32px;
            padding: 30px 28px 28px;
            box-shadow: 0 40px 70px rgba(0,0,0,0.25);
            position: relative;
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp {
            0% { opacity: 0; transform: translateY(24px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .modal-box .close-modal {
            position: absolute;
            top: 18px;
            right: 22px;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            transition: 0.15s;
            background: none;
            border: none;
        }
        .modal-box .close-modal:hover {
            color: #1e293b;
        }
        .modal-box h2 {
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 6px;
            color: #0f172a;
        }
        .modal-box .modal-sub {
            color: #475569;
            margin-bottom: 22px;
            font-size: 0.95rem;
        }
        .modal-box label {
            font-weight: 600;
            font-size: 0.9rem;
            display: block;
            margin: 14px 0 4px;
            color: #1e293b;
        }
        .modal-box input, .modal-box select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #dce1e9;
            border-radius: 16px;
            font-size: 0.95rem;
            background: white;
            transition: 0.15s;
            font-family: 'Open Sans', sans-serif;
        }
        .modal-box input:focus, .modal-box select:focus {
            border-color: #1e3a8a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(30,58,138,0.12);
        }
        .modal-box .row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .modal-box .btn-primary {
            background: #1e3a8a;
            border: none;
            color: white;
            font-weight: 700;
            padding: 14px 20px;
            border-radius: 40px;
            font-size: 1rem;
            width: 100%;
            margin-top: 22px;
            cursor: pointer;
            transition: 0.15s;
            font-family: 'Montserrat', sans-serif;
        }
        .modal-box .btn-primary:hover {
            background: #172e6b;
        }
        .modal-box .btn-primary:disabled {
            opacity: 0.6;
            cursor: default;
        }
        .modal-box .amount-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 12px 0 6px;
        }
        .modal-box .amount-options .pill {
            background: #f1f5f9;
            padding: 8px 18px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: 0.1s;
            color: #1e293b;
        }
        .modal-box .amount-options .pill.active {
            border-color: #1e3a8a;
            background: #e6edfc;
            color: #1e3a8a;
        }
        .modal-box .amount-options .pill:hover {
            background: #e2e8f0;
        }
        .modal-box .fine-options {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .modal-box .fine-options .fine-pill {
            background: #f1f5f9;
            padding: 6px 18px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            border: 1.5px solid transparent;
        }
        .modal-box .fine-options .fine-pill.active {
            border-color: #b91c1c;
            background: #fee2e2;
            color: #991b1b;
        }
        .modal-box .fine-options .fine-pill:hover {
            background: #e2e8f0;
        }
        .modal-box .confirmation-box {
            background: #f0fdf4;
            border-radius: 16px;
            padding: 16px 18px;
            margin: 16px 0 8px;
            border-left: 4px solid #15803d;
        }
        .modal-box .confirmation-box p {
            font-weight: 500;
            color: #166534;
        }
        .modal-box .confirmation-box i {
            margin-right: 8px;
        }
        .modal-box .mobile-input {
            margin: 8px 0 4px;
        }

        .autocomplete-list {
            background: white;
            border-radius: 16px;
            border: 1px solid #dce1e9;
            max-height: 160px;
            overflow-y: auto;
            margin-top: 4px;
            position: absolute;
            width: calc(100% - 32px);
            z-index: 20;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .autocomplete-list .item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .autocomplete-list .item:hover {
            background: #f8fafc;
        }
        .relative {
            position: relative;
        }

        .app-footer {
            background: white;
            border-top: 1px solid #e9edf2;
            padding: 18px 20px;
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 20px;
        }
        .app-footer a {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 600;
        }

        .hidden { display: none !important; }
        .mt-1 { margin-top: 8px; }
        .mb-1 { margin-bottom: 8px; }
        .text-sm { font-size: 0.85rem; color: #64748b; }

        @media (max-width: 640px) {
            .mpesa-brand-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .modal-box .row-2col {
                grid-template-columns: 1fr;
            }
            .page-title h1 { font-size: 1.5rem; }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .message-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .message {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        .message-success {
            background: #dcfce7;
            color: #166534;
        }
        .message-error {
            background: #fee2e2;
            color: #991b1b;
        }
        .message-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>

<header class="app-header">
    <div class="header-inner">
        <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        <div class="brand">Deaf Football Federation of Kenya</div>
    </div>
</header>

<main class="payment-container">
    <div class="page-title">
        <i class="fas fa-credit-card"></i>
        <h1>Payments</h1>
    </div>

    <div class="mpesa-brand-card">
        <div class="pay-icon">
            <i class="fas fa-mobile-alt"></i>
            <i class="fas fa-arrow-right" style="font-size: 1.2rem; opacity: 0.6;"></i>
            <i class="fas fa-phone" style="color: #b7e4c7;"></i>
        </div>
        <div class="pay-details">
            <span><i class="fas fa-hashtag"></i> Paybill: 5620588</span>
            <span><i class="fas fa-building"></i> Deaf Football Federation of Kenya</span>
            <span><i class="fas fa-mobile-alt"></i> Mpesa</span>
        </div>
    </div>

    <div class="cards-grid" id="paymentCardsGrid">
        <div class="payment-card" data-card="registration">
            <div class="card-icon"><i class="fas fa-user-plus"></i></div>
            <h3>Registration Fee</h3>
            <div class="desc">Player registration – male KES 250 / female KES 200</div>
            <span class="badge"><i class="fas fa-tag"></i> member / national ID</span>
        </div>
        <div class="payment-card" data-card="team">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <h3>Team Registration</h3>
            <div class="desc">Club registration: men KES 10,000 / women KES 5,000</div>
            <span class="badge"><i class="fas fa-search"></i> autocomplete club</span>
        </div>
        <div class="payment-card" data-card="donate">
            <div class="card-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <h3>Donate</h3>
            <div class="desc">Support DFFK – choose amount or custom</div>
            <span class="badge"><i class="fas fa-coins"></i> 200 / 500 / 1000 +</span>
        </div>
        <div class="payment-card" data-card="matchfine">
            <div class="card-icon"><i class="fas fa-futbol"></i></div>
            <h3>Match Fines</h3>
            <div class="desc">Red card KES 200 · Yellow card KES 150</div>
            <span class="badge"><i class="fas fa-card"></i> select fine type</span>
        </div>
        <div class="payment-card" data-card="otherfine">
            <div class="card-icon"><i class="fas fa-gavel"></i></div>
            <h3>Other Fines</h3>
            <div class="desc">Enter your name, ID, and fine description</div>
            <span class="badge"><i class="fas fa-pen"></i> custom fine</span>
        </div>
        <div class="payment-card" data-card="extra">
            <div class="card-icon"><i class="fas fa-plus-circle"></i></div>
            <h3>Extra Service</h3>
            <div class="desc">Add more payment types (scalable)</div>
            <span class="badge"><i class="fas fa-arrow-right"></i> coming soon</span>
        </div>
    </div>
</main>

<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <button class="close-modal" id="closeModalBtn"><i class="fas fa-times"></i></button>
        <h2 id="modalTitle">Payment</h2>
        <div class="modal-sub" id="modalSub">Fill in details</div>
        <div id="modalBody"></div>
        <div id="confirmationArea" class="confirmation-box hidden">
            <p><i class="fas fa-check-circle"></i> <span id="confirmMessage">Confirm payment</span></p>
        </div>
        <button class="btn-primary" id="modalActionBtn">Proceed</button>
    </div>
</div>

<footer class="app-footer">
    &copy; 2026 Deaf Football Federation of Kenya &middot; <a href="#">Privacy</a> &middot; <a href="#">Terms</a>
</footer>

<script>
    (function() {
        // ----- API Configuration -----
        const API_BASE_URL = '/dashboard/api/payment_super.php';
        const TEAMS_SEARCH_API_URL = '/dashboard/api/get_teams_by_region.php';
        const TEAM_DETAILS_API_URL = '/dashboard/api/get_team_details.php';

        // ----- API Functions -----
        const paymentAPI = {
            async initiatePayment(data) {
                const response = await fetch(API_BASE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'initiate_payment', ...data })
                });
                return await response.json();
            },

            async queryStatus(checkoutRequestId) {
                const response = await fetch(API_BASE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'query_status',
                        checkout_request_id: checkoutRequestId
                    })
                });
                return await response.json();
            },

            async validateDffk(dffkCode, paymentType) {
                const response = await fetch(API_BASE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'validate_dffk',
                        dffk_code: dffkCode,
                        payment_type: paymentType
                    })
                });
                return await response.json();
            },

            async searchClubs(searchTerm) {
                const url = new URL(TEAMS_SEARCH_API_URL);
                url.searchParams.append('search', searchTerm);
                const response = await fetch(url);
                return await response.json();
            },

            async getTeamDetails(teamId) {
                const url = new URL(TEAM_DETAILS_API_URL);
                url.searchParams.append('id', teamId);
                const response = await fetch(url);
                return await response.json();
            },

            async getAllClubs() {
                const response = await fetch(TEAMS_SEARCH_API_URL);
                return await response.json();
            }
        };

        // ----- State -----
        let currentCard = '';
        let selectedAmount = null;
        let selectedFine = null;
        let selectedClubId = null;
        let selectedClubName = '';
        let paymentPollingTimer = null;
        let allClubs = [];

        // DOM refs
        const modal = document.getElementById('paymentModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalSub = document.getElementById('modalSub');
        const modalBody = document.getElementById('modalBody');
        const confirmArea = document.getElementById('confirmationArea');
        const confirmMsg = document.getElementById('confirmMessage');
        const actionBtn = document.getElementById('modalActionBtn');
        const closeBtn = document.getElementById('closeModalBtn');

        // ----- Message System -----
        function showMessage(type, text) {
            let container = document.getElementById('messageContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'messageContainer';
                container.className = 'message-container';
                document.body.appendChild(container);
            }

            const message = document.createElement('div');
            message.className = `message message-${type}`;
            message.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                ${text}
            `;
            container.appendChild(message);
            setTimeout(() => message.remove(), 5000);
        }

        // ----- Modal Helpers -----
        function closeModal() {
            modal.classList.remove('active');
            if (paymentPollingTimer) {
                clearTimeout(paymentPollingTimer);
                paymentPollingTimer = null;
            }
            selectedAmount = null;
            selectedFine = null;
            selectedClubId = null;
            selectedClubName = '';
            confirmArea.classList.add('hidden');
            actionBtn.textContent = 'Proceed';
            actionBtn.disabled = false;
            actionBtn.onclick = null;
        }

        function openModal(cardType) {
            currentCard = cardType;
            modal.classList.add('active');
            confirmArea.classList.add('hidden');
            renderModalContent(cardType);
        }

        // ----- Render Modal Content -----
        function renderModalContent(cardType) {
            let html = '';
            let title = '';
            let sub = '';

            const basePhone = `
                <label><i class="fas fa-phone"></i> M-Pesa Mobile Number</label>
                <input type="tel" id="mpesaPhone" placeholder="0712 345 678" class="mobile-input">
                <div class="text-sm mt-1">Enter the phone number registered with M-Pesa</div>
            `;

            const baseId = `
                <label><i class="fas fa-id-card"></i> DFFK Member No. / National ID</label>
                <input type="text" id="userId" placeholder="e.g. DFFK-PL-XXXXX-2026 or 12345678">
            `;

            const baseName = `
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" id="userName" placeholder="Enter your full name">
            `;

            switch(cardType) {
                case 'registration':
                    title = 'Registration Fee';
                    sub = 'Pay your annual registration fee';
                    html = `
                        ${basePhone}
                        ${baseId}
                        <div class="text-sm mb-1">Enter your DFFK code or National ID to validate your account</div>
                        <label>Gender</label>
                        <div class="row-2col">
                            <select id="regGender">
                                <option value="male">Male (KES 250)</option>
                                <option value="female">Female (KES 200)</option>
                            </select>
                            <div style="display:flex;align-items:center;gap:6px;background:#f1f5f9;padding:0 14px;border-radius:40px;">
                                <i class="fas fa-tag"></i> <span id="regAmountDisplay">250</span> KES
                            </div>
                        </div>
                    `;
                    break;

                case 'team':
                    title = 'Team Registration';
                    sub = 'Register your club for the season';
                    html = `
                        ${basePhone}
                        <label><i class="fas fa-flag"></i> Club Name</label>
                        <div class="relative">
                            <input type="text" id="clubInput" placeholder="Start typing club name..." autocomplete="off">
                            <div id="autocompleteList" class="autocomplete-list hidden"></div>
                        </div>
                        <input type="hidden" id="clubId" value="">
                        <div id="clubDetails" class="text-sm mt-1" style="display:none;background:#f1f5f9;padding:8px 12px;border-radius:8px;">
                            <i class="fas fa-info-circle"></i> <span id="clubInfo"></span>
                        </div>
                        <label>Club Gender</label>
                        <select id="teamGender">
                            <option value="male">Men (KES 10,000)</option>
                            <option value="female">Women (KES 5,000)</option>
                        </select>
                        <div class="text-sm mt-1">Amount: <span id="teamAmountDisplay">10000</span> KES</div>
                    `;
                    break;

                case 'donate':
                    title = 'Donate';
                    sub = 'Support DFFK programs';
                    html = `
                        ${basePhone}
                        <label>Amount</label>
                        <div class="amount-options" id="donateOptions">
                            <span class="pill" data-amount="200">KES 200</span>
                            <span class="pill" data-amount="500">KES 500</span>
                            <span class="pill" data-amount="1000">KES 1000</span>
                        </div>
                        <input type="number" id="customDonate" placeholder="Custom amount (KES)" min="10">
                        <div class="text-sm mt-1">Selected: <span id="donateSelected">—</span></div>
                    `;
                    break;

                case 'matchfine':
                    title = 'Match Fines';
                    sub = 'Pay match-related fines';
                    html = `
                        ${basePhone}
                        ${baseId}
                        <div class="text-sm mb-1">Enter your DFFK code or National ID</div>
                        <label>Fine Type</label>
                        <div class="fine-options" id="fineOptions">
                            <span class="fine-pill" data-fine="red" data-amount="200">Red card – KES 200</span>
                            <span class="fine-pill" data-fine="yellow" data-amount="150">Yellow card – KES 150</span>
                        </div>
                        <div class="text-sm mt-1">Selected: <span id="fineSelected">—</span></div>
                        <input type="hidden" id="fineReference" value="">
                    `;
                    break;

                case 'otherfine':
                    title = 'Other Fines';
                    sub = 'Pay other fines and penalties';
                    html = `
                        ${basePhone}
                        ${baseName}
                        ${baseId}
                        <label><i class="fas fa-pencil-alt"></i> Fine Description</label>
                        <input type="text" id="fineDesc" placeholder="e.g. Late match reporting, misconduct...">
                        <label>Amount (KES)</label>
                        <input type="number" id="otherFineAmount" placeholder="Enter amount" min="1">
                        <input type="hidden" id="fineReference" value="">
                    `;
                    break;

                default:
                    title = 'Payment';
                    sub = 'Complete the payment form';
                    html = `
                        ${basePhone}
                        ${baseId}
                        <label>Amount (KES)</label>
                        <input type="number" id="extraAmount" placeholder="Enter amount">
                        <label>Description</label>
                        <input type="text" id="extraDesc" placeholder="Reason for payment">
                    `;
            }

            modalTitle.textContent = title;
            modalSub.textContent = sub;
            modalBody.innerHTML = html;
            attachListeners(cardType);
        }

        // ----- Attach Event Listeners -----
        function attachListeners(cardType) {
            if (cardType === 'registration') {
                const genderSel = document.getElementById('regGender');
                const display = document.getElementById('regAmountDisplay');
                if (genderSel) {
                    genderSel.addEventListener('change', function() {
                        display.textContent = this.value === 'male' ? '250' : '200';
                    });
                }
            }

            if (cardType === 'team') {
                const teamGender = document.getElementById('teamGender');
                const teamDisplay = document.getElementById('teamAmountDisplay');
                if (teamGender) {
                    teamGender.addEventListener('change', function() {
                        teamDisplay.textContent = this.value === 'male' ? '10000' : '5000';
                    });
                }

                const clubInput = document.getElementById('clubInput');
                const list = document.getElementById('autocompleteList');
                const clubIdInput = document.getElementById('clubId');
                const clubDetails = document.getElementById('clubDetails');
                const clubInfo = document.getElementById('clubInfo');

                if (clubInput) {
                    let debounceTimer;

                    clubInput.addEventListener('input', function() {
                        clearTimeout(debounceTimer);
                        const val = this.value.trim();

                        if (val.length < 2) {
                            list.classList.add('hidden');
                            clubDetails.style.display = 'none';
                            return;
                        }

                        debounceTimer = setTimeout(async () => {
                            try {
                                const clubs = await paymentAPI.searchClubs(val);

                                if (!clubs || clubs.length === 0 || clubs.error) {
                                    list.innerHTML = '<div class="item" style="color:#94a3b8;">No clubs found</div>';
                                    list.classList.remove('hidden');
                                    return;
                                }

                                allClubs = clubs;

                                list.innerHTML = clubs.map(c =>
                                    `<div class="item" data-club-id="${c.id}" data-club-name="${c.name}" data-region="${c.region_name || ''}">
                                        ${c.name} ${c.region_name ? `<span style="color:#94a3b8;font-size:0.8rem;">(${c.region_name})</span>` : ''}
                                    </div>`
                                ).join('');
                                list.classList.remove('hidden');

                                list.querySelectorAll('.item').forEach(el => {
                                    el.addEventListener('click', function() {
                                        const clubId = this.dataset.clubId;
                                        const clubName = this.dataset.clubName;
                                        const region = this.dataset.region || '';
                                        
                                        clubInput.value = clubName;
                                        clubIdInput.value = clubId;
                                        selectedClubId = parseInt(clubId);
                                        selectedClubName = clubName;
                                        
                                        // Show club details
                                        clubDetails.style.display = 'block';
                                        clubInfo.innerHTML = `${clubName} ${region ? `(Region: ${region})` : ''}`;
                                        
                                        list.classList.add('hidden');
                                    });
                                });
                            } catch (error) {
                                console.error('Error fetching clubs:', error);
                                list.classList.add('hidden');
                            }
                        }, 300);
                    });

                    clubInput.addEventListener('blur', function() {
                        setTimeout(() => list.classList.add('hidden'), 200);
                    });

                    clubInput.addEventListener('focus', function() {
                        if (this.value.trim().length >= 2) {
                            this.dispatchEvent(new Event('input'));
                        }
                    });
                }
            }

            if (cardType === 'donate') {
                const pills = document.querySelectorAll('#donateOptions .pill');
                const selectedSpan = document.getElementById('donateSelected');
                pills.forEach(p => {
                    p.addEventListener('click', function() {
                        pills.forEach(pp => pp.classList.remove('active'));
                        this.classList.add('active');
                        selectedAmount = parseInt(this.dataset.amount);
                        selectedSpan.textContent = `KES ${selectedAmount}`;
                        const custom = document.getElementById('customDonate');
                        if (custom) custom.value = '';
                    });
                });
                const customInput = document.getElementById('customDonate');
                if (customInput) {
                    customInput.addEventListener('input', function() {
                        const v = parseInt(this.value);
                        if (v > 0) {
                            pills.forEach(pp => pp.classList.remove('active'));
                            selectedAmount = v;
                            selectedSpan.textContent = `KES ${v}`;
                        }
                    });
                }
            }

            if (cardType === 'matchfine') {
                const finePills = document.querySelectorAll('#fineOptions .fine-pill');
                const fineSelected = document.getElementById('fineSelected');
                finePills.forEach(p => {
                    p.addEventListener('click', function() {
                        finePills.forEach(pp => pp.classList.remove('active'));
                        this.classList.add('active');
                        selectedFine = {
                            type: this.dataset.fine,
                            amount: parseInt(this.dataset.amount)
                        };
                        fineSelected.textContent = this.textContent.trim();
                    });
                });
            }

            actionBtn.onclick = function(e) {
                e.preventDefault();
                handlePayment(cardType);
            };
        }

        // ----- Main Payment Handler -----
        async function handlePayment(cardType) {
            const phone = document.getElementById('mpesaPhone')?.value?.trim() || '';
            const userId = document.getElementById('userId')?.value?.trim() || '';
            const dffkCode = userId;
            const name = document.getElementById('userName')?.value?.trim() || '';

            let amount = 0;
            let paymentType = '';
            let accountReference = 'DFFK-PAY';
            let transactionDesc = '';
            let referenceId = null;
            let clubId = null;
            let clubName = '';

            switch(cardType) {
                case 'registration': {
                    const gender = document.getElementById('regGender')?.value || 'male';
                    amount = gender === 'male' ? 250 : 200;
                    paymentType = 'registration';
                    accountReference = 'REG-' + (dffkCode || 'USER');
                    transactionDesc = `Registration fee (${gender})`;
                    break;
                }
                case 'team': {
                    const gender = document.getElementById('teamGender')?.value || 'male';
                    amount = gender === 'male' ? 10000 : 5000;
                    paymentType = 'team_registration';
                    clubName = document.getElementById('clubInput')?.value?.trim() || '';
                    clubId = document.getElementById('clubId')?.value || null;
                    
                    if (!clubId) {
                        showMessage('error', 'Please select a club from the list');
                        return;
                    }
                    
                    accountReference = 'TEAM-' + clubName.substring(0, 10).toUpperCase();
                    transactionDesc = `Team registration: ${clubName} (${gender})`;
                    break;
                }
                case 'donate': {
                    const custom = document.getElementById('customDonate')?.value;
                    if (custom && parseInt(custom) > 0) {
                        amount = parseInt(custom);
                    } else if (selectedAmount) {
                        amount = selectedAmount;
                    } else {
                        showMessage('error', 'Please select or enter an amount');
                        return;
                    }
                    paymentType = 'donation';
                    accountReference = 'DON-' + Date.now();
                    transactionDesc = 'Donation';
                    break;
                }
                case 'matchfine': {
                    if (!selectedFine) {
                        showMessage('error', 'Please select a fine type');
                        return;
                    }
                    amount = selectedFine.amount;
                    paymentType = 'match_fine';
                    accountReference = 'FINE-' + (dffkCode || 'USER');
                    transactionDesc = `Fine: ${selectedFine.type} card`;
                    referenceId = document.getElementById('fineReference')?.value || null;
                    break;
                }
                case 'otherfine': {
                    const amt = document.getElementById('otherFineAmount')?.value;
                    if (!amt || parseInt(amt) <= 0) {
                        showMessage('error', 'Enter a valid amount');
                        return;
                    }
                    amount = parseInt(amt);
                    paymentType = 'other_fine';
                    const desc = document.getElementById('fineDesc')?.value?.trim() || 'Other fine';
                    accountReference = 'FINE-' + (dffkCode || 'USER');
                    transactionDesc = desc;
                    referenceId = document.getElementById('fineReference')?.value || null;
                    break;
                }
                default: {
                    const amt = document.getElementById('extraAmount')?.value;
                    if (!amt || parseInt(amt) <= 0) {
                        showMessage('error', 'Enter amount');
                        return;
                    }
                    amount = parseInt(amt);
                    paymentType = 'other';
                    accountReference = 'PAY-' + Date.now();
                    transactionDesc = document.getElementById('extraDesc')?.value?.trim() || 'Payment';
                }
            }

            if (!phone || phone.length < 9) {
                showMessage('error', 'Please enter a valid M-Pesa phone number.');
                return;
            }

            if (dffkCode && (cardType === 'registration' || cardType === 'matchfine' || cardType === 'otherfine')) {
                try {
                    const validation = await paymentAPI.validateDffk(dffkCode, paymentType);
                    if (!validation.success) {
                        showMessage('error', validation.message);
                        return;
                    }
                } catch (error) {
                    showMessage('error', 'Error validating DFFK code: ' + error.message);
                    return;
                }
            }

            confirmArea.classList.remove('hidden');
            confirmMsg.innerHTML = '<i class="fas fa-spinner spinner"></i> Initiating payment...';
            actionBtn.disabled = true;

            try {
                const result = await paymentAPI.initiatePayment({
                    payment_type: paymentType,
                    amount: amount,
                    phone_number: phone,
                    account_reference: accountReference,
                    transaction_desc: transactionDesc,
                    dffk_code: dffkCode || null,
                    account_name: name || 'DFFK User',
                    reference_id: referenceId,
                    club_id: clubId,
                    club_name: clubName
                });

                if (result.success) {
                    confirmMsg.innerHTML = `
                        <i class="fas fa-check-circle" style="color:#15803d;"></i>
                        STK push sent to ${phone}. Please check your phone and enter your PIN.
                        <br><small>Checkout ID: ${result.data.checkout_request_id || 'N/A'}</small>
                    `;
                    actionBtn.textContent = 'Check Status';
                    actionBtn.onclick = function() {
                        checkPaymentStatus(result.data.checkout_request_id);
                    };

                    paymentPollingTimer = setTimeout(() => {
                        checkPaymentStatus(result.data.checkout_request_id);
                    }, 30000);

                } else {
                    confirmMsg.innerHTML = `
                        <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>
                        ${result.message}
                    `;
                    actionBtn.textContent = 'Try Again';
                    actionBtn.onclick = function() {
                        closeModal();
                        openModal(cardType);
                    };
                }

            } catch (error) {
                showMessage('error', 'Payment initiation failed: ' + error.message);
                confirmMsg.innerHTML = `
                    <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>
                    Failed to initiate payment. Please try again.
                `;
                actionBtn.textContent = 'Try Again';
                actionBtn.onclick = function() {
                    closeModal();
                    openModal(cardType);
                };
            } finally {
                actionBtn.disabled = false;
            }
        }

        // ----- Check Payment Status -----
        async function checkPaymentStatus(checkoutRequestId) {
            if (!checkoutRequestId) {
                showMessage('error', 'No checkout request ID available');
                return;
            }

            try {
                const result = await paymentAPI.queryStatus(checkoutRequestId);

                if (result.success && result.data?.status === 'completed') {
                    confirmMsg.innerHTML = `
                        <i class="fas fa-check-circle" style="color:#15803d;"></i>
                        Payment completed successfully!
                        <br><small>Receipt: ${result.data.mpesa_receipt || 'N/A'}</small>
                    `;
                    actionBtn.textContent = 'Done';
                    actionBtn.onclick = closeModal;
                    showMessage('success', 'Payment completed successfully!');
                    if (paymentPollingTimer) {
                        clearTimeout(paymentPollingTimer);
                        paymentPollingTimer = null;
                    }
                } else if (result.success && result.data?.status === 'pending') {
                    confirmMsg.innerHTML = `
                        <i class="fas fa-clock" style="color:#f59e0b;"></i>
                        Payment is still pending. Please check your phone.
                        <br><small>${result.message}</small>
                    `;
                    paymentPollingTimer = setTimeout(() => {
                        checkPaymentStatus(checkoutRequestId);
                    }, 30000);
                } else {
                    confirmMsg.innerHTML = `
                        <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>
                        ${result.message || 'Payment failed. Please try again.'}
                        ${result.data?.result_desc ? `<br><small>${result.data.result_desc}</small>` : ''}
                    `;
                    actionBtn.textContent = 'Try Again';
                    actionBtn.onclick = function() {
                        closeModal();
                        setTimeout(() => openModal(currentCard), 300);
                    };
                    if (paymentPollingTimer) {
                        clearTimeout(paymentPollingTimer);
                        paymentPollingTimer = null;
                    }
                }
            } catch (error) {
                showMessage('error', 'Failed to check payment status: ' + error.message);
            }
        }

        // ----- Card Click Listeners -----
        document.querySelectorAll('.payment-card').forEach(card => {
            card.addEventListener('click', function() {
                const cardType = this.dataset.card;
                if (cardType === 'extra') {
                    showMessage('info', 'This service is coming soon. You can add more payment types.');
                    return;
                }
                openModal(cardType);
            });
        });

        // ----- Close Modal -----
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });

        // ----- Preload clubs on page load -----
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const clubs = await paymentAPI.getAllClubs();
                if (Array.isArray(clubs) && !clubs.error) {
                    allClubs = clubs;
                }
            } catch (e) {
                // Silent fail - will fetch on demand
                console.debug('Preload clubs failed:', e);
            }
        });

    })();
</script>

</body>
</html>