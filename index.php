<?php
require_once 'includes/functions.php';
require_once 'includes/image_functions.php';

// Get service categories
$categories = fetchAll("SELECT * FROM service_categories WHERE is_active = 1");

// Handle language toggle
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    redirect('index.php');
}

$currentLang = getLanguage();

    // Get hero images
    $heroImages = [];
    $heroDir = 'uploads/hero';
    if (is_dir($heroDir)) {
        $files = scandir($heroDir);
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                $heroImages[] = $heroDir . '/' . $file;
            }
        }
    }

    // If no hero images found, use defaults
    if (empty($heroImages)) {
        $heroImages = [
            'assets/images/default/hero/hero1.svg',
            'assets/images/default/hero/hero2.svg',
            'assets/images/default/hero/hero3.svg'
        ];
    }
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S24 - Service Provider Directory</title>
    <link rel="stylesheet" href="assets/ui.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-page theme-purple">
    <!-- Header -->
    <header class="gradient-bg text-white">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold">S24</h1>
                    <span class="text-sm opacity-90"><?php echo $currentLang === 'en' ? 'Service Directory' : 'সেবা ডিরেক্টরি'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Theme Picker -->
                    <div class="theme-picker" data-theme-picker>
                        <button type="button" class="btn-outline" data-toggle>
                            <i class="fas fa-palette mr-2"></i><?php echo $currentLang === 'en' ? 'Theme' : 'থিম'; ?>
                        </button>
                        <div class="theme-menu">
                            <div class="flex items-center gap-2 p-1">
                                <div class="theme-swatch" style="background:#6d28d9" title="Purple" data-theme="theme-purple"></div>
                                <div class="theme-swatch" style="background:#10b981" title="Emerald" data-theme="theme-emerald"></div>
                                <div class="theme-swatch" style="background:#e11d48" title="Rose" data-theme="theme-rose"></div>
                                <div class="theme-swatch" style="background:#f59e0b" title="Amber" data-theme="theme-amber"></div>
                                <div class="theme-swatch" style="background:#334155" title="Slate" data-theme="theme-slate"></div>
                                <div class="theme-swatch" style="background:#06b6d4" title="Cyan" data-theme="theme-cyan"></div>
                                <div class="theme-swatch" style="background:#ec4899" title="Pink" data-theme="theme-pink"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Language Toggle -->
                    <div class="flex bg-white bg-opacity-20 rounded-lg p-1">
                        <a href="?lang=en" class="px-3 py-1 rounded <?php echo $currentLang === 'en' ? 'bg-white text-purple-600' : 'text-white'; ?>">
                            EN
                        </a>
                        <a href="?lang=bn" class="px-3 py-1 rounded <?php echo $currentLang === 'bn' ? 'bg-white text-purple-600' : 'text-white'; ?>">
                            বাংলা
                        </a>
                    </div>
                    
                    <a href="public_reviews.php" class="text-white hover:text-gray-200 font-medium">
                        <i class="fas fa-star mr-1"></i><?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="customer/dashboard.php" class="bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                            <?php echo t('dashboard'); ?>
                        </a>
                    <?php elseif (isProviderLoggedIn()): ?>
                        <a href="provider/dashboard.php" class="bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                            <?php echo t('dashboard'); ?>
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">
                            <?php echo t('login'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section with Dynamic Background -->
    <section class="hero relative h-[500px] flex items-center justify-center bg-cover bg-center" style="background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('<?php echo !empty($heroImages) ? $heroImages[array_rand($heroImages)] : ''; ?>');">
        <div class="container mx-auto px-4 text-center text-white hero-content">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">
                <?php echo $currentLang === 'en' ? 'Find Verified Local Service Providers' : 'যাচাইকৃত স্থানীয় সেবা প্রদানকারী খুঁজুন'; ?>
            </h1>
            <p class="text-xl mb-8 opacity-90 max-w-2xl mx-auto">
                <?php echo $currentLang === 'en' ? 'Connect with trusted professionals for all your home and business service needs' : 'আপনার বাড়ি এবং ব্যবসার সমস্ত সেবার জন্য বিশ্বস্ত পেশাদারদের সাথে সংযুক্ত হন'; ?>
            </p>
            
            <!-- Search Form -->
            <div class="search-container max-w-4xl mx-auto">
                <form action="search.php" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2"><?php echo t('service_category'); ?></label>
                            <select name="category" class="form-input">
                                <option value=""><?php echo $currentLang === 'en' ? 'Select Service' : 'সেবা নির্বাচন করুন'; ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo $currentLang === 'en' ? $category['name'] : $category['name_bn']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2"><?php echo t('location'); ?></label>
                            <input type="text" name="location" placeholder="<?php echo $currentLang === 'en' ? 'Enter location' : 'অবস্থান লিখুন'; ?>" 
                                   class="form-input">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2"><?php echo t('price_range'); ?></label>
                            <select name="price_range" class="form-input">
                                <option value=""><?php echo $currentLang === 'en' ? 'Any Price' : 'যেকোনো মূল্য'; ?></option>
                                <option value="500-1000">৳500 - ৳1,000</option>
                                <option value="1000-2500">৳1,000 - ৳2,500</option>
                                <option value="2500-5000">৳2,500 - ৳5,000</option>
                                <option value="5000+">৳5,000+</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full btn-primary">
                                <i class="fas fa-search mr-2"></i><?php echo t('search'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Popular Service Categories -->
    <section class="py-16 bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">
                    <?php echo $currentLang === 'en' ? 'Popular Service Categories' : 'জনপ্রিয় সেবা বিভাগ'; ?>
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    <?php echo $currentLang === 'en' ? 'Discover trusted professionals for all your service needs' : 'আপনার সমস্ত সেবার প্রয়োজন অনুযায়ী বিশ্বস্ত পেশাদারদের আবিষ্কার করুন'; ?>
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($categories as $category): 
                    // Get category image by upload folder first
                    $categoryImage = getCategoryImage((int)$category['id']);
                    
                    // Fallback: map by category name (supports EN/BN) to defaults under assets/images/default/categories
                    if (empty($categoryImage)) {
                        $nameForFallback = $currentLang === 'en' 
                            ? ($category['name'] ?? '') 
                            : ($category['name_bn'] ?? ($category['name'] ?? ''));
                        $categoryImage = getCategoryImageByName($nameForFallback);
                    }

                    // Final guard: if still empty, use a hard default
                    if (empty($categoryImage)) {
                        $categoryImage = 'assets/images/default/categories/ac-service.svg';
                    }
                    
                    // Get category icon for fallback
                    $categoryIcon = $category['icon'] ?? 'tools';
                ?>
                    <a href="search.php?category=<?php echo $category['id']; ?>" class="category-card group block">
                        <div class="relative overflow-hidden rounded-2xl bg-white shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2">
                            <div class="h-48 bg-gradient-to-br from-purple-100 to-pink-100 relative overflow-hidden">
                                <?php if (strpos($categoryImage, '.svg') !== false): ?>
                                    <!-- SVG Image with enhanced styling -->
                                    <div class="w-full h-full flex items-center justify-center p-8">
                                        <img 
                                            src="<?php echo htmlspecialchars($categoryImage); ?>" 
                                            alt="<?php echo htmlspecialchars($currentLang === 'en' ? ($category['name'] ?? '') : ($category['name_bn'] ?? ($category['name'] ?? ''))); ?>"
                                            class="w-24 h-24 object-contain filter drop-shadow-lg group-hover:scale-110 transition-transform duration-500"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >
                                        <div class="w-24 h-24 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center hidden">
                                            <i class="fas fa-<?php echo $categoryIcon; ?> text-white text-3xl"></i>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Regular Image -->
                                    <img 
                                        src="<?php echo htmlspecialchars($categoryImage); ?>" 
                                        alt="<?php echo htmlspecialchars($currentLang === 'en' ? ($category['name'] ?? '') : ($category['name_bn'] ?? ($category['name'] ?? ''))); ?>"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                        loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center hidden">
                                        <i class="fas fa-<?php echo $categoryIcon; ?> text-white text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Overlay with gradient -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                
                                <!-- Category badge -->
                                <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm rounded-full px-3 py-1 text-xs font-semibold text-gray-700 shadow-lg">
                                    <i class="fas fa-<?php echo $categoryIcon; ?> mr-1 text-purple-500"></i>
                                    <?php echo $currentLang === 'en' ? 'Service' : 'সেবা'; ?>
                                </div>
                            </div>
                            
                            <div class="p-6 bg-white">
                                <h3 class="text-xl font-bold text-gray-800 mb-3 group-hover:text-purple-600 transition-colors">
                                    <?php echo $currentLang === 'en' ? ($category['name'] ?? '') : ($category['name_bn'] ?? ($category['name'] ?? '')); ?>
                                </h3>
                                <div class="flex items-center justify-between">
                                    <p class="text-gray-600 text-sm font-medium">
                                        <?php echo $currentLang === 'en' ? 'View Providers' : 'পরিষেবা প্রদানকারীদের দেখুন'; ?>
                                    </p>
                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                        <i class="fas fa-arrow-right text-white text-sm"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Join as Provider Section -->
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-6 text-gray-800">
                <?php echo $currentLang === 'en' ? 'Are You a Service Provider?' : 'আপনি কি সেবা প্রদানকারী?'; ?>
            </h2>
            <p class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
                <?php echo $currentLang === 'en' ? 'Join our platform and start receiving service requests from customers in your area' : 'আমাদের প্ল্যাটফর্মে যোগ দিন এবং আপনার এলাকার গ্রাহকদের কাছ থেকে সেবা অনুরোধ পেতে শুরু করুন'; ?>
            </p>
            <a href="provider/register.php" class="bg-purple-600 text-white px-8 py-4 rounded-lg font-medium text-lg hover:bg-purple-700 transition duration-300 inline-flex items-center">
                <i class="fas fa-user-plus mr-2"></i><?php echo t('join_as_provider'); ?>
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">
                <?php echo $currentLang === 'en' ? 'Why Choose S24?' : 'কেন S24 বেছে নেবেন?'; ?>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800">
                        <?php echo $currentLang === 'en' ? 'Verified Providers' : 'যাচাইকৃত প্রদানকারী'; ?>
                    </h3>
                    <p class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'All service providers are verified with proper documentation' : 'সমস্ত সেবা প্রদানকারী সঠিক নথিপত্র সহ যাচাইকৃত'; ?>
                    </p>
                </div>
                
                <div class="text-center">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800">
                        <?php echo $currentLang === 'en' ? 'Customer Reviews' : 'গ্রাহক পর্যালোচনা'; ?>
                    </h3>
                    <p class="text-gray-600 mb-4">
                        <?php echo $currentLang === 'en' ? 'Read genuine reviews from real customers' : 'প্রকৃত গ্রাহকদের কাছ থেকে সত্যিকারের পর্যালোচনা পড়ুন'; ?>
                    </p>
                    <a href="public_reviews.php" class="text-blue-600 hover:text-blue-700 font-medium">
                        <?php echo $currentLang === 'en' ? 'View All Reviews' : 'সমস্ত পর্যালোচনা দেখুন'; ?>
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="text-center">
                    <div class="feature-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800">
                        <?php echo $currentLang === 'en' ? 'Direct Contact' : 'সরাসরি যোগাযোগ'; ?>
                    </h3>
                    <p class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Call or message providers directly' : 'সরাসরি কল করুন বা বার্তা পাঠান'; ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">S24</h3>
                    <p class="text-gray-400">
                        <?php echo $currentLang === 'en' ? 'Your trusted service provider directory' : 'আপনার বিশ্বস্ত সেবা প্রদানকারী ডিরেক্টরি'; ?>
                    </p>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4"><?php echo $currentLang === 'en' ? 'Services' : 'সেবা'; ?></h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'AC Servicing' : 'এসি সার্ভিসিং'; ?></a></li>
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'Plumbing' : 'প্লাম্বিং'; ?></a></li>
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'Electrical' : 'ইলেকট্রিক্যাল'; ?></a></li>
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'Cleaning' : 'ক্লিনিং'; ?></a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4"><?php echo $currentLang === 'en' ? 'Company' : 'কোম্পানি'; ?></h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'About Us' : 'আমাদের সম্পর্কে'; ?></a></li>
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'Contact' : 'যোগাযোগ'; ?></a></li>
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'Privacy Policy' : 'গোপনীয়তা নীতি'; ?></a></li>
                        <li><a href="#" class="hover:text-white"><?php echo $currentLang === 'en' ? 'Terms of Service' : 'সেবার শর্তাবলী'; ?></a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4"><?php echo $currentLang === 'en' ? 'Connect' : 'সংযুক্ত হন'; ?></h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-linkedin text-xl"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
            </div>
        </div>
    </footer>
    <script src="assets/ui.js"></script>
    <script>
        // Hero image slideshow
        document.addEventListener('DOMContentLoaded', function() {
            const heroSection = document.querySelector('.hero');
            const heroImages = <?php echo json_encode($heroImages); ?>;
            
            if (heroImages.length > 1) {
                let currentIndex = 0;
                
                function changeHeroImage() {
                    currentIndex = (currentIndex + 1) % heroImages.length;
                    heroSection.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('${heroImages[currentIndex]}')`;
                    
                    // Fade effect
                    heroSection.style.transition = 'background-image 1.5s ease-in-out';
                    setTimeout(() => {
                        heroSection.style.transition = 'none';
                    }, 1500);
                }
                
                // Change image every 5 seconds
                setInterval(changeHeroImage, 5000);
            }
        });
    </script>
</body>
</html>