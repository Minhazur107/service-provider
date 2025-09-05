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

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $selectionId = (int)$_GET['id'];
    
    switch ($action) {
        case 'contact':
            // Mark as contacted
            executeQuery("UPDATE customer_provider_selections SET status = 'contacted', customer_contacted_at = NOW() WHERE id = ? AND customer_id = ?", [$selectionId, $user['id']]);
            $success = $currentLang === 'en' ? 'Provider marked as contacted' : 'প্রদানকারী যোগাযোগ করা হয়েছে হিসাবে চিহ্নিত';
            break;
            
        case 'cancel':
            // Cancel selection (use 'rejected' to match enum)
            executeQuery("UPDATE customer_provider_selections SET status = 'rejected', updated_at = NOW() WHERE id = ? AND customer_id = ?", [$selectionId, $user['id']]);
            $success = $currentLang === 'en' ? 'Provider selection cancelled' : 'প্রদানকারী নির্বাচন বাতিল করা হয়েছে';
            break;
            
        case 'delete':
            // Delete selection
            executeQuery("DELETE FROM customer_provider_selections WHERE id = ? AND customer_id = ?", [$selectionId, $user['id']]);
            $success = $currentLang === 'en' ? 'Provider removed from selection' : 'প্রদানকারী নির্বাচন থেকে সরানো হয়েছে';
            break;
    }
}

// Get user's selected providers
$selectedProviders = fetchAll("
    SELECT cps.*, sp.name as provider_name, sp.phone as provider_phone, sp.email as provider_email,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM customer_provider_selections cps
    JOIN service_providers sp ON cps.provider_id = sp.id
    JOIN service_categories sc ON cps.category_id = sc.id
    WHERE cps.customer_id = ? AND cps.status IN ('pending', 'contacted', 'accepted')
    ORDER BY cps.created_at DESC
", [$user['id']]);

// Get cancelled selections for history
$cancelledSelections = fetchAll("
    SELECT cps.*, sp.name as provider_name, sp.phone as provider_phone,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM customer_provider_selections cps
    JOIN service_providers sp ON cps.provider_id = sp.id
    JOIN service_categories sc ON cps.category_id = sc.id
    WHERE cps.customer_id = ? AND cps.status IN ('rejected', 'expired')
    ORDER BY cps.updated_at DESC
    LIMIT 10
", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'Selected Providers' : 'নির্বাচিত প্রদানকারী'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Selected Providers' : 'নির্বাচিত প্রদানকারী'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="../search.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                        <i class="fas fa-plus mr-1"></i><?php echo $currentLang === 'en' ? 'Add More' : 'আরও যোগ করুন'; ?>
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
                <?php echo $currentLang === 'en' ? 'My Selected Providers' : 'আমার নির্বাচিত প্রদানকারী'; ?>
            </h1>
            <p class="text-gray-600">
                <?php echo $currentLang === 'en' ? 'Manage your selected service providers and track your interactions' : 'আপনার নির্বাচিত সেবা প্রদানকারী পরিচালনা করুন এবং আপনার মিথস্ক্রিয়া ট্র্যাক করুন'; ?>
            </p>
        </div>

        <!-- Active Selections -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <?php echo $currentLang === 'en' ? 'Active Selections' : 'সক্রিয় নির্বাচন'; ?>
                </h2>
                <a href="../search.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                    <i class="fas fa-plus mr-1"></i><?php echo $currentLang === 'en' ? 'Add Provider' : 'প্রদানকারী যোগ করুন'; ?>
                </a>
            </div>

            <?php if (empty($selectedProviders)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 mb-4">
                        <?php echo $currentLang === 'en' ? 'No providers selected yet' : 'এখনও কোনো প্রদানকারী নির্বাচন করা হয়নি'; ?>
                    </p>
                    <a href="../search.php" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                        <?php echo $currentLang === 'en' ? 'Find Service Providers' : 'সেবা প্রদানকারী খুঁজুন'; ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($selectedProviders as $selection): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="font-semibold text-gray-800 text-lg">
                                            <?php echo htmlspecialchars($selection['provider_name']); ?>
                                        </h3>
                                        <span class="px-3 py-1 text-xs rounded-full 
                                            <?php 
                                            switch($selection['status']) {
                                                case 'pending': echo 'bg-blue-100 text-blue-600'; break;
                                                case 'contacted': echo 'bg-yellow-100 text-yellow-600'; break;
                                                case 'accepted': echo 'bg-green-100 text-green-600'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-600'; break;
                                                case 'expired': echo 'bg-gray-100 text-gray-600'; break;
                                            }
                                            ?>">
                                            <?php echo t($selection['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-tools mr-2"></i>
                                                <?php echo $currentLang === 'en' ? $selection['category_name'] : $selection['category_name_bn']; ?>
                                            </p>
                                            <?php if ($selection['service_type']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    <?php echo htmlspecialchars($selection['service_type']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($selection['preferred_date']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    <?php echo formatDate($selection['preferred_date']); ?>
                                                    <?php if ($selection['preferred_time']): ?>
                                                        at <?php echo date('h:i A', strtotime($selection['preferred_time'])); ?>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-phone mr-2"></i>
                                                <?php echo htmlspecialchars($selection['provider_phone']); ?>
                                            </p>
                                            <?php if ($selection['provider_email']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-envelope mr-2"></i>
                                                    <?php echo htmlspecialchars($selection['provider_email']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="text-sm text-gray-500">
                                                <i class="fas fa-clock mr-2"></i>
                                                <?php echo formatDateTime($selection['created_at']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($selection['customer_notes']): ?>
                                        <div class="bg-gray-50 rounded-lg p-3 mb-4">
                                            <p class="text-sm text-gray-700">
                                                <i class="fas fa-sticky-note mr-2"></i>
                                                <?php echo htmlspecialchars($selection['customer_notes']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex flex-col space-y-2 ml-4">
                                    <a href="tel:<?php echo $selection['provider_phone']; ?>" 
                                       class="bg-green-600 text-white p-2 rounded-lg hover:bg-green-700 text-center">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                    <a href="https://wa.me/<?php echo $selection['provider_phone']; ?>" target="_blank"
                                       class="bg-green-500 text-white p-2 rounded-lg hover:bg-green-600 text-center">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <?php if ($selection['status'] === 'pending'): ?>
                                        <a href="?action=contact&id=<?php echo $selection['id']; ?>" 
                                           class="bg-yellow-600 text-white p-2 rounded-lg hover:bg-yellow-700 text-center">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit_selection.php?id=<?php echo $selection['id']; ?>" 
                                       class="bg-blue-600 text-white p-2 rounded-lg hover:bg-blue-700 text-center">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=cancel&id=<?php echo $selection['id']; ?>" 
                                       class="bg-red-600 text-white p-2 rounded-lg hover:bg-red-700 text-center"
                                       onclick="return confirm('<?php echo $currentLang === 'en' ? 'Are you sure you want to cancel this selection?' : 'আপনি কি নিশ্চিত যে আপনি এই নির্বাচন বাতিল করতে চান?'; ?>')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cancelled Selections History -->
        <?php if (!empty($cancelledSelections)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <?php echo $currentLang === 'en' ? 'Cancelled Selections' : 'বাতিলকৃত নির্বাচন'; ?>
                </h2>
                
                <div class="space-y-4">
                    <?php foreach ($cancelledSelections as $selection): ?>
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <h3 class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($selection['provider_name']); ?>
                                        </h3>
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-600 rounded-full">
                                            <?php echo t('rejected'); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600">
                                        <?php echo $currentLang === 'en' ? $selection['category_name'] : $selection['category_name_bn']; ?>
                                        <?php if ($selection['service_type']): ?>
                                            - <?php echo htmlspecialchars($selection['service_type']); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <p class="text-xs text-gray-500">
                                        <?php echo $currentLang === 'en' ? 'Cancelled on' : 'বাতিল করা হয়েছে'; ?>: <?php echo formatDateTime($selection['updated_at']); ?>
                                    </p>
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <a href="?action=delete&id=<?php echo $selection['id']; ?>" 
                                       class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                                       onclick="return confirm('<?php echo $currentLang === 'en' ? 'Are you sure you want to permanently delete this selection?' : 'আপনি কি নিশ্চিত যে আপনি এই নির্বাচন স্থায়ীভাবে মুছে ফেলতে চান?'; ?>')">
                                        <?php echo $currentLang === 'en' ? 'Delete' : 'মুছুন'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>
</body>
</html> 