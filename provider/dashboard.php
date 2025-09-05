<?php
require_once '../includes/functions.php';

// Check if provider is logged in
if (!isProviderLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$provider = getCurrentProvider();

// Get provider's confirmed bookings with customer details
$confirmedBookings = fetchAll("
    SELECT b.*, u.name as customer_name, u.phone as customer_phone, u.location as customer_location,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    LEFT JOIN service_categories sc ON b.category_id = sc.id
    WHERE b.provider_id = ? AND b.status = 'confirmed'
    ORDER BY b.booking_date ASC, b.booking_time ASC
", [$provider['id']]);

// Get provider's reviews
$reviews = fetchAll("
    SELECT r.*, u.name as customer_name, u.phone as customer_phone
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    WHERE r.provider_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 10
", [$provider['id']]);

// Get provider's income statistics
$incomeStats = fetchOne("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
        SUM(CASE WHEN status = 'completed' AND final_price IS NOT NULL THEN final_price ELSE 0 END) as total_income,
        AVG(CASE WHEN status = 'completed' AND final_price IS NOT NULL THEN final_price ELSE NULL END) as avg_booking_value
    FROM bookings 
    WHERE provider_id = ?
", [$provider['id']]);

// Get monthly income for current year
$monthlyIncome = fetchAll("
    SELECT 
        MONTH(updated_at) as month,
        SUM(final_price) as income,
        COUNT(*) as bookings
    FROM bookings 
    WHERE provider_id = ? AND status = 'completed' AND final_price IS NOT NULL 
    AND YEAR(updated_at) = YEAR(CURRENT_DATE())
    GROUP BY MONTH(updated_at)
    ORDER BY month ASC
", [$provider['id']]);

// Compute verified payments and revenue split (admin-verified)
$paymentStats = fetchOne(
    "SELECT SUM(amount) AS gross_amount FROM payments WHERE provider_id = ? AND status = 'verified'",
    [$provider['id']]
);
$grossAmount = (float)($paymentStats['gross_amount'] ?? 0);
$platformRevenue = $grossAmount * 0.10; // 10% platform share
$providerNetIncome = $grossAmount * 0.90; // 90% provider share

// Get recent notifications
$notifications = getUnreadNotifications($provider['id'], 'provider');

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationAsRead($_GET['mark_read']);
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Provider Dashboard' : 'প্রদানকারী ড্যাশবোর্ড'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .provider-bg {
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
        
        .provider-bg::before {
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
        
        .provider-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .provider-header::before {
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
        
        .provider-card {
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
        
        .provider-card::before {
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
        
        .provider-card:hover::before {
            opacity: 1;
        }
        
        .provider-card:hover {
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
        
        .provider-avatar {
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
        
        .action-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        
        .action-btn.whatsapp {
            background: linear-gradient(135deg, #25d366, #128c7e);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3);
        }
        
        .action-btn.whatsapp:hover {
            background: linear-gradient(135deg, #128c7e, #075e54);
            box-shadow: 0 12px 35px rgba(37, 211, 102, 0.4);
        }
        
        .action-btn.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .action-btn.blue:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 12px 35px rgba(59, 130, 246, 0.4);
        }
        
        .income-amount {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .monthly-income-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .monthly-income-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body class="provider-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>
    <!-- Header -->
    <header class="provider-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-tools text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Provider Dashboard' : 'প্রদানকারী ড্যাশবোর্ড'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <?php echo $currentLang === 'en' ? 'Profile' : 'প্রোফাইল'; ?>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="provider-avatar">
                            <?php echo strtoupper(substr($provider['name'], 0, 1)); ?>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($provider['name']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Service Provider' : 'পরিষেবা প্রদানকারী'; ?></div>
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
        <div class="provider-card p-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="provider-avatar">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Welcome back, ' : 'স্বাগতম, '; ?><?php echo htmlspecialchars($provider['name']); ?>!
                        </h1>
                        <p class="text-xl text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Manage your confirmed bookings and view customer details' : 'আপনার নিশ্চিত বুকিং পরিচালনা করুন এবং গ্রাহকের বিবরণ দেখুন'; ?>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-green-600">
                        <?php echo count($confirmedBookings); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Confirmed Bookings' : 'নিশ্চিত বুকিং'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Income Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-gradient-to-br from-blue-500 to-blue-600">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Total Bookings' : 'মোট বুকিং'; ?></p>
                        <p class="stat-number"><?php echo $incomeStats['total_bookings'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-gradient-to-br from-green-500 to-green-600">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Completed' : 'সম্পন্ন'; ?></p>
                        <p class="stat-number"><?php echo $incomeStats['completed_bookings'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-wallet text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Your Income (90%)' : 'আপনার আয় (৯০%)'; ?></p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo formatPrice($providerNetIncome); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo $currentLang === 'en' ? 'From verified payments' : 'যাচাইকৃত পেমেন্ট থেকে'; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-receipt text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Platform Revenue (10%)' : 'প্ল্যাটফর্ম রাজস্ব (১০%)'; ?></p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo formatPrice($platformRevenue); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo $currentLang === 'en' ? 'From verified payments' : 'যাচাইকৃত পেমেন্ট থেকে'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($notifications)): ?>
            <div class="provider-card p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-bell text-purple-600 mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Recent Notifications' : 'সাম্প্রতিক বিজ্ঞপ্তি'; ?>
                </h2>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 hover:shadow-lg transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                    <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <p class="text-sm text-gray-500 mt-3">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo formatDateTime($notification['created_at']); ?>
                                    </p>
                                </div>
                                <a href="?mark_read=<?php echo $notification['id']; ?>" 
                                   class="action-btn blue text-sm">
                                    <i class="fas fa-check mr-1"></i>
                                    <?php echo $currentLang === 'en' ? 'Mark as Read' : 'পঠিত হিসেবে চিহ্নিত করুন'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Confirmed Bookings -->
        <div class="provider-card p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-calendar-check text-purple-600 mr-3"></i>
                <?php echo $currentLang === 'en' ? 'Confirmed Bookings' : 'নিশ্চিত বুকিং'; ?>
            </h2>
            
            <?php if (empty($confirmedBookings)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-calendar-check text-4xl text-purple-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'No Confirmed Bookings' : 'কোনো নিশ্চিত বুকিং নেই'; ?>
                    </h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        <?php echo $currentLang === 'en' ? 'You don\'t have any confirmed bookings yet. New bookings will appear here once customers confirm them.' : 'আপনার এখনও কোনো নিশ্চিত বুকিং নেই। নতুন বুকিং এখানে দেখা যাবে যখন গ্রাহকরা সেগুলি নিশ্চিত করবে।'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($confirmedBookings as $booking): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- Booking Details -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3">
                                        <?php echo $currentLang === 'en' ? 'Booking Details' : 'বুকিং বিবরণ'; ?>
                                    </h3>
                                    <div class="space-y-2 text-sm text-gray-600">
                                        <div><i class="fas fa-calendar mr-2"></i><?php echo formatDate($booking['booking_date']); ?></div>
                                        <div><i class="fas fa-clock mr-2"></i><?php echo $booking['booking_time']; ?></div>
                                        <div><i class="fas fa-tag mr-2"></i><?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?></div>
                                        <?php if ($booking['service_type']): ?>
                                            <div><i class="fas fa-tools mr-2"></i><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($booking['final_price']): ?>
                                            <div><i class="fas fa-money-bill mr-2"></i><?php echo formatPrice($booking['final_price']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Customer Information -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3">
                                        <?php echo $currentLang === 'en' ? 'Customer Information' : 'গ্রাহকের তথ্য'; ?>
                                    </h3>
                                    <div class="space-y-2 text-sm text-gray-600">
                                        <div><i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                        <div><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($booking['customer_phone']); ?></div>
                                        <?php if ($booking['customer_location']): ?>
                                            <div><i class="fas fa-map-marker-alt mr-2"></i><?php echo htmlspecialchars($booking['customer_location']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Customer Address -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3">
                                        <?php echo $currentLang === 'en' ? 'Service Address' : 'সেবার ঠিকানা'; ?>
                                    </h3>
                                    <div class="text-sm text-gray-600">
                                        <?php if ($booking['customer_address']): ?>
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <i class="fas fa-home mr-2"></i>
                                                <?php echo nl2br(htmlspecialchars($booking['customer_address'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-gray-400 italic">
                                                <?php echo $currentLang === 'en' ? 'No address provided' : 'কোনো ঠিকানা দেওয়া হয়নি'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($booking['notes']): ?>
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <h4 class="font-medium text-gray-800 mb-2">
                                        <?php echo $currentLang === 'en' ? 'Customer Notes' : 'গ্রাহকের নোট'; ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                        <?php echo htmlspecialchars($booking['notes']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="mt-4 pt-4 border-t border-gray-200 flex space-x-3">
                                <a href="tel:<?php echo $booking['customer_phone']; ?>" 
                                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                                    <i class="fas fa-phone mr-2"></i><?php echo $currentLang === 'en' ? 'Call Customer' : 'গ্রাহককে কল করুন'; ?>
                                </a>
                                
                                <a href="https://wa.me/<?php echo $booking['customer_phone']; ?>?text=<?php echo urlencode($currentLang === 'en' ? 'Hi, I am calling about your confirmed booking. When would you like me to arrive?' : 'হাই, আমি আপনার নিশ্চিত বুকিং নিয়ে কল করছি। আপনি কখন আমাকে আসতে চান?'); ?>" 
                                   target="_blank"
                                   class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
                                    <i class="fab fa-whatsapp mr-2"></i><?php echo $currentLang === 'en' ? 'WhatsApp' : 'হোয়াটসঅ্যাপ'; ?>
                                </a>
                                
                                <button onclick="markAsCompleted(<?php echo $booking['id']; ?>)" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                    <i class="fas fa-check mr-2"></i><?php echo $currentLang === 'en' ? 'Mark Completed' : 'সম্পন্ন হিসেবে চিহ্নিত করুন'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customer Reviews -->
        <div class="provider-card p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-star text-purple-600 mr-3"></i>
                <?php echo $currentLang === 'en' ? 'Customer Reviews' : 'গ্রাহক পর্যালোচনা'; ?>
            </h2>
            
            <?php if (empty($reviews)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-gradient-to-br from-yellow-100 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-star text-4xl text-yellow-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'No Reviews Yet' : 'এখনও কোনো পর্যালোচনা নেই'; ?>
                    </h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        <?php echo $currentLang === 'en' ? 'You haven\'t received any reviews yet. Complete more services to start building your reputation.' : 'আপনি এখনও কোনো পর্যালোচনা পাননি। আপনার খ্যাতি তৈরি করতে আরও পরিষেবা সম্পন্ন করুন।'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($reviews as $review): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star text-<?php echo $i <= $review['rating'] ? 'yellow' : 'gray'; ?>-400"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo $review['rating']; ?>/5</span>
                                </div>
                                <span class="text-sm text-gray-500"><?php echo formatDateTime($review['created_at']); ?></span>
                            </div>
                            
                            <?php if ($review['review_text']): ?>
                                <p class="text-gray-700 mb-3"><?php echo htmlspecialchars($review['review_text']); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center">
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($review['customer_name']); ?>
                                </p>
                                <?php if ($review['review_photo']): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($review['review_photo']); ?>" 
                                       target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-image mr-1"></i>
                                        <?php echo $currentLang === 'en' ? 'View Photo' : 'ছবি দেখুন'; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Monthly Income Chart -->
        <?php if (!empty($monthlyIncome)): ?>
            <div class="provider-card p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-chart-line text-purple-600 mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Monthly Income (Current Year)' : 'মাসিক আয় (বর্তমান বছর)'; ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <?php 
                    $months = [
                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
                        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                    ];
                    $monthsBn = [
                        1 => 'জানু', 2 => 'ফেব্রু', 3 => 'মার্চ', 4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
                        7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্ট', 10 => 'অক্টো', 11 => 'নভে', 12 => 'ডিসে'
                    ];
                    
                    $monthlyData = [];
                    foreach ($monthlyIncome as $data) {
                        $monthlyData[$data['month']] = $data;
                    }
                    
                    for ($month = 1; $month <= 12; $month++):
                        $data = $monthlyData[$month] ?? null;
                        $income = $data ? $data['income'] : 0;
                        $bookings = $data ? $data['bookings'] : 0;
                    ?>
                        <div class="monthly-income-card hover:scale-105 transition-transform duration-300">
                            <div class="text-sm opacity-90 mb-2">
                                <?php echo $currentLang === 'en' ? $months[$month] : $monthsBn[$month]; ?>
                            </div>
                            <div class="text-xl font-bold mb-1">
                                <?php echo formatPrice($income); ?>
                            </div>
                            <div class="text-xs opacity-75">
                                <?php echo $bookings; ?> <?php echo $currentLang === 'en' ? 'bookings' : 'বুকিং'; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-white bg-opacity-95 backdrop-blur-sm border-t border-gray-200 py-8 mt-12 relative z-10">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-600">&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>

    <script>
        function markAsCompleted(bookingId) {
            if (confirm('<?php echo $currentLang === 'en' ? 'Mark this booking as completed?' : 'এই বুকিং সম্পন্ন হিসেবে চিহ্নিত করবেন?'; ?>')) {
                // You can implement AJAX call here to mark as completed
                // For now, redirect to a completion page
                window.location.href = `complete_booking.php?id=${bookingId}`;
            }
        }
    </script>
</body>
</html>