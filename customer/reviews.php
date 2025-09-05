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

// Get user's reviews
$reviews = fetchAll("
    SELECT r.*, sp.name as provider_name, sc.name as category_name, sc.name_bn as category_name_bn
    FROM reviews r
    JOIN service_providers sp ON r.provider_id = sp.id
    JOIN service_categories sc ON sp.category_id = sc.id
    WHERE r.customer_id = ?
    ORDER BY r.created_at DESC
", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'My Reviews' : 'আমার পর্যালোচনা'; ?> - S24</title>
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
                        <i class="fas fa-star text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'My Reviews' : 'আমার পর্যালোচনা'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- My Selections -->
                    <a href="my_selections.php" class="nav-link">
                        <i class="fas fa-handshake mr-2"></i>
                        <span><?php echo $currentLang === 'en' ? 'My Selections' : 'আমার নির্বাচন'; ?></span>
                    </a>
                    
                    <!-- Language Toggle -->
                    <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="nav-link">
                        <?php echo $currentLang === 'en' ? 'বাংলা' : 'EN'; ?>
                    </a>
                    
                    <!-- Profile -->
                    <a href="profile.php" class="nav-link">
                        <?php echo $currentLang === 'en' ? 'Profile' : 'প্রোফাইল'; ?>
                    </a>
                    
                    <!-- Logout -->
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>

                    <!-- Theme Picker -->
                    <div class="relative" data-theme-picker>
                        <button type="button" class="nav-link" data-toggle>
                            <span class="inline-block h-3 w-3 rounded-full border border-white mr-2" data-theme-current></span>
                            <i class="fas fa-palette mr-2"></i><?php echo $currentLang === 'en' ? 'Theme' : 'থিম'; ?>
                        </button>
                        <div class="theme-menu absolute right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-md p-2 hidden">
                            <div class="flex items-center gap-2">
                                <div class="theme-swatch h-6 w-6 rounded cursor-pointer" style="background:#6d28d9" title="Purple" data-theme="theme-purple"></div>
                                <div class="theme-swatch h-6 w-6 rounded cursor-pointer" style="background:#10b981" title="Emerald" data-theme="theme-emerald"></div>
                                <div class="theme-swatch h-6 w-6 rounded cursor-pointer" style="background:#e11d48" title="Rose" data-theme="theme-rose"></div>
                                <div class="theme-swatch h-6 w-6 rounded cursor-pointer" style="background:#f59e0b" title="Amber" data-theme="theme-amber"></div>
                                <div class="theme-swatch h-6 w-6 rounded cursor-pointer" style="background:#334155" title="Slate" data-theme="theme-slate"></div>
                            </div>
                        </div>
                    </div>
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
                            <i class="fas fa-star text-purple-600 mr-3"></i>
                            <?php echo $currentLang === 'en' ? 'My Reviews' : 'আমার পর্যালোচনা'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'View and manage your service reviews' : 'আপনার পরিষেবা পর্যালোচনা দেখুন এবং পরিচালনা করুন'; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-purple-600"><?php echo count($reviews); ?></div>
                        <div class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Total Reviews' : 'মোট পর্যালোচনা'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($reviews)): ?>
                <div class="customer-card p-12 text-center">
                    <div class="w-24 h-24 bg-gradient-to-br from-yellow-100 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-star text-4xl text-yellow-500"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">
                        <?php echo $currentLang === 'en' ? 'No Reviews Yet' : 'এখনও কোনো পর্যালোচনা নেই'; ?>
                    </h3>
                    <p class="text-gray-500 max-w-md mx-auto mb-6">
                        <?php echo $currentLang === 'en' ? 'You haven\'t submitted any reviews yet. Complete a service to leave a review.' : 'আপনি এখনও কোনো পর্যালোচনা জমা দেননি। পর্যালোচনা দিতে একটি সেবা সম্পন্ন করুন।'; ?>
                    </p>
                    <a href="../search.php" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-300 font-semibold inline-flex items-center">
                        <i class="fas fa-search mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Find Services' : 'সেবা খুঁজুন'; ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($reviews as $review): ?>
                        <div class="customer-card p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800 text-lg">
                                            <?php echo htmlspecialchars($review['provider_name']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-tools mr-1"></i>
                                            <?php echo $currentLang === 'en' ? $review['category_name'] : $review['category_name_bn']; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center bg-gradient-to-r from-yellow-400 to-orange-400 text-white px-3 py-1 rounded-full">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star text-<?php echo $i <= $review['rating'] ? 'white' : 'rgba(255,255,255,0.3)'; ?> text-sm"></i>
                                        <?php endfor; ?>
                                        <span class="ml-2 font-semibold"><?php echo $review['rating']; ?>/5</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($review['review_text']): ?>
                                <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($review['review_text']); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span><?php echo formatDateTime($review['created_at']); ?></span>
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

    <!-- Theme script -->
    <script src="../assets/ui.js"></script>
</body>
</html> 