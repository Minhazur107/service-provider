<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

// Get service history (completed bookings)
$serviceHistory = fetchAll("
    SELECT b.*, sp.name as provider_name, sp.phone as provider_phone, sp.service_areas,
           sc.name as category_name, sc.name_bn as category_name_bn,
           r.rating, r.review_text, r.created_at as review_date
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    LEFT JOIN service_categories sc ON b.category_id = sc.id
    LEFT JOIN reviews r ON b.id = r.booking_id
    WHERE b.customer_id = ? AND b.status = 'completed'
    ORDER BY b.booking_date DESC, b.booking_time DESC
", [$user['id']]);

// Calculate service statistics
$totalServices = count($serviceHistory);
$totalSpent = fetchOne("
    SELECT SUM(p.amount) as total 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id 
    WHERE b.customer_id = ? AND b.status = 'completed' AND p.status = 'verified'
", [$user['id']])['total'] ?? 0;
$averageRating = fetchOne("
    SELECT AVG(r.rating) as avg_rating 
    FROM reviews r 
    JOIN bookings b ON r.booking_id = b.id 
    WHERE b.customer_id = ? AND b.status = 'completed'
", [$user['id']])['avg_rating'] ?? 0;

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
    <title><?php echo $currentLang === 'en' ? 'Service History' : 'পরিষেবার ইতিহাস'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .history-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .history-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .history-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
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
            color: white;
        }
        .service-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
    </style>
</head>
<body class="history-bg min-h-screen">
    <!-- Header -->
    <header class="bg-white bg-opacity-95 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-history text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'Service History' : 'পরিষেবার ইতিহাস'; ?>
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
                    <a href="payments.php" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        <?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?></div>
                        </div>
                    </div>
                    <a href="?logout=1" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- Welcome Section -->
        <div class="history-card p-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white text-2xl">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Service History' : 'পরিষেবার ইতিহাস'; ?>
                        </h1>
                        <p class="text-xl text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Track all your completed services and past experiences' : 'আপনার সমস্ত সম্পন্ন পরিষেবা এবং অতীত অভিজ্ঞতা ট্র্যাক করুন'; ?>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-green-600">
                        <?php echo number_format($totalServices); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Services Completed' : 'সম্পন্ন পরিষেবা'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Services -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-full flex items-center justify-center text-white text-2xl">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800"><?php echo number_format($totalServices); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Services Completed' : 'সম্পন্ন পরিষেবা'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Spent -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-500 rounded-full flex items-center justify-center text-white text-2xl">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800">৳<?php echo number_format($totalSpent, 0); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Total Spent' : 'মোট ব্যয়'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Average Rating -->
            <div class="stat-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-full flex items-center justify-center text-white text-2xl">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800"><?php echo number_format($averageRating, 1); ?></div>
                        <div class="text-gray-600 font-medium">
                            <?php echo $currentLang === 'en' ? 'Average Rating' : 'গড় রেটিং'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service History -->
        <div class="history-card">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-list-alt text-purple-600 mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Completed Services' : 'সম্পন্ন পরিষেবা'; ?>
                </h2>
                <p class="text-gray-600 mt-2">
                    <?php echo $currentLang === 'en' ? 'All your completed service bookings in chronological order' : 'ক্রোনোলজিকাল ক্রমে আপনার সমস্ত সম্পন্ন পরিষেবা বুকিং'; ?>
                </p>
            </div>
            
            <div class="p-6">
                <?php if (empty($serviceHistory)): ?>
                    <div class="text-center py-12">
                        <div class="w-24 h-24 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-history text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">
                            <?php echo $currentLang === 'en' ? 'No Service History' : 'কোন পরিষেবার ইতিহাস নেই'; ?>
                        </h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            <?php echo $currentLang === 'en' ? 'You haven\'t completed any services yet. Start booking services to build your service history.' : 'আপনি এখনও কোন পরিষেবা সম্পন্ন করেননি। আপনার পরিষেবার ইতিহাস তৈরি করতে পরিষেবা বুকিং শুরু করুন।'; ?>
                        </p>
                        <a href="../search.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            <i class="fas fa-search mr-2"></i>
                            <?php echo $currentLang === 'en' ? 'Find Services' : 'পরিষেবা খুঁজুন'; ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($serviceHistory as $service): ?>
                            <div class="bg-gray-50 rounded-xl p-6 hover:bg-gray-100 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr($service['provider_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($service['provider_name']); ?>
                                            </h3>
                                            <p class="text-gray-600">
                                                <?php echo $currentLang === 'en' ? $service['category_name'] : $service['category_name_bn']; ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo $currentLang === 'en' ? 'Service Date:' : 'পরিষেবার তারিখ:'; ?> 
                                                <?php echo date('M j, Y', strtotime($service['booking_date'])); ?>
                                                <?php if ($service['booking_time']): ?>
                                                    at <?php echo date('g:i A', strtotime($service['booking_time'])); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <div class="mb-2">
                                            <span class="service-badge">
                                                <?php echo $currentLang === 'en' ? 'Completed' : 'সম্পন্ন'; ?>
                                            </span>
                                        </div>
                                        <?php if ($service['rating']): ?>
                                            <div class="mb-2">
                                                <div class="flex items-center justify-end">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star text-yellow-400 <?php echo $i <= $service['rating'] ? '' : 'text-gray-300'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ml-2 text-sm text-gray-600">(<?php echo $service['rating']; ?>)</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($service['booking_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($service['review_text']): ?>
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="bg-white rounded-lg p-4">
                                            <div class="flex items-start space-x-3">
                                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-comment text-purple-600 text-sm"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="text-sm text-gray-600 mb-1">
                                                        <?php echo $currentLang === 'en' ? 'Your Review' : 'আপনার পর্যালোচনা'; ?>
                                                        <span class="text-gray-400">
                                                            - <?php echo date('M j, Y', strtotime($service['review_date'])); ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-gray-800">
                                                        "<?php echo htmlspecialchars($service['review_text']); ?>"
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <div class="flex items-center justify-between text-sm">
                                        <div class="flex items-center space-x-4">
                                            <span class="text-gray-600">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($service['provider_phone']); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <a href="tel:<?php echo htmlspecialchars($service['provider_phone']); ?>" 
                                               class="text-blue-600 hover:text-blue-700 font-medium">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo $currentLang === 'en' ? 'Call Again' : 'আবার কল করুন'; ?>
                                            </a>
                                            <?php if (!$service['rating']): ?>
                                                <a href="review.php?booking_id=<?php echo $service['id']; ?>" 
                                                   class="text-purple-600 hover:text-purple-700 font-medium">
                                                    <i class="fas fa-star mr-1"></i>
                                                    <?php echo $currentLang === 'en' ? 'Add Review' : 'পর্যালোচনা যোগ করুন'; ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
