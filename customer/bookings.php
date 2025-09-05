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

// Get filter parameters
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = ["b.customer_id = ?"];
$params = [$user['id']];

if ($status) {
    $whereConditions[] = "b.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countSql = "SELECT COUNT(*) as count FROM bookings b WHERE $whereClause";
$totalCount = fetchOne($countSql, $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get bookings
$sql = "SELECT b.*, sp.name as provider_name, sp.phone as provider_phone, 
        sc.name as category_name, sc.name_bn as category_name_bn
        FROM bookings b
        JOIN service_providers sp ON b.provider_id = sp.id
        JOIN service_categories sc ON b.category_id = sc.id
        WHERE $whereClause
        ORDER BY b.created_at DESC
        LIMIT $perPage OFFSET $offset";

$bookings = fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'My Bookings' : 'আমার বুকিং'; ?> - S24</title>
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
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .pagination-btn {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
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
                        <i class="fas fa-calendar-alt text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'My Bookings' : 'আমার বুকিং'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-arrow-left"></i>
                        <?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <?php echo $currentLang === 'en' ? 'Notifications' : 'বিজ্ঞপ্তি'; ?>
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
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="customer-card p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">
                            <i class="fas fa-calendar-alt text-purple-600 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'My Bookings' : 'আমার বুকিং'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Track and manage your service bookings' : 'আপনার পরিষেবা বুকিং ট্র্যাক এবং পরিচালনা করুন'; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-purple-600"><?php echo $totalCount; ?></div>
                        <div class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Total Bookings' : 'মোট বুকিং'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="customer-card p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-64">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Filter by Status' : 'স্ট্যাটাস অনুযায়ী ফিল্টার'; ?>
                        </label>
                        <select name="status" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value=""><?php echo $currentLang === 'en' ? 'All Statuses' : 'সব স্ট্যাটাস'; ?></option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমান'; ?>
                            </option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? 'Confirmed' : 'নিশ্চিত'; ?>
                            </option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? 'Completed' : 'সম্পন্ন'; ?>
                            </option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? 'Cancelled' : 'বাতিল'; ?>
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-300 font-semibold">
                            <i class="fas fa-filter mr-2"></i>
                            <?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bookings List -->
            <?php if (empty($bookings)): ?>
                <div class="customer-card p-12 text-center">
                    <div class="w-24 h-24 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-calendar-times text-4xl text-purple-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'No Bookings Found' : 'কোনো বুকিং পাওয়া যায়নি'; ?>
                    </h3>
                    <p class="text-gray-500 max-w-md mx-auto mb-6">
                        <?php echo $currentLang === 'en' ? 'You haven\'t made any bookings yet. Start by searching for services.' : 'আপনি এখনও কোনো বুকিং করেননি। পরিষেবা অনুসন্ধান করে শুরু করুন।'; ?>
                    </p>
                    <a href="../search.php" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-300 font-semibold inline-flex items-center">
                        <i class="fas fa-search mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Search Services' : 'পরিষেবা অনুসন্ধান'; ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="customer-card p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                                        <i class="fas fa-tools text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?>
                                        </h3>
                                        <p class="text-gray-600">
                                            <?php echo $currentLang === 'en' ? 'Provider' : 'প্রদানকারী'; ?>: <?php echo htmlspecialchars($booking['provider_name']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php 
                                        $statusText = [
                                            'pending' => $currentLang === 'en' ? 'Pending' : 'অপেক্ষমান',
                                            'confirmed' => $currentLang === 'en' ? 'Confirmed' : 'নিশ্চিত',
                                            'completed' => $currentLang === 'en' ? 'Completed' : 'সম্পন্ন',
                                            'cancelled' => $currentLang === 'en' ? 'Cancelled' : 'বাতিল'
                                        ];
                                        echo $statusText[$booking['status']] ?? $booking['status'];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-calendar text-purple-600"></i>
                                    <span class="text-gray-700">
                                        <?php echo formatDate($booking['booking_date']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-clock text-purple-600"></i>
                                    <span class="text-gray-700"><?php echo $booking['booking_time']; ?></span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-phone text-purple-600"></i>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($booking['provider_phone']); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($booking['final_price']): ?>
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg mb-4">
                                    <span class="text-gray-700 font-medium">
                                        <?php echo $currentLang === 'en' ? 'Final Price' : 'চূড়ান্ত মূল্য'; ?>:
                                    </span>
                                    <span class="text-2xl font-bold text-purple-600">
                                        <?php echo formatPrice($booking['final_price']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['notes']): ?>
                                <div class="mb-4">
                                    <h4 class="font-medium text-gray-800 mb-2">
                                        <?php echo $currentLang === 'en' ? 'Notes' : 'নোট'; ?>:
                                    </h4>
                                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">
                                        <?php echo htmlspecialchars($booking['notes']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex flex-wrap gap-3">
                                <a href="tel:<?php echo $booking['provider_phone']; ?>" 
                                   class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 font-semibold">
                                    <i class="fas fa-phone mr-2"></i>
                                    <?php echo $currentLang === 'en' ? 'Call Provider' : 'প্রদানকারীকে কল করুন'; ?>
                                </a>
                                
                                <a href="https://wa.me/<?php echo $booking['provider_phone']; ?>?text=<?php echo urlencode($currentLang === 'en' ? 'Hi, I have a booking with you. Can you please confirm the details?' : 'হাই, আমার আপনার সাথে একটি বুকিং আছে। আপনি কি বিবরণ নিশ্চিত করতে পারেন?'); ?>" 
                                   target="_blank"
                                   class="bg-gradient-to-r from-green-400 to-green-500 text-white px-4 py-2 rounded-lg hover:from-green-500 hover:to-green-600 transition-all duration-300 font-semibold">
                                    <i class="fab fa-whatsapp mr-2"></i>
                                    <?php echo $currentLang === 'en' ? 'WhatsApp' : 'হোয়াটসঅ্যাপ'; ?>
                                </a>
                                
                                <?php if ($booking['status'] === 'completed'): ?>
                                    <a href="review.php?booking_id=<?php echo $booking['id']; ?>" 
                                       class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-4 py-2 rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-300 font-semibold">
                                        <i class="fas fa-star mr-2"></i>
                                        <?php echo $currentLang === 'en' ? 'Write Review' : 'পর্যালোচনা লিখুন'; ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>" 
                                       class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 font-semibold"
                                       onclick="return confirm('<?php echo $currentLang === 'en' ? 'Are you sure you want to cancel this booking?' : 'আপনি কি নিশ্চিত যে আপনি এই বুকিং বাতিল করতে চান?'; ?>')">
                                        <i class="fas fa-times mr-2"></i>
                                        <?php echo $currentLang === 'en' ? 'Cancel Booking' : 'বুকিং বাতিল করুন'; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="customer-card p-6 mt-6">
                        <div class="flex justify-center items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left mr-1"></i>
                                    <?php echo $currentLang === 'en' ? 'Previous' : 'পূর্ববর্তী'; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>" 
                                   class="pagination-btn">
                                    <?php echo $currentLang === 'en' ? 'Next' : 'পরবর্তী'; ?>
                                    <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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