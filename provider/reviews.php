<?php
require_once '../includes/functions.php';

// Check if provider is logged in
if (!isProviderLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$provider = getCurrentProvider();

// Get filters
$rating = $_GET['rating'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$whereConditions = ["r.provider_id = ?"];
$params = [$provider['id']];

if ($rating) {
    $whereConditions[] = "r.rating = ?";
    $params[] = $rating;
}

if ($date_from) {
    $whereConditions[] = "r.created_at >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $whereConditions[] = "r.created_at <= ?";
    $params[] = $date_to;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get provider's reviews
$reviews = fetchAll("
    SELECT r.*, u.name as customer_name, u.phone as customer_phone, u.location as customer_location,
           b.booking_date, b.service_type, b.final_price
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    $whereClause
    ORDER BY r.created_at DESC
", $params);

// Get review statistics
$reviewStats = fetchOne("
    SELECT 
        COUNT(*) as total_reviews,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
        COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
        COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
        COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
        COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
    FROM reviews 
    WHERE provider_id = ? AND status = 'approved'
", [$provider['id']]);

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
    <title><?php echo $currentLang === 'en' ? 'My Reviews' : 'আমার পর্যালোচনা'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-purple-600">S24</a>
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Provider Reviews' : 'প্রদানকারী পর্যালোচনা'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="text-purple-600 hover:text-purple-700">
                        <?php echo $currentLang === 'en' ? 'বাংলা' : 'EN'; ?>
                    </a>
                    <span class="text-gray-700"><?php echo htmlspecialchars($provider['name']); ?></span>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">
                <?php echo $currentLang === 'en' ? 'My Reviews' : 'আমার পর্যালোচনা'; ?>
            </h1>
            <div class="text-sm text-gray-600">
                <?php echo count($reviews); ?> <?php echo $currentLang === 'en' ? 'reviews' : 'পর্যালোচনা'; ?>
            </div>
        </div>

        <!-- Review Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-blue-600 mb-2">
                    <?php echo number_format($reviewStats['avg_rating'], 1); ?>
                </div>
                <div class="flex justify-center mb-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star text-<?php echo $i <= $reviewStats['avg_rating'] ? 'yellow' : 'gray'; ?>-400"></i>
                    <?php endfor; ?>
                </div>
                <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Average Rating' : 'গড় রেটিং'; ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $reviewStats['total_reviews']; ?></div>
                <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Total Reviews' : 'মোট পর্যালোচনা'; ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-yellow-600 mb-2"><?php echo $reviewStats['five_star']; ?></div>
                <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? '5 Star Reviews' : '৫ তারকা পর্যালোচনা'; ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-purple-600 mb-2">
                    <?php echo $reviewStats['total_reviews'] > 0 ? round(($reviewStats['five_star'] / $reviewStats['total_reviews']) * 100) : 0; ?>%
                </div>
                <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Satisfaction Rate' : 'সন্তুষ্টির হার'; ?></p>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <?php echo $currentLang === 'en' ? 'Rating Distribution' : 'রেটিং বিতরণ'; ?>
            </h2>
            <div class="space-y-3">
                <?php 
                $ratings = [
                    5 => ['count' => $reviewStats['five_star'], 'color' => 'bg-green-500'],
                    4 => ['count' => $reviewStats['four_star'], 'color' => 'bg-blue-500'],
                    3 => ['count' => $reviewStats['three_star'], 'color' => 'bg-yellow-500'],
                    2 => ['count' => $reviewStats['two_star'], 'color' => 'bg-orange-500'],
                    1 => ['count' => $reviewStats['one_star'], 'color' => 'bg-red-500']
                ];
                
                foreach ($ratings as $rating => $data):
                    $percentage = $reviewStats['total_reviews'] > 0 ? ($data['count'] / $reviewStats['total_reviews']) * 100 : 0;
                ?>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 w-20">
                            <span class="text-sm font-medium"><?php echo $rating; ?> ★</span>
                        </div>
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="<?php echo $data['color']; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="w-16 text-right text-sm text-gray-600">
                            <?php echo $data['count']; ?>
                        </div>
                        <div class="w-16 text-right text-sm text-gray-500">
                            <?php echo round($percentage, 1); ?>%
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Rating' : 'রেটিং'; ?>
                    </label>
                    <select name="rating" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Ratings' : 'সব রেটিং'; ?></option>
                        <option value="5" <?php echo $rating === '5' ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5</option>
                        <option value="4" <?php echo $rating === '4' ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4</option>
                        <option value="3" <?php echo $rating === '3' ? 'selected' : ''; ?>>⭐⭐⭐ 3</option>
                        <option value="2" <?php echo $rating === '2' ? 'selected' : ''; ?>>⭐⭐ 2</option>
                        <option value="1" <?php echo $rating === '1' ? 'selected' : ''; ?>>⭐ 1</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'From Date' : 'শুরুর তারিখ'; ?>
                    </label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'To Date' : 'শেষের তারিখ'; ?>
                    </label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        <i class="fas fa-search mr-2"></i><?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Reviews List -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <?php if (empty($reviews)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-star text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">
                        <?php echo $currentLang === 'en' ? 'No reviews found' : 'কোনো পর্যালোচনা পাওয়া যায়নি'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-6 p-6">
                    <?php foreach ($reviews as $review): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center space-x-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star text-<?php echo $i <= $review['rating'] ? 'yellow' : 'gray'; ?>-400"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-lg font-semibold text-gray-800"><?php echo $review['rating']; ?>/5</span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500"><?php echo formatDateTime($review['created_at']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($review['review_text']): ?>
                                <p class="text-gray-700 mb-4 text-lg"><?php echo htmlspecialchars($review['review_text']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($review['review_photo']): ?>
                                <div class="mb-4">
                                    <a href="../uploads/<?php echo htmlspecialchars($review['review_photo']); ?>" 
                                       target="_blank" class="inline-block">
                                        <img src="../uploads/<?php echo htmlspecialchars($review['review_photo']); ?>" 
                                             alt="Review Photo" class="w-32 h-32 object-cover rounded-lg">
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-gray-200">
                                <div>
                                    <h4 class="font-medium text-gray-800 mb-1">
                                        <?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?>
                                    </h4>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['customer_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['customer_phone']); ?></p>
                                    <?php if ($review['customer_location']): ?>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['customer_location']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($review['booking_date']): ?>
                                    <div>
                                        <h4 class="font-medium text-gray-800 mb-1">
                                            <?php echo $currentLang === 'en' ? 'Service Date' : 'সেবার তারিখ'; ?>
                                        </h4>
                                        <p class="text-sm text-gray-600"><?php echo formatDate($review['booking_date']); ?></p>
                                        <?php if ($review['service_type']): ?>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['service_type']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($review['final_price']): ?>
                                    <div>
                                        <h4 class="font-medium text-gray-800 mb-1">
                                            <?php echo $currentLang === 'en' ? 'Service Value' : 'সেবার মূল্য'; ?>
                                        </h4>
                                        <p class="text-lg font-semibold text-green-600"><?php echo formatPrice($review['final_price']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>
</body>
</html> 