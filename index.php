<?php
// Configuration
$config = [
    'ZENOPAY_API_KEY' => 'vTTHiLf0IcCHbvhroFX-PVWFpYt0s1Zpr6qkPTiBfnPWQD1XXhQECgUHGZ-nq7XEwhqLHGC322B8GbWygvHkSA',
    'SUCCESS_URL' => 'http://localhost:8000/success.php'
];

// Handle AJAX payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    header('Content-Type: application/json');
    
    try {
        $phoneNumber = $_POST['phoneNumber'] ?? '';
        $amount = (int)($_POST['amount'] ?? 1000);
        
        if (empty($phoneNumber)) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required']);
            exit;
        }
        

        if (!preg_match('/^0[67][0-9]{8}$/', $phoneNumber)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit phone number (06XXXXXXXX or 07XXXXXXXX)']);
            exit;
        }
        

        $orderId = 'PAY_' . time() . '_' . substr(md5(rand()), 0, 8);
        
        $payload = [
            'order_id' => $orderId,
            'buyer_email' => 'customer@example.com',
            'buyer_name' => 'Customer',
            'buyer_phone' => $phoneNumber,
            'amount' => $amount,
            'webhook_url' => $config['SUCCESS_URL']
        ];
        

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://zenoapi.com/api/payments/mobile_money_tanzania');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $config['ZENOPAY_API_KEY']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode(['success' => false, 'message' => 'Network error: ' . $error]);
            exit;
        }
        
        $responseData = json_decode($response, true);
        

        
        if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
            echo json_encode([
                'success' => true,
                'message' => $responseData['message'] ?? 'USSD push sent successfully. Please check your phone.',
                'order_id' => $responseData['order_id'] ?? $orderId
            ]);
        } else {
            $errorMessage = 'Payment failed';
            if ($httpCode === 403) {
                $errorMessage = 'Invalid API Key. Please verify your API credentials.';
            } elseif (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            } elseif (isset($responseData['detail'])) {
                $errorMessage = $responseData['detail'];
            } elseif (isset($responseData['error'])) {
                $errorMessage = $responseData['error'];
            }
            
            echo json_encode(['success' => false, 'message' => $errorMessage]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }

        .image-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }

        .fullscreen-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.3) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            text-align: center;
            color: white;
            max-width: 600px;
            padding: 2rem;
        }

        .content h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .download-btn {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }

        .top-right-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            padding: 12px 25px;
            font-size: 1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 30px 30px 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .modal-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #000;
        }

        .payment-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .phone-input input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .phone-input input:focus {
            outline: none;
            border-color: #007bff;
        }

        .input-help {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .amount-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: #333;
        }

        .amount {
            color: #007bff;
            font-size: 1.1rem;
        }

        .pay-btn {
            width: 100%;
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 15px;
            font-size: 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .pay-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }

        .pay-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .payment-info {
            text-align: center;
            padding: 20px 30px 30px;
            font-size: 0.9rem;
            color: #666;
        }

        .payment-info p {
            margin: 5px 0;
        }

        /* Message Styles */
        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
        }

        .message {
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid;
            animation: slideInRight 0.3s ease;
        }

        .message.success { border-left-color: #28a745; }
        .message.error { border-left-color: #dc3545; }
        .message.info { border-left-color: #17a2b8; }

        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        @media (max-width: 768px) {
            .content h1 { font-size: 2rem; }
            .modal-content { margin: 10% auto; width: 95%; }
            .modal-header, .payment-form { padding: 20px; }
            
            .top-right-btn {
                top: 15px;
                right: 15px;
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Full-screen image container -->
    <div class="image-container">
        <img src="Picha" alt="Premium Image" class="fullscreen-image">
        <div class="overlay">
            <div class="content">
                <!-- <h1>Premium Image Collection</h1>
                <p>High-quality professional image available for download</p> -->
            </div>
        </div>
        
        <!-- Top right download button -->
        <button id="downloadBtn" class="download-btn top-right-btn">
            <span class="btn-icon">⬇</span>
            Download Now
        </button>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="modal-header">
                <h2>Lipia TSH 2000/= Kuendelea</h2>
                <p>Ujiunge Na Group la connection zote
Group la malaya wote TZ<br>
Connection zote zipo</p>
            </div>
            
            <form id="paymentForm" class="payment-form">
                <div class="form-group">
                    <label for="phoneNumber">Namba ya simu yenye hela</label>
                    <div class="phone-input">
                        <input
                            type="tel"
                            id="phoneNumber"
                            name="phoneNumber"
                            placeholder="Jaza namba ya simu"
                            pattern="^0[67][0-9]{8}$"
                            minlength="10"
                            maxlength="10"
                            inputmode="numeric"
                            required
                        >
                    </div>
                    <small class="input-help"></small>
                </div>
                
                <div class="amount-info">
                    <div class="amount-row">
                        <span><img src="logo.png" alt="" width="auto" height="30px"></span>
                        <span class="amount">Tsh 2,000 </span>
                    </div>
                </div>

                <button type="submit" class="pay-btn" id="payBtn">
                    <span class="btn-text">Lipia</span>
                    <div class="loading-spinner" style="display: none;"></div>
                </button>
            </form>

            
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="messageContainer" class="message-container"></div>

    <script>
        // DOM elements
        const paymentModal = document.getElementById('paymentModal');
        const downloadBtn = document.getElementById('downloadBtn');
        const closeBtn = document.querySelector('.close');
        const paymentForm = document.getElementById('paymentForm');
        const payBtn = document.getElementById('payBtn');
        const phoneInput = document.getElementById('phoneNumber');
        const messageContainer = document.getElementById('messageContainer');

        // Auto-show modal after 3 seconds
        setTimeout(() => {
            showModal();
        }, 3000);

        // Event listeners
        downloadBtn.addEventListener('click', showModal);
        closeBtn.addEventListener('click', hideModal);
        paymentForm.addEventListener('submit', handlePayment);

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target === paymentModal) {
                hideModal();
            }
        });

        // Phone number formatting for Tanzania (10 digits)
        phoneInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            
            if (value.length > 0 && !value.startsWith('0')) {
                value = '0' + value.slice(0, 9);
            }
            
            e.target.value = value;
        });

        // Modal functions
        function showModal() {
            paymentModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            phoneInput.focus();
        }

        function hideModal() {
            paymentModal.style.display = 'none';
            document.body.style.overflow = 'auto';
            resetForm();
        }

        function resetForm() {
            paymentForm.reset();
            setPayButtonState(false);
            clearMessages();
        }

        // Payment handling
        async function handlePayment(event) {
            event.preventDefault();
            
            const phoneNumber = phoneInput.value.trim();
            
            if (!validatePhoneNumber(phoneNumber)) {
                showMessage('Please enter a valid 10-digit phone number (06XXXXXXXX or 07XXXXXXXX)', 'error');
                return;
            }
            
            setPayButtonState(true);
            clearMessages();
            
            try {
                const formData = new FormData();
                formData.append('action', 'pay');
                formData.append('phoneNumber', phoneNumber);
                formData.append('amount', '2000');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    
                    // Start payment monitoring
                    const orderId = result.order_id;
                    showPaymentWaiting(orderId);
                    startPaymentPolling(orderId);
                    
                } else {
                    showMessage(result.message || 'Payment failed. Please try again.', 'error');
                    setPayButtonState(false);
                }
                
            } catch (error) {
                console.error('Payment error:', error);
                showMessage('Network error. Please check your connection and try again.', 'error');
                setPayButtonState(false);
            }
        }

        // Validation
        function validatePhoneNumber(phoneNumber) {
            const phoneRegex = /^0[67][0-9]{8}$/;
            return phoneRegex.test(phoneNumber);
        }

        // UI state management
        function setPayButtonState(loading) {
            const btnText = payBtn.querySelector('.btn-text');
            const spinner = payBtn.querySelector('.loading-spinner');
            
            if (loading) {
                payBtn.disabled = true;
                btnText.style.display = 'none';
                spinner.style.display = 'block';
            } else {
                payBtn.disabled = false;
                btnText.style.display = 'block';
                spinner.style.display = 'none';
            }
        }

        // Message system
        function showMessage(text, type = 'info') {
            const message = document.createElement('div');
            message.className = `message ${type}`;
            message.textContent = text;
            
            messageContainer.appendChild(message);
            
            setTimeout(() => {
                if (message.parentNode) {
                    message.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }
            }, 5000);
        }

        function clearMessages() {
            messageContainer.innerHTML = '';
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && paymentModal.style.display === 'block') {
                hideModal();
            }
        });

        // Payment status polling
        let pollingInterval = null;
        let pollingTimeout = null;
        
        function showPaymentWaiting(orderId) {
            // Update modal content to show waiting state
            const modalContent = document.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="waiting-spinner"></div>
                    <h2 style="color: #333; margin: 20px 0;">Endelea kulipia...</h2>
                    <p style="color: #666; margin-bottom: 20px;">Tafashali Kamilisha malipo kwa simu yako</p>
                
                    <div class="waiting-steps" style="margin: 30px 0; text-align: left; max-width: 300px; margin: 30px auto;">
                        <div class="step">📱 Check your phone for USSD prompt</div>
                        <div class="step">💳 Enter your PIN to complete payment</div>
                        <div class="step">⏳ Waiting for confirmation...</div>
                    </div>
                    <button onclick="cancelPayment()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 20px;">
                        Cancel
                    </button>
                </div>
            `;
            
            // Add spinner CSS
            if (!document.getElementById('spinner-style')) {
                const style = document.createElement('style');
                style.id = 'spinner-style';
                style.textContent = `
                    .waiting-spinner {
                        width: 60px;
                        height: 60px;
                        border: 4px solid #f3f3f3;
                        border-top: 4px solid #007bff;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin: 0 auto;
                    }
                    .step {
                        padding: 8px 0;
                        border-left: 3px solid #007bff;
                        padding-left: 15px;
                        margin: 10px 0;
                        background: #f8f9fa;
                        border-radius: 0 5px 5px 0;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        function startPaymentPolling(orderId) {
            let pollCount = 0;
            const maxPolls = 60; // 5 minutes (5 seconds × 60)
            
            pollingInterval = setInterval(async () => {
                pollCount++;
                
                try {
                    const response = await fetch(`check_status.php?order_id=${orderId}&format=json`);
                    const result = await response.json();
                    
                    if (result.status === 'success' && result.payment_status === 'COMPLETED') {
                        // Payment successful!
                        clearInterval(pollingInterval);
                        showPaymentSuccess(orderId);
                        setTimeout(() => {
                            window.location.href = `success.php?order_id=${orderId}`;
                        }, 2000);
                        
                    } else if (result.status === 'success' && result.payment_status === 'FAILED') {
                        // Payment failed
                        clearInterval(pollingInterval);
                        showPaymentFailed();
                        
                    } else if (pollCount >= maxPolls) {
                        // Timeout
                        clearInterval(pollingInterval);
                        showPaymentTimeout(orderId);
                    }
                    
                } catch (error) {
                    console.error('Polling error:', error);
                    if (pollCount >= maxPolls) {
                        clearInterval(pollingInterval);
                        showPaymentTimeout(orderId);
                    }
                }
            }, 5000); // Check every 5 seconds
        }
        
        function showPaymentSuccess(orderId) {
            const modalContent = document.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="width: 80px; height: 80px; background: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <span style="color: white; font-size: 40px;">✓</span>
                    </div>
                    <h2 style="color: #28a745; margin-bottom: 15px;">Payment Successful!</h2>
                    <p style="color: #666;">Redirecting to download page...</p>
                    <p style="font-size: 0.9rem; color: #888;">Order ID: ${orderId}</p>
                </div>
            `;
        }
        
        function showPaymentFailed() {
            const modalContent = document.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="width: 80px; height: 80px; background: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <span style="color: white; font-size: 40px;">✗</span>
                    </div>
                    <h2 style="color: #dc3545; margin-bottom: 15px;">Payment Failed</h2>
                    <p style="color: #666; margin-bottom: 20px;">Your payment could not be processed</p>
                    <button onclick="resetPaymentForm()" style="background: #007bff; color: white; border: none; padding: 15px 30px; border-radius: 5px; cursor: pointer;">
                        Try Again
                    </button>
                </div>
            `;
        }
        
        function showPaymentTimeout(orderId) {
            const modalContent = document.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="width: 80px; height: 80px; background: #ffc107; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <span style="color: white; font-size: 40px;">⏰</span>
                    </div>
                    <h2 style="color: #ffc107; margin-bottom: 15px;">Payment Timeout</h2>
                    <p style="color: #666; margin-bottom: 20px;">We're still waiting for payment confirmation</p>
                    <p style="font-size: 0.9rem; color: #888; margin-bottom: 20px;">Order ID: ${orderId}</p>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button onclick="continuePolling('${orderId}')" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                            Continue Waiting
                        </button>
                        <button onclick="resetPaymentForm()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                            Start Over
                        </button>
                    </div>
                </div>
            `;
        }
        
        function continuePolling(orderId) {
            showPaymentWaiting(orderId);
            startPaymentPolling(orderId);
        }
        
        function cancelPayment() {
            clearInterval(pollingInterval);
            // Reload the page to completely reset everything
            location.reload();
        }
        
        function resetPaymentForm() {
            clearInterval(pollingInterval);
            location.reload(); // Reload the page to reset everything
        }

        
    </script>
</body>
</html> 