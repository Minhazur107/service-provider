<?php
require_once 'includes/functions.php';

$currentLang = getLanguage();

// Get filters
$rating = $_GET['rating'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$whereConditions = ["r.status = 'approved'"];
$params = [];

if ($rating) {
    $whereConditions[] = "r.rating = ?";
    $params[] = $rating;
}

if ($category) {
    $whereConditions[] = "sc.id = ?";
    $params[] = $category;
}

if ($search) {
    $whereConditions[] = "(sp.name LIKE ? OR sc.name LIKE ? OR r.review_text LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Build order clause
$orderClause = match($sort) {
    'newest' => 'ORDER BY r.created_at DESC',
    'oldest' => 'ORDER BY r.created_at ASC',
    'highest' => 'ORDER BY r.rating DESC, r.created_at DESC',
    'lowest' => 'ORDER BY r.rating ASC, r.created_at DESC',
    default => 'ORDER BY r.created_at DESC'
};

// Get reviews
$reviews = fetchAll("
    SELECT r.*, u.name as customer_name, u.location as customer_location,
           sp.name as provider_name, sp.phone as provider_phone, sp.service_areas,
           sc.name as category_name, sc.name_bn as category_name_bn,
           b.booking_date, b.service_type, b.final_price
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    JOIN service_providers sp ON r.provider_id = sp.id
    JOIN service_categories sc ON sp.category_id = sc.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    $whereClause
    $orderClause
    LIMIT 50
", $params);

// Get categories for filter
$categories = fetchAll("
    SELECT DISTINCT sc.id, sc.name, sc.name_bn
    FROM service_categories sc
    JOIN service_providers sp ON sc.id = sp.category_id
    JOIN reviews r ON sp.id = r.provider_id
    WHERE r.status = 'approved'
    ORDER BY sc.name
");

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
    WHERE status = 'approved'
");
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Customer Reviews' : 'গ্রাহক পর্যালোচনা'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-2xl font-bold text-purple-600">S24</a>
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Customer Reviews' : 'গ্রাহক পর্যালোচনা'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="search.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-search mr-1"></i><?php echo $currentLang === 'en' ? 'Find Services' : 'সেবা খুঁজুন'; ?>
                    </a>
                    <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="text-purple-600 hover:text-purple-700">
                        <?php echo $currentLang === 'en' ? 'বাংলা' : 'EN'; ?>
                    </a>
                    <a href="auth/login.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        <?php echo $currentLang === 'en' ? 'Login' : 'লগইন'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                <?php echo $currentLang === 'en' ? 'Customer Reviews' : 'গ্রাহক পর্যালোচনা'; ?>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                <?php echo $currentLang === 'en' ? 'Read authentic reviews from real customers to help you choose the best service providers' : 'সেরা সেবা প্রদানকারী নির্বাচন করতে সাহায্য করার জন্য আসল গ্রাহকদের কাছ থেকে সত্যিকারের পর্যালোচনা পড়ুন'; ?>
            </p>
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

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Search' : 'অনুসন্ধান'; ?>
                    </label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo $currentLang === 'en' ? 'Provider, category, review...' : 'প্রদানকারী, বিভাগ, পর্যালোচনা...'; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                
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
                        <?php echo $currentLang === 'en' ? 'Category' : 'বিভাগ'; ?>
                    </label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Categories' : 'সব বিভাগ'; ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? $cat['name'] : $cat['name_bn']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'Sort By' : 'সাজান'; ?>
                    </label>
                    <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Newest First' : 'নতুন প্রথম'; ?>
                        </option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Oldest First' : 'পুরানো প্রথম'; ?>
                        </option>
                        <option value="highest" <?php echo $sort === 'highest' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Highest Rated' : 'সর্বোচ্চ রেটেড'; ?>
                        </option>
                        <option value="lowest" <?php echo $sort === 'lowest' ? 'selected' : ''; ?>>
                            <?php echo $currentLang === 'en' ? 'Lowest Rated' : 'সর্বনিম্ন রেটেড'; ?>
                        </option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        <i class="fas fa-search mr-2"></i><?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Reviews List -->
        <div class="space-y-6">
            <?php if (empty($reviews)): ?>
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-star text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">
                        <?php echo $currentLang === 'en' ? 'No reviews found' : 'কোনো পর্যালোচনা পাওয়া যায়নি'; ?>
                    </h3>
                    <p class="text-gray-500 mb-4">
                        <?php echo $currentLang === 'en' ? 'Try adjusting your filters or search terms' : 'আপনার ফিল্টার বা অনুসন্ধানের শব্দগুলি সামঞ্জস্য করার চেষ্টা করুন'; ?>
                    </p>
                    <a href="public_reviews.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-300">
                        <?php echo $currentLang === 'en' ? 'Clear Filters' : 'ফিল্টার সাফ করুন'; ?>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6">
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
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">
                                    <?php echo $currentLang === 'en' ? 'Verified Review' : 'যাচাইকৃত পর্যালোচনা'; ?>
                                </span>
                                <p class="text-sm text-gray-500 mt-1"><?php echo formatDateTime($review['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($review['review_text']): ?>
                            <p class="text-gray-700 mb-4 text-lg"><?php echo htmlspecialchars($review['review_text']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($review['review_photo']): ?>
                            <div class="mb-4">
                                <a href="uploads/<?php echo htmlspecialchars($review['review_photo']); ?>" 
                                   target="_blank" class="inline-block">
                                    <img src="uploads/<?php echo htmlspecialchars($review['review_photo']); ?>" 
                                         alt="Review Photo" class="w-32 h-32 object-cover rounded-lg">
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4 border-t border-gray-200">
                            <div>
                                <h4 class="font-medium text-gray-800 mb-1">
                                    <?php echo $currentLang === 'en' ? 'Service Provider' : 'সেবা প্রদানকারী'; ?>
                                </h4>
                                <p class="text-sm text-gray-600 font-semibold"><?php echo htmlspecialchars($review['provider_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['provider_phone']); ?></p>
                                <?php if ($review['service_areas']): ?>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['service_areas']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-gray-800 mb-1">
                                    <?php echo $currentLang === 'en' ? 'Service Category' : 'সেবা বিভাগ'; ?>
                                </h4>
                                <p class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? $review['category_name'] : $review['category_name_bn']; ?></p>
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
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo $currentLang === 'en' ? 'Reviewed by' : 'পর্যালোচনা করেছেন'; ?>: 
                                    <?php echo htmlspecialchars($review['customer_name']); ?>
                                    <?php if ($review['customer_location']): ?>
                                        (<?php echo htmlspecialchars($review['customer_location']); ?>)
                                    <?php endif; ?>
                                </span>
                                <a href="search.php?provider=<?php echo urlencode($review['provider_name']); ?>" 
                                   class="text-purple-600 hover:text-purple-700 font-medium">
                                    <?php echo $currentLang === 'en' ? 'View Provider' : 'প্রদানকারী দেখুন'; ?>
                                    <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Call to Action -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white rounded-xl p-8 text-center mt-12">
            <h2 class="text-2xl font-bold mb-4">
                <?php echo $currentLang === 'en' ? 'Ready to Experience Great Service?' : 'দারুণ সেবা অনুভব করার জন্য প্রস্তুত?'; ?>
            </h2>
            <p class="text-lg opacity-90 mb-6">
                <?php echo $currentLang === 'en' ? 'Find and book trusted service providers based on real customer reviews' : 'আসল গ্রাহক পর্যালোচনার ভিত্তিতে বিশ্বস্ত সেবা প্রদানকারী খুঁজুন এবং বুক করুন'; ?>
            </p>
            <a href="search.php" class="bg-white text-purple-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300 inline-block">
                <?php echo $currentLang === 'en' ? 'Find Services Now' : 'এখনই সেবা খুঁজুন'; ?>
            </a>
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