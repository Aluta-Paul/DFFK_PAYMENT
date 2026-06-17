// Add to payment.php - updated JavaScript for API integration

const paymentAPI = {
    baseUrl: '/dashboard/api/payment_super.php',
    
    async initiatePayment(data) {
        const response = await fetch(this.baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'initiate_payment',
                ...data
            })
        });
        return await response.json();
    },
    
    async queryStatus(checkoutRequestId) {
        const response = await fetch(this.baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'query_status',
                checkout_request_id: checkoutRequestId
            })
        });
        return await response.json();
    },
    
    async validateDffk(dffkCode, paymentType) {
        const response = await fetch(this.baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'validate_dffk',
                dffk_code: dffkCode,
                payment_type: paymentType
            })
        });
        return await response.json();
    },
    
    async getHistory(identifier, identifierType = 'dffk_code') {
        const response = await fetch(this.baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_payment_history',
                identifier: identifier,
                identifier_type: identifierType
            })
        });
        return await response.json();
    }
};

// Update the handlePayment function to use the API
async function handlePayment(cardType) {
    // Gather data from modal
    const phone = document.getElementById('mpesaPhone')?.value || '';
    const userId = document.getElementById('userId')?.value || '';
    const dffkCode = document.getElementById('dffkCode')?.value || '';
    const name = document.getElementById('userName')?.value || '';
    
    let amount = 0;
    let paymentType = '';
    let accountReference = 'DFFK-PAY';
    let transactionDesc = '';
    let referenceId = null;
    
    // Determine payment type and amount based on card
    switch(cardType) {
        case 'registration': {
            const gender = document.getElementById('regGender')?.value || 'male';
            amount = gender === 'male' ? 250 : 200;
            paymentType = 'registration';
            accountReference = 'REG-' + (dffkCode || userId);
            transactionDesc = `Registration fee (${gender})`;
            break;
        }
        case 'team': {
            const gender = document.getElementById('teamGender')?.value || 'male';
            amount = gender === 'male' ? 10000 : 5000;
            paymentType = 'team_registration';
            const club = document.getElementById('clubInput')?.value || 'Unknown';
            accountReference = 'TEAM-' + club.substring(0, 10);
            transactionDesc = `Team registration: ${club} (${gender})`;
            break;
        }
        case 'donate': {
            const custom = document.getElementById('customDonate')?.value;
            if (custom && parseInt(custom) > 0) amount = parseInt(custom);
            else if (selectedAmount) amount = selectedAmount;
            else { showMessage('error', 'Please select or enter an amount'); return; }
            paymentType = 'donation';
            accountReference = 'DON-' + Date.now();
            transactionDesc = 'Donation';
            break;
        }
        case 'matchfine': {
            if (!selectedFine) { showMessage('error', 'Please select a fine type'); return; }
            amount = selectedFine.amount;
            paymentType = 'match_fine';
            accountReference = 'FINE-' + (dffkCode || userId);
            transactionDesc = `Fine: ${selectedFine.type} card`;
            referenceId = document.getElementById('fineReference')?.value || null;
            break;
        }
        case 'otherfine': {
            const amt = document.getElementById('otherFineAmount')?.value;
            if (!amt || parseInt(amt) <= 0) { showMessage('error', 'Enter a valid amount'); return; }
            amount = parseInt(amt);
            paymentType = 'other_fine';
            const desc = document.getElementById('fineDesc')?.value || 'Other fine';
            accountReference = 'FINE-' + (dffkCode || userId);
            transactionDesc = desc;
            referenceId = document.getElementById('fineReference')?.value || null;
            break;
        }
        default: {
            const amt = document.getElementById('extraAmount')?.value;
            if (!amt || parseInt(amt) <= 0) { showMessage('error', 'Enter amount'); return; }
            amount = parseInt(amt);
            paymentType = 'other';
            accountReference = 'PAY-' + Date.now();
            transactionDesc = document.getElementById('extraDesc')?.value || 'Payment';
        }
    }
    
    if (!phone || phone.length < 9) {
        showMessage('error', 'Please enter a valid M-Pesa phone number.');
        return;
    }
    
    // Validate DFFK code if provided
    if (dffkCode && (cardType === 'registration' || cardType === 'matchfine' || cardType === 'otherfine')) {
        const validation = await paymentAPI.validateDffk(dffkCode, paymentType);
        if (!validation.success) {
            showMessage('error', validation.message);
            return;
        }
    }
    
    // Show processing state
    confirmArea.classList.remove('hidden');
    confirmMsg.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Initiating payment...';
    actionBtn.disabled = true;
    
    try {
        // Initiate payment
        const result = await paymentAPI.initiatePayment({
            payment_type: paymentType,
            amount: amount,
            phone_number: phone,
            account_reference: accountReference,
            transaction_desc: transactionDesc,
            dffk_code: dffkCode || null,
            account_name: name || 'DFFK User',
            reference_id: referenceId,
            club_id: document.getElementById('clubId')?.value || null,
            club_name: document.getElementById('clubInput')?.value || null
        });
        
        if (result.success) {
            // Payment initiated successfully
            confirmMsg.innerHTML = `
                <i class="fas fa-check-circle" style="color:#15803d;"></i>
                STK push sent to ${phone}. Please check your phone and enter your PIN.
                <br><small>Checkout ID: ${result.data.checkout_request_id || 'N/A'}</small>
            `;
            actionBtn.textContent = 'Check Status';
            actionBtn.onclick = function() {
                checkPaymentStatus(result.data.checkout_request_id);
            };
            
            // Start polling for status
            setTimeout(() => {
                checkPaymentStatus(result.data.checkout_request_id);
            }, 30000); // Check after 30 seconds
            
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

async function checkPaymentStatus(checkoutRequestId) {
    if (!checkoutRequestId) {
        showMessage('error', 'No checkout request ID available');
        return;
    }
    
    try {
        const result = await paymentAPI.queryStatus(checkoutRequestId);
        
        if (result.success && result.data.status === 'completed') {
            confirmMsg.innerHTML = `
                <i class="fas fa-check-circle" style="color:#15803d;"></i>
                Payment completed successfully!
                <br><small>Receipt: ${result.data.mpesa_receipt || 'N/A'}</small>
            `;
            actionBtn.textContent = 'Done';
            actionBtn.onclick = closeModal;
            showMessage('success', 'Payment completed successfully!');
        } else if (result.success && result.data.status === 'pending') {
            confirmMsg.innerHTML = `
                <i class="fas fa-clock" style="color:#f59e0b;"></i>
                Payment is still pending. Please check your phone.
                <br><small>${result.message}</small>
            `;
            // Try again in 30 seconds
            setTimeout(() => {
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
                const cardType = currentCard;
                setTimeout(() => openModal(cardType), 300);
            };
        }
    } catch (error) {
        showMessage('error', 'Failed to check payment status: ' + error.message);
    }
}

// Show message function
function showMessage(type, text) {
    const container = document.getElementById('messageContainer');
    if (!container) {
        // Create message container if it doesn't exist
        const newContainer = document.createElement('div');
        newContainer.id = 'messageContainer';
        newContainer.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;max-width:400px;';
        document.body.appendChild(newContainer);
        
        const message = document.createElement('div');
        message.className = `message message-${type}`;
        message.style.cssText = `
            background: ${type === 'success' ? '#dcfce7' : '#fee2e2'};
            color: ${type === 'success' ? '#166534' : '#991b1b'};
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        `;
        message.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            ${text}
        `;
        newContainer.appendChild(message);
        
        setTimeout(() => {
            message.remove();
        }, 5000);
        return;
    }
    
    const message = document.createElement('div');
    message.className = `message message-${type}`;
    message.style.cssText = `
        background: ${type === 'success' ? '#dcfce7' : '#fee2e2'};
        color: ${type === 'success' ? '#166534' : '#991b1b'};
        padding: 12px 20px;
        border-radius: 12px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        animation: slideIn 0.3s ease;
    `;
    message.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${text}
    `;
    container.appendChild(message);
    
    setTimeout(() => {
        message.remove();
    }, 5000);
}