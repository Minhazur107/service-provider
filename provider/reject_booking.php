<?php
require_once '../includes/functions.php';

// Check if provider is logged in
if (!isProviderLoggedIn()) {
    redirect('../auth/login.php');
}

$currentLang = getLanguage();
$provider = getCurrentProvider();
$error = '';
$success = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}

// Get booking ID from URL
$bookingId = $_GET['id'] ?? 0;

if (!$bookingId) {
    redirect('bookings.php');
}

// Get booking details
$booking = fetchOne("
    SELECT b.*, u.name as customer_name, u.phone as customer_phone, sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    JOIN service_categories sc ON b.category_id = sc.id
    WHERE b.id = ? AND b.provider_id = ? AND b.status = 'pending'
", [$bookingId, $provider['id']]);

if (!$booking) {
    redirect('bookings.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rejectionReason = sanitizeInput($_POST['rejection_reason']);
    
    // Validation
    if (empty($rejectionReason)) {
        $error = $currentLang === 'en' ? 'Please provide a reason for rejection' : 'প্রত্যাখ্যানের কারণ প্রদান করুন';
    } else {
        // Update booking status
        $updateSql = "UPDATE bookings SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW() WHERE id = ?";
        $updateParams = [$rejectionReason, $bookingId];
        
        try {
            executeQuery($updateSql, $updateParams);
            
            // Create notification for customer
            $notificationTitle = 'Booking Rejected';
            $notificationTitleBn = 'বুকিং প্রত্যাখ্যান করা হয়েছে';
            $notificationMessage = "Your booking for {$booking['service_type']} on " . formatDate($booking['booking_date']) . " has been rejected by {$provider['name']}. Reason: {$rejectionReason}";
            $notificationMessageBn = "আপনার {$booking['service_type']} এর বুকিং " . formatDate($booking['booking_date']) . " তারিখে {$provider['name']} দ্বারা প্রত্যাখ্যান করা হয়েছে। কারণ: {$rejectionReason}";
            
            createNotification(
                'customer',
                $booking['customer_id'],
                null,
                $notificationTitle,
                $notificationTitleBn,
                $notificationMessage,
                $notificationMessageBn,
                'error',
                $bookingId,
                'booking'
            );
            
            $success = $currentLang === 'en' ? 'Booking rejected successfully! Customer has been notified.' : 'বুকিং সফলভাবে প্রত্যাখ্যান করা হয়েছে! গ্রাহককে জানানো হয়েছে।';
            
            // Redirect after a short delay
            header("refresh:2;url=bookings.php");
        } catch (Exception $e) {
            $error = $currentLang === 'en' ? 'Failed to reject booking. Please try again.' : 'বুকিং প্রত্যাখ্যান করতে ব্যর্থ। আবার চেষ্টা করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Reject Booking' : 'বুকিং প্রত্যাখ্যান করুন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Reject Booking' : 'বুকিং প্রত্যাখ্যান করুন'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="bookings.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back to Bookings' : 'বুকিংয়ে ফিরে যান'; ?>
                    </a>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo t('logout'); ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Warning -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-red-800">
                            <?php echo $currentLang === 'en' ? 'Are you sure you want to reject this booking?' : 'আপনি কি নিশ্চিত যে আপনি এই বুকিংটি প্রত্যাখ্যান করতে চান?'; ?>
                        </h3>
                        <p class="text-sm text-red-700 mt-1">
                            <?php echo $currentLang === 'en' ? 'This action cannot be undone and the customer will be notified immediately.' : 'এই কাজটি অপরিবর্তনীয় এবং গ্রাহককে অবিলম্বে জানানো হবে।'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Booking Details -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <?php echo $currentLang === 'en' ? 'Booking Details' : 'বুকিংয়ের বিবরণ'; ?>
                </h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?></span>
                        <span class="font-medium"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?></span>
                        <span class="font-medium"><?php echo htmlspecialchars($booking['service_type']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Category' : 'বিভাগ'; ?></span>
                        <span class="font-medium"><?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Date & Time' : 'তারিখ ও সময়'; ?></span>
                        <span class="font-medium">
                            <?php echo formatDate($booking['booking_date']); ?> at <?php echo date('h:i A', strtotime($booking['booking_time'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($booking['notes']): ?>
                        <div class="flex justify-between items-start py-2 border-b border-gray-200">
                            <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Customer Notes' : 'গ্রাহকের নোট'; ?></span>
                            <span class="font-medium text-right"><?php echo htmlspecialchars($booking['notes']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Contact' : 'যোগাযোগ'; ?></span>
                        <span class="font-medium"><?php echo $booking['customer_phone']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Reject Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <?php echo $currentLang === 'en' ? 'Reject This Booking' : 'এই বুকিংটি প্রত্যাখ্যান করুন'; ?>
                </h2>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Reason for Rejection' : 'প্রত্যাখ্যানের কারণ'; ?> *
                        </label>
                        <textarea name="rejection_reason" required rows="4" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Please provide a clear reason for rejecting this booking (e.g., unavailable on that date, outside service area, etc.)' : 'এই বুকিং প্রত্যাখ্যান করার একটি স্পষ্ট কারণ প্রদান করুন (যেমন: সেই তারিখে অনুপলব্ধ, সেবা এলাকার বাইরে, ইত্যাদি)'; ?>"
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $currentLang === 'en' ? 'This reason will be shared with the customer' : 'এই কারণটি গ্রাহকের সাথে ভাগ করা হবে'; ?>
                        </p>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <h3 class="font-semibold text-yellow-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'What happens when you reject?' : 'আপনি প্রত্যাখ্যান করলে কী হবে?'; ?>
                        </h3>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li>• <?php echo $currentLang === 'en' ? 'Customer will be notified immediately' : 'গ্রাহককে অবিলম্বে জানানো হবে'; ?></li>
                            <li>• <?php echo $currentLang === 'en' ? 'Booking status will change to cancelled' : 'বুকিংয়ের স্ট্যাটাস বাতিল হিসাবে পরিবর্তিত হবে'; ?></li>
                            <li>• <?php echo $currentLang === 'en' ? 'Customer can book with another provider' : 'গ্রাহক অন্য প্রদানকারীর সাথে বুক করতে পারেন'; ?></li>
                        </ul>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 bg-red-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-red-700 transition duration-300"
                                onclick="return confirm('<?php echo $currentLang === 'en' ? 'Are you absolutely sure you want to reject this booking?' : 'আপনি কি সম্পূর্ণ নিশ্চিত যে আপনি এই বুকিংটি প্রত্যাখ্যান করতে চান?'; ?>')">
                            <i class="fas fa-times mr-2"></i><?php echo $currentLang === 'en' ? 'Reject Booking' : 'বুকিং প্রত্যাখ্যান করুন'; ?>
                        </button>
                        <a href="bookings.php" class="flex-1 bg-gray-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-gray-700 transition duration-300 text-center">
                            <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Alternative Actions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                <h3 class="font-semibold text-blue-800 mb-2">
                    <?php echo $currentLang === 'en' ? 'Consider These Alternatives' : 'এই বিকল্পগুলি বিবেচনা করুন'; ?>
                </h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• <?php echo $currentLang === 'en' ? 'Contact the customer to discuss alternative dates/times' : 'বিকল্প তারিখ/সময় নিয়ে আলোচনা করতে গ্রাহকের সাথে যোগাযোগ করুন'; ?></li>
                    <li>• <?php echo $currentLang === 'en' ? 'Suggest a different service approach if possible' : 'সম্ভব হলে একটি ভিন্ন সেবা পদ্ধতি প্রস্তাব করুন'; ?></li>
                    <li>• <?php echo $currentLang === 'en' ? 'Refer to another provider if you cannot help' : 'আপনি সাহায্য করতে না পারলে অন্য প্রদানকারীর কাছে রেফার করুন'; ?></li>
                </ul>
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