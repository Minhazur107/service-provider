<?php
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

// Get statistics
$totalProviders = fetchOne("SELECT COUNT(*) as count FROM service_providers")['count'];
$pendingProviders = fetchOne("SELECT COUNT(*) as count FROM service_providers WHERE verification_status = 'pending'")['count'];
$totalCustomers = fetchOne("SELECT COUNT(*) as count FROM users")['count'];
$totalBookings = fetchOne("SELECT COUNT(*) as count FROM bookings")['count'];
$pendingBookings = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")['count'];
$completedBookings = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'completed'")['count'];

// Verified payments aggregates for revenue split
$verifiedGross = fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'")['total'] ?? 0;
$platformRevenueTotal = (float)$verifiedGross * 0.10; // 10% to platform
$providerPayoutsTotal = (float)$verifiedGross * 0.90; // 90% to providers (for reference)

// Admin unread notifications
$notifications = getUnreadNotifications($admin['id'], 'admin');

// Get recent pending providers
$recentPendingProviders = fetchAll("
    SELECT sp.*, sc.name as category_name, sc.name_bn as category_name_bn
    FROM service_providers sp
    JOIN service_categories sc ON sp.category_id = sc.id
    WHERE sp.verification_status = 'pending'
    ORDER BY sp.created_at DESC
    LIMIT 5
");

// Get recent bookings
$recentBookings = fetchAll("
    SELECT b.*, u.name as customer_name, sp.name as provider_name, sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN service_categories sc ON b.category_id = sc.id
    ORDER BY b.created_at DESC
    LIMIT 10
");

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Admin Dashboard' : 'অ্যাডমিন ড্যাশবোর্ড'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .admin-bg {
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
        
        .admin-bg::before {
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
        
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .admin-header::before {
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
        
        .admin-card {
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
        
        .admin-card::before {
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
        
        .admin-card:hover::before {
            opacity: 1;
        }
        
        .admin-card:hover {
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
            color: #000000;
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
        
        .welcome-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.9));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
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
        
        .welcome-section:hover .admin-avatar {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
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
        
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .status-verified {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .status-completed {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="admin-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <!-- Header -->
    <header class="admin-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-shield-alt text-purple-600 mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Admin Panel' : 'অ্যাডমিন প্যানেল'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <?php echo $currentLang === 'en' ? 'Users' : 'ব্যবহারকারী'; ?>
                    </a>
                    <a href="providers.php" class="nav-link">
                        <i class="fas fa-user-check"></i>
                        <?php echo $currentLang === 'en' ? 'Providers' : 'প্রদানকারী'; ?>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="payments.php" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        <?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="admin-avatar">
                            <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($admin['username']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo ucfirst($admin['role']); ?></div>
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
        <div class="welcome-section p-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Welcome back,' : 'স্বাগতম,' ?> 
                            <span class="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </span>
                        </h1>
                        <p class="text-xl text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Here\'s what\'s happening with your platform today' : 'আজ আপনার প্ল্যাটফর্মে কী ঘটছে তা এখানে দেখুন'; ?>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-800">
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo date('g:i A'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Providers -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-blue-500 to-blue-600">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo number_format($totalProviders); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Total Providers' : 'মোট প্রদানকারী'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Active on platform' : 'প্ল্যাটফর্মে সক্রিয়'; ?>
                    </span>
                    <a href="providers.php" class="text-blue-600 hover:text-blue-700 font-medium">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Pending Providers -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-yellow-500 to-orange-500">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo number_format($pendingProviders); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Pending Verification' : 'যাচাইকরণ অপেক্ষমাণ'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Awaiting approval' : 'অনুমোদনের অপেক্ষায়'; ?>
                    </span>
                    <a href="providers.php?status=pending" class="text-yellow-600 hover:text-yellow-700 font-medium">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Total Customers -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-green-500 to-emerald-600">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo number_format($totalCustomers); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Total Customers' : 'মোট গ্রাহক'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Registered users' : 'নিবন্ধিত ব্যবহারকারী'; ?>
                    </span>
                    <a href="users.php" class="text-green-600 hover:text-green-700 font-medium">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Total Bookings -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-gradient-to-br from-purple-500 to-pink-600">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo number_format($totalBookings); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Total Bookings' : 'মোট বুকিং'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        <?php echo $currentLang === 'en' ? 'Service requests' : 'সেবার অনুরোধ'; ?>
                    </span>
                    <a href="bookings.php" class="text-purple-600 hover:text-purple-700 font-medium">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Revenue Section -->
        <div class="admin-card p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-line text-green-600 mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Platform Revenue' : 'প্ল্যাটফর্ম রাজস্ব'; ?>
                </h2>
                <div class="text-right">
                    <div class="text-3xl font-bold text-green-600">
                        ৳<?php echo number_format($platformRevenueTotal, 2); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Total Platform Revenue' : 'মোট প্ল্যাটফর্ম রাজস্ব'; ?>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-gray-600">
                                <?php echo $currentLang === 'en' ? 'Gross Revenue' : 'মোট রাজস্ব'; ?>
                            </div>
                            <div class="text-2xl font-bold text-green-600">
                                ৳<?php echo number_format($verifiedGross, 2); ?>
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-4 rounded-xl border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-gray-600">
                                <?php echo $currentLang === 'en' ? 'Provider Payouts' : 'প্রদানকারী পেমেন্ট'; ?>
                            </div>
                            <div class="text-2xl font-bold text-blue-600">
                                ৳<?php echo number_format($providerPayoutsTotal, 2); ?>
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-hand-holding-usd text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Pending Providers -->
            <div class="table-container">
                <div class="table-header">
                    <i class="fas fa-clock mr-2"></i>
                    <?php echo $currentLang === 'en' ? 'Recent Pending Providers' : 'সাম্প্রতিক অপেক্ষমাণ প্রদানকারী'; ?>
                </div>
                <div class="p-6">
                    <?php if (empty($recentPendingProviders)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                            <p class="text-gray-600">
                                <?php echo $currentLang === 'en' ? 'No pending providers' : 'কোন অপেক্ষমাণ প্রদানকারী নেই'; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentPendingProviders as $provider): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-semibold">
                                            <?php echo strtoupper(substr($provider['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($provider['name']); ?></div>
                                            <div class="text-sm text-gray-600">
                                                <?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="status-badge status-pending">
                                            <?php echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমাণ'; ?>
                                        </span>
                                        <a href="providers.php?id=<?php echo $provider['id']; ?>" class="text-blue-600 hover:text-blue-700">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="providers.php?status=pending" class="text-blue-600 hover:text-blue-700 font-medium">
                                <?php echo $currentLang === 'en' ? 'View All Pending' : 'সব অপেক্ষমাণ দেখুন'; ?>
                                <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="table-container">
                <div class="table-header">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    <?php echo $currentLang === 'en' ? 'Recent Bookings' : 'সাম্প্রতিক বুকিং'; ?>
                </div>
                <div class="p-6">
                    <?php if (empty($recentBookings)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600">
                                <?php echo $currentLang === 'en' ? 'No recent bookings' : 'কোন সাম্প্রতিক বুকিং নেই'; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentBookings as $booking): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-full flex items-center justify-center text-white font-semibold">
                                            <?php echo strtoupper(substr($booking['customer_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                            <div class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($booking['provider_name']); ?> - 
                                                <?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                        <a href="bookings.php?id=<?php echo $booking['id']; ?>" class="text-blue-600 hover:text-blue-700">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="bookings.php" class="text-blue-600 hover:text-blue-700 font-medium">
                                <?php echo $currentLang === 'en' ? 'View All Bookings' : 'সব বুকিং দেখুন'; ?>
                                <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
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
        document.querySelectorAll('.stat-card, .admin-card, .table-container').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>