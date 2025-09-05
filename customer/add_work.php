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

// Get service categories
$categories = fetchAll("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");

// Get Dhaka locations
$locations = getDhakaLocations();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)$_POST['category_id'];
    $location = sanitizeInput($_POST['location']);
    $description = sanitizeInput($_POST['description']);
    $budget_min = (float)$_POST['budget_min'];
    $budget_max = (float)$_POST['budget_max'];
    $preferred_date = $_POST['preferred_date'];
    $contact_phone = sanitizeInput($_POST['contact_phone']);
    
    // Validation
    if (empty($category_id) || empty($location) || empty($description)) {
        $error = $currentLang === 'en' ? 'Please fill in all required fields' : 'সব প্রয়োজনীয় ক্ষেত্র পূরণ করুন';
    } elseif ($budget_min > $budget_max) {
        $error = $currentLang === 'en' ? 'Minimum budget cannot be greater than maximum budget' : 'ন্যূনতম বাজেট সর্বোচ্চ বাজেটের চেয়ে বেশি হতে পারে না';
    } elseif ($preferred_date < date('Y-m-d')) {
        $error = $currentLang === 'en' ? 'Preferred date cannot be in the past' : 'পছন্দের তারিখ অতীতে হতে পারে না';
    } else {
        // Insert work request
        $sql = "INSERT INTO work_requests (customer_id, category_id, location, description, budget_min, budget_max, preferred_date, contact_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')";
        
        if (executeQuery($sql, [$user['id'], $category_id, $location, $description, $budget_min, $budget_max, $preferred_date, $contact_phone])) {
            $success = $currentLang === 'en' ? 'Work request posted successfully! Providers will be notified.' : 'কাজের অনুরোধ সফলভাবে পোস্ট হয়েছে! প্রদানকারীরা জানানো হবে।';
        } else {
            $error = $currentLang === 'en' ? 'Failed to post work request. Please try again.' : 'কাজের অনুরোধ পোস্ট করতে ব্যর্থ। আবার চেষ্টা করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Add Work Request' : 'কাজের অনুরোধ যোগ করুন'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Add Work Request' : 'কাজের অনুরোধ যোগ করুন'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back to Dashboard' : 'ড্যাশবোর্ডে ফিরে যান'; ?>
                    </a>
                    <a href="add_work.php?logout=true" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Page Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <?php echo $currentLang === 'en' ? 'Post a Work Request' : 'একটি কাজের অনুরোধ পোস্ট করুন'; ?>
                </h1>
                <p class="text-gray-600">
                    <?php echo $currentLang === 'en' ? 'Describe your work requirement and let verified providers bid on it' : 'আপনার কাজের প্রয়োজনীয়তা বর্ণনা করুন এবং যাচাইকৃত প্রদানকারীদের এটি নিয়ে বিড করতে দিন'; ?>
                </p>
            </div>

            <!-- Form -->
            <div class="bg-white rounded-xl shadow-lg p-8">
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
                    <!-- Service Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Service Category' : 'সেবার বিভাগ'; ?> *
                        </label>
                        <select name="category_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value=""><?php echo $currentLang === 'en' ? 'Select a category' : 'একটি বিভাগ নির্বাচন করুন'; ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo $currentLang === 'en' ? $category['name'] : $category['name_bn']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?> *
                        </label>
                        <select name="location" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value=""><?php echo $currentLang === 'en' ? 'Select location' : 'অবস্থান নির্বাচন করুন'; ?></option>
                            <?php foreach ($locations[$currentLang] as $location): ?>
                                <option value="<?php echo $locations['en'][array_search($location, $locations[$currentLang])]; ?>">
                                    <?php echo $location; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Work Description' : 'কাজের বিবরণ'; ?> *
                        </label>
                        <textarea name="description" required rows="4" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Describe your work requirement in detail...' : 'আপনার কাজের প্রয়োজনীয়তা বিস্তারিতভাবে বর্ণনা করুন...'; ?>"
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                    </div>

                    <!-- Budget Range -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Minimum Budget (৳)' : 'ন্যূনতম বাজেট (৳)'; ?>
                            </label>
                            <input type="number" name="budget_min" min="0" step="100" 
                                   placeholder="1000"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $currentLang === 'en' ? 'Maximum Budget (৳)' : 'সর্বোচ্চ বাজেট (৳)'; ?>
                            </label>
                            <input type="number" name="budget_max" min="0" step="100" 
                                   placeholder="5000"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Preferred Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Preferred Date' : 'পছন্দের তারিখ'; ?>
                        </label>
                        <input type="date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <!-- Contact Phone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Contact Phone' : 'যোগাযোগের ফোন'; ?>
                        </label>
                        <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter contact phone' : 'যোগাযোগের ফোন লিখুন'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-purple-700 transition duration-300">
                        <?php echo $currentLang === 'en' ? 'Post Work Request' : 'কাজের অনুরোধ পোস্ট করুন'; ?>
                    </button>
                </form>

                <!-- Info Box -->
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'How it works' : 'এটি কীভাবে কাজ করে'; ?>
                    </h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li><?php echo $currentLang === 'en' ? '• Your work request will be visible to verified providers' : '• আপনার কাজের অনুরোধ যাচাইকৃত প্রদানকারীদের কাছে দৃশ্যমান হবে'; ?></li>
                        <li><?php echo $currentLang === 'en' ? '• Providers can submit bids with their quotes' : '• প্রদানকারীরা তাদের মূল্য উদ্ধৃতির সাথে বিড জমা দিতে পারেন'; ?></li>
                        <li><?php echo $currentLang === 'en' ? '• You can review and select the best offer' : '• আপনি সেরা প্রস্তাব পর্যালোচনা এবং নির্বাচন করতে পারেন'; ?></li>
                        <li><?php echo $currentLang === 'en' ? '• Manage all your work requests from your dashboard' : '• আপনার ড্যাশবোর্ড থেকে সব কাজের অনুরোধ পরিচালনা করুন'; ?></li>
                    </ul>
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