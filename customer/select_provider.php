<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

$error = '';
$success = '';

// Get provider ID from URL
$providerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Support alternate query param from search page
if (!$providerId && isset($_GET['provider_id'])) {
	$providerId = (int)$_GET['provider_id'];
}

if (!$providerId) {
    redirect('dashboard.php');
}

// Get provider details
$provider = fetchOne("
    SELECT sp.*, sc.name as category_name, sc.name_bn as category_name_bn
    FROM service_providers sp
    LEFT JOIN service_categories sc ON sp.category_id = sc.id
    WHERE sp.id = ? AND sp.is_active = 1
", [$providerId]);

if (!$provider) {
    redirect('dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceType = sanitizeInput($_POST['service_type']);
    $preferredDate = $_POST['preferred_date'];
    $preferredTime = $_POST['preferred_time'];
    $customerAddress = sanitizeInput($_POST['customer_address']);
    $customerNotes = sanitizeInput($_POST['customer_notes']);
    $budgetMin = $_POST['budget_min'] ? (float)$_POST['budget_min'] : null;
    $budgetMax = $_POST['budget_max'] ? (float)$_POST['budget_max'] : null;
    
    // Validation
    if (empty($serviceType)) {
        $error = $currentLang === 'en' ? 'Service type is required' : 'সেবার ধরন প্রয়োজন';
    } elseif (empty($preferredDate)) {
        $error = $currentLang === 'en' ? 'Preferred date is required' : 'পছন্দের তারিখ প্রয়োজন';
    } elseif (strtotime($preferredDate) < strtotime('today')) {
        $error = $currentLang === 'en' ? 'Preferred date cannot be in the past' : 'পছন্দের তারিখ অতীত হতে পারে না';
    } elseif (empty($customerAddress)) {
        $error = $currentLang === 'en' ? 'Service address is required' : 'সেবার ঠিকানা প্রয়োজন';
    } elseif ($budgetMin && $budgetMax && $budgetMin > $budgetMax) {
        $error = $currentLang === 'en' ? 'Minimum budget cannot be greater than maximum budget' : 'ন্যূনতম বাজেট সর্বোচ্চ বাজেটের চেয়ে বেশি হতে পারে না';
    } else {
        try {
			// Create selection (allow multiple selections for same provider)
			executeQuery("
				INSERT INTO customer_provider_selections 
				(customer_id, provider_id, category_id, service_type, preferred_date, preferred_time, 
				 customer_address, customer_notes, budget_min, budget_max, status) 
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
			", [
				$user['id'], $providerId, $provider['category_id'], $serviceType, 
				$preferredDate, $preferredTime, $customerAddress, $customerNotes, 
				$budgetMin, $budgetMax
			]);
			
			$success = $currentLang === 'en' ? 'Provider selected successfully! They will contact you soon.' : 'প্রদানকারী সফলভাবে নির্বাচিত হয়েছে! তারা শীঘ্রই আপনার সাথে যোগাযোগ করবে।';
		} catch (Exception $e) {
			$error = $currentLang === 'en' ? 'Failed to select provider. Please try again.' : 'প্রদানকারী নির্বাচন করতে ব্যর্থ। আবার চেষ্টা করুন।';
		}
    }
}

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
    <title><?php echo $currentLang === 'en' ? 'Select Provider' : 'প্রদানকারী নির্বাচন করুন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Select Provider' : 'প্রদানকারী নির্বাচন করুন'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="text-purple-600 hover:text-purple-700">
                        <?php echo $currentLang === 'en' ? 'বাংলা' : 'EN'; ?>
                    </a>
                    <span class="text-gray-700"><?php echo htmlspecialchars($user['name']); ?></span>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Provider Info Card -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center space-x-4 mb-4">
                    <?php if ($provider['profile_picture']): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($provider['profile_picture']); ?>" 
                             alt="Provider" class="w-16 h-16 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-gray-400 text-2xl"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($provider['name']); ?></h1>
                        <p class="text-gray-600">
                            <?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
                        </p>
                        <div class="flex items-center space-x-4 text-sm text-gray-500 mt-2">
                            <span><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($provider['phone']); ?></span>
                            <span><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($provider['email']); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($provider['description']): ?>
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'About' : 'সম্পর্কে'; ?>
                        </h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($provider['description']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($provider['service_areas']): ?>
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Service Areas' : 'সেবার এলাকা'; ?>
                        </h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($provider['service_areas']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($provider['hourly_rate']): ?>
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Hourly Rate' : 'ঘণ্টায় হার'; ?>
                        </h3>
                        <p class="text-green-600 font-semibold"><?php echo formatPrice($provider['hourly_rate']); ?>/hour</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Selection Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <?php echo $currentLang === 'en' ? 'Request Service' : 'সেবা অনুরোধ করুন'; ?>
                </h2>
                
                <?php if ($error): ?>
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?> *
                            </label>
                            <input type="text" name="service_type" value="<?php echo htmlspecialchars($_POST['service_type'] ?? ''); ?>" 
                                   placeholder="<?php echo $currentLang === 'en' ? 'e.g., AC repair, plumbing, electrical' : 'যেমন, এসি মেরামত, প্লাম্বিং, বৈদ্যুতিক'; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Preferred Date' : 'পছন্দের তারিখ'; ?> *
                            </label>
                            <input type="date" name="preferred_date" value="<?php echo htmlspecialchars($_POST['preferred_date'] ?? ''); ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Preferred Time' : 'পছন্দের সময়'; ?> *
                            </label>
                            <input type="time" name="preferred_time" value="<?php echo htmlspecialchars($_POST['preferred_time'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Budget Range' : 'বাজেটের পরিসর'; ?>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="budget_min" value="<?php echo htmlspecialchars($_POST['budget_min'] ?? ''); ?>" 
                                       placeholder="<?php echo $currentLang === 'en' ? 'Min' : 'ন্যূনতম'; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <input type="number" name="budget_max" value="<?php echo htmlspecialchars($_POST['budget_max'] ?? ''); ?>" 
                                       placeholder="<?php echo $currentLang === 'en' ? 'Max' : 'সর্বোচ্চ'; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Service Address' : 'সেবার ঠিকানা'; ?> *
                        </label>
                        <textarea name="customer_address" rows="3" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Enter your full address where you need the service' : 'আপনার সম্পূর্ণ ঠিকানা লিখুন যেখানে সেবা প্রয়োজন'; ?>"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required><?php echo htmlspecialchars($_POST['customer_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Additional Notes' : 'অতিরিক্ত নোট'; ?>
                        </label>
                        <textarea name="customer_notes" rows="3" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Any specific requirements or details about your service need' : 'আপনার সেবা প্রয়োজন সম্পর্কে কোনো নির্দিষ্ট প্রয়োজনীয়তা বা বিবরণ'; ?>"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['customer_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="dashboard.php" 
                           class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            <i class="fas fa-handshake mr-2"></i>
                            <?php echo $currentLang === 'en' ? 'Select Provider' : 'প্রদানকারী নির্বাচন করুন'; ?>
                        </button>
                    </div>
                </form>
            </div>
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