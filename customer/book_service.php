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

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}

// Get provider ID from URL
$providerId = $_GET['provider_id'] ?? 0;

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

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceType = sanitizeInput($_POST['service_type']);
    $bookingDate = $_POST['booking_date'];
    $bookingTime = $_POST['booking_time'];
    $notes = sanitizeInput($_POST['notes']);
    
    // Validation
    if (empty($serviceType) || empty($bookingDate) || empty($bookingTime)) {
        $error = $currentLang === 'en' ? 'Please fill in all required fields' : 'সব প্রয়োজনীয় ক্ষেত্র পূরণ করুন';
    } elseif (strtotime($bookingDate) < strtotime(date('Y-m-d'))) {
        $error = $currentLang === 'en' ? 'Booking date cannot be in the past' : 'বুকিং তারিখ অতীত হতে পারে না';
    } else {
        // Check if the date/time is available (basic check)
        $existingBooking = fetchOne("
            SELECT id FROM bookings 
            WHERE provider_id = ? AND booking_date = ? AND booking_time = ? AND status IN ('pending', 'confirmed')
        ", [$providerId, $bookingDate, $bookingTime]);
        
        if ($existingBooking) {
            $error = $currentLang === 'en' ? 'This time slot is already booked. Please choose another time.' : 'এই সময় স্লট ইতিমধ্যে বুক করা আছে। অন্য সময় বেছে নিন।';
        } else {
            // Create booking
            $sql = "INSERT INTO bookings (customer_id, provider_id, category_id, service_type, booking_date, booking_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $params = [$user['id'], $providerId, $provider['category_id'], $serviceType, $bookingDate, $bookingTime, $notes];
            
            try {
                executeQuery($sql, $params);
                $success = $currentLang === 'en' ? 'Booking request sent successfully! The provider will confirm your booking soon.' : 'বুকিং অনুরোধ সফলভাবে পাঠানো হয়েছে! প্রদানকারী শীঘ্রই আপনার বুকিং নিশ্চিত করবে।';
            } catch (Exception $e) {
                $error = $currentLang === 'en' ? 'Booking failed. Please try again.' : 'বুকিং ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Book Service' : 'সেবা বুক করুন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Book Service' : 'সেবা বুক করুন'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back to Dashboard' : 'ড্যাশবোর্ডে ফিরে যান'; ?>
                    </a>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Provider Info -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center space-x-4 mb-4">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center">
                        <?php if ($provider['profile_picture']): ?>
                            <img src="../uploads/profiles/<?php echo $provider['profile_picture']; ?>" 
                                 alt="<?php echo htmlspecialchars($provider['name']); ?>" 
                                 class="w-16 h-16 rounded-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user text-purple-600 text-2xl"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($provider['name']); ?></h1>
                        <p class="text-gray-600">
                            <?php echo $currentLang === 'en' ? $provider['category_name'] : $provider['category_name_bn']; ?>
                        </p>
                        <?php if ($provider['verification_badge']): ?>
                            <span class="bg-green-100 text-green-600 px-2 py-1 rounded-full text-xs font-medium">
                                <i class="fas fa-check-circle mr-1"></i><?php echo t('verified'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>
                        <span><?php echo htmlspecialchars($provider['service_areas']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-tag text-purple-500 mr-2"></i>
                        <span><?php echo formatPrice($provider['price_min']); ?> - <?php echo formatPrice($provider['price_max']); ?></span>
                    </div>
                    <?php if ($provider['hourly_rate']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-purple-500 mr-2"></i>
                            <span><?php echo formatPrice($provider['hourly_rate']); ?>/<?php echo $currentLang === 'en' ? 'hour' : 'ঘণ্টা'; ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($provider['availability_hours']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-calendar text-purple-500 mr-2"></i>
                            <span><?php echo htmlspecialchars($provider['availability_hours']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <?php echo $currentLang === 'en' ? 'Book Service' : 'সেবা বুক করুন'; ?>
                </h2>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo $success; ?>
                        <div class="mt-2">
                            <a href="dashboard.php" class="text-green-800 underline">
                                <?php echo $currentLang === 'en' ? 'Go to Dashboard' : 'ড্যাশবোর্ডে যান'; ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?> *
                        </label>
                        <input type="text" name="service_type" required 
                               placeholder="<?php echo $currentLang === 'en' ? 'e.g., AC repair, Plumbing work' : 'যেমন: এসি মেরামত, প্লাম্বিং কাজ'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Preferred Date' : 'পছন্দের তারিখ'; ?> *
                            </label>
                            <input type="date" name="booking_date" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Preferred Time' : 'পছন্দের সময়'; ?> *
                            </label>
                            <select name="booking_time" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value=""><?php echo $currentLang === 'en' ? 'Select time' : 'সময় নির্বাচন করুন'; ?></option>
                                <option value="09:00:00">9:00 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="12:00:00">12:00 PM</option>
                                <option value="13:00:00">1:00 PM</option>
                                <option value="14:00:00">2:00 PM</option>
                                <option value="15:00:00">3:00 PM</option>
                                <option value="16:00:00">4:00 PM</option>
                                <option value="17:00:00">5:00 PM</option>
                                <option value="18:00:00">6:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Additional Notes' : 'অতিরিক্ত নোট'; ?>
                        </label>
                        <textarea name="notes" rows="4" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Describe your service requirements, special instructions, or any other details' : 'আপনার সেবার প্রয়োজনীয়তা, বিশেষ নির্দেশনা বা অন্য বিবরণ বর্ণনা করুন'; ?>"
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Important Information' : 'গুরুত্বপূর্ণ তথ্য'; ?>
                        </h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• <?php echo $currentLang === 'en' ? 'Your booking request will be sent to the provider for confirmation' : 'আপনার বুকিং অনুরোধ নিশ্চিতকরণের জন্য প্রদানকারীর কাছে পাঠানো হবে'; ?></li>
                            <li>• <?php echo $currentLang === 'en' ? 'The provider will contact you to confirm the booking and discuss pricing' : 'প্রদানকারী বুকিং নিশ্চিত করতে এবং মূল্য নিয়ে আলোচনা করতে আপনার সাথে যোগাযোগ করবে'; ?></li>
                            <li>• <?php echo $currentLang === 'en' ? 'You can cancel the booking before it is confirmed without any charge' : 'নিশ্চিত হওয়ার আগে আপনি কোনো চার্জ ছাড়াই বুকিং বাতিল করতে পারেন'; ?></li>
                        </ul>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 bg-purple-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-purple-700 transition duration-300">
                            <?php echo $currentLang === 'en' ? 'Send Booking Request' : 'বুকিং অনুরোধ পাঠান'; ?>
                        </button>
                        <a href="../search.php" class="bg-gray-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-gray-700 transition duration-300">
                            <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Contact Provider -->
            <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <?php echo $currentLang === 'en' ? 'Contact Provider' : 'প্রদানকারীর সাথে যোগাযোগ করুন'; ?>
                </h3>
                <div class="flex space-x-4">
                    <a href="tel:<?php echo $provider['phone']; ?>" 
                       class="flex-1 bg-green-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-green-700 transition duration-300 text-center">
                        <i class="fas fa-phone mr-2"></i><?php echo $currentLang === 'en' ? 'Call Now' : 'এখনই কল করুন'; ?>
                    </a>
                    <a href="https://wa.me/<?php echo $provider['phone']; ?>?text=<?php echo urlencode($currentLang === 'en' ? 'Hi, I am interested in your services. Can you provide more details?' : 'হাই, আমি আপনার সেবায় আগ্রহী। আপনি কি আরও বিস্তারিত দিতে পারেন?'); ?>" 
                       target="_blank"
                       class="flex-1 bg-green-500 text-white py-3 px-6 rounded-lg font-medium hover:bg-green-600 transition duration-300 text-center">
                        <i class="fab fa-whatsapp mr-2"></i><?php echo $currentLang === 'en' ? 'WhatsApp' : 'হোয়াটসঅ্যাপ'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>

    <script>
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="booking_date"]').min = today;
        
        // Auto-focus on service type input
        document.querySelector('input[name="service_type"]').focus();
    </script>
</body>
</html> 