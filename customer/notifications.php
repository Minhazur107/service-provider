<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $notificationId = (int)$_GET['id'];
    
    switch ($action) {
        case 'read':
            markNotificationAsRead($notificationId);
            break;
            
        case 'read_all':
            markAllNotificationsAsRead($user['id']);
            break;
    }
    
    // Redirect to remove action parameters
    redirect('notifications.php');
}

// Get notifications
$notifications = getAllNotifications($user['id'], 'customer', 50);
$unreadCount = getNotificationCount($user['id'], 'customer');
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Notifications' : 'বিজ্ঞপ্তি'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .customer-bg {
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
        
        .customer-bg::before {
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
        
        .customer-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .customer-header::before {
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
        
        .customer-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .customer-card::before {
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
        
        .customer-card:hover::before {
            opacity: 1;
        }
        
        .customer-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .nav-link {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
        
        .customer-avatar {
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
        
        .notification-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }
    </style>
</head>
<body class="customer-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>
    <!-- Header -->
    <header class="customer-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-bell text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Notifications' : 'বিজ্ঞপ্তি'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-arrow-left"></i>
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
                    <a href="my_selections.php" class="nav-link">
                        <i class="fas fa-heart"></i>
                        <?php echo $currentLang === 'en' ? 'Selections' : 'নির্বাচন'; ?>
                    </a>
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <?php echo $currentLang === 'en' ? 'Profile' : 'প্রোফাইল'; ?>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="customer-avatar">
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
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="customer-card p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">
                            <i class="fas fa-bell text-purple-600 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'Notifications' : 'বিজ্ঞপ্তি'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Stay updated with your service notifications' : 'আপনার পরিষেবা বিজ্ঞপ্তি দিয়ে আপডেট থাকুন'; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-purple-600"><?php echo count($notifications); ?></div>
                        <div class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Total Notifications' : 'মোট বিজ্ঞপ্তি'; ?>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <div class="mt-2">
                                <span class="notification-badge">
                                    <?php echo $unreadCount; ?> <?php echo $currentLang === 'en' ? 'unread' : 'অপঠিত'; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="?action=read_all" class="action-btn">
                            <i class="fas fa-check-double"></i>
                            <?php echo $currentLang === 'en' ? 'Mark all as read' : 'সব পঠিত হিসাবে চিহ্নিত করুন'; ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="customer-card p-12 text-center">
                    <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-bell text-4xl text-blue-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'No Notifications Yet' : 'এখনও কোনো বিজ্ঞপ্তি নেই'; ?>
                    </h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        <?php echo $currentLang === 'en' ? 'You\'ll see notifications here when providers respond to your bookings.' : 'প্রদানকারীরা আপনার বুকিংয়ে সাড়া দিলে আপনি এখানে বিজ্ঞপ্তি দেখতে পাবেন।'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="customer-card p-6 <?php echo !$notification['is_read'] ? 'border-l-4 border-purple-500' : ''; ?>">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="font-semibold text-gray-800 text-lg">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h3>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="bg-purple-100 text-purple-600 px-2 py-1 rounded-full text-xs font-medium">
                                                <?php echo $currentLang === 'en' ? 'New' : 'নতুন'; ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?php echo $notification['type'] === 'success' ? 'bg-green-100 text-green-600' : 
                                                ($notification['type'] === 'error' ? 'bg-red-100 text-red-600' : 
                                                ($notification['type'] === 'warning' ? 'bg-yellow-100 text-yellow-600' : 'bg-blue-100 text-blue-600')); ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-gray-700 mb-3">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500">
                                            <?php echo formatDateTime($notification['created_at']); ?>
                                        </span>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                            <a href="?action=read&id=<?php echo $notification['id']; ?>" 
                                               class="action-btn">
                                                <i class="fas fa-check"></i>
                                                <?php echo $currentLang === 'en' ? 'Mark as read' : 'পঠিত হিসাবে চিহ্নিত করুন'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="ml-4">
                                    <!-- View Details button removed - related_type field doesn't exist -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white bg-opacity-95 backdrop-blur-sm border-t border-gray-200 py-8 mt-12 relative z-10">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-600">&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>
</body>
</html> 