<?php
require_once '../includes/functions.php';

$currentLang = getLanguage();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $userType = $_POST['user_type'];
    
    if (empty($phone) || empty($password)) {
        $error = $currentLang === 'en' ? 'Please fill in all fields' : 'সব ক্ষেত্র পূরণ করুন';
    } else {
        if ($userType === 'customer') {
            $user = fetchOne("SELECT * FROM users WHERE phone = ?", [$phone]);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'customer';
                setFlashMessage('success', $currentLang === 'en' ? 'Login successful!' : 'লগইন সফল!');
                redirect('../customer/dashboard.php');
            } else {
                $error = $currentLang === 'en' ? 'Invalid phone number or password' : 'ভুল ফোন নম্বর বা পাসওয়ার্ড';
            }
        } else {
            $provider = fetchOne("SELECT * FROM service_providers WHERE phone = ?", [$phone]);
            if ($provider && password_verify($password, $provider['password'])) {
                $_SESSION['provider_id'] = $provider['id'];
                $_SESSION['user_type'] = 'provider';
                setFlashMessage('success', $currentLang === 'en' ? 'Login successful!' : 'লগইন সফল!');
                redirect('../provider/dashboard.php');
            } else {
                $error = $currentLang === 'en' ? 'Invalid phone number or password' : 'ভুল ফোন নম্বর বা পাসওয়ার্ড';
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
    <title><?php echo $currentLang === 'en' ? 'Login' : 'লগইন'; ?> - S24</title>
    <link rel="stylesheet" href="../assets/ui.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .login-bg {
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
        
        .login-bg::before {
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
            animation: float 6s ease-in-out infinite;
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
        
        .login-container {
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
        
        .login-container::before {
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
        
        .user-type-card {
            background: rgba(255, 255, 255, 0.8);
            border: 2px solid transparent;
            border-radius: 1rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .user-type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .user-type-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .user-type-card:hover::before {
            opacity: 1;
        }
        
        .user-type-card.selected {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.2);
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
        
        .btn-login {
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
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
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
        
        .type-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 1rem;
            transition: all 0.3s ease;
        }
        
        .type-icon.customer {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .type-icon.provider {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .user-type-card:hover .type-icon {
            transform: scale(1.1);
        }
        
        .user-type-card.selected .type-icon {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="login-bg min-h-screen p-4">
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="max-w-md w-full relative z-10 mx-auto py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="../index.php" class="inline-block">
                <div class="brand-logo text-5xl mb-3">
                    S24
                </div>
            </a>
            <h1 class="text-4xl font-bold text-white mb-3 drop-shadow-lg">
                <?php echo $currentLang === 'en' ? 'Welcome Back!' : 'আবার স্বাগতম!'; ?>
            </h1>
            <p class="text-white text-lg opacity-90">
                <?php echo $currentLang === 'en' ? 'Sign in to your account' : 'আপনার অ্যাকাউন্টে সাইন ইন করুন'; ?>
            </p>
        </div>

        <!-- Login Form -->
        <div class="login-container p-8">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle mr-3 text-red-500"></i>
                    <span class="text-red-700 font-semibold"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- User Type Selection -->
                <div>
                    <label class="block text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-users mr-2 text-blue-600"></i>
                        <?php echo $currentLang === 'en' ? 'Select User Type' : 'ব্যবহারকারীর ধরন নির্বাচন করুন'; ?>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="user-type-card">
                            <input type="radio" name="user_type" value="customer" checked class="sr-only">
                            <div class="flex items-center">
                                <div class="type-icon customer">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-800 text-lg"><?php echo $currentLang === 'en' ? 'Customer' : 'গ্রাহক'; ?></div>
                                    <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Service Seeker' : 'সেবা প্রার্থী'; ?></div>
                                </div>
                            </div>
                        </label>
                        <label class="user-type-card">
                            <input type="radio" name="user_type" value="provider" class="sr-only">
                            <div class="flex items-center">
                                <div class="type-icon provider">
                                    <i class="fas fa-tools text-white"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-800 text-lg"><?php echo $currentLang === 'en' ? 'Provider' : 'প্রদানকারী'; ?></div>
                                    <div class="text-sm text-gray-600"><?php echo $currentLang === 'en' ? 'Service Provider' : 'সেবা প্রদানকারী'; ?></div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="relative">
                    <label class="block text-lg font-bold text-gray-800 mb-3">
                        <i class="fas fa-phone mr-2 text-blue-600"></i>
                        <?php echo $currentLang === 'en' ? 'Phone Number' : 'ফোন নম্বর'; ?>
                    </label>
                    <div class="relative">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone" required 
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your phone number' : 'আপনার ফোন নম্বর লিখুন'; ?>"
                               class="form-input w-full">
                    </div>
                </div>

                <!-- Password -->
                <div class="relative">
                    <label class="block text-lg font-bold text-gray-800 mb-3">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>
                        <?php echo $currentLang === 'en' ? 'Password' : 'পাসওয়ার্ড'; ?>
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" required 
                               placeholder="<?php echo $currentLang === 'en' ? 'Enter your password' : 'আপনার পাসওয়ার্ড লিখুন'; ?>"
                               class="form-input w-full pr-12" id="login-password">
                        <button type="button" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors" id="toggle-login-password">
                            <i class="fas fa-eye text-lg" id="login-password-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login w-full">
                    <i class="fas fa-sign-in-alt mr-3"></i>
                    <?php echo $currentLang === 'en' ? 'Sign In' : 'সাইন ইন করুন'; ?>
                </button>
            </form>

            <!-- Links -->
            <div class="mt-8 text-center space-y-4">
                <p class="text-gray-700 font-semibold">
                    <?php echo $currentLang === 'en' ? "Don't have an account?" : 'অ্যাকাউন্ট নেই?'; ?>
                    <a href="register.php" class="link-style ml-2">
                        <?php echo $currentLang === 'en' ? 'Sign up now' : 'এখনই সাইন আপ করুন'; ?>
                    </a>
                </p>
                <div class="border-t border-gray-200 pt-4">
                    <a href="../admin/login.php" class="link-style">
                        <i class="fas fa-user-shield mr-2"></i>
                        <?php echo $currentLang === 'en' ? 'Admin Login' : 'অ্যাডমিন লগইন'; ?>
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
        // Auto-focus on phone input
        document.querySelector('input[name="phone"]').focus();
        
        // Handle user type selection with enhanced styling
        document.querySelectorAll('input[name="user_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.user-type-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Add selected class to current card
                if (this.checked) {
                    this.closest('.user-type-card').classList.add('selected');
                }
            });
        });

        // Initialize first card as selected
        document.querySelector('input[name="user_type"]:checked').closest('.user-type-card').classList.add('selected');

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

        // Initialize password toggle for login
        togglePasswordVisibility('login-password', 'toggle-login-password', 'login-password-icon');
    </script>
</body>
</html> 