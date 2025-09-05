<?php
require_once '../includes/functions.php';

// Check if provider is logged in
if (!isProviderLoggedIn()) {
    redirect('../auth/login.php');
}

$currentLang = getLanguage();
$provider = getCurrentProvider();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = ["b.provider_id = ?"];
$params = [$provider['id']];

if ($status) {
    $whereConditions[] = "b.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countSql = "SELECT COUNT(*) as count FROM bookings b WHERE $whereClause";
$totalCount = fetchOne($countSql, $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get bookings
$sql = "SELECT b.*, u.name as customer_name, u.phone as customer_phone, 
        sc.name as category_name, sc.name_bn as category_name_bn
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        JOIN service_categories sc ON b.category_id = sc.id
        WHERE $whereClause
        ORDER BY b.created_at DESC
        LIMIT $perPage OFFSET $offset";

$bookings = fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'My Bookings' : 'আমার বুকিং'; ?> - S24</title>
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
                    <span class="text-gray-600"><?php echo $currentLang === 'en' ? 'My Bookings' : 'আমার বুকিং'; ?></span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="?logout=1" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt mr-1"></i><?php echo t('logout'); ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    <?php echo $currentLang === 'en' ? 'My Bookings' : 'আমার বুকিং'; ?>
                </h1>
                <div class="text-sm text-gray-600">
                    <?php echo $currentLang === 'en' ? 'Total' : 'মোট'; ?>: <?php echo $totalCount; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="flex space-x-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $currentLang === 'en' ? 'Filter by Status' : 'স্ট্যাটাস অনুযায়ী ফিল্টার'; ?>
                        </label>
                        <select name="status" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value=""><?php echo $currentLang === 'en' ? 'All Status' : 'সব স্ট্যাটাস'; ?></option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>><?php echo t('pending'); ?></option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>><?php echo t('confirmed'); ?></option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>><?php echo t('completed'); ?></option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>><?php echo t('cancelled'); ?></option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-300">
                            <?php echo $currentLang === 'en' ? 'Filter' : 'ফিল্টার'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bookings List -->
            <?php if (empty($bookings)): ?>
                <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                    <i class="fas fa-calendar text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">
                        <?php echo $currentLang === 'en' ? 'No bookings found' : 'কোনো বুকিং পাওয়া যায়নি'; ?>
                    </h3>
                    <p class="text-gray-500">
                        <?php echo $currentLang === 'en' ? 'You don\'t have any bookings yet.' : 'আপনার এখনও কোনো বুকিং নেই।'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <h3 class="font-semibold text-gray-800 text-lg">
                                            <?php echo htmlspecialchars($booking['customer_name']); ?>
                                        </h3>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            <?php echo $booking['status'] === 'completed' ? 'bg-green-100 text-green-600' : 
                                                ($booking['status'] === 'confirmed' ? 'bg-blue-100 text-blue-600' : 
                                                ($booking['status'] === 'pending' ? 'bg-yellow-100 text-yellow-600' : 'bg-red-100 text-red-600')); ?>">
                                            <?php echo t($booking['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-tools mr-2"></i>
                                                <?php echo $currentLang === 'en' ? $booking['category_name'] : $booking['category_name_bn']; ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-calendar mr-2"></i>
                                                <?php echo formatDate($booking['booking_date']); ?> at <?php echo date('h:i A', strtotime($booking['booking_time'])); ?>
                                            </p>
                                            <?php if ($booking['service_type']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    <?php echo htmlspecialchars($booking['service_type']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <?php if ($booking['final_price']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-tag mr-2"></i>
                                                    <?php echo $currentLang === 'en' ? 'Final Price' : 'চূড়ান্ত মূল্য'; ?>: <?php echo formatPrice($booking['final_price']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-clock mr-2"></i>
                                                <?php echo formatDateTime($booking['created_at']); ?>
                                            </p>
                                            <?php if ($booking['cancellation_reason']): ?>
                                                <p class="text-sm text-red-600">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <?php echo htmlspecialchars($booking['cancellation_reason']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($booking['notes']): ?>
                                        <div class="bg-gray-50 rounded-lg p-3 mb-4">
                                            <p class="text-sm text-gray-700">
                                                <i class="fas fa-sticky-note mr-2"></i>
                                                <?php echo htmlspecialchars($booking['notes']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex flex-col space-y-2 ml-4">
                                    <a href="tel:<?php echo $booking['customer_phone']; ?>" 
                                       class="bg-green-600 text-white p-2 rounded-lg hover:bg-green-700 text-center">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                    <a href="https://wa.me/<?php echo $booking['customer_phone']; ?>" target="_blank"
                                       class="bg-green-500 text-white p-2 rounded-lg hover:bg-green-600 text-center">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <a href="accept_booking.php?id=<?php echo $booking['id']; ?>" 
                                           class="bg-blue-600 text-white p-2 rounded-lg hover:bg-blue-700 text-center">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="reject_booking.php?id=<?php echo $booking['id']; ?>" 
                                           class="bg-red-600 text-white p-2 rounded-lg hover:bg-red-700 text-center">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <a href="complete_booking.php?id=<?php echo $booking['id']; ?>" 
                                           class="bg-green-600 text-white p-2 rounded-lg hover:bg-green-700 text-center">
                                            <i class="fas fa-check-double"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="booking_details.php?id=<?php echo $booking['id']; ?>" 
                                       class="bg-purple-600 text-white p-2 rounded-lg hover:bg-purple-700 text-center">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-8">
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <?php echo $currentLang === 'en' ? 'Previous' : 'পূর্ববর্তী'; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg <?php echo $i === $page ? 'bg-purple-600 text-white' : 'hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <?php echo $currentLang === 'en' ? 'Next' : 'পরবর্তী'; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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