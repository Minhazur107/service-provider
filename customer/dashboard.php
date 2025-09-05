<?php
require_once '../includes/functions.php';
require_once '../includes/image_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
	redirect('../auth/login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

// Get user's approved/confirmed bookings
$approvedBookings = fetchAll("
    SELECT b.*, sp.name as provider_name, sp.phone as provider_phone, sp.service_areas,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    LEFT JOIN service_categories sc ON b.category_id = sc.id
    WHERE b.customer_id = ? AND b.status IN ('confirmed', 'completed')
    ORDER BY b.booking_date DESC, b.booking_time DESC
", [$user['id']]);

// Get recent notifications
$notifications = getUnreadNotifications($user['id'], 'customer');

// Reviews summary for dashboard
$reviewSummary = fetchOne("
	SELECT 
		COUNT(*) as total,
		COUNT(*) as approved
	FROM reviews
	WHERE customer_id = ?
", [$user['id']]);
$recentReviews = fetchAll("
\tSELECT r.*, sp.name as provider_name
\tFROM reviews r
\tJOIN service_providers sp ON r.provider_id = sp.id
\tWHERE r.customer_id = ?
\tORDER BY r.created_at DESC
\tLIMIT 3
", [$user['id']]);

// Handle logout
if (isset($_GET['logout'])) {
	session_destroy();
	redirect('../index.php');
}

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
	markNotificationAsRead($_GET['mark_read']);
	redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $currentLang === 'en' ? 'Customer Dashboard' : 'কাস্টমার ড্যাশবোর্ড'; ?> - S24</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<!-- Add Google Fonts for better typography -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<style>
		body { font-family: 'Inter', sans-serif; }
		.shadow-soft { box-shadow: 0 10px 30px -15px rgba(0, 0, 0, 0.1); }
		.hover-scale { transition: transform 0.2s ease-in-out; }
		.hover-scale:hover { transform: translateY(-3px); }
	</style>
</head>
<body class="bg-gray-50">
	<!-- Header with Profile -->
	<header class="bg-white shadow-sm sticky top-0 z-10">
		<nav class="container mx-auto px-4 py-3">
			<div class="flex justify-between items-center">
				<div class="flex items-center space-x-4">
					<a href="../index.php" class="text-2xl font-bold text-purple-600 flex items-center">
						<img src="../assets/images/logo.png" alt="S24 Logo" class="h-10 w-auto mr-2">
						S24
					</a>
					<span class="text-gray-600 hidden md:inline-block"><?php echo $currentLang === 'en' ? 'Customer Dashboard' : 'কাস্টমার ড্যাশবোর্ড'; ?></span>
				</div>
				
				<div class="flex items-center space-x-4">
					<!-- My Selections -->
					<a href="my_selections.php" class="flex items-center text-purple-600 hover:text-purple-700 px-3 py-2 rounded-lg hover:bg-purple-50 transition-colors">
						<i class="fas fa-handshake mr-2"></i>
						<span><?php echo $currentLang === 'en' ? 'My Selections' : 'আমার নির্বাচন'; ?></span>
					</a>
					
					<!-- Language Toggle -->
					<a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="text-purple-600 hover:text-purple-700 px-3 py-2 rounded-lg hover:bg-purple-50 transition-colors">
						<?php echo $currentLang === 'en' ? 'বাংলা' : 'EN'; ?>
					</a>
					
					<!-- Profile -->
					<a href="profile.php" class="text-purple-600 hover:text-purple-700 px-3 py-2 rounded-lg hover:bg-purple-50 transition-colors">
						<?php echo $currentLang === 'en' ? 'Profile' : 'প্রোফাইল'; ?>
					</a>
					
					<!-- Logout -->
					<a href="?logout=1" class="text-red-600 hover:text-red-700 px-3 py-2 rounded-lg hover:bg-red-50 transition-colors">
						<?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
					</a>

					<!-- Theme Picker -->
					<div class="relative" data-theme-picker>
						<button type="button" class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700 hover:text-purple-700 hover:border-purple-300 flex items-center" data-toggle>
							<span class="inline-block h-3 w-3 rounded-full border border-gray-300 mr-2" data-theme-current></span>
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
			</div>
		</nav>
	</header>

	<div class="container mx-auto px-4 py-6">
		<!-- Welcome Section with Improved Design -->
		<div class="bg-gradient-to-r from-purple-600 to-indigo-700 text-white rounded-2xl p-8 mb-8 shadow-soft relative overflow-hidden">
			<div class="absolute top-0 right-0 w-64 h-64 bg-white opacity-10 rounded-full transform translate-x-32 -translate-y-32"></div>
			<div class="absolute bottom-0 left-0 w-64 h-64 bg-white opacity-10 rounded-full transform -translate-x-32 translate-y-32"></div>
			
			<div class="relative z-10">
				<div class="flex flex-col md:flex-row md:items-center justify-between">
					<div class="mb-6 md:mb-0">
						<h1 class="text-3xl md:text-4xl font-bold mb-3">
							<?php echo $currentLang === 'en' ? 'Welcome back,' : 'স্বাগতম,'; ?> <?php echo htmlspecialchars($user['name']); ?>! 
						</h1>
						<p class="text-purple-100 text-lg opacity-90 max-w-2xl">
							<?php echo $currentLang === 'en' ? 'Manage your service bookings and track your service history in one place.' : 'এক জায়গায় আপনার পরিষেবা বুকিং এবং ইতিহাস ট্র্যাক করুন।'; ?>
						</p>
					</div>
					<div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 text-center">
						<div class="text-3xl font-bold"><?php echo count($approvedBookings); ?></div>
						<div class="text-purple-100 text-sm opacity-90">
							<?php echo $currentLang === 'en' ? 'Upcoming Services' : 'আসন্ন পরিষেবা'; ?>
						</div>
					</div>
				</div>
				
				<!-- Quick Stats Row -->
				<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mt-8">
					<a href="bookings.php" class="block bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 hover:bg-opacity-20 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-purple-400" aria-label="<?php echo $currentLang === 'en' ? 'Active Bookings' : 'সক্রিয় বুকিং'; ?>">
						<div class="flex items-center justify-center h-12 w-12 rounded-full bg-white bg-opacity-20 mb-3 mx-auto">
							<i class="fas fa-calendar-check text-xl text-white"></i>
						</div>
						<div class="text-2xl font-bold text-center"><?php echo count($approvedBookings); ?></div>
						<div class="text-purple-100 text-sm opacity-90 text-center"><?php echo $currentLang === 'en' ? 'Active Bookings' : 'সক্রিয় বুকিং'; ?></div>
					</a>
					
					<a href="reviews.php" class="block bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 hover:bg-opacity-20 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-purple-400" aria-label="<?php echo $currentLang === 'en' ? 'Total Reviews' : 'মোট পর্যালোচনা'; ?>">
						<div class="flex items-center justify-center h-12 w-12 rounded-full bg-white bg-opacity-20 mb-3 mx-auto">
							<i class="fas fa-star text-xl text-yellow-300"></i>
						</div>
						<div class="text-2xl font-bold text-center"><?php echo (int)($reviewSummary['total'] ?? 0); ?></div>
						<div class="text-purple-100 text-sm opacity-90 text-center"><?php echo $currentLang === 'en' ? 'Total Reviews' : 'মোট পর্যালোচনা'; ?></div>
					</a>
					
					<a href="notifications.php" class="block bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 hover:bg-opacity-20 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-purple-400" aria-label="<?php echo $currentLang === 'en' ? 'Notifications' : 'নোটিফিকেশন'; ?>">
						<div class="flex items-center justify-center h-12 w-12 rounded-full bg-white bg-opacity-20 mb-3 mx-auto">
							<i class="fas fa-bell text-xl text-white"></i>
							<?php if (count($notifications) > 0): ?>
								<span class="absolute -mt-3 -mr-3 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
									<?php echo count($notifications) > 9 ? '9+' : count($notifications); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="text-2xl font-bold text-center"><?php echo count($notifications); ?></div>
						<div class="text-purple-100 text-sm opacity-90 text-center"><?php echo $currentLang === 'en' ? 'Notifications' : 'নোটিফিকেশন'; ?></div>
					</a>
					
					<a href="my_selections.php" class="block bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 hover:bg-opacity-20 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-purple-400" aria-label="<?php echo $currentLang === 'en' ? 'Favorites' : 'প্রিয়'; ?>">
						<div class="flex items-center justify-center h-12 w-12 rounded-full bg-white bg-opacity-20 mb-3 mx-auto">
							<i class="fas fa-heart text-xl text-pink-300"></i>
						</div>
						<?php
						$totalSelections = fetchOne("SELECT COUNT(*) as count FROM customer_provider_selections WHERE customer_id = ?", [$user['id']]);
						?>
						<div class="text-2xl font-bold text-center"><?php echo (int)($totalSelections['count'] ?? 0); ?></div>
						<div class="text-purple-100 text-sm opacity-90 text-center"><?php echo $currentLang === 'en' ? 'Favorites' : 'প্রিয়'; ?></div>
					</a>
					
					<a href="payments.php" class="block bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 hover:bg-opacity-20 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-purple-400" aria-label="<?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?>">
						<div class="flex items-center justify-center h-12 w-12 rounded-full bg-white bg-opacity-20 mb-3 mx-auto">
							<i class="fas fa-credit-card text-xl text-green-300"></i>
						</div>
						<?php
						$totalPayments = fetchOne("SELECT COUNT(*) as count FROM payments WHERE customer_id = ? AND status = 'verified'", [$user['id']]);
						?>
						<div class="text-2xl font-bold text-center"><?php echo (int)($totalPayments['count'] ?? 0); ?></div>
						<div class="text-purple-100 text-sm opacity-90 text-center"><?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?></div>
					</a>
					
					<a href="service_history.php" class="block bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 hover:bg-opacity-20 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-purple-400" aria-label="<?php echo $currentLang === 'en' ? 'Service History' : 'পরিষেবার ইতিহাস'; ?>">
						<div class="flex items-center justify-center h-12 w-12 rounded-full bg-white bg-opacity-20 mb-3 mx-auto">
							<i class="fas fa-history text-xl text-blue-300"></i>
						</div>
						<?php
						$totalHistory = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ? AND status = 'completed'", [$user['id']]);
						?>
						<div class="text-2xl font-bold text-center"><?php echo (int)($totalHistory['count'] ?? 0); ?></div>
						<div class="text-purple-100 text-sm opacity-90 text-center"><?php echo $currentLang === 'en' ? 'Service History' : 'পরিষেবার ইতিহাস'; ?></div>
					</a>
				</div>
			</div>
		</div>

		<!-- Find Services Section -->
		<div class="bg-white rounded-2xl shadow-soft p-6 mb-8 hover-scale">
			<div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
				<div>
					<h2 class="text-2xl font-bold text-gray-800">
						<i class="fas fa-search text-purple-600 mr-3"></i>
						<?php echo $currentLang === 'en' ? 'Find Services' : 'পরিষেবা খুঁজুন'; ?>
					</h2>
					<p class="text-gray-500 mt-1">
						<?php echo $currentLang === 'en' ? 'Search and book services in your area' : 'আপনার এলাকায় পরিষেবাগুলি অনুসন্ধান এবং বুকিং করুন'; ?>
					</p>
				</div>
				<a href="../search.php" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-2.5 rounded-lg hover:opacity-90 transition-opacity mt-4 md:mt-0 inline-block">
					<i class="fas fa-search mr-2"></i>
					<?php echo $currentLang === 'en' ? 'Advanced Search' : 'উন্নত সার্চ'; ?>
				</a>
			</div>
			<!-- Quick Search Form -->
			<div class="bg-gray-50 rounded-lg p-4 mb-6">
				<form action="../search.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
					<div>
						<label class="block text-gray-700 font-medium mb-2 text-sm">
							<?php echo $currentLang === 'en' ? 'Service Category' : 'পরিষেবা বিভাগ'; ?>
						</label>
						<select name="category" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
							<option value=""><?php echo $currentLang === 'en' ? 'Select Service' : 'পরিষেবা নির্বাচন করুন'; ?></option>
							<?php 
							$categories = fetchAll("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
							foreach ($categories as $category): 
							?>
								<option value="<?php echo $category['id']; ?>">
									<?php echo $currentLang === 'en' ? $category['name'] : $category['name_bn']; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<div>
						<label class="block text-gray-700 font-medium mb-2 text-sm">
							<?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?>
						</label>
						<input type="text" name="location" placeholder="<?php echo $currentLang === 'en' ? 'Enter your area' : 'আপনার এলাকা লিখুন'; ?>" 
							   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
					</div>
					
					<div>
						<label class="block text-gray-700 font-medium mb-2 text-sm">
							<?php echo $currentLang === 'en' ? 'Price Range' : 'মূল্য পরিসর'; ?>
						</label>
						<select name="price_range" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
							<option value=""><?php echo $currentLang === 'en' ? 'Any Price' : 'যেকোনো মূল্য'; ?></option>
							<option value="500-1000">৫০০ - ১,০০০</option>
							<option value="1000-2500">১,০০০ - ২,৫০০</option>
							<option value="2500-5000">২,৫০০ - ৫,০০০</option>
							<option value="5000+">৫,০০০+</option>
						</select>
					</div>
					
					<div class="flex items-end">
						<button type="submit" class="w-full bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition duration-300">
							<i class="fas fa-search mr-2"></i>
							<?php echo $currentLang === 'en' ? 'Search' : 'সার্চ'; ?>
						</button>
					</div>
				</form>
			</div>
			
			<!-- Popular Service Categories -->
			<div class="bg-white rounded-2xl shadow-soft p-6 mb-8">
				<div class="flex items-center justify-between mb-6">
					<h3 class="text-xl font-bold text-gray-800">
						<i class="fas fa-th-large text-purple-600 mr-3"></i>
						<?php echo $currentLang === 'en' ? 'Popular Service Categories' : 'জনপ্রিয় পরিষেবা বিভাগ'; ?>
					</h3>
					<a href="../search.php" class="text-purple-600 hover:text-purple-700 font-medium inline-flex items-center">
						<?php echo $currentLang === 'en' ? 'View All' : 'সব দেখুন'; ?>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
						</svg>
					</a>
				</div>
				<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
					<?php 
					$popularCategories = fetchAll("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name LIMIT 8");
					foreach ($popularCategories as $category): 
						// Get category image
						$categoryImage = getCategoryImage((int)$category['id']);
						if (empty($categoryImage)) {
							$nameForFallback = $currentLang === 'en' 
								? ($category['name'] ?? '') 
								: ($category['name_bn'] ?? ($category['name'] ?? ''));
							$categoryImage = getCategoryImageByName($nameForFallback);
						}
						if (empty($categoryImage)) {
							$categoryImage = '../assets/images/default/categories/ac-service.svg';
						}
						$categoryIcon = $category['icon'] ?? 'tools';
					?>
						<a href="../search.php?category=<?php echo $category['id']; ?>" 
						   class="category-card-dashboard group block">
							<div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 transition-all duration-300 border border-purple-200 hover:border-purple-300 p-4 text-center">
								<div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
									<?php if (strpos($categoryImage, '.svg') !== false): ?>
										<img src="<?php echo htmlspecialchars($categoryImage); ?>" 
											 alt="<?php echo htmlspecialchars($currentLang === 'en' ? $category['name'] : $category['name_bn']); ?>" 
											 class="w-6 h-6 object-contain filter brightness-0 invert"
											 loading="lazy"
											 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
										<i class="fas fa-<?php echo $categoryIcon; ?> text-white text-lg hidden"></i>
									<?php else: ?>
										<img src="<?php echo htmlspecialchars($categoryImage); ?>" 
											 alt="<?php echo htmlspecialchars($currentLang === 'en' ? $category['name'] : $category['name_bn']); ?>" 
											 class="w-6 h-6 object-contain rounded-full"
											 loading="lazy"
											 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
										<i class="fas fa-<?php echo $categoryIcon; ?> text-white text-lg hidden"></i>
									<?php endif; ?>
								</div>
								<h4 class="text-sm font-semibold text-gray-800 group-hover:text-purple-700 transition-colors">
									<?php echo $currentLang === 'en' ? $category['name'] : $category['name_bn']; ?>
								</h4>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			
			<!-- Quick Actions -->
			<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
				<a href="../search.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg p-4 text-center hover:from-blue-600 hover:to-blue-700 transition duration-300">
					<div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
						<i class="fas fa-search text-xl"></i>
					</div>
					<h4 class="font-semibold mb-1"><?php echo $currentLang === 'en' ? 'Search All Services' : 'সমস্ত পরিষেবা খুঁজুন'; ?></h4>
					<p class="text-sm opacity-90"><?php echo $currentLang === 'en' ? 'Find providers by category, location, and price' : 'বিভাগ, অবস্থান এবং মূল্য দ্বারা প্রদানকারী খুঁজুন'; ?></p>
				</a>
				
				<a href="my_selections.php" class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg p-4 text-center hover:from-green-600 hover:to-green-700 transition duration-300">
					<div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
						<i class="fas fa-handshake text-xl"></i>
					</div>
					<h4 class="font-semibold mb-1"><?php echo $currentLang === 'en' ? 'My Selections' : 'আমার নির্বাচন'; ?></h4>
					<p class="text-sm opacity-90"><?php echo $currentLang === 'en' ? 'View your selected service providers' : 'আপনার নির্বাচিত পরিষেবা প্রদানকারীদের দেখুন'; ?></p>
				</a>
				
				<a href="my_work_requests.php" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg p-4 text-center hover:from-purple-500 hover:to-purple-700 transition duration-300">
					<div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
						<i class="fas fa-tools text-xl"></i>
					</div>
					<h4 class="font-semibold mb-1"><?php echo $currentLang === 'en' ? 'Work Requests' : 'কাজের অনুরোধ'; ?></h4>
					<p class="text-sm opacity-90"><?php echo $currentLang === 'en' ? 'Manage your service work requests' : 'আপনার পরিষেবার কাজের অনুরোধ পরিচালনা করুন'; ?></p>
				</a>
			</div>
		</div>

		<!-- Search Suggestions -->
		<div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
			<h3 class="text-lg font-semibold text-gray-800 mb-4">
				<i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
				<?php echo $currentLang === 'en' ? 'Popular Searches' : 'জনপ্রিয় সার্চ'; ?>
			</h3>
			<div class="flex flex-wrap gap-2">
				<?php
				$popularSearches = [
					['term' => 'AC Service', 'bn' => 'এসি পরিষেবা', 'icon' => 'snowflake'],
					['term' => 'Plumbing', 'bn' => 'প্লাম্বিং', 'icon' => 'wrench'],
					['term' => 'Electrical', 'bn' => 'বৈদ্যুতিক', 'icon' => 'bolt'],
					['term' => 'Cleaning', 'bn' => 'পরিষ্কার', 'icon' => 'broom'],
					['term' => 'Carpentry', 'bn' => 'কাঠের কাজ', 'icon' => 'hammer'],
					['term' => 'Painting', 'bn' => 'আঁকা', 'icon' => 'paint-brush'],
					['term' => 'Moving', 'bn' => 'স্থানান্তর', 'icon' => 'truck'],
					['term' => 'Security', 'bn' => 'নিরাপত্তা', 'icon' => 'shield-alt']
				];
				
				foreach ($popularSearches as $search): ?>
					<a href="../search.php?q=<?php echo urlencode($search['term']); ?>" 
					   class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-purple-100 text-gray-700 hover:text-purple-700 rounded-full text-sm font-medium transition duration-300 border border-gray-200 hover:border-purple-300">
						<i class="fas fa-<?php echo $search['icon']; ?> mr-2 text-purple-500"></i>
						<?php echo $currentLang === 'en' ? $search['term'] : $search['bn']; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Recent Service Providers -->
		<div class="bg-white rounded-2xl shadow-soft p-6 mb-8">
			<div class="flex items-center justify-between mb-6">
				<h2 class="text-2xl font-bold text-gray-800">
					<i class="fas fa-users text-purple-600 mr-3"></i>
					<?php echo $currentLang === 'en' ? 'Featured Service Providers' : 'বাছাইকৃত পরিষেবা প্রদানকারী'; ?>
				</h2>
				<a href="../search.php" class="text-purple-600 hover:text-purple-700 font-medium inline-flex items-center">
					<?php echo $currentLang === 'en' ? 'View All' : 'সব দেখুন'; ?>
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
					</svg>
				</a>
			</div>

			<?php
			// Fetch recent service providers with their categories and ratings
			$recentProviders = fetchAll("
				SELECT sp.*, 
				       sc.name as category_name, 
				       sc.name_bn as category_name_bn,
				       sc.icon as category_icon,
				       AVG(r.rating) as avg_rating, 
				       COUNT(r.id) as review_count,
				       sp.profile_picture as profile_image
				FROM service_providers sp
				LEFT JOIN service_categories sc ON sp.category_id = sc.id
				LEFT JOIN reviews r ON sp.id = r.provider_id AND r.status = 'approved'
				WHERE sp.verification_status = 'verified' AND sp.is_active = 1
				GROUP BY sp.id
				ORDER BY sp.created_at DESC
				LIMIT 4
			");
			?>

			<?php if (!empty($recentProviders)): ?>
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
					<?php foreach ($recentProviders as $provider): 
						// Generate a default gradient if no image
						$gradientColors = [
							'from-blue-500 to-purple-600',
							'from-green-500 to-teal-600',
							'from-amber-500 to-orange-600',
							'from-pink-500 to-rose-600'
						];
						$gradient = $gradientColors[array_rand($gradientColors)];
					?>
						<div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 border border-gray-100">
							<!-- Provider Image -->
							<div class="h-40 relative overflow-hidden">
								<?php if (!empty($provider['profile_image'])): ?>
									<img 
										src="<?php echo htmlspecialchars($provider['profile_image']); ?>" 
										alt="<?php echo htmlspecialchars($provider['name']); ?>"
										class="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
									>
								<?php else: ?>
									<div class="w-full h-full flex items-center justify-center bg-gradient-to-r <?php echo $gradient; ?>">
										<span class="text-white text-4xl font-bold">
											<?php echo strtoupper(substr($provider['name'] ?? 'SP', 0, 1)); ?>
										</span>
									</div>
								<?php endif; ?>
								
								<!-- Category Badge -->
								<div class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm rounded-full px-3 py-1 text-xs font-medium text-gray-800 shadow-sm">
									<?php if (!empty($provider['category_icon'])): ?>
										<i class="fas fa-<?php echo htmlspecialchars($provider['category_icon']); ?> mr-1"></i>
									<?php endif; ?>
									<?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
								</div>
							</div>

							<!-- Provider Info -->
							<div class="p-4">
								<div class="flex justify-between items-start mb-2">
									<h3 class="font-bold text-lg text-gray-800 line-clamp-1">
										<?php echo htmlspecialchars($provider['name']); ?>
									</h3>
									<?php if ($provider['verification_status'] === 'verified'): ?>
										<span class="inline-flex items-center text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full">
											<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
												<path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
											</svg>
										<?php echo $currentLang === 'en' ? 'Verified' : 'যাচাইকৃত'; ?>
									</span>
								<?php endif; ?>
							</div>
							<div class="mt-2">
								<?php if ($provider['avg_rating'] > 0): ?>
									<div class="flex items-center text-yellow-500 mb-1">
										<?php for ($i = 1; $i <= 5; $i++): ?>
											<?php if ($i <= floor($provider['avg_rating'])): ?>
												<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
													<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 01-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
												</svg>
											<?php else: ?>
												<svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
													<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 01-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
												</svg>
											<?php endif; ?>
										<?php endfor; ?>
										<span class="text-sm text-gray-600 ml-1"><?php echo number_format($provider['avg_rating'], 1); ?> (<?php echo $provider['review_count']; ?>)</span>
									</div>
								<?php else: ?>
									<div class="text-sm text-gray-500"><?php echo $currentLang === 'en' ? 'No reviews yet' : 'এখনও কোন রিভিউ নেই'; ?></div>
								<?php endif; ?>

								<?php if (!empty($provider['service_areas'])): ?>
									<div class="flex items-center text-sm text-gray-600 mt-1">
										<svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
										</svg>
										<span class="truncate"><?php echo htmlspecialchars($provider['service_areas']); ?></span>
									</div>
								<?php endif; ?>
							</div>

							<!-- Action Buttons -->
							<div class="mt-4 flex space-x-2">
								<a href="../provider/profile.php?id=<?php echo $provider['id']; ?>" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white text-center py-2 px-3 rounded-lg text-sm font-medium transition duration-300">
									<?php echo $currentLang === 'en' ? 'View Profile' : 'প্রোফাইল দেখুন'; ?>
								</a>
								<a href="tel:<?php echo htmlspecialchars($provider['phone']); ?>" class="inline-flex items-center justify-center w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 transition-colors">
									<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
										<path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
									</svg>
								</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="text-center py-12 bg-gray-50 rounded-xl">
				<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
				</svg>
				<h3 class="mt-2 text-lg font-medium text-gray-900">
					<?php echo $currentLang === 'en' ? 'No service providers found' : 'কোন পরিষেবা প্রদানকারী পাওয়া যায়নি'; ?>
				</h3>
				<p class="mt-1 text-sm text-gray-500">
					<?php echo $currentLang === 'en' 
						? 'Check back later for new service providers.' 
						: 'নতুন পরিষেবা প্রদানকারীদের জন্য পরে আবার চেক করুন।'; ?>
				</p>
				<div class="mt-6">
					<a href="../search.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
						<svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
						</svg>
						<?php echo $currentLang === 'en' ? 'Browse Services' : 'পরিষেবা ব্রাউজ করুন'; ?>
					</a>
				</div>
			</div>
		<?php endif; ?>
		</div>

		<!-- Footer -->
		<footer class="bg-white border-t border-gray-200 mt-12">
			<div class="container mx-auto px-4 py-6">
				<div class="flex flex-col md:flex-row justify-between items-center">
					<div class="flex items-center space-x-4 mb-4 md:mb-0">
						<a href="../index.php" class="text-xl font-bold text-purple-600">S24</a>
						<span class="text-gray-500 text-sm"> 2023 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সমস্ত অধিকার সংরক্ষিত।'; ?></span>
					</div>
					<div class="flex space-x-6">
						<a href="#" class="text-gray-500 hover:text-purple-600">
							<span class="sr-only">Facebook</span>
							<svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987H7v-2.333C7 6.51 8.807 4 12 4c4.36 0 7.93 2.64 8.33 6.496V9v2.333h-1.67v6.987A6.048 6.048 0 0122 12z" clip-rule="evenodd" />
							</svg>
						</a>
						<a href="#" class="text-gray-500 hover:text-purple-600">
							<span class="sr-only">Twitter</span>
							<svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
							</svg>
						</a>
					</div>
				</div>
			</div>
		</footer>
	</div>

	<!-- Scripts -->
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
	<script>
		// Mobile menu toggle
		document.getElementById('mobile-menu-button').addEventListener('click', function() {
			document.getElementById('mobile-menu').classList.toggle('hidden');
		});

		// Initialize tooltips
		document.addEventListener('DOMContentLoaded', function() {
			var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
			var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl);
			});
		});

		// Auto-hide alerts after 5 seconds
		setTimeout(function() {
			var alerts = document.querySelectorAll('.alert');
			alerts.forEach(function(alert) {
				var alertInstance = bootstrap.Alert.getInstance(alert);
				if (alertInstance) {
					alertInstance.close();
				}
			});
		}, 5000);
	</script>

	<!-- Theme script -->
	<script src="../assets/ui.js"></script>
</body>
</html>