<?php
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
	redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

// CSRF
$csrfToken = generateCSRFToken();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$paymentId = (int)($_POST['payment_id'] ?? 0);
	$csrf = $_POST['csrf_token'] ?? '';
	if (!validateCSRFToken($csrf)) {
		setFlashMessage('error', $currentLang === 'en' ? 'Invalid request' : 'অবৈধ অনুরোধ');
		redirect('payments.php');
	}
	
	$payment = fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
	if (!$payment) {
		setFlashMessage('error', $currentLang === 'en' ? 'Payment not found' : 'পেমেন্ট পাওয়া যায়নি');
		redirect('payments.php');
	}
	
	if ($action === 'verify') {
		executeQuery("UPDATE payments SET status = 'verified', updated_at = NOW() WHERE id = ?", [$paymentId]);
		// Notify customer and provider
		createNotification($payment['customer_id'], 'customer', 'Payment Verified', 'Your payment has been verified.', 'general', $payment['booking_id']);
		createNotification($payment['provider_id'], 'provider', 'Payment Verified', 'A customer payment has been verified for your booking.', 'general', $payment['booking_id']);
		setFlashMessage('success', $currentLang === 'en' ? 'Payment verified' : 'পেমেন্ট যাচাইকৃত');
	} elseif ($action === 'reject') {
		executeQuery("UPDATE payments SET status = 'rejected', updated_at = NOW() WHERE id = ?", [$paymentId]);
		createNotification($payment['customer_id'], 'customer', 'Payment Rejected', 'Your payment was rejected. Please contact support.', 'general', $payment['booking_id']);
		setFlashMessage('success', $currentLang === 'en' ? 'Payment rejected' : 'পেমেন্ট প্রত্যাখ্যাত');
	}
	redirect('payments.php');
}

// Filters
$status = $_GET['status'] ?? 'pending';
$allowedStatuses = ['all','pending','verified','rejected'];
if (!in_array($status, $allowedStatuses, true)) { $status = 'pending'; }

$sql = "SELECT p.*, b.booking_date, b.booking_time, u.name as customer_name, sp.name as provider_name
		FROM payments p
		JOIN bookings b ON p.booking_id = b.id
		JOIN users u ON p.customer_id = u.id
		JOIN service_providers sp ON p.provider_id = sp.id";
$params = [];
if ($status !== 'all') {
	$sql .= " WHERE p.status = ?";
	$params[] = $status;
}
$sql .= " ORDER BY p.created_at DESC";
$payments = fetchAll($sql, $params);

$flash = getFlashMessage();

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?> - S24</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		
		body {
			font-family: 'Inter', sans-serif;
		}
		
		.admin-bg {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			position: relative;
			overflow-x: hidden;
		}
		
		.floating-elements {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			pointer-events: none;
			z-index: 1;
		}
		
		.floating-element {
			position: absolute;
			border-radius: 50%;
			background: rgba(255, 255, 255, 0.1);
			animation: float 6s ease-in-out infinite;
		}
		
		.floating-element:nth-child(1) {
			width: 80px;
			height: 80px;
			top: 20%;
			left: 10%;
			animation-delay: 0s;
		}
		
		.floating-element:nth-child(2) {
			width: 120px;
			height: 120px;
			top: 60%;
			right: 15%;
			animation-delay: 2s;
		}
		
		.floating-element:nth-child(3) {
			width: 60px;
			height: 60px;
			bottom: 20%;
			left: 20%;
			animation-delay: 4s;
		}
		
		.floating-element:nth-child(4) {
			width: 100px;
			height: 100px;
			top: 10%;
			right: 30%;
			animation-delay: 1s;
		}
		
		.floating-element:nth-child(5) {
			width: 70px;
			height: 70px;
			bottom: 40%;
			right: 5%;
			animation-delay: 3s;
		}
		
		@keyframes float {
			0%, 100% { transform: translateY(0px) rotate(0deg); }
			50% { transform: translateY(-20px) rotate(180deg); }
		}
		
		.admin-header {
			background: rgba(255, 255, 255, 0.95);
			backdrop-filter: blur(20px);
			-webkit-backdrop-filter: blur(20px);
			border-bottom: 1px solid rgba(255, 255, 255, 0.2);
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
		}
		
		.admin-card {
			background: rgba(255, 255, 255, 0.95);
			backdrop-filter: blur(20px);
			-webkit-backdrop-filter: blur(20px);
			border: 1px solid rgba(255, 255, 255, 0.2);
			border-radius: 20px;
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
			transition: all 0.3s ease;
		}
		
		.admin-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
		}
		
		.nav-link {
			color: #000000;
			transition: all 0.3s ease;
			padding: 8px 16px;
			border-radius: 8px;
			font-weight: 500;
			text-decoration: none;
		}
		
		.nav-link:hover {
			background: rgba(102, 126, 234, 0.1);
			color: #667eea;
			transform: translateY(-2px);
		}
		
		.logout-btn {
			background: linear-gradient(135deg, #ff6b6b, #ee5a52);
			color: white;
			padding: 8px 16px;
			border-radius: 8px;
			text-decoration: none;
			font-weight: 500;
			transition: all 0.3s ease;
		}
		
		.logout-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(238, 90, 82, 0.3);
		}
		
		.btn-primary {
			background: linear-gradient(135deg, #667eea, #764ba2);
			color: white;
			padding: 10px 20px;
			border: none;
			border-radius: 10px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s ease;
		}
		
		.btn-primary:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
		}
		
		.btn-success {
			background: linear-gradient(135deg, #10b981, #059669);
			color: white;
			padding: 6px 12px;
			border: none;
			border-radius: 6px;
			font-size: 12px;
			cursor: pointer;
			transition: all 0.3s ease;
		}
		
		.btn-success:hover {
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
		}
		
		.btn-danger {
			background: linear-gradient(135deg, #ef4444, #dc2626);
			color: white;
			padding: 6px 12px;
			border: none;
			border-radius: 6px;
			font-size: 12px;
			cursor: pointer;
			transition: all 0.3s ease;
		}
		
		.btn-danger:hover {
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
		}
		
		.status-badge {
			padding: 4px 8px;
			border-radius: 12px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		
		.status-pending {
			background: linear-gradient(135deg, #fbbf24, #f59e0b);
			color: white;
		}
		
		.status-verified {
			background: linear-gradient(135deg, #10b981, #059669);
			color: white;
		}
		
		.status-rejected {
			background: linear-gradient(135deg, #ef4444, #dc2626);
			color: white;
		}
		
		.filter-btn {
			padding: 8px 16px;
			border-radius: 8px;
			text-decoration: none;
			font-weight: 500;
			transition: all 0.3s ease;
		}
		
		.filter-btn.active {
			background: linear-gradient(135deg, #667eea, #764ba2);
			color: white;
		}
		
		.filter-btn:not(.active) {
			background: rgba(255, 255, 255, 0.8);
			color: #374151;
		}
		
		.filter-btn:not(.active):hover {
			background: rgba(255, 255, 255, 0.9);
			transform: translateY(-1px);
		}
		
		.table-header {
			background: linear-gradient(135deg, #f8fafc, #e2e8f0);
			border-radius: 12px 12px 0 0;
		}
		
		.table-row {
			transition: all 0.3s ease;
		}
		
		.table-row:hover {
			background: rgba(102, 126, 234, 0.05);
			transform: scale(1.01);
		}
		
		.empty-state {
			text-align: center;
			padding: 60px 20px;
			color: #6b7280;
		}
		
		.empty-icon {
			font-size: 48px;
			margin-bottom: 16px;
			color: #d1d5db;
		}
	</style>
</head>
<body class="admin-bg min-h-screen">
	<div class="floating-elements">
		<div class="floating-element"></div>
		<div class="floating-element"></div>
		<div class="floating-element"></div>
		<div class="floating-element"></div>
		<div class="floating-element"></div>
	</div>
	
	<header class="admin-header sticky top-0 z-50">
		<nav class="container mx-auto px-4 py-4">
			<div class="flex justify-between items-center">
				<div class="flex items-center space-x-6">
					<a href="dashboard.php" class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
						<i class="fas fa-cogs mr-2"></i>S24
					</a>
					<div class="flex items-center space-x-1">
						<i class="fas fa-credit-card text-purple-600"></i>
						<span class="text-xl font-semibold text-gray-800"><?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?></span>
					</div>
				</div>
				<div class="flex items-center space-x-6">
					<a href="dashboard.php" class="nav-link">
						<i class="fas fa-tachometer-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Dashboard' : 'ড্যাশবোর্ড'; ?>
					</a>
					<a href="users.php" class="nav-link">
						<i class="fas fa-users mr-2"></i><?php echo $currentLang === 'en' ? 'Users' : 'ব্যবহারকারী'; ?>
					</a>
					<a href="providers.php" class="nav-link">
						<i class="fas fa-user-tie mr-2"></i><?php echo $currentLang === 'en' ? 'Providers' : 'প্রদানকারী'; ?>
					</a>
					<a href="bookings.php" class="nav-link">
						<i class="fas fa-calendar-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Bookings' : 'বুকিং'; ?>
					</a>
					<a href="reviews.php" class="nav-link">
						<i class="fas fa-star mr-2"></i><?php echo $currentLang === 'en' ? 'Reviews' : 'পর্যালোচনা'; ?>
					</a>
					<a href="payments.php" class="nav-link">
						<i class="fas fa-credit-card mr-2"></i><?php echo $currentLang === 'en' ? 'Payments' : 'পেমেন্ট'; ?>
					</a>
					<div class="flex items-center space-x-3">
						<div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
							<?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
						</div>
						<span class="text-gray-700 font-medium"><?php echo htmlspecialchars($admin['username']); ?></span>
					</div>
					<a href="?logout=1" class="logout-btn">
						<i class="fas fa-sign-out-alt mr-2"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
					</a>
				</div>
			</div>
		</nav>
	</header>
	
	<div class="container mx-auto px-4 py-8 relative z-10">
		<?php if ($flash): ?>
			<div class="admin-card p-4 mb-6">
				<div class="flex items-center space-x-3">
					<i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600'; ?> text-xl"></i>
					<span class="<?php echo $flash['type'] === 'success' ? 'text-green-700' : 'text-red-700'; ?> font-medium">
						<?php echo htmlspecialchars($flash['message']); ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
		
		<div class="admin-card p-8">
			<div class="flex justify-between items-center mb-8">
				<div class="flex items-center space-x-4">
					<div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-xl flex items-center justify-center">
						<i class="fas fa-credit-card text-white text-xl"></i>
					</div>
					<div>
						<h2 class="text-2xl font-bold text-gray-800"><?php echo $currentLang === 'en' ? 'Payment Management' : 'পেমেন্ট ব্যবস্থাপনা'; ?></h2>
						<p class="text-gray-600"><?php echo $currentLang === 'en' ? 'Manage and verify payment transactions' : 'পেমেন্ট লেনদেন পরিচালনা এবং যাচাই করুন'; ?></p>
					</div>
				</div>
				<div class="flex items-center space-x-2">
					<a href="?status=all" class="filter-btn <?php echo $status==='all'?'active':''; ?>">
						<i class="fas fa-list mr-1"></i><?php echo $currentLang === 'en' ? 'All' : 'সব'; ?>
					</a>
					<a href="?status=pending" class="filter-btn <?php echo $status==='pending'?'active':''; ?>">
						<i class="fas fa-clock mr-1"></i><?php echo t('pending'); ?>
					</a>
					<a href="?status=verified" class="filter-btn <?php echo $status==='verified'?'active':''; ?>">
						<i class="fas fa-check mr-1"></i><?php echo t('verified'); ?>
					</a>
					<a href="?status=rejected" class="filter-btn <?php echo $status==='rejected'?'active':''; ?>">
						<i class="fas fa-times mr-1"></i><?php echo t('rejected'); ?>
					</a>
				</div>
			</div>
			
			<div class="overflow-x-auto">
				<?php if (empty($payments)): ?>
					<div class="empty-state">
						<div class="empty-icon">
							<i class="fas fa-credit-card"></i>
						</div>
						<h3 class="text-xl font-semibold mb-2"><?php echo $currentLang === 'en' ? 'No Payments Found' : 'কোন পেমেন্ট পাওয়া যায়নি'; ?></h3>
						<p class="text-gray-500"><?php echo $currentLang === 'en' ? 'There are no payments matching your current filter.' : 'আপনার বর্তমান ফিল্টারের সাথে মিলে এমন কোন পেমেন্ট নেই।'; ?></p>
					</div>
				<?php else: ?>
					<table class="min-w-full">
						<thead class="table-header">
							<tr>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">#</th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Booking' : 'বুকিং'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Provider' : 'প্রদানকারী'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Method' : 'পদ্ধতি'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Amount' : 'পরিমাণ'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">TX ID</th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Proof' : 'প্রমাণ'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Status' : 'অবস্থা'; ?></th>
								<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"><?php echo $currentLang === 'en' ? 'Actions' : 'কর্ম'; ?></th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-200">
							<?php foreach ($payments as $p): ?>
							<tr class="table-row">
								<td class="px-6 py-4 text-sm text-gray-700 font-medium">#<?php echo $p['id']; ?></td>
								<td class="px-6 py-4 text-sm text-gray-700">
									<a href="bookings.php?view=<?php echo $p['booking_id']; ?>" class="text-purple-600 hover:text-purple-700 font-medium">#<?php echo $p['booking_id']; ?></a>
									<div class="text-xs text-gray-500 mt-1">
										<i class="fas fa-calendar mr-1"></i><?php echo formatDate($p['booking_date']); ?>
										<i class="fas fa-clock ml-2 mr-1"></i><?php echo $p['booking_time']; ?>
									</div>
								</td>
								<td class="px-6 py-4 text-sm text-gray-700">
									<div class="flex items-center space-x-2">
										<div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white text-xs font-semibold">
											<?php echo strtoupper(substr($p['customer_name'], 0, 1)); ?>
										</div>
										<span><?php echo htmlspecialchars($p['customer_name']); ?></span>
									</div>
								</td>
								<td class="px-6 py-4 text-sm text-gray-700">
									<div class="flex items-center space-x-2">
										<div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full flex items-center justify-center text-white text-xs font-semibold">
											<?php echo strtoupper(substr($p['provider_name'], 0, 1)); ?>
										</div>
										<span><?php echo htmlspecialchars($p['provider_name']); ?></span>
									</div>
								</td>
								<td class="px-6 py-4 text-sm text-gray-700 capitalize">
									<span class="px-3 py-1 bg-gray-100 rounded-full text-xs font-medium">
										<i class="fas fa-credit-card mr-1"></i><?php echo htmlspecialchars($p['method']); ?>
									</span>
								</td>
								<td class="px-6 py-4 text-sm text-gray-700 font-semibold">
									<span class="text-green-600"><?php echo formatPrice($p['amount']); ?></span>
								</td>
								<td class="px-6 py-4 text-sm text-gray-700">
									<?php if ($p['transaction_id']): ?>
										<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-mono"><?php echo htmlspecialchars($p['transaction_id']); ?></span>
									<?php else: ?>
										<span class="text-gray-400">-</span>
									<?php endif; ?>
								</td>
								<td class="px-6 py-4 text-sm text-gray-700">
									<?php if ($p['proof_file']): ?>
										<a href="../uploads/payments/<?php echo htmlspecialchars($p['proof_file']); ?>" target="_blank" class="btn-primary text-xs">
											<i class="fas fa-eye mr-1"></i><?php echo $currentLang === 'en' ? 'View' : 'দেখুন'; ?>
										</a>
									<?php else: ?>
										<span class="text-gray-400">-</span>
									<?php endif; ?>
								</td>
								<td class="px-6 py-4 text-sm">
									<span class="status-badge <?php echo $p['status'] === 'verified' ? 'status-verified' : ($p['status'] === 'pending' ? 'status-pending' : 'status-rejected'); ?>">
										<?php echo t($p['status']); ?>
									</span>
								</td>
								<td class="px-6 py-4 text-sm">
									<?php if ($p['status'] === 'pending'): ?>
										<div class="flex space-x-2">
											<form method="post" class="inline">
												<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
												<input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
												<button name="action" value="verify" class="btn-success" title="<?php echo $currentLang === 'en' ? 'Verify Payment' : 'পেমেন্ট যাচাই করুন'; ?>">
													<i class="fas fa-check"></i>
												</button>
											</form>
											<form method="post" class="inline">
												<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
												<input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
												<button name="action" value="reject" class="btn-danger" title="<?php echo $currentLang === 'en' ? 'Reject Payment' : 'পেমেন্ট প্রত্যাখ্যান করুন'; ?>">
													<i class="fas fa-times"></i>
												</button>
											</form>
										</div>
									<?php else: ?>
										<span class="text-gray-400">-</span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<footer class="bg-white bg-opacity-95 backdrop-blur-sm border-t border-gray-200 py-8 mt-12 relative z-10">
		<div class="container mx-auto px-4 text-center">
			<p class="text-gray-600">&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
		</div>
	</footer>
</body>
</html> 