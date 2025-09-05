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
    $finalPrice = sanitizeInput($_POST['final_price']);
    $providerNotes = sanitizeInput($_POST['provider_notes']);
    
    // Validation
    if (empty($finalPrice) || !is_numeric($finalPrice) || $finalPrice <= 0) {
        $error = $currentLang === 'en' ? 'Please enter a valid final price' : 'একটি বৈধ চূড়ান্ত মূল্য লিখুন';
    } else {
        // Update booking status
        $updateSql = "UPDATE bookings SET status = 'confirmed', final_price = ?, updated_at = NOW() WHERE id = ?";
        $updateParams = [$finalPrice, $bookingId];
        
        try {
            executeQuery($updateSql, $updateParams);
            
            // Create notification for customer
            $notificationTitle = 'Booking Confirmed';
            $notificationTitleBn = 'বুকিং নিশ্চিত করা হয়েছে';
            $notificationMessage = "Your booking for {$booking['service_type']} on " . formatDate($booking['booking_date']) . " has been confirmed by {$provider['name']}. Final price: " . formatPrice($finalPrice);
            $notificationMessageBn = "আপনার {$booking['service_type']} এর বুকিং " . formatDate($booking['booking_date']) . " তারিখে {$provider['name']} দ্বারা নিশ্চিত করা হয়েছে। চূড়ান্ত মূল্য: " . formatPrice($finalPrice);
            
            createNotification(
                'customer',
                $booking['customer_id'],
                null,
                $notificationTitle,
                $notificationTitleBn,
                $notificationMessage,
                $notificationMessageBn,
                'success',
                $bookingId,
                'booking'
            );
            
            $success = $currentLang === 'en' ? 'Booking accepted successfully! Customer has been notified.' : 'বুকিং সফলভাবে গ্রহণ করা হয়েছে! গ্রাহককে জানানো হয়েছে।';
            
            // Redirect after a short delay
            header("refresh:2;url=bookings.php");
        } catch (Exception $e) {
            $error = $currentLang === 'en' ? 'Failed to accept booking. Please try again.' : 'বুকিং গ্রহণ করতে ব্যর্থ। আবার চেষ্টা করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Accept Booking' : 'বুকিং গ্রহণ করুন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Accept Booking' : 'বুকিং গ্রহণ করুন'; ?></span>
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

            <!-- Accept Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <?php echo $currentLang === 'en' ? 'Accept This Booking' : 'এই বুকিংটি গ্রহণ করুন'; ?>
                </h2>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Final Price (৳)' : 'চূড়ান্ত মূল্য (৳)'; ?> *
                        </label>
                        <input type="number" name="final_price" required min="1" step="0.01"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter final price' : 'চূড়ান্ত মূল্য লিখুন'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $currentLang === 'en' ? 'This will be the final amount the customer pays' : 'এটি হবে গ্রাহক যে চূড়ান্ত পরিমাণ প্রদান করবে'; ?>
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Provider Notes (Optional)' : 'প্রদানকারীর নোট (ঐচ্ছিক)'; ?>
                        </label>
                        <textarea name="provider_notes" rows="4" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Any additional notes or instructions for the customer' : 'গ্রাহকের জন্য কোনো অতিরিক্ত নোট বা নির্দেশনা'; ?>"
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'What happens when you accept?' : 'আপনি গ্রহণ করলে কী হবে?'; ?>
                        </h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• <?php echo $currentLang === 'en' ? 'Customer will be notified immediately' : 'গ্রাহককে অবিলম্বে জানানো হবে'; ?></li>
                            <li>• <?php echo $currentLang === 'en' ? 'Booking status will change to confirmed' : 'বুকিংয়ের স্ট্যাটাস নিশ্চিত হিসাবে পরিবর্তিত হবে'; ?></li>
                            <li>• <?php echo $currentLang === 'en' ? 'You can now proceed with the service' : 'আপনি এখন সেবা দিতে পারেন'; ?></li>
                        </ul>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 bg-green-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-green-700 transition duration-300">
                            <i class="fas fa-check mr-2"></i><?php echo $currentLang === 'en' ? 'Accept Booking' : 'বুকিং গ্রহণ করুন'; ?>
                        </button>
                        <a href="bookings.php" class="flex-1 bg-gray-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-gray-700 transition duration-300 text-center">
                            <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
                        </a>
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