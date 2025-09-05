-- S24 Service Provider Website - Complete Database Schema
-- This file merges all database migrations and creates a complete system

-- Create database
CREATE DATABASE IF NOT EXISTS s24_services;
USE s24_services;

-- Users table (customers)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    language ENUM('en', 'bn') DEFAULT 'en',
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Service categories
CREATE TABLE service_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    name_bn VARCHAR(100),
    icon VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service providers
CREATE TABLE service_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    category_id INT,
    description TEXT,
    service_areas TEXT,
    price_min DECIMAL(10,2),
    price_max DECIMAL(10,2),
    hourly_rate DECIMAL(10,2),
    availability_hours TEXT,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_badge BOOLEAN DEFAULT FALSE,
    nid_document VARCHAR(255),
    license_document VARCHAR(255),
    certificate_document VARCHAR(255),
    profile_picture VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES service_categories(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Bookings table (with customer_address column)
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    provider_id INT,
    category_id INT,
    service_type VARCHAR(100),
    booking_date DATE,
    booking_time TIME,
    notes TEXT,
    customer_address TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    final_price DECIMAL(10,2),
    cancellation_reason TEXT,
    cancellation_fee DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (provider_id) REFERENCES service_providers(id),
    FOREIGN KEY (category_id) REFERENCES service_categories(id)
);

-- Payments (customer payments for bookings)
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('bkash','nagad','bank') NOT NULL,
    transaction_id VARCHAR(100),
    proof_file VARCHAR(255),
    status ENUM('pending','verified','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (provider_id) REFERENCES service_providers(id)
);

-- Reviews
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    customer_id INT,
    provider_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    review_photo VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (provider_id) REFERENCES service_providers(id)
);

-- Admin users
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Platform settings
CREATE TABLE platform_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Work requests (for customers to post work)
CREATE TABLE work_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    category_id INT,
    location VARCHAR(100),
    description TEXT,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    preferred_date DATE,
    contact_phone VARCHAR(20),
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES service_categories(id)
);

-- Work bids (for providers to bid on work requests)
CREATE TABLE work_bids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    work_request_id INT,
    provider_id INT,
    bid_amount DECIMAL(10,2),
    estimated_days INT,
    proposal TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_request_id) REFERENCES work_requests(id),
    FOREIGN KEY (provider_id) REFERENCES service_providers(id)
);

-- Work assignments (when customer accepts a bid)
CREATE TABLE work_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    work_request_id INT,
    provider_id INT,
    customer_id INT,
    accepted_bid_id INT,
    final_amount DECIMAL(10,2),
    start_date DATE,
    completion_date DATE,
    status ENUM('assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'assigned',
    customer_notes TEXT,
    provider_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (work_request_id) REFERENCES work_requests(id),
    FOREIGN KEY (provider_id) REFERENCES service_providers(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (accepted_bid_id) REFERENCES work_bids(id)
);

-- Customer-Provider Selections (NEW - tracks when customers select providers)
CREATE TABLE customer_provider_selections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    category_id INT NOT NULL,
    service_type VARCHAR(255),
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    customer_address TEXT,
    customer_notes TEXT,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    status ENUM('pending', 'contacted', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
    customer_contacted_at TIMESTAMP NULL,
    provider_responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE,
    
    INDEX idx_customer_status (customer_id, status),
    INDEX idx_provider_status (provider_id, status),
    INDEX idx_category_status (category_id, status),
    INDEX idx_status_created (status, created_at)
);

-- Notifications table (NEW - for communication between users and providers)
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_type ENUM('customer', 'provider') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking_approved', 'booking_confirmed', 'booking_completed', 'booking_cancelled', 'review_received', 'general') DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for notifications table
CREATE INDEX idx_notifications_user ON notifications(user_id, user_type, is_read);
CREATE INDEX idx_notifications_type ON notifications(type, created_at);

-- Insert default data
INSERT INTO service_categories (name, name_bn, icon, description) VALUES
('AC Servicing', 'এসি সার্ভিসিং', 'ac', 'Air conditioning installation, repair, and maintenance'),
('Plumbing', 'প্লাম্বিং', 'plumbing', 'Pipe repair, installation, and maintenance'),
('Electrical', 'ইলেকট্রিক্যাল', 'electrical', 'Electrical wiring, repair, and installation'),
('Cleaning', 'ক্লিনিং', 'cleaning', 'Home and office cleaning services'),
('Carpentry', 'কার্পেন্টারি', 'carpentry', 'Woodwork, furniture repair, and installation'),
('Painting', 'পেইন্টিং', 'painting', 'Interior and exterior painting services'),
('Moving', 'মুভিং', 'moving', 'Home and office relocation services'),
('Gardening', 'গার্ডেনিং', 'gardening', 'Landscaping and garden maintenance');

-- Insert default admin
INSERT INTO admins (username, email, password, role) VALUES
('admin', 'admin@s24.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Insert platform settings
INSERT INTO platform_settings (setting_key, setting_value, description) VALUES
('cancellation_fee_percentage', '10', 'Percentage fee for late cancellations'),
('min_booking_hours', '2', 'Minimum hours before service for cancellation without fee'),
('max_price_range', '50000', 'Maximum price range for services'),
('min_price_range', '100', 'Minimum price range for services');

-- Insert sample service providers (40 providers)
INSERT INTO service_providers (name, phone, email, password, category_id, description, service_areas, price_min, price_max, hourly_rate, availability_hours, verification_status, verification_badge) VALUES
-- AC Servicing Providers (8 providers)
('Rafiq AC Service', '01712345678', 'rafiq@acservice.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Professional AC installation, repair, and maintenance services. 10+ years experience.', 'Dhanmondi, Lalbagh, Old Dhaka', 800, 2500, 300, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Cool Comfort AC', '01812345678', 'cool@comfort.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Expert AC technicians for all brands. Same day service available.', 'Gulshan, Banani, Baridhara', 1000, 3000, 400, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Dhaka AC Solutions', '01912345678', 'dhaka@acsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Complete AC solutions including gas refill, cleaning, and repair.', 'Mirpur, Pallabi, Kafrul', 600, 2000, 250, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Air Master Pro', '01612345678', 'air@masterpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Professional AC service with warranty. 24/7 emergency service.', 'Uttara, Tongi, Gazipur', 700, 2200, 280, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Cool Zone AC', '01512345678', 'cool@zone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Reliable AC service with genuine parts. Free consultation.', 'Mohammadpur, Adabor, Dhanmondi', 750, 2400, 320, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Frost AC Service', '01412345678', 'frost@acservice.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Expert AC technicians with modern equipment. Quick service.', 'Badda, Rampura, Khilgaon', 650, 2100, 270, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Chill AC Care', '01312345678', 'chill@accare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Professional AC maintenance and repair. Affordable rates.', 'Jatrabari, Demra, Shyampur', 500, 1800, 220, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Arctic AC Solutions', '01212345678', 'arctic@acsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Complete AC solutions with quality service. Licensed technicians.', 'Motijheel, Paltan, Ramna', 900, 2800, 350, '8:00 AM - 8:00 PM', 'verified', TRUE),

-- Plumbing Providers (8 providers)
('Rahim Plumber', '01723456789', 'rahim@plumber.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Expert plumbing services with 15 years experience. Emergency service available.', 'Dhanmondi, Lalbagh, Old Dhaka', 500, 1500, 200, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Water Flow Pro', '01823456789', 'water@flowpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Professional plumbing and pipe fitting. Quality materials used.', 'Gulshan, Banani, Baridhara', 800, 2000, 300, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Pipe Master', '01923456789', 'pipe@master.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Complete plumbing solutions. Licensed and insured.', 'Mirpur, Pallabi, Kafrul', 400, 1200, 180, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Aqua Plumber', '01623456789', 'aqua@plumber.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Reliable plumbing service with warranty. 24/7 emergency calls.', 'Uttara, Tongi, Gazipur', 600, 1800, 250, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Flow Solutions', '01523456789', 'flow@solutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Expert plumbers for all types of work. Quick response time.', 'Mohammadpur, Adabor, Dhanmondi', 700, 1900, 280, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Plumb Right', '01423456789', 'plumb@right.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Professional plumbing with modern tools. Clean work guaranteed.', 'Badda, Rampura, Khilgaon', 450, 1400, 200, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Water Works', '01323456789', 'water@works.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Complete water system solutions. Affordable rates.', 'Jatrabari, Demra, Shyampur', 350, 1100, 150, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Pipe Pro', '01223456789', 'pipe@pro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Expert pipe fitting and repair. Quality service guaranteed.', 'Motijheel, Paltan, Ramna', 750, 2200, 320, '8:00 AM - 8:00 PM', 'verified', TRUE),

-- Electrical Providers (8 providers)
('Karim Electric', '01734567890', 'karim@electric.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Licensed electrician with 12 years experience. All electrical work.', 'Dhanmondi, Lalbagh, Old Dhaka', 600, 1800, 250, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Power Solutions', '01834567890', 'power@solutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Professional electrical services. Safety first approach.', 'Gulshan, Banani, Baridhara', 1000, 2500, 400, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Volt Master', '01934567890', 'volt@master.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Complete electrical solutions. Licensed and insured.', 'Mirpur, Pallabi, Kafrul', 500, 1500, 200, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Electric Pro', '01634567890', 'electric@pro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Expert electrical work with warranty. Emergency service available.', 'Uttara, Tongi, Gazipur', 700, 2000, 300, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Current Electric', '01534567890', 'current@electric.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Professional electrician for all types of work. Quick response.', 'Mohammadpur, Adabor, Dhanmondi', 800, 2200, 350, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Spark Electric', '01434567890', 'spark@electric.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Reliable electrical service with modern equipment.', 'Badda, Rampura, Khilgaon', 550, 1700, 250, '8:00 AM - 8:00 PM', 'verified', TRUE),
('Watt Works', '01334567890', 'watt@works.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Complete electrical solutions. Affordable and reliable.', 'Jatrabari, Demra, Shyampur', 400, 1200, 180, '7:00 AM - 9:00 PM', 'verified', TRUE),
('Circuit Pro', '01234567890', 'circuit@pro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Expert circuit work and electrical repair. Quality guaranteed.', 'Motijheel, Paltan, Ramna', 900, 2800, 450, '8:00 AM - 8:00 PM', 'verified', TRUE),

-- Cleaning Providers (8 providers)
('Clean Pro', '01745678901', 'clean@pro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Professional cleaning services for home and office. Eco-friendly products.', 'Dhanmondi, Lalbagh, Old Dhaka', 800, 2000, 150, '8:00 AM - 6:00 PM', 'verified', TRUE),
('Sparkle Clean', '01845678901', 'sparkle@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Complete cleaning solutions with trained staff. Satisfaction guaranteed.', 'Gulshan, Banani, Baridhara', 1200, 3000, 200, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Fresh Clean', '01945678901', 'fresh@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Reliable cleaning service with modern equipment. Regular maintenance available.', 'Mirpur, Pallabi, Kafrul', 600, 1500, 120, '7:00 AM - 8:00 PM', 'verified', TRUE),
('Spotless Clean', '01645678901', 'spotless@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Professional cleaning with attention to detail. Licensed and insured.', 'Uttara, Tongi, Gazipur', 700, 1800, 140, '8:00 AM - 6:00 PM', 'verified', TRUE),
('Pure Clean', '01545678901', 'pure@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Complete cleaning solutions for all types of spaces.', 'Mohammadpur, Adabor, Dhanmondi', 900, 2200, 180, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Bright Clean', '01445678901', 'bright@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Professional cleaning with eco-friendly products. Quick service.', 'Badda, Rampura, Khilgaon', 650, 1700, 130, '8:00 AM - 6:00 PM', 'verified', TRUE),
('Neat Clean', '01345678901', 'neat@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Affordable cleaning services with quality work.', 'Jatrabari, Demra, Shyampur', 500, 1200, 100, '7:00 AM - 8:00 PM', 'verified', TRUE),
('Shine Clean', '01245678901', 'shine@clean.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Expert cleaning for homes and offices. Satisfaction guaranteed.', 'Motijheel, Paltan, Ramna', 1000, 2500, 200, '8:00 AM - 6:00 PM', 'verified', TRUE),

-- Carpentry Providers (4 providers)
('Wood Master', '01756789012', 'wood@master.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Expert carpenter with 20 years experience. Custom furniture and repairs.', 'Dhanmondi, Lalbagh, Old Dhaka', 1000, 3000, 300, '8:00 AM - 6:00 PM', 'verified', TRUE),
('Craft Wood', '01856789012', 'craft@wood.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Professional carpentry services. Custom designs and quality work.', 'Gulshan, Banani, Baridhara', 1500, 4000, 400, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Timber Pro', '01956789012', 'timber@pro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Complete carpentry solutions. Furniture repair and installation.', 'Mirpur, Pallabi, Kafrul', 800, 2500, 250, '7:00 AM - 8:00 PM', 'verified', TRUE),
('Wood Works', '01656789012', 'wood@works.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Expert carpenter for all types of woodwork. Quality materials used.', 'Uttara, Tongi, Gazipur', 900, 2800, 280, '8:00 AM - 6:00 PM', 'verified', TRUE),

-- Painting Providers (4 providers)
('Color Master', '01767890123', 'color@master.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 'Professional painter with 15 years experience. Interior and exterior painting.', 'Dhanmondi, Lalbagh, Old Dhaka', 1200, 3500, 250, '8:00 AM - 6:00 PM', 'verified', TRUE),
('Paint Pro', '01867890123', 'paint@pro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 'Complete painting solutions. Quality paints and professional finish.', 'Gulshan, Banani, Baridhara', 1800, 5000, 350, '9:00 AM - 7:00 PM', 'verified', TRUE),
('Brush Master', '01967890123', 'brush@master.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 'Expert painter for all types of surfaces. Clean and neat work.', 'Mirpur, Pallabi, Kafrul', 1000, 2800, 200, '7:00 AM - 8:00 PM', 'verified', TRUE),
('Art Paint', '01667890123', 'art@paint.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 'Professional painting with artistic touch. Interior design consultation.', 'Uttara, Tongi, Gazipur', 1100, 3200, 220, '8:00 AM - 6:00 PM', 'verified', TRUE);

-- Insert sample users (customers) for testing
INSERT INTO users (name, email, phone, password, location, language) VALUES
('John Doe', 'john@example.com', '01711111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dhaka, Bangladesh', 'en'),
('Jane Smith', 'jane@example.com', '01722222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Chittagong, Bangladesh', 'en'),
('Ahmed Khan', 'ahmed@example.com', '01733333333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sylhet, Bangladesh', 'bn'),
('Fatima Begum', 'fatima@example.com', '01744444444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rajshahi, Bangladesh', 'bn');

-- Insert sample customer-provider selections for testing
INSERT INTO customer_provider_selections 
(customer_id, provider_id, category_id, service_type, preferred_date, preferred_time, customer_address, customer_notes, budget_min, budget_max, status, customer_contacted_at) 
VALUES 
(1, 1, 1, 'AC Servicing', '2025-08-15', '10:00:00', 'Dhaka, Bangladesh', 'Need AC service urgently', 500.00, 1500.00, 'pending', NOW()),
(2, 2, 2, 'Plumbing', '2025-08-16', '09:00:00', 'Chittagong, Bangladesh', 'Pipe repair needed', 300.00, 800.00, 'contacted', NOW());

-- Insert sample notifications
INSERT INTO notifications (user_id, user_type, title, message, type, related_id) VALUES
(1, 'customer', 'Booking Approved', 'Your booking with Rafiq AC Service has been approved!', 'booking_approved', 1),
(1, 'provider', 'New Booking Confirmed', 'You have a new confirmed booking from John Doe', 'booking_confirmed', 1);

-- Display completion message
SELECT 'Database setup completed successfully!' as status;
SELECT COUNT(*) as total_categories FROM service_categories;
SELECT COUNT(*) as total_providers FROM service_providers;
SELECT COUNT(*) as total_selections FROM customer_provider_selections;
SELECT COUNT(*) as total_notifications FROM notifications; 