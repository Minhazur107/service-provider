<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
	redirect('../auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect('dashboard.php');
}

$customer = getCurrentUser();
$currentLang = getLanguage();

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf)) {
	setFlashMessage('error', $currentLang === 'en' ? 'Invalid request' : 'অবৈধ অনুরোধ');
	redirect('dashboard.php');
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$method = $_POST['method'] ?? '';
$amount = (float)($_POST['amount'] ?? 0);
$transactionId = trim($_POST['transaction_id'] ?? '');

$allowedMethods = ['bkash','nagad','bank'];
if (!$bookingId || !in_array($method, $allowedMethods, true) || $amount <= 0) {
	setFlashMessage('error', $currentLang === 'en' ? 'Please fill all required fields' : 'অনুগ্রহ করে সব প্রয়োজনীয় তথ্য দিন');
	redirect("booking_details.php?id={$bookingId}");
}

$booking = fetchOne("SELECT * FROM bookings WHERE id = ? AND customer_id = ? AND status = 'completed'", [$bookingId, $customer['id']]);
if (!$booking) {
	setFlashMessage('error', $currentLang === 'en' ? 'Booking not eligible for payment' : 'এই বুকিং পেমেন্টের জন্য উপযুক্ত নয়');
	redirect('dashboard.php');
}

$proofFileName = null;
if (!empty($_FILES['payment_proof']['name'])) {
	$proofFileName = uploadFile($_FILES['payment_proof'], '../uploads/payments/');
}

try {
	executeQuery("INSERT INTO payments (booking_id, customer_id, provider_id, amount, method, transaction_id, proof_file, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')", [
		$booking['id'], $customer['id'], $booking['provider_id'], $amount, $method, $transactionId ?: null, $proofFileName
	]);
	setFlashMessage('success', $currentLang === 'en' ? 'Payment submitted for verification' : 'পেমেন্ট যাচাইয়ের জন্য জমা হয়েছে');
} catch (Exception $e) {
	setFlashMessage('error', $currentLang === 'en' ? 'Failed to submit payment' : 'পেমেন্ট জমা ব্যর্থ হয়েছে');
}

redirect("booking_details.php?id={$booking['id']}"); 