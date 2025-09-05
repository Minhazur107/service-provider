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

$bookingId = $_GET['booking_id'] ?? 0;

// Get booking details
$booking = fetchOne("
    SELECT b.*, sp.name as provider_name, sp.id as provider_id, sc.name as category_name, sc.name_bn as category_name_bn
    FROM bookings b
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN service_categories sc ON b.category_id = sc.id
    WHERE b.id = ? AND b.customer_id = ? AND b.status = 'completed'
", [$bookingId, $user['id']]);

if (!$booking) {
    setFlashMessage('error', $currentLang === 'en' ? 'Booking not found or cannot be reviewed' : 'বুকিং পাওয়া যায়নি বা পর্যালোচনা করা যায় না');
    redirect('dashboard.php');
}

// Check if review already exists
$existingReview = fetchOne("SELECT id FROM reviews WHERE booking_id = ?", [$bookingId]);
if ($existingReview) {
    setFlashMessage('error', $currentLang === 'en' ? 'Review already submitted for this booking' : 'এই বুকিংয়ের জন্য ইতিমধ্যে পর্যালোচনা জমা দেওয়া হয়েছে');
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $reviewText = sanitizeInput($_POST['review_text']);
    
    if ($rating < 1 || $rating > 5) {
        $error = $currentLang === 'en' ? 'Please select a valid rating' : 'সঠিক রেটিং নির্বাচন করুন';
    } else {
        // Handle photo upload
        $reviewPhoto = '';
        if (isset($_FILES['review_photo']) && $_FILES['review_photo']['error'] === UPLOAD_ERR_OK) {
            $reviewPhoto = uploadFile($_FILES['review_photo'], '../uploads/reviews/');
        }
        
        // Insert review
        $sql = "INSERT INTO reviews (booking_id, customer_id, provider_id, rating, review_text, review_photo, status) VALUES (?, ?, ?, ?, ?, ?, 'approved')";
        try {
            executeQuery($sql, [$bookingId, $user['id'], $booking['provider_id'], $rating, $reviewText, $reviewPhoto]);
            $success = $currentLang === 'en' ? 'Review submitted successfully! Your review is now visible to other customers.' : 'পর্যালোচনা সফলভাবে জমা হয়েছে! আপনার পর্যালোচনা এখন অন্য গ্রাহকদের কাছে দৃশ্যমান।';
        } catch (Exception $e) {
            $error = $currentLang === 'en' ? 'Failed to submit review. Please try again.' : 'পর্যালোচনা জমা দিতে ব্যর্থ। আবার চেষ্টা করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Submit Review' : 'পর্যালোচনা জমা দিন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Submit Review' : 'পর্যালোচনা জমা দিন'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back to Dashboard' : 'ড্যাশবোর্ডে ফিরে যান'; ?>
                    </a>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'প্রস্থান'; ?>
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
                <!-- Service Details -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <?php echo $currentLang === 'en' ? 'Submit Review' : 'পর্যালোচনা জমা দিন'; ?>
                    </h1>

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

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Rating' : 'রেটিং'; ?> *
                            </label>
                            <div class="flex space-x-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" required class="hidden">
                                        <i class="fas fa-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors rating-star" data-rating="<?php echo $i; ?>"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">
                                <?php echo $currentLang === 'en' ? 'Click on the stars to rate the service' : 'সেবার রেটিং দিতে তারকায় ক্লিক করুন'; ?>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Review' : 'পর্যালোচনা'; ?>
                            </label>
                            <textarea name="review_text" rows="4" 
                                      placeholder="<?php echo $currentLang === 'en' ? 'Share your experience with this service provider (optional)' : 'এই সেবা প্রদানকারীর সাথে আপনার অভিজ্ঞতা শেয়ার করুন (ঐচ্ছিক)'; ?>"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Photo (Optional)' : 'ছবি (ঐচ্ছিক)'; ?>
                            </label>
                            <input type="file" name="review_photo" accept="image/*"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <p class="text-sm text-gray-500 mt-1">
                                <?php echo $currentLang === 'en' ? 'Upload a photo related to the service (optional)' : 'সেবার সাথে সম্পর্কিত একটি ছবি আপলোড করুন (ঐচ্ছিক)'; ?>
                            </p>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="flex-1 bg-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-purple-700 transition duration-300">
                                <?php echo $currentLang === 'en' ? 'Submit Review' : 'পর্যালোচনা জমা দিন'; ?>
                            </button>
                            <a href="dashboard.php" class="flex-1 bg-gray-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-gray-700 transition duration-300 text-center">
                                <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
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

    <script>
        // Star rating functionality
        const stars = document.querySelectorAll('.rating-star');
        const radioButtons = document.querySelectorAll('input[name="rating"]');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                const rating = index + 1;
                
                // Update radio button
                radioButtons[index].checked = true;
                
                // Update star display
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.remove('text-gray-300');
                        s.classList.add('text-yellow-400');
                    } else {
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });
            });
            
            star.addEventListener('mouseenter', () => {
                const rating = index + 1;
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.add('text-yellow-400');
                    }
                });
            });
            
            star.addEventListener('mouseleave', () => {
                const selectedRating = document.querySelector('input[name="rating"]:checked');
                const rating = selectedRating ? parseInt(selectedRating.value) : 0;
                
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.add('text-yellow-400');
                    } else {
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });
            });
        });
    </script>
</body>
</html> 