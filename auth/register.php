<?php
require_once '../includes/functions.php';

$currentLang = getLanguage();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $location = sanitizeInput($_POST['location']);
    
    // Validation
    if (empty($name) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $error = $currentLang === 'en' ? 'Please fill in all required fields' : 'সব প্রয়োজনীয় ক্ষেত্র পূরণ করুন';
    } elseif (!validatePhone($phone)) {
        $error = $currentLang === 'en' ? 'Please enter a valid phone number' : 'সঠিক ফোন নম্বর লিখুন';
    } elseif ($email && !validateEmail($email)) {
        $error = $currentLang === 'en' ? 'Please enter a valid email address' : 'সঠিক ইমেইল ঠিকানা লিখুন';
    } elseif (strlen($password) < 6) {
        $error = $currentLang === 'en' ? 'Password must be at least 6 characters long' : 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
    } elseif ($password !== $confirmPassword) {
        $error = $currentLang === 'en' ? 'Passwords do not match' : 'পাসওয়ার্ড মিলছে না';
    } else {
        // Check if phone already exists
        $existingUser = fetchOne("SELECT id FROM users WHERE phone = ?", [$phone]);
        if ($existingUser) {
            $error = $currentLang === 'en' ? 'Phone number already registered' : 'ফোন নম্বর ইতিমধ্যে নিবন্ধিত';
        } else {
            // Check if email already exists (if provided)
            if ($email) {
                $existingEmail = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                if ($existingEmail) {
                    $error = $currentLang === 'en' ? 'Email address already registered' : 'ইমেইল ঠিকানা ইতিমধ্যে নিবন্ধিত';
                }
            }
            
            if (!$error) {
                // Create user account
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (name, email, phone, password, location, language) VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$name, $email ?: null, $phone, $hashedPassword, $location, $currentLang];
                
                try {
                    executeQuery($sql, $params);
                    $success = $currentLang === 'en' ? 'Account created successfully! You can now login.' : 'অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে! আপনি এখন লগইন করতে পারেন।';
                } catch (Exception $e) {
                    $error = $currentLang === 'en' ? 'Registration failed. Please try again.' : 'নিবন্ধন ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
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
    <title><?php echo $currentLang === 'en' ? 'Register' : 'নিবন্ধন'; ?> - S24</title>
    <link rel="stylesheet" href="../assets/ui.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .register-bg {
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
        
        .register-bg::before {
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
        
        .floating-element:nth-child(5) {
            width: 60px;
            height: 60px;
            top: 50%;
            left: 50%;
            animation-delay: 3s;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.15));
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            50% { transform: translateY(-40px) rotate(180deg) scale(1.1); }
        }
        
        .register-container {
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
        
        .register-container::before {
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
        
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .form-input {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 1rem;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            color: #000000;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            width: 100%;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: rgba(255, 255, 255, 1);
            color: #000000;
            transform: translateY(-2px);
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.1rem;
            z-index: 10;
        }
        
        .form-label {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }
        
        .form-label i {
            color: #667eea;
            margin-right: 0.5rem;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            width: 100%;
        }
        
        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-register:hover::before {
            left: 100%;
        }
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border-left: 4px solid #ef4444;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
        }
        
        .success-message {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1));
            border-left: 4px solid #22c55e;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }
        
        .success-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            margin-top: 0.75rem;
        }
        
        .success-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.3);
        }
        
        .link-style {
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .link-style:hover {
            color: #764ba2;
            transform: translateY(-1px);
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
        
        .password-strength {
            height: 6px;
            border-radius: 3px;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .strength-weak { 
            background: linear-gradient(90deg, #ef4444 0%, #fca5a5 100%); 
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
        }
        .strength-medium { 
            background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); 
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
        }
        .strength-strong { 
            background: linear-gradient(90deg, #22c55e 0%, #4ade80 100%); 
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.3);
        }
        
        .provider-link {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .provider-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .info-text {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .info-text i {
            color: #667eea;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body class="register-bg min-h-screen p-4">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="max-w-lg w-full relative z-10 mx-auto py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="../index.php" class="inline-block">
                <div class="brand-logo text-5xl mb-3">
                    S24
                </div>
            </a>
            <h1 class="text-4xl font-bold text-white mb-3 drop-shadow-lg">
                <?php echo $currentLang === 'en' ? 'Join S24 Today!' : 'আজই S24-এ যোগ দিন!'; ?>
            </h1>
            <p class="text-white text-lg opacity-90">
                <?php echo $currentLang === 'en' ? 'Create your account and find trusted service providers' : 'আপনার অ্যাকাউন্ট তৈরি করুন এবং বিশ্বস্ত সেবা প্রদানকারী খুঁজুন'; ?>
            </p>
        </div>

        <!-- Registration Form -->
        <div class="register-container p-8">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle mr-3 text-red-500"></i>
                    <span class="text-red-700 font-semibold"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-green-500"></i>
                        <span class="text-green-700 font-semibold"><?php echo $success; ?></span>
                    </div>
                    <a href="login.php" class="success-btn">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Go to Login' : 'লগইনে যান'; ?>
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i>
                        <?php echo $currentLang === 'en' ? 'Full Name' : 'পূর্ণ নাম'; ?> *
                    </label>
                    <div class="relative">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="name" required 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your full name' : 'আপনার পূর্ণ নাম লিখুন'; ?>"
                               class="form-input">
                    </div>
                </div>

                <!-- Email Address -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        <?php echo $currentLang === 'en' ? 'Email Address' : 'ইমেইল ঠিকানা'; ?>
                    </label>
                    <div class="relative">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your email (optional)' : 'আপনার ইমেইল লিখুন (ঐচ্ছিক)'; ?>"
                               class="form-input">
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-phone"></i>
                        <?php echo $currentLang === 'en' ? 'Phone Number' : 'ফোন নম্বর'; ?> *
                    </label>
                    <div class="relative">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone" required 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your phone number' : 'আপনার ফোন নম্বর লিখুন'; ?>"
                               class="form-input">
                    </div>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Location' : 'অবস্থান'; ?>
                    </label>
                    <div class="relative">
                        <i class="fas fa-map-marker-alt input-icon"></i>
                        <input type="text" name="location" 
                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your location (optional)' : 'আপনার অবস্থান লিখুন (ঐচ্ছিক)'; ?>"
                               class="form-input">
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        <?php echo $currentLang === 'en' ? 'Password' : 'পাসওয়ার্ড'; ?> *
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" required 
                               placeholder="<?php echo $currentLang === 'en' ? 'Create a password' : 'একটি পাসওয়ার্ড তৈরি করুন'; ?>"
                               class="form-input pr-12" id="password">
                        <button type="button" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors" id="toggle-password">
                            <i class="fas fa-eye text-lg" id="password-icon"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="password-strength"></div>
                    <p class="info-text">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $currentLang === 'en' ? 'Minimum 6 characters' : 'ন্যূনতম ৬ অক্ষর'; ?>
                    </p>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-shield-alt"></i>
                        <?php echo $currentLang === 'en' ? 'Confirm Password' : 'পাসওয়ার্ড নিশ্চিত করুন'; ?> *
                    </label>
                    <div class="relative">
                        <i class="fas fa-shield-alt input-icon"></i>
                        <input type="password" name="confirm_password" required 
                               placeholder="<?php echo $currentLang === 'en' ? 'Confirm your password' : 'আপনার পাসওয়ার্ড নিশ্চিত করুন'; ?>"
                               class="form-input pr-12" id="confirm-password">
                        <button type="button" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors" id="toggle-confirm-password">
                            <i class="fas fa-eye text-lg" id="confirm-password-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Create Account' : 'অ্যাকাউন্ট তৈরি করুন'; ?>
                </button>
            </form>

            <!-- Links -->
            <div class="mt-8 text-center space-y-4">
                <p class="text-gray-700 font-semibold">
                    <?php echo $currentLang === 'en' ? 'Already have an account?' : 'ইতিমধ্যে অ্যাকাউন্ট আছে?'; ?>
                    <a href="login.php" class="link-style ml-2">
                        <?php echo $currentLang === 'en' ? 'Sign in now' : 'এখনই সাইন ইন করুন'; ?>
                    </a>
                </p>
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-gray-700 mb-3 font-semibold">
                        <?php echo $currentLang === 'en' ? 'Are you a service provider?' : 'আপনি কি সেবা প্রদানকারী?'; ?>
                    </p>
                    <a href="../provider/register.php" class="provider-link">
                        <i class="fas fa-tools mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Register as Provider' : 'প্রদানকারী হিসেবে নিবন্ধন করুন'; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Language Toggle -->
        <div class="text-center mt-6">
            <a href="?lang=<?php echo $currentLang === 'en' ? 'bn' : 'en'; ?>" class="language-toggle">
                <i class="fas fa-globe mr-2"></i>
                <?php echo $currentLang === 'en' ? 'বাংলায় দেখুন' : 'View in English'; ?>
            </a>
        </div>
    </div>

    <script src="../assets/ui.js"></script>
    <script>
        // Auto-focus on name input
        document.querySelector('input[name="name"]').focus();
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('password-strength');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            passwordStrength.className = 'password-strength';
            
            if (strength <= 2) {
                passwordStrength.classList.add('strength-weak');
            } else if (strength <= 3) {
                passwordStrength.classList.add('strength-medium');
            } else {
                passwordStrength.classList.add('strength-strong');
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm-password').addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('<?php echo $currentLang === 'en' ? 'Passwords do not match' : 'পাসওয়ার্ড মিলছে না'; ?>');
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
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