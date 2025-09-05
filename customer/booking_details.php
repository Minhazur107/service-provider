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
    SELECT b.*, sp.name as provider_name, sp.phone as provider_phone, sp.email as provider_email, 
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN service_categories sc ON b.category_id = sc.id
    WHERE b.id = ? AND b.customer_id = ?
", [$bookingId, $user['id']]);

if (!$booking) {
    setFlashMessage('error', $currentLang === 'en' ? 'Booking not found' : 'বুকিং পাওয়া যায়নি');
    redirect('dashboard.php');
}

// Get review if exists
$review = fetchOne("SELECT * FROM reviews WHERE booking_id = ?", [$bookingId]);
// Payments for this booking
$payments = fetchAll("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC", [$bookingId]);
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Booking Details' : 'বুকিং বিবরণ'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Booking Details' : 'বুকিং বিবরণ'; ?></span>
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
        <div class="max-w-4xl mx-auto">
            <!-- Booking Status Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">
                            <?php echo $currentLang === 'en' ? 'Booking Details' : 'বুকিং বিবরণ'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $currentLang === 'en' ? 'Booking ID' : 'বুকিং আইডি'; ?>: #<?php echo $booking['id']; ?>
                        </p>
                    </div>
                    <span class="px-4 py-2 rounded-full text-sm font-medium
                        <?php echo $booking['status'] === 'completed' ? 'bg-green-100 text-green-600' : 
                            ($booking['status'] === 'confirmed' ? 'bg-blue-100 text-blue-600' : 
                            ($booking['status'] === 'pending' ? 'bg-yellow-100 text-yellow-600' : 'bg-red-100 text-red-600')); ?>">
                        <?php echo t($booking['status']); ?>
                    </span>
                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                        <a href="edit_booking.php?id=<?php echo $booking['id']; ?>" class="ml-3 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-edit mr-1"></i><?php echo $currentLang === 'en' ? 'Edit' : 'সম্পাদনা'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Service Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <?php echo $currentLang === 'en' ? 'Service Information' : 'সেবার তথ্য'; ?>
                        </h2>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Category' : 'সেবা বিভাগ'; ?></span>
                                <span class="font-medium"><?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?></span>
                            </div>
                            
                            <?php if ($booking['service_type']): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?></span>
                                    <span class="font-medium"><?php echo htmlspecialchars($booking['service_type']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Date' : 'সেবার তারিখ'; ?></span>
                                <span class="font-medium"><?php echo formatDate($booking['booking_date']); ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Service Time' : 'সেবার সময়'; ?></span>
                                <span class="font-medium"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></span>
                            </div>
                            
                            <?php if ($booking['final_price']): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Final Price' : 'চূড়ান্ত মূল্য'; ?></span>
                                    <span class="font-medium text-lg text-purple-600"><?php echo formatPrice($booking['final_price']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['cancellation_fee'] > 0): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Cancellation Fee' : 'বাতিলকরণ ফি'; ?></span>
                                    <span class="font-medium text-red-600"><?php echo formatPrice($booking['cancellation_fee']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($booking['notes']): ?>
                            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                <h3 class="font-medium text-gray-800 mb-2">
                                    <?php echo $currentLang === 'en' ? 'Customer Notes' : 'গ্রাহকের নোট'; ?>
                                </h3>
                                <p class="text-gray-700"><?php echo htmlspecialchars($booking['notes']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['cancellation_reason']): ?>
                            <div class="mt-6 p-4 bg-red-50 rounded-lg">
                                <h3 class="font-medium text-red-800 mb-2">
                                    <?php echo $currentLang === 'en' ? 'Cancellation Reason' : 'বাতিলকরণের কারণ'; ?>
                                </h3>
                                <p class="text-red-700"><?php echo htmlspecialchars($booking['cancellation_reason']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Section (only when completed) -->
                    <?php if ($booking['status'] === 'completed'): ?>
                        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo $currentLang === 'en' ? 'Payment' : 'পেমেন্ট'; ?></h2>
                            <?php if (!empty($payments)): ?>
                                <div class="mb-4 space-y-2">
                                    <?php foreach ($payments as $pay): ?>
                                        <div class="flex items-center justify-between border rounded-lg p-3">
                                            <div class="text-sm text-gray-700">
                                                <span class="font-medium capitalize"><?php echo htmlspecialchars($pay['method']); ?></span>
                                                <span class="ml-2"><?php echo formatPrice($pay['amount']); ?></span>
                                                <?php if ($pay['transaction_id']): ?>
                                                    <span class="ml-2 text-gray-500">TX: <?php echo htmlspecialchars($pay['transaction_id']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="px-2 py-1 rounded-full text-xs <?php echo $pay['status'] === 'verified' ? 'bg-green-100 text-green-600' : ($pay['status'] === 'pending' ? 'bg-yellow-100 text-yellow-600' : 'bg-red-100 text-red-600'); ?>"><?php echo t($pay['status']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form action="submit_payment.php" method="post" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Payment Method' : 'পেমেন্ট পদ্ধতি'; ?></label>
                                    <div class="flex items-center space-x-4">
                                        <label class="inline-flex items-center space-x-2">
                                            <input type="radio" name="method" value="bkash" class="text-purple-600" required>
                                            <span>bKash</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2">
                                            <input type="radio" name="method" value="nagad" class="text-purple-600" required>
                                            <span>Nagad</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2">
                                            <input type="radio" name="method" value="bank" class="text-purple-600" required>
                                            <span>Bank</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-700 mb-1"><?php echo $currentLang === 'en' ? 'Amount' : 'পরিমাণ'; ?></label>
                                        <input type="number" step="0.01" min="0" name="amount" value="<?php echo htmlspecialchars($booking['final_price'] ?? ''); ?>" class="w-full border rounded-lg px-3 py-2" required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm text-gray-700 mb-1"><?php echo $currentLang === 'en' ? 'Transaction ID (if applicable)' : 'লেনদেন আইডি (যদি থাকে)'; ?></label>
                                        <input type="text" name="transaction_id" class="w-full border rounded-lg px-3 py-2" placeholder="e.g., bKash/Nagad TXN ID">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1"><?php echo $currentLang === 'en' ? 'Upload Proof (optional)' : 'প্রমাণ আপলোড (ঐচ্ছিক)'; ?></label>
                                    <input type="file" name="payment_proof" accept="image/*,application/pdf" class="w-full">
                                </div>
                                <div class="text-right">
                                    <button type="submit" class="bg-purple-600 text-white px-5 py-2 rounded-lg hover:bg-purple-700"><?php echo $currentLang === 'en' ? 'Submit Payment' : 'পেমেন্ট জমা দিন'; ?></button>
                                </div>
                            </form>
                        </div>

                    <!-- Review Section -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">
                                <?php echo $currentLang === 'en' ? 'Review' : 'পর্যালোচনা'; ?>
                            </h2>
                            
                            <?php if ($review): ?>
                                <div class="space-y-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="flex items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star text-<?php echo $i <= $review['rating'] ? 'yellow' : 'gray'; ?>-400"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm text-gray-600"><?php echo $review['rating']; ?>/5</span>
                                        <span class="px-2 py-1 rounded-full text-xs 
                                            <?php echo $review['status'] === 'approved' ? 'bg-green-100 text-green-600' : 
                                                ($review['status'] === 'pending' ? 'bg-yellow-100 text-yellow-600' : 'bg-red-100 text-red-600'); ?>">
                                            <?php echo t($review['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($review['review_text']): ?>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($review['review_text']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($review['review_photo']): ?>
                                        <img src="../uploads/reviews/<?php echo $review['review_photo']; ?>" 
                                             alt="Review Photo" class="w-32 h-32 object-cover rounded-lg">
                                    <?php endif; ?>
                                    
                                    <p class="text-sm text-gray-500">
                                        <?php echo formatDateTime($review['created_at']); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-star text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 mb-4">
                                        <?php echo $currentLang === 'en' ? 'You haven\'t reviewed this service yet.' : 'আপনি এখনও এই সেবার পর্যালোচনা করেননি।'; ?>
                                    </p>
                                    <a href="review.php?booking_id=<?php echo $booking['id']; ?>" 
                                       class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-300">
                                        <?php echo $currentLang === 'en' ? 'Write a Review' : 'পর্যালোচনা লিখুন'; ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Provider Information -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <?php echo $currentLang === 'en' ? 'Service Provider' : 'সেবা প্রদানকারী'; ?>
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking['provider_name']); ?></h3>
                                <p class="text-sm text-gray-600">
                                    <?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?>
                                </p>
                            </div>
                            
                            <div class="space-y-2">
                                <a href="tel:<?php echo $booking['provider_phone']; ?>" 
                                   class="flex items-center space-x-2 text-green-600 hover:text-green-700">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo $booking['provider_phone']; ?></span>
                                </a>
                                
                                <?php if ($booking['provider_email']): ?>
                                    <a href="mailto:<?php echo $booking['provider_email']; ?>" 
                                       class="flex items-center space-x-2 text-blue-600 hover:text-blue-700">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo $booking['provider_email']; ?></span>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="https://wa.me/<?php echo $booking['provider_phone']; ?>" target="_blank"
                                   class="flex items-center space-x-2 text-green-500 hover:text-green-600">
                                    <i class="fab fa-whatsapp"></i>
                                    <span><?php echo $currentLang === 'en' ? 'WhatsApp' : 'হোয়াটসঅ্যাপ'; ?></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Timeline -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <?php echo $currentLang === 'en' ? 'Timeline' : 'সময়রেখা'; ?>
                        </h2>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-3 h-3 bg-purple-600 rounded-full mt-2"></div>
                                <div>
                                    <p class="font-medium text-gray-800">
                                        <?php echo $currentLang === 'en' ? 'Booking Created' : 'বুকিং তৈরি হয়েছে'; ?>
                                    </p>
                                    <p class="text-sm text-gray-500"><?php echo formatDateTime($booking['created_at']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($booking['status'] !== 'pending'): ?>
                                <div class="flex items-start space-x-3">
                                    <div class="w-3 h-3 bg-blue-600 rounded-full mt-2"></div>
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            <?php echo $currentLang === 'en' ? 'Booking Confirmed' : 'বুকিং নিশ্চিত হয়েছে'; ?>
                                        </p>
                                        <p class="text-sm text-gray-500"><?php echo formatDateTime($booking['updated_at']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'completed'): ?>
                                <div class="flex items-start space-x-3">
                                    <div class="w-3 h-3 bg-green-600 rounded-full mt-2"></div>
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            <?php echo $currentLang === 'en' ? 'Service Completed' : 'সেবা সম্পন্ন হয়েছে'; ?>
                                        </p>
                                        <p class="text-sm text-gray-500"><?php echo formatDateTime($booking['updated_at']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'cancelled'): ?>
                                <div class="flex items-start space-x-3">
                                    <div class="w-3 h-3 bg-red-600 rounded-full mt-2"></div>
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            <?php echo $currentLang === 'en' ? 'Booking Cancelled' : 'বুকিং বাতিল হয়েছে'; ?>
                                        </p>
                                        <p class="text-sm text-gray-500"><?php echo formatDateTime($booking['updated_at']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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
</body>
</html> 