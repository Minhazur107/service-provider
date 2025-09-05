<?php
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
	redirect('login.php');
}

$currentLang = getLanguage();
$admin = getCurrentAdmin();

$selectionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$selection = fetchOne("\n\tSELECT cps.*, u.name as customer_name, sp.name as provider_name, sc.name as category_name, sc.name_bn as category_name_bn\n\tFROM customer_provider_selections cps\n\tJOIN users u ON cps.customer_id = u.id\n\tJOIN service_providers sp ON cps.provider_id = sp.id\n\tJOIN service_categories sc ON cps.category_id = sc.id\n\tWHERE cps.id = ?\n", [$selectionId]);

if (!$selection) {
	setFlashMessage('error', 'Selection not found');
	redirect('bookings.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$serviceType = sanitizeInput($_POST['service_type']);
	$preferredDate = $_POST['preferred_date'];
	$preferredTime = $_POST['preferred_time'];
	$customerAddress = sanitizeInput($_POST['customer_address']);
	$customerNotes = sanitizeInput($_POST['customer_notes']);
	$status = $_POST['status'];
	
	if (empty($preferredDate)) {
		$error = $currentLang === 'en' ? 'Preferred date is required' : 'পছন্দের তারিখ প্রয়োজন';
	} elseif (strtotime($preferredDate) < strtotime('today')) {
		$error = $currentLang === 'en' ? 'Preferred date cannot be in the past' : 'পছন্দের তারিখ অতীত হতে পারে না';
	} elseif (empty($preferredTime)) {
		$error = $currentLang === 'en' ? 'Preferred time is required' : 'পছন্দের সময় প্রয়োজন';
	} elseif (empty($customerAddress)) {
		$error = $currentLang === 'en' ? 'Service address is required' : 'সেবার ঠিকানা প্রয়োজন';
	} elseif (!in_array($status, ['pending', 'contacted', 'accepted', 'rejected', 'expired'])) {
		$error = 'Invalid status';
	} else {
		try {
			executeQuery("\n\t\t\tUPDATE customer_provider_selections\n\t\t\tSET service_type = ?, preferred_date = ?, preferred_time = ?, customer_address = ?, customer_notes = ?, status = ?, updated_at = NOW()\n\t\t\tWHERE id = ?\n\t\t", [
				$serviceType ?: null,
				$preferredDate,
				$preferredTime,
				$customerAddress,
				$customerNotes ?: null,
				$status,
				$selection['id']
			]);
			setFlashMessage('success', 'Selection updated successfully');
			redirect('bookings.php');
		} catch (Exception $e) {
			$error = $currentLang === 'en' ? 'Failed to update selection' : 'নির্বাচন হালনাগাদ ব্যর্থ হয়েছে';
		}
	}
}

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
	<title><?php echo $currentLang === 'en' ? 'Edit Selection' : 'নির্বাচন সম্পাদনা'; ?> - S24 Admin</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
	<header class="bg-white shadow-sm">
		<nav class="container mx-auto px-4 py-4">
			<div class="flex justify-between items-center">
				<div class="flex items-center space-x-4">
					<a href="../index.php" class="text-2xl font-bold text-purple-600">S24</a>
					<span class="text-gray-600"><?php echo $currentLang === 'en' ? 'Edit Selection' : 'নির্বাচন সম্পাদনা'; ?></span>
				</div>
				<div class="flex items-center space-x-4">
					<a href="bookings.php" class="text-purple-600 hover:text-purple-700">
						<i class="fas fa-arrow-left mr-1"></i><?php echo $currentLang === 'en' ? 'Back' : 'ফিরে যান'; ?>
					</a>
					<a href="?logout=1" class="text-red-600 hover:text-red-700">
						<i class="fas fa-sign-out-alt mr-1"></i><?php echo $currentLang === 'en' ? 'Logout' : 'লগআউট'; ?>
					</a>
				</div>
			</div>
		</nav>
	</header>

	<div class="container mx-auto px-4 py-8">
		<div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg p-6">
			<h1 class="text-2xl font-bold text-gray-800 mb-6"><?php echo $currentLang === 'en' ? 'Edit Selection' : 'নির্বাচন সম্পাদনা'; ?></h1>

			<?php if ($error): ?>
				<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
					<?php echo $error; ?>
				</div>
			<?php endif; ?>

			<form method="POST" class="space-y-6">
				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Service Type' : 'সেবার ধরন'; ?></label>
						<input type="text" name="service_type" value="<?php echo htmlspecialchars($_POST['service_type'] ?? ($selection['service_type'] ?? '')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Preferred Date' : 'পছন্দের তারিখ'; ?> *</label>
						<input type="date" name="preferred_date" value="<?php echo htmlspecialchars($_POST['preferred_date'] ?? $selection['preferred_date']); ?>" min="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Preferred Time' : 'পছন্দের সময়'; ?> *</label>
						<input type="time" name="preferred_time" value="<?php echo htmlspecialchars($_POST['preferred_time'] ?? $selection['preferred_time']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Service Address' : 'সেবার ঠিকানা'; ?> *</label>
						<textarea name="customer_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required><?php echo htmlspecialchars($_POST['customer_address'] ?? ($selection['customer_address'] ?? '')); ?></textarea>
					</div>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Customer Notes' : 'গ্রাহকের নোট'; ?></label>
					<textarea name="customer_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['customer_notes'] ?? ($selection['customer_notes'] ?? '')); ?></textarea>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $currentLang === 'en' ? 'Status' : 'অবস্থা'; ?> *</label>
					<select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
						<?php foreach (['pending','contacted','accepted','rejected','expired'] as $st): ?>
							<option value="<?php echo $st; ?>" <?php echo ($selection['status'] === $st ? 'selected' : ''); ?>><?php echo t($st); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="flex justify-end space-x-4">
					<a href="bookings.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><?php echo $currentLang === 'en' ? 'Cancel' : 'বাতিল'; ?></a>
					<button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
						<i class="fas fa-save mr-2"></i><?php echo $currentLang === 'en' ? 'Save Changes' : 'পরিবর্তন সংরক্ষণ করুন'; ?>
					</button>
				</div>
			</form>
		</div>
	</div>

	<footer class="bg-gray-800 text-white py-8 mt-12">
		<div class="container mx-auto px-4 text-center">
			<p>&copy; 2024 S24. <?php echo $currentLang === 'en' ? 'All rights reserved.' : 'সর্বস্বত্ব সংরক্ষিত।'; ?></p>
		</div>
	</footer>
</body>
</html> 