<?php
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

// Handle actions
if (isset($_POST['action']) && isset($_POST['review_id'])) {
    $reviewId = (int)$_POST['review_id'];
    $action = $_POST['action'];
    
    if ($action === 'delete') {
        executeQuery("DELETE FROM reviews WHERE id = ?", [$reviewId]);
        setFlashMessage('success', 'Review deleted successfully');
    }
    
    redirect('reviews.php');
}

// Get filters
$rating = $_GET['rating'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($rating) {
    $whereConditions[] = "r.rating = ?";
    $params[] = $rating;
}

if ($search) {
    $whereConditions[] = "(u.name LIKE ? OR sp.name LIKE ? OR r.review_text LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($date_from) {
    $whereConditions[] = "r.created_at >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $whereConditions[] = "r.created_at <= ?";
    $params[] = $date_to;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get reviews
$reviews = fetchAll("
    SELECT r.*, u.name as customer_name, u.phone as customer_phone, 
           sp.name as provider_name, sp.phone as provider_phone,
           b.service_type, b.booking_date
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    JOIN service_providers sp ON r.provider_id = sp.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    $whereClause
    ORDER BY r.created_at DESC
", $params);

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
    <title><?php echo $currentLang === 'en' ? 'Manage Reviews' : 'পর্যালোচনা পরিচালনা'; ?> - S24 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .admin-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .floating-element:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 30%;
            right: 30%;
            animation-delay: 1s;
        }
        
        .floating-element:nth-child(5) {
            width: 40px;
            height: 40px;
            bottom: 40%;
            right: 10%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .nav-link {
            color: #000000;
            transition: all 0.3s ease;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: #4f46e5;
            background: rgba(79, 70, 229, 0.1);
        }
        
        .nav-link.active {
            color: #4f46e5;
            background: rgba(79, 70, 229, 0.1);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .review-card {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.2);
        }
        
        .rating-stars {
            color: #f59e0b;
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
        <div class="floating-element"></div>
    </div>

    <!-- Header -->
    <header class="admin-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-home mr-2"></i>S24
                    </a>
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-shield-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Admin Panel' : 'অ্যাডমিন প্যানেল'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users mr-2"></i><?php echo $currentLang === 'en' ? 'Users' : 'ব্যবহারকারী'; ?>
                    </a>
                    <a href="providers.php" class="nav-link">
                        <i class="fas fa-user-check mr-2"></i><?php echo $currentLang === 'en' ? 'Providers' : 'প্রদানকারী'; ?>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link active">
                        <i class="fas fa-star mr-2"></i><?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="payments.php" class="nav-link">
                        <i class="fas fa-credit-card mr-2"></i><?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?>
                    </a>
                    <span class="text-gray-700 font-medium">
                        <i class="fas fa-user-shield mr-2"></i><?php echo htmlspecialchars($admin['username']); ?> (<?php echo $admin['role']; ?>)
                    </span>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8 relative z-10">
        <!-- Page Header -->
        <div class="admin-card p-8 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-2">
                        <i class="fas fa-star mr-3"></i><?php echo $currentLang === 'en' ? 'Manage Reviews' : 'পর্যালোচনা পরিচালনা'; ?>
                    </h1>
                    <p class="text-gray-600 text-lg">
                        <i class="fas fa-chart-line mr-2"></i><?php echo count($reviews); ?> <?php echo $currentLang === 'en' ? 'total reviews' : 'মোট পর্যালোচনা'; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-purple-600">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="admin-card p-6 mb-8">
                <div class="flex items-center <?php echo $flash['type'] === 'success' ? 'text-green-700' : 'text-red-700'; ?>">
                    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3 text-xl"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($flash['message']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="admin-card p-6 mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-filter mr-2 text-purple-600"></i><?php echo $currentLang === 'en' ? 'Filter Reviews' : 'পর্যালোচনা ফিল্টার'; ?>
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search mr-1"></i><?php echo $currentLang === 'en' ? 'Search' : 'অনুসন্ধান'; ?>
                    </label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo $currentLang === 'en' ? 'Customer, provider, review...' : 'গ্রাহক, প্রদানকারী, পর্যালোচনা...'; ?>"
                           class="form-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-star mr-1"></i><?php echo $currentLang === 'en' ? 'Rating' : 'রেটিং'; ?>
                    </label>
                    <select name="rating" class="form-input w-full">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Ratings' : 'সব রেটিং'; ?></option>
                        <option value="5" <?php echo $rating === '5' ? 'selected' : ''; ?>>5 <?php echo $currentLang === 'en' ? 'Stars' : 'তারকা'; ?></option>
                        <option value="4" <?php echo $rating === '4' ? 'selected' : ''; ?>>4 <?php echo $currentLang === 'en' ? 'Stars' : 'তারকা'; ?></option>
                        <option value="3" <?php echo $rating === '3' ? 'selected' : ''; ?>>3 <?php echo $currentLang === 'en' ? 'Stars' : 'তারকা'; ?></option>
                        <option value="2" <?php echo $rating === '2' ? 'selected' : ''; ?>>2 <?php echo $currentLang === 'en' ? 'Stars' : 'তারকা'; ?></option>
                        <option value="1" <?php echo $rating === '1' ? 'selected' : ''; ?>>1 <?php echo $currentLang === 'en' ? 'Star' : 'তারকা'; ?></option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-1"></i><?php echo $currentLang === 'en' ? 'From Date' : 'শুরুর তারিখ'; ?>
                    </label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                           class="form-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-1"></i><?php echo $currentLang === 'en' ? 'To Date' : 'শেষের তারিখ'; ?>
                    </label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                           class="form-input w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full">
                        <i class="fas fa-search mr-2"></i><?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Reviews List -->
        <div class="admin-card overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-list mr-3 text-purple-600"></i><?php echo $currentLang === 'en' ? 'All Reviews' : 'সব পর্যালোচনা'; ?>
                </h3>
            </div>
            
            <?php if (empty($reviews)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-star text-6xl text-gray-300 mb-6"></i>
                    <p class="text-gray-500 text-lg">
                        <?php echo $currentLang === 'en' ? 'No reviews found' : 'কোনো পর্যালোচনা পাওয়া যায়নি'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="p-6 space-y-6">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                                <!-- Rating -->
                                <div class="flex items-center">
                                    <div class="rating-stars text-2xl mr-3">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-yellow-500' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-lg font-bold text-gray-800"><?php echo $review['rating']; ?>/5</span>
                                </div>
                                
                                <!-- Customer Info -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 text-lg">
                                        <i class="fas fa-user mr-2 text-blue-600"></i><?php echo htmlspecialchars($review['customer_name']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($review['customer_phone']); ?>
                                    </p>
                                </div>
                                
                                <!-- Provider Info -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 text-lg">
                                        <i class="fas fa-user-check mr-2 text-green-600"></i><?php echo htmlspecialchars($review['provider_name']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($review['provider_phone']); ?>
                                    </p>
                                </div>
                                
                                <!-- Review Details -->
                                <div>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <i class="fas fa-calendar mr-2"></i><?php echo formatDate($review['created_at']); ?>
                                    </p>
                                    <?php if (isset($review['service_type']) && $review['service_type']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-tools mr-2"></i><?php echo htmlspecialchars($review['service_type']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Review Text -->
                            <div class="mt-4 pt-4 border-t border-yellow-200">
                                <p class="text-gray-700 text-lg">
                                    <i class="fas fa-comment mr-2 text-yellow-600"></i><?php echo htmlspecialchars($review['review_text']); ?>
                                </p>
                            </div>
                            
                            <!-- Actions -->
                            <div class="mt-4 flex justify-end">
                                <form method="POST" onsubmit="return confirm('<?php echo $currentLang === 'en' ? 'Delete this review?' : 'এই পর্যালোচনা মুছে ফেলবেন?'; ?>')">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-danger">
                                        <i class="fas fa-trash mr-2"></i><?php echo $currentLang === 'en' ? 'Delete' : 'মুছুন'; ?>
                                    </button>
                                </form>
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