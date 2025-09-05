<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

// Get payment history
$payments = fetchAll("
    SELECT p.*, b.booking_date, b.booking_time, sp.name as provider_name, 
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN service_providers sp ON b.provider_id = sp.id
    LEFT JOIN service_categories sc ON b.category_id = sc.id
    WHERE p.customer_id = ?
    ORDER BY p.created_at DESC
", [$user['id']]);

// Calculate payment statistics
$totalPaid = fetchOne("SELECT SUM(amount) as total FROM payments WHERE customer_id = ? AND status = 'verified'", [$user['id']])['total'] ?? 0;
$pendingPayments = fetchOne("SELECT COUNT(*) as count FROM payments WHERE customer_id = ? AND status = 'pending'", [$user['id']])['count'] ?? 0;
$verifiedPayments = fetchOne("SELECT COUNT(*) as count FROM payments WHERE customer_id = ? AND status = 'verified'", [$user['id']])['count'] ?? 0;

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Payment History' : 'পেমেন্ট ইতিহাস'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .payment-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .payment-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .floating-elements {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 150px;
            height: 150px;
            top: 5%;
            left: 5%;
            animation-delay: 0s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        .floating-element:nth-child(2) {
            width: 100px;
            height: 100px;
            top: 15%;
            right: 10%;
            animation-delay: 2s;
            background: linear-gradient(45deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
        }
        
        .floating-element:nth-child(3) {
            width: 120px;
            height: 120px;
            bottom: 15%;
            left: 10%;
            animation-delay: 4s;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.2));
        }
        
        .floating-element:nth-child(4) {
            width: 80px;
            height: 80px;
            bottom: 5%;
            right: 15%;
            animation-delay: 1s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            50% { transform: translateY(-30px) rotate(180deg) scale(1.1); }
        }
        
        .payment-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .payment-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 4s ease-in-out infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .payment-card:hover::before {
            opacity: 1;
        }
        
        .payment-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 4s ease-in-out infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .stat-icon {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .nav-link {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .admin-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-verified {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .status-failed {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .payment-amount {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .provider-avatar {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body class="payment-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <!-- Header -->
    <header class="payment-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-credit-card text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Payment History' : 'পেমেন্ট ইতিহাস'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <?php echo $currentLang === 'en' ? 'Notifications' : 'নোটিফিকেশন'; ?>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="admin-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?></div>
                        </div>
                    </div>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8 relative z-10">
        <!-- Welcome Section -->
        <div class="payment-card p-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="admin-avatar">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Payment History' : 'পেমেন্ট ইতিহাস'; ?>
                        </h1>
                        <p class="text-xl text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Track all your service payments and transactions' : 'আপনার সমস্ত পরিষেবা পেমেন্ট এবং লেনদেন ট্র্যাক করুন'; ?>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-green-600">
                        ৳<?php echo number_format($totalPaid, 2); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Total Paid' : 'মোট প্রদত্ত'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Payments -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-green-500 to-emerald-600">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo number_format($verifiedPayments); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Verified Payments' : 'যাচাইকৃত পেমেন্ট'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Successfully processed' : 'সফলভাবে প্রক্রিয়াকৃত'; ?>
                    </span>
                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-white text-sm"></i>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-yellow-500 to-orange-500">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo number_format($pendingPayments); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Pending Payments' : 'অপেক্ষমাণ পেমেন্ট'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Awaiting verification' : 'যাচাইকরণের অপেক্ষায়'; ?>
                    </span>
                    <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-white text-sm"></i>
                    </div>
                </div>
            </div>

            <!-- Total Amount -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-purple-500 to-pink-600">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number">৳<?php echo number_format($totalPaid, 0); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Total Amount' : 'মোট পরিমাণ'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Verified payments only' : 'শুধুমাত্র যাচাইকৃত পেমেন্ট'; ?>
                    </span>
                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-white text-sm"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="payment-card">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-history text-purple-600 mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Payment History' : 'পেমেন্ট ইতিহাস'; ?>
                </h2>
                <p class="text-gray-600 mt-2">
                    <?php echo $currentLang === 'en' ? 'All your payment transactions in chronological order' : 'ক্রোনোলজিকাল ক্রমে আপনার সমস্ত পেমেন্ট লেনদেন'; ?>
                </p>
            </div>
            
            <div class="p-6">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-12">
                        <div class="w-24 h-24 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-credit-card text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">
                            <?php echo $currentLang === 'en' ? 'No Payment History' : 'কোন পেমেন্ট ইতিহাস নেই'; ?>
                        </h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            <?php echo $currentLang === 'en' ? 'You haven\'t made any payments yet. Start booking services to see your payment history here.' : 'আপনি এখনও কোন পেমেন্ট করেননি। আপনার পেমেন্ট ইতিহাস দেখতে এখানে পরিষেবা বুকিং শুরু করুন।'; ?>
                        </p>
                        <a href="../search.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            <i class="fas fa-search mr-2"></i>
                            <?php echo $currentLang === 'en' ? 'Find Services' : 'পরিষেবা খুঁজুন'; ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($payments as $payment): ?>
                            <div class="bg-gray-50 rounded-xl p-6 hover:bg-gray-100 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="provider-avatar">
                                            <?php echo strtoupper(substr($payment['provider_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($payment['provider_name']); ?>
                                            </h3>
                                            <p class="text-gray-600">
                                                <?php echo $currentLang === 'en' ? $payment['category_name'] : $payment['category_name_bn']; ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo $currentLang === 'en' ? 'Booking Date:' : 'বুকিং তারিখ:'; ?> 
                                                <?php echo date('M j, Y', strtotime($payment['booking_date'])); ?>
                                                <?php if ($payment['booking_time']): ?>
                                                    at <?php echo date('g:i A', strtotime($payment['booking_time'])); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <div class="payment-amount mb-2">
                                            ৳<?php echo number_format($payment['amount'], 2); ?>
                                        </div>
                                        <div class="mb-2">
                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                <?php 
                                                switch($payment['status']) {
                                                    case 'verified':
                                                        echo $currentLang === 'en' ? 'Verified' : 'যাচাইকৃত';
                                                        break;
                                                    case 'pending':
                                                        echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমাণ';
                                                        break;
                                                    case 'failed':
                                                        echo $currentLang === 'en' ? 'Failed' : 'ব্যর্থ';
                                                        break;
                                                    default:
                                                        echo ucfirst($payment['status']);
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($payment['transaction_id']): ?>
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600">
                                                <?php echo $currentLang === 'en' ? 'Transaction ID:' : 'লেনদেন আইডি:'; ?>
                                            </span>
                                            <span class="font-mono text-gray-800">
                                                <?php echo htmlspecialchars($payment['transaction_id']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
        
        // Add intersection observer for animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe all cards
        document.querySelectorAll('.stat-card, .payment-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>
