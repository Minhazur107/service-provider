<?php
require_once '../includes/functions.php';

$currentLang = getLanguage();
$error = '';
$success = '';

// Get service categories
$categories = fetchAll("SELECT * FROM service_categories WHERE is_active = 1");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $categoryId = $_POST['category_id'];
    $description = sanitizeInput($_POST['description']);
    $serviceAreas = sanitizeInput($_POST['service_areas']);
    $priceMin = $_POST['price_min'];
    $priceMax = $_POST['price_max'];
    $hourlyRate = $_POST['hourly_rate'];
    $availabilityHours = sanitizeInput($_POST['availability_hours']);
    
    // Validation
    if (empty($name) || empty($phone) || empty($password) || empty($confirmPassword) || empty($categoryId)) {
        $error = $currentLang === 'en' ? 'Please fill in all required fields' : 'সব প্রয়োজনীয় ক্ষেত্র পূরণ করুন';
    } elseif (!validatePhone($phone)) {
        $error = $currentLang === 'en' ? 'Please enter a valid phone number' : 'সঠিক ফোন নম্বর লিখুন';
    } elseif ($email && !validateEmail($email)) {
        $error = $currentLang === 'en' ? 'Please enter a valid email address' : 'সঠিক ইমেইল ঠিকানা লিখুন';
    } elseif (strlen($password) < 6) {
        $error = $currentLang === 'en' ? 'Password must be at least 6 characters long' : 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
    } elseif ($password !== $confirmPassword) {
        $error = $currentLang === 'en' ? 'Passwords do not match' : 'পাসওয়ার্ড মিলছে না';
    } elseif ($priceMin && $priceMax && $priceMin > $priceMax) {
        $error = $currentLang === 'en' ? 'Minimum price cannot be greater than maximum price' : 'ন্যূনতম মূল্য সর্বোচ্চ মূল্যের চেয়ে বেশি হতে পারে না';
    } else {
        // Check if phone already exists
        $existingProvider = fetchOne("SELECT id FROM service_providers WHERE phone = ?", [$phone]);
        if ($existingProvider) {
            $error = $currentLang === 'en' ? 'Phone number already registered' : 'ফোন নম্বর ইতিমধ্যে নিবন্ধিত';
        } else {
            // Check if email already exists (if provided)
            if ($email) {
                $existingEmail = fetchOne("SELECT id FROM service_providers WHERE email = ?", [$email]);
                if ($existingEmail) {
                    $error = $currentLang === 'en' ? 'Email address already registered' : 'ইমেইল ঠিকানা ইতিমধ্যে নিবন্ধিত';
                }
            }
            
            if (!$error) {
                // Handle file uploads
                $nidDocument = '';
                $licenseDocument = '';
                $certificateDocument = '';
                $profilePicture = '';
                
                // Upload NID document
                if (isset($_FILES['nid_document']) && $_FILES['nid_document']['error'] === UPLOAD_ERR_OK) {
                    $nidDocument = uploadFile($_FILES['nid_document'], '../uploads/documents/');
                    if (!$nidDocument) {
                        $error = $currentLang === 'en' ? 'Failed to upload NID document' : 'এনআইডি নথি আপলোড করতে ব্যর্থ';
                    }
                }
                
                // Upload license document (optional)
                if (!$error && isset($_FILES['license_document']) && $_FILES['license_document']['error'] === UPLOAD_ERR_OK) {
                    $licenseDocument = uploadFile($_FILES['license_document'], '../uploads/documents/');
                }
                
                // Upload certificate document (optional)
                if (!$error && isset($_FILES['certificate_document']) && $_FILES['certificate_document']['error'] === UPLOAD_ERR_OK) {
                    $certificateDocument = uploadFile($_FILES['certificate_document'], '../uploads/documents/');
                }
                
                // Upload profile picture (optional)
                if (!$error && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $profilePicture = uploadFile($_FILES['profile_picture'], '../uploads/profiles/');
                }
                
                if (!$error) {
                    // Create provider account
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO service_providers (name, email, phone, password, category_id, description, service_areas, price_min, price_max, hourly_rate, availability_hours, nid_document, license_document, certificate_document, profile_picture, language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $name, $email ?: null, $phone, $hashedPassword, $categoryId, $description, 
                        $serviceAreas, $priceMin ?: null, $priceMax ?: null, $hourlyRate ?: null, 
                        $availabilityHours, $nidDocument, $licenseDocument ?: null, 
                        $certificateDocument ?: null, $profilePicture ?: null, $currentLang
                    ];
                    
                    try {
                        executeQuery($sql, $params);
                        $success = $currentLang === 'en' ? 'Registration submitted successfully! Your account will be reviewed by admin and you will be notified once approved.' : 'নিবন্ধন সফলভাবে জমা হয়েছে! আপনার অ্যাকাউন্ট অ্যাডমিন দ্বারা পর্যালোচনা করা হবে এবং অনুমোদিত হলে আপনাকে জানানো হবে।';
                    } catch (Exception $e) {
                        $error = $currentLang === 'en' ? 'Registration failed. Please try again.' : 'নিবন্ধন ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
                    }
                }
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
    <title><?php echo $currentLang === 'en' ? 'Register as Service Provider' : 'সেবা প্রদানকারী হিসেবে নিবন্ধন'; ?> - S24</title>
    <link rel="stylesheet" href="../assets/ui.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .provider-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .provider-bg::before {
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
            animation: float 10s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 120px;
            height: 120px;
            top: 10%;
            left: 5%;
            animation-delay: 0s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        .floating-element:nth-child(2) {
            width: 80px;
            height: 80px;
            top: 20%;
            right: 10%;
            animation-delay: 2s;
            background: linear-gradient(45deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
        }
        
        .floating-element:nth-child(3) {
            width: 100px;
            height: 100px;
            bottom: 20%;
            left: 15%;
            animation-delay: 4s;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.2));
        }
        
        .floating-element:nth-child(4) {
            width: 60px;
            height: 60px;
            bottom: 10%;
            right: 20%;
            animation-delay: 1s;
            background: linear-gradient(45deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            50% { transform: translateY(-30px) rotate(180deg) scale(1.1); }
        }
        
        .auth-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .auth-container::before {
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
        
        .brand-logo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .form-section {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        .form-section:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            position: relative;
        }
        .form-group .icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            z-index: 10;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding-left: 3rem;
            color: #000000;
        }
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .file-upload input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            border: 2px dashed var(--primary);
            border-radius: 0.75rem;
            background: rgba(99, 102, 241, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .file-upload-label:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--secondary);
        }
        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 1.25rem;
        }
        
        .language-toggle {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .language-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="provider-bg min-h-screen py-8">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="max-w-4xl mx-auto px-4 relative z-10">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="../index.php" class="inline-block">
                <div class="brand-logo text-5xl mb-3">
                    S24
                </div>
            </a>
            <h1 class="text-4xl font-bold text-white mb-3 drop-shadow-lg">
                <?php echo $currentLang === 'en' ? 'Join as Service Provider' : 'সেবা প্রদানকারী হিসেবে যোগ দিন'; ?>
            </h1>
            <p class="text-white text-lg opacity-90 max-w-2xl mx-auto">
                <?php echo $currentLang === 'en' ? 'Register your service business and start receiving customer requests' : 'আপনার সেবা ব্যবসা নিবন্ধন করুন এবং গ্রাহকদের অনুরোধ পেতে শুরু করুন'; ?>
            </p>
        </div>

        <!-- Registration Form -->
        <div class="auth-container p-8">
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php echo $success; ?>
                    </div>
                    <div class="mt-3">
                        <a href="../auth/login.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            <?php echo $currentLang === 'en' ? 'Go to Login' : 'লগইনে যান'; ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="flex items-center mb-4">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 ml-3">
                            <?php echo $currentLang === 'en' ? 'Personal Information' : 'ব্যক্তিগত তথ্য'; ?>
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Full Name' : 'পূর্ণ নাম'; ?> *
                            </label>
                            <div class="relative">
                                <i class="fas fa-user icon"></i>
                                <input type="text" name="name" required 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'Enter your full name' : 'আপনার পূর্ণ নাম লিখুন'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Phone Number' : 'ফোন নম্বর'; ?> *
                            </label>
                            <div class="relative">
                                <i class="fas fa-phone icon"></i>
                                <input type="tel" name="phone" required 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'Enter your phone number' : 'আপনার ফোন নম্বর লিখুন'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Email Address' : 'ইমেইল ঠিকানা'; ?>
                            </label>
                            <div class="relative">
                                <i class="fas fa-envelope icon"></i>
                                <input type="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'Enter your email (optional)' : 'আপনার ইমেইল লিখুন (ঐচ্ছিক)'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Profile Picture' : 'প্রোফাইল ছবি'; ?>
                            </label>
                            <div class="file-upload">
                                <input type="file" name="profile_picture" accept="image/*">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt mr-2 text-primary"></i>
                                    <span class="text-primary font-medium">
                                        <?php echo $currentLang === 'en' ? 'Choose Profile Picture' : 'প্রোফাইল ছবি নির্বাচন করুন'; ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Information -->
                <div class="form-section">
                    <div class="flex items-center mb-4">
                        <div class="section-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 ml-3">
                            <?php echo $currentLang === 'en' ? 'Service Information' : 'সেবার তথ্য'; ?>
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Service Category' : 'সেবা বিভাগ'; ?> *
                            </label>
                            <div class="relative">
                                <i class="fas fa-list icon"></i>
                                <select name="category_id" required class="form-input">
                                    <option value=""><?php echo $currentLang === 'en' ? 'Select service category' : 'সেবা বিভাগ নির্বাচন করুন'; ?></option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo $currentLang === 'en' ? $category['name'] : $category['name_bn']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Service Areas' : 'সেবা এলাকা'; ?>
                            </label>
                            <div class="relative">
                                <i class="fas fa-map-marker-alt icon"></i>
                                <input type="text" name="service_areas" 
                                       value="<?php echo isset($_POST['service_areas']) ? htmlspecialchars($_POST['service_areas']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'e.g., Dhanmondi, Banani, Gulshan' : 'যেমন: ধানমন্ডি, বনানী, গুলশান'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Minimum Price (৳)' : 'ন্যূনতম মূল্য (৳)'; ?>
                            </label>
                            <div class="relative">
                                <i class="fas fa-tag icon"></i>
                                <input type="number" name="price_min" min="0"
                                       value="<?php echo isset($_POST['price_min']) ? htmlspecialchars($_POST['price_min']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'e.g., 500' : 'যেমন: ৫০০'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Maximum Price (৳)' : 'সর্বোচ্চ মূল্য (৳)'; ?>
                            </label>
                            <div class="relative">
                                <i class="fas fa-tag icon"></i>
                                <input type="number" name="price_max" min="0"
                                       value="<?php echo isset($_POST['price_max']) ? htmlspecialchars($_POST['price_max']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'e.g., 2000' : 'যেমন: ২০০০'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Hourly Rate (৳)' : 'ঘণ্টাপ্রতি মূল্য (৳)'; ?>
                            </label>
                            <div class="relative">
                                <i class="fas fa-clock icon"></i>
                                <input type="number" name="hourly_rate" min="0"
                                       value="<?php echo isset($_POST['hourly_rate']) ? htmlspecialchars($_POST['hourly_rate']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'e.g., 300' : 'যেমন: ৩০০'; ?>"
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Availability Hours' : 'উপলব্ধতার সময়'; ?>
                            </label>
                            <div class="relative">
                                <i class="fas fa-calendar icon"></i>
                                <input type="text" name="availability_hours" 
                                       value="<?php echo isset($_POST['availability_hours']) ? htmlspecialchars($_POST['availability_hours']) : ''; ?>"
                                       placeholder="<?php echo $currentLang === 'en' ? 'e.g., 9 AM - 6 PM' : 'যেমন: সকাল ৯টা - বিকাল ৬টা'; ?>"
                                       class="form-input">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-2 text-primary"></i>
                            <?php echo $currentLang === 'en' ? 'Service Description' : 'সেবার বিবরণ'; ?>
                        </label>
                        <textarea name="description" rows="4" 
                                  placeholder="<?php echo $currentLang === 'en' ? 'Describe your services, experience, and what makes you unique' : 'আপনার সেবা, অভিজ্ঞতা এবং যা আপনাকে অনন্য করে তোলে তা বর্ণনা করুন'; ?>"
                                  class="form-input"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Documents -->
                <div class="form-section">
                    <div class="flex items-center mb-4">
                        <div class="section-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 ml-3">
                            <?php echo $currentLang === 'en' ? 'Documents' : 'নথিপত্র'; ?>
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-id-card mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'NID Document' : 'এনআইডি নথি'; ?> *
                            </label>
                            <div class="file-upload">
                                <input type="file" name="nid_document" required accept=".pdf,.jpg,.jpeg,.png">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt mr-2 text-primary"></i>
                                    <span class="text-primary font-medium">
                                        <?php echo $currentLang === 'en' ? 'Upload NID Document' : 'এনআইডি নথি আপলোড করুন'; ?>
                                    </span>
                                </label>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?php echo $currentLang === 'en' ? 'Upload your NID card or passport' : 'আপনার এনআইডি কার্ড বা পাসপোর্ট আপলোড করুন'; ?>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-certificate mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'License/Certificate' : 'লাইসেন্স/সার্টিফিকেট'; ?>
                            </label>
                            <div class="file-upload">
                                <input type="file" name="license_document" accept=".pdf,.jpg,.jpeg,.png">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt mr-2 text-primary"></i>
                                    <span class="text-primary font-medium">
                                        <?php echo $currentLang === 'en' ? 'Upload License/Certificate' : 'লাইসেন্স/সার্টিফিকেট আপলোড করুন'; ?>
                                    </span>
                                </label>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?php echo $currentLang === 'en' ? 'Upload relevant license or certificate (optional)' : 'প্রাসঙ্গিক লাইসেন্স বা সার্টিফিকেট আপলোড করুন (ঐচ্ছিক)'; ?>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-award mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Additional Certificate' : 'অতিরিক্ত সার্টিফিকেট'; ?>
                            </label>
                            <div class="file-upload">
                                <input type="file" name="certificate_document" accept=".pdf,.jpg,.jpeg,.png">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt mr-2 text-primary"></i>
                                    <span class="text-primary font-medium">
                                        <?php echo $currentLang === 'en' ? 'Upload Additional Certificate' : 'অতিরিক্ত সার্টিফিকেট আপলোড করুন'; ?>
                                    </span>
                                </label>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?php echo $currentLang === 'en' ? 'Upload additional certificates (optional)' : 'অতিরিক্ত সার্টিফিকেট আপলোড করুন (ঐচ্ছিক)'; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Account Security -->
                <div class="form-section">
                    <div class="flex items-center mb-4">
                        <div class="section-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 ml-3">
                            <?php echo $currentLang === 'en' ? 'Account Security' : 'অ্যাকাউন্ট নিরাপত্তা'; ?>
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-lock mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Password' : 'পাসওয়ার্ড'; ?> *
                            </label>
                            <div class="relative">
                                <i class="fas fa-lock icon"></i>
                                <input type="password" name="password" required 
                                       placeholder="<?php echo $currentLang === 'en' ? 'Create a password' : 'একটি পাসওয়ার্ড তৈরি করুন'; ?>"
                                       class="form-input pr-12" id="password">
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors" id="toggle-password">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?php echo $currentLang === 'en' ? 'Minimum 6 characters' : 'ন্যূনতম ৬ অক্ষর'; ?>
                            </p>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-shield-alt mr-2 text-primary"></i>
                                <?php echo $currentLang === 'en' ? 'Confirm Password' : 'পাসওয়ার্ড নিশ্চিত করুন'; ?> *
                            </label>
                            <div class="relative">
                                <i class="fas fa-shield-alt icon"></i>
                                <input type="password" name="confirm_password" required 
                                       placeholder="<?php echo $currentLang === 'en' ? 'Confirm your password' : 'আপনার পাসওয়ার্ড নিশ্চিত করুন'; ?>"
                                       class="form-input pr-12" id="confirm-password">
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary transition-colors" id="toggle-confirm-password">
                                    <i class="fas fa-eye" id="confirm-password-icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full btn-primary text-lg py-4">
                    <i class="fas fa-paper-plane mr-2"></i>
                    <?php echo $currentLang === 'en' ? 'Submit Registration' : 'নিবন্ধন জমা দিন'; ?>
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-gray-600">
                    <?php echo $currentLang === 'en' ? 'Already have an account?' : 'ইতিমধ্যে অ্যাকাউন্ট আছে?'; ?>
                    <a href="../auth/login.php" class="text-primary hover:text-secondary font-semibold transition-colors">
                        <?php echo $currentLang === 'en' ? 'Sign in' : 'সাইন ইন করুন'; ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Language Toggle -->
        <div class="text-center mt-6">
            <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" 
               class="language-toggle">
                <i class="fas fa-globe mr-2"></i>
                <?php echo $currentLang === 'en' ? 'বাংলায় দেখুন' : 'View in English'; ?>
            </a>
        </div>
    </div>

    <script src="../assets/ui.js"></script>
    <script>
        // Auto-focus on name input
        document.querySelector('input[name="name"]').focus();
        
        // Password confirmation validation
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('<?php echo $currentLang === 'en' ? 'Passwords do not match' : 'পাসওয়ার্ড মিলছে না'; ?>');
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
        });

        // File upload preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const label = this.nextElementSibling;
                const fileName = this.files[0]?.name || '';
                if (fileName) {
                    label.innerHTML = `<i class="fas fa-check mr-2 text-green-500"></i><span class="text-green-600 font-medium">${fileName}</span>`;
                }
            });
        });

        // Password show/hide toggle functionality
        function togglePasswordVisibility(inputId, toggleId, iconId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(toggleId);
            const icon = document.getElementById(iconId);
            
            toggle.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        // Initialize password toggles
        togglePasswordVisibility('password', 'toggle-password', 'password-icon');
        togglePasswordVisibility('confirm-password', 'toggle-confirm-password', 'confirm-password-icon');
    </script>
</body>
</html> 