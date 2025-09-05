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

$bookingId = $_GET['id'] ?? 0;

// Get booking details
$booking = fetchOne("
    SELECT b.*, sp.name as provider_name, sp.phone as provider_phone, sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN service_categories sc ON b.category_id = sc.id
    WHERE b.id = ? AND b.customer_id = ? AND b.status IN ('pending','confirmed')
", [$bookingId, $user['id']]);

if (!$booking) {
    setFlashMessage('error', $currentLang === 'en' ? 'Booking not found or cannot be cancelled' : 'বুকিং পাওয়া যায়নি বা বাতিল করা যায় না');
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitizeInput($_POST['reason']);
    
    if (empty($reason)) {
        $error = $currentLang === 'en' ? 'Please provide a cancellation reason' : 'বাতিলকরণের কারণ দিন';
    } else {
        // Calculate cancellation fee if applicable
        $cancellationFee = 0;
        $bookingDateTime = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']);
        $currentTime = time();
        $hoursDifference = ($bookingDateTime - $currentTime) / 3600;
        
        // Get platform settings
        $minBookingHours = fetchOne("SELECT setting_value FROM platform_settings WHERE setting_key = 'min_booking_hours'")['setting_value'] ?? 2;
        $cancellationFeePercentage = fetchOne("SELECT setting_value FROM platform_settings WHERE setting_key = 'cancellation_fee_percentage'")['setting_value'] ?? 10;
        
        if ($hoursDifference < $minBookingHours) {
            $cancellationFee = ($booking['final_price'] ?? 0) * ($cancellationFeePercentage / 100);
        }
        
        // Update booking
        $sql = "UPDATE bookings SET status = 'cancelled', cancellation_reason = ?, cancellation_fee = ? WHERE id = ?";
        try {
            executeQuery($sql, [$reason, $cancellationFee, $bookingId]);
            $success = $currentLang === 'en' ? 'Booking cancelled successfully!' : 'বুকিং সফলভাবে বাতিল হয়েছে!';
            
            if ($cancellationFee > 0) {
                $success .= ' ' . ($currentLang === 'en' ? 'A cancellation fee of ' : 'একটি বাতিলকরণ ফি ') . formatPrice($cancellationFee) . ($currentLang === 'en' ? ' has been applied.' : ' প্রয়োগ করা হয়েছে।');
            }
        } catch (Exception $e) {
            $error = $currentLang === 'en' ? 'Failed to cancel booking. Please try again.' : 'বুকিং বাতিল করতে ব্যর্থ। আবার চেষ্টা করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Cancel Booking' : 'বুকিং বাতিল করুন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Cancel Booking' : 'বুকিং বাতিল করুন'; ?></span>
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
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success; ?>
                    <div class="mt-2">
                        <a href="dashboard.php" class="text-green-800 underline">
                            <?php echo $currentLang === 'en' ? 'Go to Dashboard' : 'ড্যাশবোর্ডে যান'; ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Booking Details -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <?php echo $currentLang === 'en' ? 'Cancel Booking' : 'বুকিং বাতিল করুন'; ?>
                    </h1>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                            <p class="text-yellow-800">
                                <?php echo $currentLang === 'en' ? 'Are you sure you want to cancel this booking? This action cannot be undone.' : 'আপনি কি নিশ্চিত যে আপনি এই বুকিং বাতিল করতে চান? এই কাজটি পূর্বাবস্থায় ফেরানো যায় না।'; ?>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Provider' : 'সেবা প্রদানকারী'; ?></span>
                            <span class="font-medium"><?php echo htmlspecialchars($booking['provider_name']); ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Category' : 'সেবা বিভাগ'; ?></span>
                            <span class="font-medium"><?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Date' : 'সেবার তারিখ'; ?></span>
                            <span class="font-medium"><?php echo formatDate($booking['booking_date']); ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Time' : 'সেবার সময়'; ?></span>
                            <span class="font-medium"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></span>
                        </div>
                        
                        <?php if ($booking['service_type']): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?></span>
                                <span class="font-medium"><?php echo htmlspecialchars($booking['service_type']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['final_price']): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Final Price' : 'চূড়ান্ত মূল্য'; ?></span>
                                <span class="font-medium"><?php echo formatPrice($booking['final_price']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Cancellation Reason' : 'বাতিলকরণের কারণ'; ?> *
                            </label>
                            <textarea name="reason" required rows="4" 
                                      placeholder="<?php echo $currentLang === 'en' ? 'Please provide a reason for cancellation' : 'বাতিলকরণের কারণ দিন'; ?>"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="flex-1 bg-red-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-red-700 transition duration-300">
                                <?php echo $currentLang === 'en' ? 'Cancel Booking' : 'বুকিং বাতিল করুন'; ?>
                            </button>
                            <a href="dashboard.php" class="flex-1 bg-gray-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-gray-700 transition duration-300 text-center">
                                <?php echo $currentLang === 'en' ? 'Keep Booking' : 'বুকিং রাখুন'; ?>
                            </a>
                        </div>
                    </form>
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