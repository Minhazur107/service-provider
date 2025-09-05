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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $location = sanitizeInput($_POST['location']);
    
    // Validation
    if (empty($name)) {
        $error = $currentLang === 'en' ? 'Name is required' : 'নাম প্রয়োজন';
    } elseif ($email && !validateEmail($email)) {
        $error = $currentLang === 'en' ? 'Please enter a valid email address' : 'সঠিক ইমেইল ঠিকানা লিখুন';
    } else {
        // Check if email already exists (if changed)
        if ($email && $email !== $user['email']) {
            $existingEmail = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user['id']]);
            if ($existingEmail) {
                $error = $currentLang === 'en' ? 'Email address already registered' : 'ইমেইল ঠিকানা ইতিমধ্যে নিবন্ধিত';
            }
        }
        
        if (!$error) {
            // Update user profile
            $sql = "UPDATE users SET name = ?, email = ?, location = ? WHERE id = ?";
            try {
                executeQuery($sql, [$name, $email ?: null, $location, $user['id']]);
                $success = $currentLang === 'en' ? 'Profile updated successfully!' : 'প্রোফাইল সফলভাবে আপডেট হয়েছে!';
                // Refresh user data
                $user = getCurrentUser();
            } catch (Exception $e) {
                $error = $currentLang === 'en' ? 'Update failed. Please try again.' : 'আপডেট ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
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
    <title><?php echo $currentLang === 'en' ? 'My Profile' : 'আমার প্রোফাইল'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'My Profile' : 'আমার প্রোফাইল'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back to Dashboard' : 'ড্যাশবোর্ডে ফিরে যান'; ?>
                    </a>
                    <a href="?logout=true" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Profile Form -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">
                    <?php echo $currentLang === 'en' ? 'Profile Information' : 'প্রোফাইল তথ্য'; ?>
                </h1>

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

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Full Name' : 'পূর্ণ নাম'; ?> *
                        </label>
                        <input type="text" name="name" required 
                               value="<?php echo htmlspecialchars($user['name']); ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Email Address' : 'ইমেইল ঠিকানা'; ?>
                        </label>
                        <input type="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Phone Number' : 'ফোন নম্বর'; ?>
                        </label>
                        <input type="tel" value="<?php echo htmlspecialchars($user['phone']); ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $currentLang === 'en' ? 'Phone number cannot be changed' : 'ফোন নম্বর পরিবর্তন করা যায় না'; ?>
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?>
                        </label>
                        <input type="text" name="location" 
                               value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your location' : 'আপনার অবস্থান লিখুন'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Language Preference' : 'ভাষার পছন্দ'; ?>
                        </label>
                        <select name="language" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="en" <?php echo $user['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="bn" <?php echo $user['language'] === 'bn' ? 'selected' : ''; ?>>বাংলা</option>
                        </select>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 bg-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-purple-700 transition duration-300">
                            <?php echo $currentLang === 'en' ? 'Update Profile' : 'প্রোফাইল আপডেট করুন'; ?>
                        </button>
                        <a href="dashboard.php" class="flex-1 bg-gray-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-gray-700 transition duration-300 text-center">
                            <?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Account Information -->
            <div class="bg-white rounded-xl shadow-lg p-8 mt-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <?php echo $currentLang === 'en' ? 'Account Information' : 'অ্যাকাউন্ট তথ্য'; ?>
                </h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-gray-200">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Member Since' : 'সদস্য হওয়ার তারিখ'; ?></span>
                        <span class="font-medium"><?php echo formatDate($user['created_at']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center py-3 border-b border-gray-200">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Total Bookings' : 'মোট বুকিং'; ?></span>
                        <span class="font-medium">
                            <?php 
                            $totalBookings = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?", [$user['id']]);
                            echo $totalBookings['count'];
                            ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-center py-3">
                        <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Completed Services' : 'সম্পন্ন সেবা'; ?></span>
                        <span class="font-medium">
                            <?php 
                            $completedBookings = fetchOne("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ? AND status = 'completed'", [$user['id']]);
                            echo $completedBookings['count'];
                            ?>
                        </span>
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