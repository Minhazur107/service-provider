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

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $work_id = (int)$_GET['delete'];
    $work_request = fetchOne("SELECT * FROM work_requests WHERE id = ? AND customer_id = ?", [$work_id, $user['id']]);
    
    if ($work_request && $work_request['status'] === 'open') {
        if (executeQuery("DELETE FROM work_requests WHERE id = ?", [$work_id])) {
            $success = $currentLang === 'en' ? 'Work request deleted successfully' : 'কাজের অনুরোধ সফলভাবে মুছে ফেলা হয়েছে';
        } else {
            $error = $currentLang === 'en' ? 'Failed to delete work request' : 'কাজের অনুরোধ মুছতে ব্যর্থ';
        }
    } else {
        $error = $currentLang === 'en' ? 'Work request not found or cannot be deleted' : 'কাজের অনুরোধ পাওয়া যায়নি বা মুছে ফেলা যাবে না';
    }
}

// Get user's work requests
$workRequests = fetchAll("
    SELECT wr.*, sc.name as category_name, sc.name_bn as category_name_bn,
           (SELECT COUNT(*) FROM work_bids WHERE work_request_id = wr.id) as bid_count
    FROM work_requests wr
    JOIN service_categories sc ON wr.category_id = sc.id
    WHERE wr.customer_id = ?
    ORDER BY wr.created_at DESC
", [$user['id']]);

// Get work assignments
$workAssignments = fetchAll("
    SELECT wa.*, sp.name as provider_name, sp.phone as provider_phone,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM work_assignments wa
    JOIN service_providers sp ON wa.provider_id = sp.id
    JOIN work_requests wr ON wa.work_request_id = wr.id
    JOIN service_categories sc ON wr.category_id = sc.id
    WHERE wa.customer_id = ?
    ORDER BY wa.created_at DESC
", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'My Work Requests' : 'আমার কাজের অনুরোধ'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'My Work Requests' : 'আমার কাজের অনুরোধ'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="add_work.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        <i class="fas fa-plus mr-1"></i><?php echo $currentLang === 'en' ? 'Add Work' : 'কাজ যোগ করুন'; ?>
                    </a>
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
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

        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <?php echo $currentLang === 'en' ? 'My Work Requests' : 'আমার কাজের অনুরোধ'; ?>
            </h1>
            <p class="text-gray-600">
                <?php echo $currentLang === 'en' ? 'Manage your posted work requests and track their progress' : 'আপনার পোস্ট করা কাজের অনুরোধ পরিচালনা করুন এবং তাদের অগ্রগতি ট্র্যাক করুন'; ?>
            </p>
        </div>

        <!-- Work Requests -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <?php echo $currentLang === 'en' ? 'Posted Work Requests' : 'পোস্ট করা কাজের অনুরোধ'; ?>
                </h2>
                <a href="add_work.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                    <i class="fas fa-plus mr-1"></i><?php echo $currentLang === 'en' ? 'Add New' : 'নতুন যোগ করুন'; ?>
                </a>
            </div>

            <?php if (empty($workRequests)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 mb-4">
                        <?php echo $currentLang === 'en' ? 'No work requests posted yet' : 'এখনও কোনো কাজের অনুরোধ পোস্ট করা হয়নি'; ?>
                    </p>
                    <a href="add_work.php" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        <?php echo $currentLang === 'en' ? 'Post Your First Work Request' : 'আপনার প্রথম কাজের অনুরোধ পোস্ট করুন'; ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($workRequests as $work): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="font-semibold text-gray-800">
                                            <?php echo $currentLang === 'en' ? $work['category_name'] : $work['category_name_bn']; ?>
                                        </h3>
                                        <span class="px-3 py-1 text-xs rounded-full 
                                            <?php 
                                            switch($work['status']) {
                                                case 'open': echo 'bg-green-100 text-green-600'; break;
                                                case 'in_progress': echo 'bg-blue-100 text-blue-600'; break;
                                                case 'completed': echo 'bg-purple-100 text-purple-600'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-600'; break;
                                            }
                                            ?>">
                                            <?php echo t($work['status']); ?>
                                        </span>
                                        <?php if ($work['bid_count'] > 0): ?>
                                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-600 rounded-full">
                                                <?php echo $work['bid_count']; ?> <?php echo $currentLang === 'en' ? 'bids' : 'বিড'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($work['description']); ?></p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                                        <div>
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            <?php echo htmlspecialchars($work['location']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-tag mr-1"></i>
                                            ৳<?php echo number_format($work['budget_min']); ?> - ৳<?php echo number_format($work['budget_max']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo formatDate($work['preferred_date']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-xs text-gray-500 mt-2">
                                        <?php echo $currentLang === 'en' ? 'Posted on' : 'পোস্ট করা হয়েছে'; ?>: <?php echo formatDateTime($work['created_at']); ?>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <?php if ($work['status'] === 'open'): ?>
                                        <a href="view_bids.php?work_id=<?php echo $work['id']; ?>" 
                                           class="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                            <?php echo $currentLang === 'en' ? 'View Bids' : 'বিড দেখুন'; ?>
                                        </a>
                                        <a href="edit_work.php?id=<?php echo $work['id']; ?>" 
                                           class="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-sm">
                                            <?php echo $currentLang === 'en' ? 'Edit' : 'সম্পাদনা'; ?>
                                        </a>
                                        <a href="?delete=<?php echo $work['id']; ?>" 
                                           class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm"
                                           onclick="return confirm('<?php echo $currentLang === 'en' ? 'Are you sure you want to delete this work request?' : 'আপনি কি নিশ্চিত যে আপনি এই কাজের অনুরোধ মুছে ফেলতে চান?'; ?>')">
                                            <?php echo $currentLang === 'en' ? 'Delete' : 'মুছুন'; ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="work_details.php?id=<?php echo $work['id']; ?>" 
                                           class="bg-purple-600 text-white px-3 py-2 rounded-lg hover:bg-purple-700 text-sm">
                                            <?php echo $currentLang === 'en' ? 'Details' : 'বিস্তারিত'; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Work Assignments -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <?php echo $currentLang === 'en' ? 'Work Assignments' : 'কাজের নিয়োগ'; ?>
            </h2>

            <?php if (empty($workAssignments)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">
                        <?php echo $currentLang === 'en' ? 'No work assignments yet' : 'এখনও কোনো কাজের নিয়োগ নেই'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($workAssignments as $assignment): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($assignment['provider_name']); ?>
                                        </h3>
                                        <span class="px-3 py-1 text-xs rounded-full 
                                            <?php 
                                            switch($assignment['status']) {
                                                case 'assigned': echo 'bg-blue-100 text-blue-600'; break;
                                                case 'in_progress': echo 'bg-yellow-100 text-yellow-600'; break;
                                                case 'completed': echo 'bg-green-100 text-green-600'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-600'; break;
                                            }
                                            ?>">
                                            <?php echo t($assignment['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-gray-600 mb-2">
                                        <?php echo $currentLang === 'en' ? $assignment['category_name'] : $assignment['category_name_bn']; ?>
                                    </p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                                        <div>
                                            <i class="fas fa-tag mr-1"></i>
                                            ৳<?php echo number_format($assignment['final_amount']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo formatDate($assignment['start_date']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-phone mr-1"></i>
                                            <?php echo htmlspecialchars($assignment['provider_phone']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <a href="tel:<?php echo $assignment['provider_phone']; ?>" 
                                       class="bg-green-600 text-white p-2 rounded-lg hover:bg-green-700">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                    <a href="https://wa.me/<?php echo $assignment['provider_phone']; ?>" target="_blank"
                                       class="bg-green-500 text-white p-2 rounded-lg hover:bg-green-600">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <a href="assignment_details.php?id=<?php echo $assignment['id']; ?>" 
                                       class="bg-purple-600 text-white px-3 py-2 rounded-lg hover:bg-purple-700 text-sm">
                                        <?php echo $currentLang === 'en' ? 'Details' : 'বিস্তারিত'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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