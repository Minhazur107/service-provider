<?php
require_once 'includes/functions.php';

$currentLang = getLanguage();

// Get search parameters
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$priceRange = $_GET['price_range'] ?? '';
$sortBy = $_GET['sort'] ?? 'rating';

// Build search query
$whereConditions = ["sp.verification_status = 'verified'", "sp.is_active = 1"];
$params = [];

if ($category) {
    $whereConditions[] = "sp.category_id = ?";
    $params[] = $category;
}

if ($location) {
    $whereConditions[] = "sp.service_areas LIKE ?";
    $params[] = "%$location%";
}

if ($priceRange) {
    switch ($priceRange) {
        case '500-1000':
            $whereConditions[] = "sp.price_min >= 500 AND sp.price_max <= 1000";
            break;
        case '1000-2500':
            $whereConditions[] = "sp.price_min >= 1000 AND sp.price_max <= 2500";
            break;
        case '2500-5000':
            $whereConditions[] = "sp.price_min >= 2500 AND sp.price_max <= 5000";
            break;
        case '5000+':
            $whereConditions[] = "sp.price_min >= 5000";
            break;
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Order by clause
$orderBy = match($sortBy) {
    'price_low' => 'sp.price_min ASC',
    'price_high' => 'sp.price_max DESC',
    'rating' => 'avg_rating DESC',
    'reviews' => 'total_reviews DESC',
    default => 'avg_rating DESC'
};

// Get providers with ratings
$sql = "SELECT sp.*, sc.name as category_name, sc.name_bn as category_name_bn,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(r.id) as total_reviews
        FROM service_providers sp
        LEFT JOIN service_categories sc ON sp.category_id = sc.id
        LEFT JOIN reviews r ON sp.id = r.provider_id AND r.status = 'approved'
        WHERE $whereClause
        GROUP BY sp.id
        ORDER BY $orderBy";

$providers = fetchAll($sql, $params);

// Map of the logged-in customer's approved reviews for providers in results
$myReviews = [];
if (isLoggedIn() && !empty($providers)) {
    $user = getCurrentUser();
    $providerIds = array_column($providers, 'id');
    if (!empty($providerIds)) {
        $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
        $queryParams = array_merge([$user['id']], $providerIds);
        $rows = fetchAll(
            "SELECT r.provider_id, r.rating, r.review_text AS comment, r.created_at
             FROM reviews r
             WHERE r.customer_id = ? AND r.status = 'approved' AND r.provider_id IN ($placeholders)",
            $queryParams
        );
        foreach ($rows as $row) {
            $myReviews[$row['provider_id']] = $row;
        }
    }
}

// Get categories for filter
$categories = fetchAll("SELECT * FROM service_categories WHERE is_active = 1");
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Search Results' : 'অনুসন্ধানের ফলাফল'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .search-bg {
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
        
        .search-bg::before {
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
        
        .results-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .results-header::before {
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
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .provider-card:hover .provider-avatar {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }
        
        .verification-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .rating-stars {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .btn-select {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-select::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-select:hover::before {
            left: 100%;
        }
        
        .btn-select:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .btn-call {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.75rem;
            border-radius: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-call:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .stats-counter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
            font-size: 3rem;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .category-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            margin-right: 0.75rem;
        }
        
        .btn-call {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.75rem;
            border-radius: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-call:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .my-review-badge {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 1rem;
            padding: 0.75rem;
            backdrop-filter: blur(10px);
        }
        
        .my-review-badge {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 1rem;
            padding: 0.75rem;
            backdrop-filter: blur(10px);
        }
        
        .empty-state {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
        }
        
        .empty-icon {
            width: 6rem;
            height: 6rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 15px 35px rgba(240, 147, 251, 0.3);
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
    </style>
</head>
<body class="search-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <!-- Header -->
    <header class="bg-white/90 backdrop-blur-lg border-b border-white/20 sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                    S24
                </a>
                <nav class="flex space-x-6">
                    <a href="index.php" class="text-gray-700 hover:text-purple-600 transition-colors">
                        <?php echo $currentLang === 'en' ? 'Home' : 'হোম'; ?>
                    </a>
                    <a href="public_reviews.php" class="text-gray-700 hover:text-purple-600 transition-colors">
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="customer/dashboard.php" class="text-gray-700 hover:text-purple-600 transition-colors">
                            <?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="text-gray-700 hover:text-purple-600 transition-colors">
                            <?php echo $currentLang === 'en' ? 'Login' : 'লগইন'; ?>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Results Header -->
    <section class="py-8 relative z-10">
        <div class="container mx-auto px-4">
            <div class="results-header p-8 mb-8">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6">
                    <div class="mb-4 lg:mb-0">
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Found' : 'পাওয়া গেছে'; ?> 
                            <span class="stats-counter"><?php echo count($providers); ?></span> 
                            <?php echo $currentLang === 'en' ? 'Service Providers' : 'সেবা প্রদানকারী'; ?>
                        </h1>
                        <p class="text-lg text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Discover trusted professionals for your needs' : 'আপনার প্রয়োজন অনুযায়ী বিশ্বস্ত পেশাদারদের আবিষ্কার করুন'; ?>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600"><?php echo count($providers); ?></div>
                            <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Providers' : 'প্রদানকারী'; ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">
                                <?php echo count(array_filter($providers, fn($p) => $p['avg_rating'] >= 4)); ?>
                            </div>
                            <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Top Rated' : 'শীর্ষ রেটেড'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Search Filters -->
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <select name="category" class="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Categories' : 'সব বিভাগ'; ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $currentLang === 'en' ? $cat['name'] : $cat['name_bn']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" 
                           placeholder="<?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?>"
                           class="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    
                    <select name="price_range" class="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value=""><?php echo $currentLang === 'en' ? 'All Prices' : 'সব মূল্য'; ?></option>
                        <option value="500-1000" <?php echo $priceRange === '500-1000' ? 'selected' : ''; ?>>৳500 - ৳1,000</option>
                        <option value="1000-2500" <?php echo $priceRange === '1000-2500' ? 'selected' : ''; ?>>৳1,000 - ৳2,500</option>
                        <option value="2500-5000" <?php echo $priceRange === '2500-5000' ? 'selected' : ''; ?>>৳2,500 - ৳5,000</option>
                        <option value="5000+" <?php echo $priceRange === '5000+' ? 'selected' : ''; ?>>৳5,000+</option>
                    </select>
                    
                    <button type="submit" class="bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-semibold">
                        <i class="fas fa-search mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Search' : 'অনুসন্ধান'; ?>
                    </button>
                </form>
            </div>

            <?php if (empty($providers)): ?>
                <div class="empty-state p-12 text-center">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">
                        <?php echo $currentLang === 'en' ? 'No providers found' : 'কোনো প্রদানকারী পাওয়া যায়নি'; ?>
                    </h3>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                        <?php echo $currentLang === 'en' ? 'Try adjusting your search criteria or browse all categories to find the perfect service provider for your needs.' : 'আপনার অনুসন্ধানের মানদণ্ড পরিবর্তন করে দেখুন বা আপনার প্রয়োজন অনুযায়ী নিখুঁত সেবা প্রদানকারী খুঁজতে সব বিভাগ ব্রাউজ করুন।'; ?>
                    </p>
                    <a href="index.php" class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-semibold">
                        <i class="fas fa-home mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Browse All Categories' : 'সব বিভাগ ব্রাউজ করুন'; ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($providers as $provider): ?>
                        <div class="provider-card p-6">
                            <div class="flex items-start justify-between mb-6">
                                <div class="flex items-center space-x-4">
                                    <div class="provider-avatar">
                                        <?php if ($provider['profile_picture']): ?>
                                            <img src="uploads/<?php echo $provider['profile_picture']; ?>" 
                                                 alt="<?php echo htmlspecialchars($provider['name']); ?>" 
                                                 class="w-full h-full rounded-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($provider['name']); ?></h3>
                                        <div class="flex items-center">
                                            <div class="category-icon">
                                                <i class="fas fa-tools"></i>
                                            </div>
                                            <span class="text-sm text-gray-600 font-medium">
                                                <?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($provider['verification_status'] === 'verified'): ?>
                                    <div class="verification-badge">
                                        <i class="fas fa-check-circle mr-1"></i><?php echo $currentLang === 'en' ? 'Verified' : 'যাচাইকৃত'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-4 mb-6">
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-map-marker-alt text-purple-500 mr-3 text-lg"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars($provider['service_areas']); ?></span>
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-tag text-green-500 mr-3 text-lg"></i>
                                    <span class="font-medium"><?php echo formatPrice($provider['price_min']); ?> - <?php echo formatPrice($provider['price_max']); ?></span>
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-star text-yellow-400 mr-3 text-lg"></i>
                                    <span class="rating-stars"><?php echo number_format($provider['avg_rating'], 1); ?></span>
                                    <span class="ml-2">(<?php echo $provider['total_reviews']; ?> <?php echo $currentLang === 'en' ? 'reviews' : 'পর্যালোচনা'; ?>)</span>
                                </div>

                                <?php if (isLoggedIn() && isset($myReviews[$provider['id']])): ?>
                                    <?php 
                                        $comment = $myReviews[$provider['id']]['comment'] ?? '';
                                        $shortComment = (strlen($comment) > 80) ? substr($comment, 0, 80) . '…' : $comment;
                                    ?>
                                    <div class="my-review-badge">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-user-check text-purple-600 mr-2"></i>
                                            <span class="font-semibold text-purple-700"><?php echo $currentLang === 'en' ? 'Your Review' : 'আপনার পর্যালোচনা'; ?></span>
                                            <span class="ml-2 rating-stars"><?php echo number_format($myReviews[$provider['id']]['rating'], 1); ?>/5</span>
                                        </div>
                                        <?php if (!empty($shortComment)): ?>
                                            <p class="text-sm text-gray-600 italic">"<?php echo htmlspecialchars($shortComment); ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex space-x-3">
                                <?php if (isLoggedIn()): ?>
                                    <a href="customer/select_provider.php?id=<?php echo $provider['id']; ?>" 
                                       class="btn-select flex-1 text-center">
                                        <i class="fas fa-check mr-2"></i>
                                        <?php echo $currentLang === 'en' ? 'Select Provider' : 'প্রদানকারী নির্বাচন করুন'; ?>
                                    </a>
                                <?php else: ?>
                                    <a href="provider/profile.php?id=<?php echo $provider['id']; ?>" 
                                       class="btn-select flex-1 text-center">
                                        <i class="fas fa-eye mr-2"></i>
                                        <?php echo $currentLang === 'en' ? 'View Profile' : 'প্রোফাইল দেখুন'; ?>
                                    </a>
                                <?php endif; ?>
                                <a href="tel:<?php echo $provider['phone']; ?>" 
                                   class="btn-call">
                                    <i class="fas fa-phone"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
        
        // Observe all provider cards
        document.querySelectorAll('.provider-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>