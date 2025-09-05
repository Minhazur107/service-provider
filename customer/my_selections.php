<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentLang = getLanguage();
$user = getCurrentUser();

// Get user's active selections
$selections = fetchAll("
    SELECT cps.*, sp.name as provider_name, sp.phone as provider_phone, sp.email as provider_email,
           sc.name as category_name, sc.name_bn as category_name_bn
    FROM customer_provider_selections cps
    JOIN service_providers sp ON cps.provider_id = sp.id
    JOIN service_categories sc ON cps.category_id = sc.id
    WHERE cps.customer_id = ? AND cps.status IN ('pending', 'contacted')
    ORDER BY cps.created_at DESC
", [$user['id']]);

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLang === 'en' ? 'My Selections' : 'আমার নির্বাচন'; ?> - S24</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .customer-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .customer-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .floating-elements {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 150px;
            height: 150px;
            top: 5%;
            left: 5%;
            animation-delay: 0s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        .floating-element:nth-child(2) {
            width: 100px;
            height: 100px;
            top: 15%;
            right: 10%;
            animation-delay: 2s;
            background: linear-gradient(45deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
        }
        
        .floating-element:nth-child(3) {
            width: 120px;
            height: 120px;
            bottom: 15%;
            left: 10%;
            animation-delay: 4s;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.2));
        }
        
        .floating-element:nth-child(4) {
            width: 80px;
            height: 80px;
            bottom: 5%;
            right: 15%;
            animation-delay: 1s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            50% { transform: translateY(-30px) rotate(180deg) scale(1.1); }
        }
        
        .customer-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .customer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .customer-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1.5rem;
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .customer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 200% 100%;
            animation: shimmer 4s ease-in-out infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .customer-card:hover::before {
            opacity: 1;
        }
        
        .customer-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .nav-link {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .customer-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }
        
        .status-contacted {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }
        
        .action-btn.whatsapp {
            background: linear-gradient(135deg, #25d366, #128c7e);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3);
        }
        
        .action-btn.whatsapp:hover {
            background: linear-gradient(135deg, #128c7e, #075e54);
            box-shadow: 0 12px 35px rgba(37, 211, 102, 0.4);
        }
    </style>
</head>
<body class="customer-bg min-h-screen">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>
    <!-- Header -->
    <header class="customer-header sticky top-0 z-50">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        S24
                    </a>
                    <span class="text-gray-700 font-semibold text-lg">
                        <i class="fas fa-heart text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'My Selections' : 'আমার নির্বাচন'; ?>
                    </span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-arrow-left"></i>
                        <?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        <?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
                    </a>
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <?php echo $currentLang === 'en' ? 'Notifications' : 'বিজ্ঞপ্তি'; ?>
                    </a>
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <?php echo $currentLang === 'en' ? 'Profile' : 'প্রোফাইল'; ?>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="customer-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?></div>
                        </div>
                    </div>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mx-auto px-4 py-8 relative z-10">
        <!-- Page Header -->
        <div class="customer-card p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-heart text-purple-600 mr-3"></i>
                        <?php echo $currentLang === 'en' ? 'My Provider Selections' : 'আমার প্রদানকারী নির্বাচন'; ?>
                    </h1>
                    <p class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Manage your favorite service providers' : 'আপনার প্রিয় পরিষেবা প্রদানকারীদের পরিচালনা করুন'; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-purple-600"><?php echo count($selections); ?></div>
                    <div class="text-gray-600">
                        <?php echo $currentLang === 'en' ? 'Active Selections' : 'সক্রিয় নির্বাচন'; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($selections)): ?>
            <div class="customer-card p-12 text-center">
                <div class="w-24 h-24 bg-gradient-to-br from-pink-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-heart text-4xl text-pink-500"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">
                    <?php echo $currentLang === 'en' ? 'No Selections Yet' : 'এখনও কোনো নির্বাচন নেই'; ?>
                </h3>
                <p class="text-gray-500 max-w-md mx-auto mb-6">
                    <?php echo $currentLang === 'en' ? 'You haven\'t selected any providers yet. Start by searching for services.' : 'আপনি এখনও কোনো প্রদানকারী নির্বাচন করেননি। পরিষেবা অনুসন্ধান করে শুরু করুন।'; ?>
                </p>
                <a href="../search.php" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-300 font-semibold inline-flex items-center">
                    <i class="fas fa-search mr-2"></i>
                    <?php echo $currentLang === 'en' ? 'Find Providers' : 'প্রদানকারী খুঁজুন'; ?>
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($selections as $selection): ?>
                    <div class="customer-card p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center space-x-3">
                                <span class="status-badge <?php echo $selection['status'] === 'pending' ? 'status-pending' : 'status-contacted'; ?>">
                                    <?php if ($selection['status'] === 'pending'): ?>
                                        <i class="fas fa-clock mr-1"></i><?php echo $currentLang === 'en' ? 'Pending' : 'অপেক্ষমান'; ?>
                                    <?php elseif ($selection['status'] === 'contacted'): ?>
                                        <i class="fas fa-phone mr-1"></i><?php echo $currentLang === 'en' ? 'Contacted' : 'যোগাযোগ করা হয়েছে'; ?>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm text-gray-500">
                                    <?php echo formatDateTime($selection['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Provider Information -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3">
                                    <?php echo $currentLang === 'en' ? 'Provider Details' : 'প্রদানকারীর বিবরণ'; ?>
                                </h3>
                                <div class="space-y-2">
                                    <p class="text-lg font-medium text-gray-800">
                                        <?php echo htmlspecialchars($selection['provider_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($selection['provider_phone']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($selection['provider_email']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-tag mr-2"></i><?php echo $currentLang === 'en' ? $selection['category_name'] : $selection['category_name_bn']; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Service Details -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3">
                                    <?php echo $currentLang === 'en' ? 'Service Details' : 'সেবার বিবরণ'; ?>
                                </h3>
                                <div class="space-y-2">
                                    <?php if ($selection['service_type']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-tools mr-2"></i><?php echo htmlspecialchars($selection['service_type']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-calendar mr-2"></i><?php echo formatDate($selection['preferred_date']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-clock mr-2"></i><?php echo $selection['preferred_time']; ?>
                                    </p>
                                    <?php if ($selection['budget_min'] || $selection['budget_max']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-money-bill mr-2"></i>
                                            <?php 
                                            if ($selection['budget_min'] && $selection['budget_max']) {
                                                echo formatPrice($selection['budget_min']) . ' - ' . formatPrice($selection['budget_max']);
                                            } elseif ($selection['budget_min']) {
                                                echo 'Min: ' . formatPrice($selection['budget_min']);
                                            } elseif ($selection['budget_max']) {
                                                echo 'Max: ' . formatPrice($selection['budget_max']);
                                            }
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Service Address & Notes -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3">
                                    <?php echo $currentLang === 'en' ? 'Service Location' : 'সেবার অবস্থান'; ?>
                                </h3>
                                <div class="space-y-2">
                                    <?php if ($selection['customer_address']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-2"></i>
                                            <?php echo htmlspecialchars($selection['customer_address']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($selection['customer_notes']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-comment mr-2"></i>
                                            <?php echo htmlspecialchars($selection['customer_notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-6 pt-4 border-t border-gray-200 flex flex-wrap gap-3">
                            <a href="tel:<?php echo $selection['provider_phone']; ?>" 
                               class="action-btn">
                                <i class="fas fa-phone"></i><?php echo $currentLang === 'en' ? 'Call Provider' : 'প্রদানকারীকে কল করুন'; ?>
                            </a>
                            
                            <a href="https://wa.me/<?php echo $selection['provider_phone']; ?>?text=<?php echo urlencode($currentLang === 'en' ? 'Hi, I selected you for my service request. Can you provide more details?' : 'হাই, আমি আপনাকে আমার সেবা অনুরোধের জন্য নির্বাচন করেছি। আপনি কি আরও বিস্তারিত দিতে পারেন?'); ?>" 
                               target="_blank"
                               class="action-btn whatsapp">
                                <i class="fab fa-whatsapp"></i><?php echo $currentLang === 'en' ? 'WhatsApp' : 'হোয়াটসঅ্যাপ'; ?>
                            </a>
                            
                            <?php if ($selection['status'] === 'pending'): ?>
                                <button onclick="markAsContacted(<?php echo $selection['id']; ?>)" 
                                        class="action-btn">
                                    <i class="fas fa-phone"></i><?php echo $currentLang === 'en' ? 'Mark as Contacted' : 'যোগাযোগ করা হয়েছে হিসেবে চিহ্নিত করুন'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-white bg-opacity-95 backdrop-blur-sm border-t border-gray-200 py-8 mt-12 relative z-10">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-600">&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
        </div>
    </footer>

    <script>
        function markAsContacted(selectionId) {
            if (confirm('<?php echo $currentLang === 'en' ? 'Mark this selection as contacted?' : 'এই নির্বাচন যোগাযোগ করা হয়েছে হিসেবে চিহ্নিত করবেন?'; ?>')) {
                // You can implement AJAX call here to update status
                // For now, redirect to a status update page
                window.location.href = `update_selection_status.php?id=${selectionId}&status=contacted`;
            }
        }
    </script>
</body>
</html> 