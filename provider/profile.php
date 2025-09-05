<?php
require_once '../includes/functions.php';

$currentLang = getLanguage();
$providerId = $_GET['id'] ?? 0;

if (!$providerId) {
    redirect('../search.php');
}

// Get provider details
$provider = fetchOne("
    SELECT sp.*, sc.name as category_name, sc.name_bn as category_name_bn
    FROM service_providers sp
    JOIN service_categories sc ON sp.category_id = sc.id
    WHERE sp.id = ? AND sp.verification_status = 'verified' AND sp.is_active = 1
", [$providerId]);

if (!$provider) {
    redirect('../search.php');
}

// Get provider reviews
$reviews = fetchAll("
    SELECT r.*, u.name as customer_name
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    WHERE r.provider_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 10
", [$providerId]);

// Get average rating
$ratingData = getAverageRating($providerId);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($provider['name']); ?> - <?php echo $currentLang === 'en' ? 'Service Provider' : 'সেবা প্রদানকারী'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .profile-bg {
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
        
        .profile-bg::before {
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
        
        .profile-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
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
        
        .profile-card {
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
        
        .profile-card::before {
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
        
        .profile-card:hover::before {
            opacity: 1;
        }
        
        .profile-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .provider-avatar {
            width: 6rem;
            height: 6rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .profile-card:hover .provider-avatar {
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
        
        .btn-primary {
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
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .btn-whatsapp {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3);
        }
        
        .btn-whatsapp:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 12px 35px rgba(37, 211, 102, 0.4);
            background: linear-gradient(135deg, #128c7e, #075e54);
        }
        
        .nav-link {
            color: #6b7280;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .review-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
        }
        
        .stats-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 1rem;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .stats-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
        }
    </style>
</head>
<body class="profile-bg min-h-screen">
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
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-600 font-medium">
                        <i class="fas fa-user-circle mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Provider Profile' : 'প্রদানকারী প্রোফাইল'; ?>
                    </span>
                </div>
                
                <nav class="flex items-center space-x-4">
                    <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="nav-link">
                        <i class="fas fa-language mr-1"></i>
                        <?php echo $currentLang === 'en' ? 'বাংলা' : 'EN'; ?>
                    </a>
                    <a href="../search.php" class="nav-link">
                        <i class="fas fa-arrow-left mr-1"></i>
                        <?php echo $currentLang === 'en' ? 'Back to Search' : 'অনুসন্ধানে ফিরে যান'; ?>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8 relative z-10">
        <div class="max-w-4xl mx-auto">
            <!-- Provider Header -->
            <div class="profile-header p-8 mb-8">
                <div class="flex items-start space-x-6">
                    <div class="provider-avatar">
                        <?php if ($provider['profile_picture']): ?>
                            <img src="../uploads/<?php echo $provider['profile_picture']; ?>" 
                                 alt="<?php echo htmlspecialchars($provider['name']); ?>" 
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-3">
                            <h1 class="text-4xl font-bold text-gray-800"><?php echo htmlspecialchars($provider['name']); ?></h1>
                            <?php if ($provider['verification_badge']): ?>
                                <div class="verification-badge">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <?php echo $currentLang === 'en' ? 'Verified' : 'যাচাইকৃত'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <p class="text-xl text-gray-600 mb-4">
                            <i class="fas fa-tools mr-2 text-purple-600"></i>
                            <?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
                        </p>
                        
                        <div class="flex items-center space-x-6 mb-4">
                            <div class="flex items-center space-x-2">
                                <div class="flex items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star text-<?php echo $i <= $ratingData['average'] ? 'yellow' : 'gray'; ?>-400"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-lg font-semibold text-gray-800"><?php echo $ratingData['average']; ?></span>
                                <span class="text-gray-600">(<?php echo $ratingData['total']; ?> <?php echo $currentLang === 'en' ? 'reviews' : 'পর্যালোচনা'; ?>)</span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>
                                <span class="font-medium"><?php echo htmlspecialchars($provider['service_areas']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-tag text-green-500 mr-2"></i>
                                <span class="font-medium"><?php echo formatPrice($provider['price_min']); ?> - <?php echo formatPrice($provider['price_max']); ?></span>
                            </div>
                            <?php if ($provider['hourly_rate']): ?>
                                <div class="flex items-center">
                                    <i class="fas fa-clock text-blue-500 mr-2"></i>
                                    <span class="font-medium"><?php echo formatPrice($provider['hourly_rate']); ?>/<?php echo $currentLang === 'en' ? 'hour' : 'ঘণ্টা'; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($provider['availability_hours']): ?>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar text-orange-500 mr-2"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars($provider['availability_hours']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Service Information -->
                <div class="lg:col-span-2">
                    <div class="profile-card p-6 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-info-circle text-purple-600 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'Service Information' : 'সেবার তথ্য'; ?>
                        </h2>
                        
                        <?php if ($provider['description']): ?>
                            <p class="text-gray-700 mb-6 leading-relaxed"><?php echo htmlspecialchars($provider['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="space-y-4">
                            <div class="stats-item">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Service Category' : 'সেবা বিভাগ'; ?></span>
                                    <span class="font-semibold text-gray-800"><?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?></span>
                                </div>
                            </div>
                            
                            <div class="stats-item">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Service Areas' : 'সেবা এলাকা'; ?></span>
                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($provider['service_areas']); ?></span>
                                </div>
                            </div>
                            
                            <div class="stats-item">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Price Range' : 'মূল্য পরিসীমা'; ?></span>
                                    <span class="font-semibold text-gray-800"><?php echo formatPrice($provider['price_min']); ?> - <?php echo formatPrice($provider['price_max']); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($provider['hourly_rate']): ?>
                                <div class="stats-item">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Hourly Rate' : 'ঘণ্টার হার'; ?></span>
                                        <span class="font-semibold text-gray-800"><?php echo formatPrice($provider['hourly_rate']); ?>/<?php echo $currentLang === 'en' ? 'hour' : 'ঘণ্টা'; ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($provider['availability_hours']): ?>
                                <div class="stats-item">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Availability' : 'উপলব্ধতা'; ?></span>
                                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($provider['availability_hours']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div class="profile-card p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-star text-yellow-500 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'Customer Reviews' : 'গ্রাহক পর্যালোচনা'; ?>
                        </h2>
                        
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-star text-white text-2xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg">
                                    <?php echo $currentLang === 'en' ? 'No reviews yet' : 'এখনও কোনো পর্যালোচনা নেই'; ?>
                                </p>
                                <p class="text-gray-400 text-sm mt-2">
                                    <?php echo $currentLang === 'en' ? 'Be the first to review this provider!' : 'এই প্রদানকারীর প্রথম পর্যালোচনা করুন!'; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-card p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <div class="flex items-center space-x-2">
                                                <div class="flex items-center">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star text-<?php echo $i <= $review['rating'] ? 'yellow' : 'gray'; ?>-400"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="text-sm font-semibold text-gray-700"><?php echo $review['rating']; ?>/5</span>
                                            </div>
                                            <span class="text-sm text-gray-500"><?php echo formatDateTime($review['created_at']); ?></span>
                                        </div>
                                        
                                        <?php if ($review['review_text']): ?>
                                            <p class="text-gray-700 mb-3 leading-relaxed"><?php echo htmlspecialchars($review['review_text']); ?></p>
                                        <?php endif; ?>
                                        
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-user mr-1"></i>
                                            <?php echo htmlspecialchars($review['customer_name']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact & Actions -->
                <div class="lg:col-span-1">
                    <div class="profile-card p-6 mb-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-phone text-green-600 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'Contact Provider' : 'প্রদানকারীর সাথে যোগাযোগ করুন'; ?>
                        </h3>
                        
                        <div class="space-y-4 mb-6">
                            <a href="tel:<?php echo $provider['phone']; ?>" 
                               class="flex items-center space-x-3 text-green-600 hover:text-green-700 transition-colors">
                                <i class="fas fa-phone text-xl"></i>
                                <span class="font-medium"><?php echo $provider['phone']; ?></span>
                            </a>
                            
                            <?php if ($provider['email']): ?>
                                <a href="mailto:<?php echo $provider['email']; ?>" 
                                   class="flex items-center space-x-3 text-blue-600 hover:text-blue-700 transition-colors">
                                    <i class="fas fa-envelope text-xl"></i>
                                    <span class="font-medium"><?php echo $provider['email']; ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-3">
                            <a href="tel:<?php echo $provider['phone']; ?>" 
                               class="btn-success w-full text-center block">
                                <i class="fas fa-phone mr-2"></i>
                                <?php echo $currentLang === 'en' ? 'Call Now' : 'এখনই কল করুন'; ?>
                            </a>
                            
                            <a href="https://wa.me/<?php echo $provider['phone']; ?>?text=<?php echo urlencode($currentLang === 'en' ? 'Hi, I am interested in your services. Can you provide more details?' : 'হাই, আমি আপনার সেবায় আগ্রহী। আপনি কি আরও বিস্তারিত দিতে পারেন?'); ?>" 
                               target="_blank"
                               class="btn-whatsapp w-full text-center block">
                                <i class="fab fa-whatsapp mr-2"></i>
                                <?php echo $currentLang === 'en' ? 'WhatsApp' : 'হোয়াটসঅ্যাপ'; ?>
                            </a>
                            
                            <?php if (isLoggedIn()): ?>
                                <a href="../customer/select_provider.php?provider_id=<?php echo $provider['id']; ?>" 
                                   class="btn-primary w-full text-center block">
                                    <i class="fas fa-plus mr-2"></i>
                                    <?php echo $currentLang === 'en' ? 'Add to My Selections' : 'আমার নির্বাচনে যোগ করুন'; ?>
                                </a>
                            <?php else: ?>
                                <a href="../auth/login.php" 
                                   class="btn-primary w-full text-center block">
                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                    <?php echo $currentLang === 'en' ? 'Login to Select' : 'নির্বাচন করতে লগইন করুন'; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Provider Stats -->
                    <div class="profile-card p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-chart-bar text-purple-600 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'Provider Stats' : 'প্রদানকারী পরিসংখ্যান'; ?>
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="stats-item">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Member Since' : 'সদস্য হওয়ার তারিখ'; ?></span>
                                    <span class="font-semibold text-gray-800"><?php echo formatDate($provider['created_at']); ?></span>
                                </div>
                            </div>
                            
                            <div class="stats-item">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Total Reviews' : 'মোট পর্যালোচনা'; ?></span>
                                    <span class="font-semibold text-gray-800"><?php echo $ratingData['total']; ?></span>
                                </div>
                            </div>
                            
                            <div class="stats-item">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium"><?php echo $currentLang === 'en' ? 'Average Rating' : 'গড় রেটিং'; ?></span>
                                    <span class="font-semibold text-gray-800"><?php echo $ratingData['average']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white bg-opacity-95 backdrop-blur-sm border-t border-gray-200 py-8 mt-12 relative z-10">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-600">&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>

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
        
        // Observe all profile cards
        document.querySelectorAll('.profile-card, .review-card, .stats-item').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html> 