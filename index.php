<?php
require_once 'config/database.php';

// Get company info
$company_result = $conn->query("SELECT * FROM company_info LIMIT 1");
$company = $company_result->fetch_assoc();

// Get active services
$services_result = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY id ASC LIMIT 8");

// Get active products (remove duplicates by name, keep first occurrence)
$products_result = $conn->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' 
    AND p.id = (
        SELECT MIN(id) 
        FROM products p2 
        WHERE LOWER(p2.name) = LOWER(p.name)
    )
    ORDER BY p.id DESC 
    LIMIT 8
");

// Get unique categories (remove duplicates by name)
$categories_result = $conn->query("
    SELECT c.* 
    FROM categories c 
    WHERE c.id = (
        SELECT MIN(id) 
        FROM categories c2 
        WHERE LOWER(c2.name) = LOWER(c.name)
    )
    ORDER BY c.name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company['name'] ?? 'Sims-Tech Zambia'); ?> - Your Trusted Technology Partner</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        /* Additional Landing Page Styles */
        .hero-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite;
        }
        
        .particle:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; top: 80%; animation-delay: 2s; }
        .particle:nth-child(3) { left: 30%; top: 40%; animation-delay: 4s; }
        .particle:nth-child(4) { left: 40%; top: 60%; animation-delay: 1s; }
        .particle:nth-child(5) { left: 50%; top: 30%; animation-delay: 3s; }
        .particle:nth-child(6) { left: 60%; top: 70%; animation-delay: 5s; }
        .particle:nth-child(7) { left: 70%; top: 50%; animation-delay: 2.5s; }
        .particle:nth-child(8) { left: 80%; top: 20%; animation-delay: 1.5s; }
        .particle:nth-child(9) { left: 90%; top: 90%; animation-delay: 3.5s; }
        .particle:nth-child(10) { left: 15%; top: 55%; animation-delay: 4.5s; }
        
        .wave-divider {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            overflow: hidden;
            line-height: 0;
        }
        
        .wave-divider svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 80px;
        }
        
        .wave-divider .shape-fill {
            fill: var(--light-bg);
        }
        
        .category-tabs {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .category-tab {
            padding: 0.6rem 1.5rem;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            cursor: pointer;
            transition: all var(--transition-fast);
            font-weight: 500;
        }
        
        .category-tab:hover,
        .category-tab.active {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
        
        .features-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .feature-item {
            text-align: center;
            padding: 2rem;
        }
        
        .feature-item i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .feature-item h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .feature-item p {
            opacity: 0.8;
        }
        
        .cta-section {
            background: var(--light-bg);
            text-align: center;
        }
        
        .cta-box {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            border-radius: var(--border-radius-lg);
            padding: 4rem;
            color: #fff;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .cta-box h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .cta-box p {
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-fast);
            z-index: 99;
        }
        
        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .scroll-top:hover {
            background: var(--secondary-color);
            transform: translateY(-5px);
        }
        
        /* Floating Logos Animation */
        .floating-logos {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }
        
        .floating-logo {
            position: absolute;
            width: 80px;
            height: 80px;
            object-fit: contain;
            opacity: 0.15;
            filter: brightness(1.5);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
        }
        
        .floating-logo-1 {
            left: 5%;
            top: 15%;
            animation: floatLogo1 8s ease-in-out infinite;
            
        }
        
        .floating-logo-2 {
            right: 8%;
            top: 25%;
            animation: floatLogo2 10s ease-in-out infinite;
            width: 70px;
            height: 70px;
        }
        
        .floating-logo-3 {
            left: 15%;
            bottom: 20%;
            animation: floatLogo3 12s ease-in-out infinite;
            width: 90px;
            height: 90px;
        }
        
        .floating-logo-4 {
            right: 12%;
            bottom: 30%;
            animation: floatLogo1 9s ease-in-out infinite reverse;
            width: 65px;
            height: 65px;
        }
        
        .floating-logo-5 {
            left: 50%;
            top: 10%;
            transform: translateX(-50%);
            animation: floatLogo2 11s ease-in-out infinite;
            width: 75px;
            height: 75px;
        }
        
        @keyframes floatLogo1 {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(5deg); }
            50% { transform: translateY(-10px) rotate(-3deg); }
            75% { transform: translateY(-25px) rotate(3deg); }
        }
        
        @keyframes floatLogo2 {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            33% { transform: translateY(-30px) rotate(-5deg) scale(1.05); }
            66% { transform: translateY(-15px) rotate(5deg) scale(0.95); }
        }
        
        @keyframes floatLogo3 {
            0%, 100% { transform: translateX(0) translateY(0); }
            25% { transform: translateX(15px) translateY(-20px); }
            50% { transform: translateX(-10px) translateY(-30px); }
            75% { transform: translateX(20px) translateY(-10px); }
        }
        
        /* Bright Color Buttons */
        .btn-bright-orange {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: #fff;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-bright-orange:hover {
            background: linear-gradient(135deg, #ff8c5a 0%, #ffaa4d 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.6);
        }
        
        .btn-bright-green {
            background: linear-gradient(135deg, #00d084 0%, #00b371 100%);
            color: #fff;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 6px 20px rgba(0, 208, 132, 0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-bright-green:hover {
            background: linear-gradient(135deg, #2de89d 0%, #00d084 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 30px rgba(0, 208, 132, 0.6);
        }
        
        .btn-bright-yellow {
            background: linear-gradient(135deg, #ffd93d 0%, #ffb800 100%);
            color: #1a365d;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 6px 20px rgba(255, 217, 61, 0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            transition: all 0.3s ease;
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        .btn-bright-yellow:hover {
            background: linear-gradient(135deg, #ffe566 0%, #ffd93d 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 30px rgba(255, 217, 61, 0.7);
        }
        
        @keyframes glow {
            from { box-shadow: 0 6px 20px rgba(255, 217, 61, 0.5); }
            to { box-shadow: 0 6px 30px rgba(255, 217, 61, 0.8), 0 0 40px rgba(255, 217, 61, 0.3); }
        }
        
        /* Floating Tech Icons */
        .floating-icon {
            position: absolute;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
        }
        
        .floating-icon-1 {
            left: 8%;
            top: 60%;
            animation: floatIcon1 7s ease-in-out infinite;
        }
        
        .floating-icon-2 {
            right: 15%;
            top: 50%;
            animation: floatIcon2 9s ease-in-out infinite;
            font-size: 20px;
        }
        
        .floating-icon-3 {
            left: 25%;
            top: 35%;
            animation: floatIcon3 11s ease-in-out infinite;
            font-size: 22px;
        }
        
        .floating-icon-4 {
            right: 20%;
            bottom: 25%;
            animation: floatIcon1 8s ease-in-out infinite reverse;
            font-size: 18px;
        }
        
        .floating-icon-5 {
            left: 40%;
            top: 15%;
            animation: floatIcon2 10s ease-in-out infinite;
            font-size: 26px;
        }
        
        .floating-icon-6 {
            right: 35%;
            top: 70%;
            animation: floatIcon3 12s ease-in-out infinite;
            font-size: 20px;
        }
        
        .floating-icon-7 {
            left: 60%;
            bottom: 15%;
            animation: floatIcon1 9s ease-in-out infinite;
            font-size: 22px;
        }
        
        .floating-icon-8 {
            left: 75%;
            top: 40%;
            animation: floatIcon2 8s ease-in-out infinite reverse;
            font-size: 24px;
        }
        
        @keyframes floatIcon1 {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.2; }
            25% { transform: translateY(-15px) rotate(90deg); opacity: 0.4; }
            50% { transform: translateY(-25px) rotate(180deg); opacity: 0.3; }
            75% { transform: translateY(-10px) rotate(270deg); opacity: 0.5; }
        }
        
        @keyframes floatIcon2 {
            0%, 100% { transform: translateX(0) translateY(0) scale(1); opacity: 0.2; }
            33% { transform: translateX(20px) translateY(-20px) scale(1.1); opacity: 0.4; }
            66% { transform: translateX(-15px) translateY(-30px) scale(0.9); opacity: 0.3; }
        }
        
        @keyframes floatIcon3 {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); opacity: 0.2; }
            25% { transform: translateY(-20px) rotate(-45deg) scale(1.05); opacity: 0.5; }
            50% { transform: translateY(-35px) rotate(45deg) scale(1.1); opacity: 0.4; }
            75% { transform: translateY(-15px) rotate(-90deg) scale(0.95); opacity: 0.3; }
        }
        
        /* Bright Yellow Navigation Links */
        .nav-red {
            background: linear-gradient(135deg, #f6e05e 0%, #ecc94b 100%);
            color: #1a202c !important;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(236, 201, 75, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
            animation: pulse 2s infinite;
        }
        
        .nav-red::after {
            display: none;
        }
        
        .nav-red:hover {
            background: linear-gradient(135deg, #faf089 0%, #f6e05e 100%);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 5px 15px rgba(236, 201, 75, 0.6);
            color: #1a202c !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <img src="assets/images/sims-tech-logo.jpg" alt="Sims-Tech Zambia Logo" onerror="this.style.display='none'">
                <span>Sims-Tech Zambia</span>
            </a>
            <ul class="nav-links">
                <li><a href="#home" class="nav-red">Home</a></li>
                <li><a href="#services" class="nav-red">Services</a></li>
                <li><a href="#products" class="nav-red">Products</a></li>
                <li><a href="#about" class="nav-red">About Us</a></li>
                <li><a href="#contact" class="nav-red">Contact</a></li>
                <li><a href="signup.php" class="nav-btn" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);">Sign Up</a></li>
                <li><a href="login.php" class="nav-btn">Login</a></li>
            </ul>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        
        <!-- Floating Logos and Images -->
        <div class="floating-logos">
            <img src="assets/images/sims-tech-logo.jpg" alt="" class="floating-logo floating-logo-1" onerror="this.style.display='none'">
            <img src="assets/images/sims-tech-logo.jpg" alt="" class="floating-logo floating-logo-2" onerror="this.style.display='none'">
            <img src="assets/images/sims-tech-logo.jpg" alt="" class="floating-logo floating-logo-3" onerror="this.style.display='none'">
            <img src="assets/images/sims-tech-logo.jpg" alt="" class="floating-logo floating-logo-4" onerror="this.style.display='none'">
            <img src="assets/images/sims-tech-logo.jpg" alt="" class="floating-logo floating-logo-5" onerror="this.style.display='none'">
            
            <!-- Additional Tech Images -->
            <div class="floating-icon floating-icon-1">
                <i class="fas fa-laptop"></i>
            </div>
            <div class="floating-icon floating-icon-2">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="floating-icon floating-icon-3">
                <i class="fas fa-headphones"></i>
            </div>
            <div class="floating-icon floating-icon-4">
                <i class="fas fa-usb"></i>
            </div>
            <div class="floating-icon floating-icon-5">
                <i class="fas fa-wifi"></i>
            </div>
            <div class="floating-icon floating-icon-6">
                <i class="fas fa-battery-full"></i>
            </div>
            <div class="floating-icon floating-icon-7">
                <i class="fas fa-camera"></i>
            </div>
            <div class="floating-icon floating-icon-8">
                <i class="fas fa-print"></i>
            </div>
        </div>
        
        <div class="hero-content">
            <img src="assets/images/sims-tech-logo.jpg" alt="Sims-Tech Zambia Logo" class="hero-logo" onerror="this.src='https://via.placeholder.com/200x200?text=Sims-Tech'">
            <h1><?php echo htmlspecialchars($company['name'] ?? 'Sims-Tech Zambia'); ?></h1>
            <p><?php echo htmlspecialchars($company['tagline'] ?? 'We sale New and Preowned Laptops from UK and Provide Repair Service for all types of Computers and Phones'); ?></p>
            <div class="hero-buttons">
                <a href="#products" class="btn btn-bright-orange"><i class="fas fa-shopping-cart"></i> Shop Now</a>
                <a href="#services" class="btn btn-bright-green"><i class="fas fa-tools"></i> Our Services</a>
                <a href="signup.php" class="btn btn-bright-yellow"><i class="fas fa-user-plus"></i> Sign Up</a>
                <a href="login.php" class="btn btn-bright-yellow" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><i class="fas fa-sign-in-alt"></i> Login</a>
            </div>
        </div>
        <div class="wave-divider">
            <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V95.8C57.1,118.92,156.63,69.08,321.39,56.44Z" class="shape-fill"></path>
            </svg>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <div class="section-header" data-animate="fadeInUp">
                <h2>Our Services</h2>
                <p>Professional technology services to meet all your needs</p>
            </div>
            <div class="services-grid">
                <?php if ($services_result->num_rows > 0): ?>
                    <?php while ($service = $services_result->fetch_assoc()): ?>
                        <div class="service-card" data-animate="fadeInUp">
                            <?php if (!empty($service['image'])): ?>
                                <img src="uploads/services/<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                            <?php else: ?>
                                <div class="service-icon">
                                    <?php
                                    $icons = [
                                        'Laptop Repair' => 'fa-laptop-medical',
                                        'Phone Repair' => 'fa-mobile-screen',
                                        'Passport Photos' => 'fa-camera',
                                        'Printing Services' => 'fa-print'
                                    ];
                                    $icon = $icons[$service['name']] ?? 'fa-cogs';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p><?php echo htmlspecialchars($service['description']); ?></p>
                            <p class="product-price">K<?php echo number_format($service['price'], 2); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="service-card" data-animate="fadeInUp">
                        <div class="service-icon"><i class="fas fa-laptop-medical"></i></div>
                        <h3>Laptop Repair</h3>
                        <p>Professional laptop repair and maintenance services</p>
                    </div>
                    <div class="service-card" data-animate="fadeInUp">
                        <div class="service-icon"><i class="fas fa-mobile-screen"></i></div>
                        <h3>Phone Repair</h3>
                        <p>Mobile phone screen replacement and repairs</p>
                    </div>
                    <div class="service-card" data-animate="fadeInUp">
                        <div class="service-icon"><i class="fas fa-camera"></i></div>
                        <h3>Passport Photos</h3>
                        <p>Professional passport size photo services</p>
                    </div>
                    <div class="service-card" data-animate="fadeInUp">
                        <div class="service-icon"><i class="fas fa-print"></i></div>
                        <h3>Printing Services</h3>
                        <p>Document printing, scanning and copying</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="features-grid">
                <div class="feature-item" data-animate="fadeInUp">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Quality Guaranteed</h3>
                    <p>All our products come with warranty and quality assurance</p>
                </div>
                <div class="feature-item" data-animate="fadeInUp">
                    <i class="fas fa-truck"></i>
                    <h3>Fast Delivery</h3>
                    <p>Quick and reliable delivery across Zambia</p>
                </div>
                <div class="feature-item" data-animate="fadeInUp">
                    <i class="fas fa-headset"></i>
                    <h3>24/7 Support</h3>
                    <p>Our support team is always ready to help you</p>
                </div>
                <div class="feature-item" data-animate="fadeInUp">
                    <i class="fas fa-tags"></i>
                    <h3>Best Prices</h3>
                    <p>Competitive prices on all products and services</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products">
        <div class="container">
            <div class="section-header" data-animate="fadeInUp">
                <h2>Our Products</h2>
                <p>Quality electronics and accessories for all your needs</p>
            </div>
            
            <div class="category-tabs" data-animate="fadeInUp">
                <span class="category-tab active" data-category="all">All Products</span>
                <?php 
                $categories_result->data_seek(0);
                while ($category = $categories_result->fetch_assoc()): 
                ?>
                    <span class="category-tab" data-category="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></span>
                <?php endwhile; ?>
            </div>
            
            <div class="products-grid">
                <?php if ($products_result->num_rows > 0): ?>
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <div class="product-card" data-animate="fadeInUp" data-category="<?php echo $product['category_id']; ?>">
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-box" style="font-size: 4rem; color: var(--text-secondary);"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <span class="badge badge-info"><?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?></span>
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 80)); ?>...</p>
                                <p class="product-price">K<?php echo number_format($product['price'], 2); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-products" data-animate="fadeInUp" style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-box-open" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 10px;">No Products Available</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 30px;">Check back soon for our latest products and accessories!</p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Products
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" style="background: var(--light-bg);">
        <div class="container">
            <div class="about-content">
                <div class="about-image" data-animate="slideInLeft">
                    <img src="assets/images/sims-tech-logo.jpg" alt="About Sims-Tech Zambia" style="max-width: 400px; margin: 0 auto; display: block;" onerror="this.src='https://via.placeholder.com/400x300?text=Sims-Tech'">
                </div>
                <div class="about-text" data-animate="slideInRight">
                    <h2>About <?php echo htmlspecialchars($company['name'] ?? 'Sims-Tech Zambia'); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($company['about_us'] ?? 'Sims-Tech Zambia is a leading technology solutions provider offering quality electronics, computer accessories, and professional repair services. We are committed to delivering excellent products and services to our valued customers.')); ?></p>
                    <p>We specialize in laptops, power accessories, storage devices, and offer expert repair services for all your electronic devices.</p>
                    <div class="about-features">
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Quality Products</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Expert Technicians</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Fast Service</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Affordable Prices</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-box" data-animate="scaleIn">
                <h2>Ready to Get Started?</h2>
                <p>Visit our shop today or contact us for any inquiries about our products and services.</p>
                <a href="#contact" class="btn btn-primary btn-lg"><i class="fas fa-phone"></i> Contact Us Now</a>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact">
        <div class="container">
            <div class="section-header" data-animate="fadeInUp">
                <h2>Contact Us</h2>
                <p>Get in touch with us for any inquiries</p>
            </div>
            <div class="contact-wrapper">
                <div class="contact-info" data-animate="slideInLeft">
                    <h3>Get In Touch</h3>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Address</h4>
                            <p><?php echo htmlspecialchars($company['address'] ?? 'Lusaka, Zambia'); ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>Phone</h4>
                            <p><?php echo htmlspecialchars($company['phone'] ?? '0979145428'); ?> / <?php echo htmlspecialchars($company['mobile'] ?? '0968745131'); ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p><?php echo htmlspecialchars($company['email'] ?? 'info@actechnology.co.zm'); ?></p>
                        </div>
                    </div>
                    <div class="social-links" style="margin-top: 2rem;">
                        <a href="<?php echo htmlspecialchars($company['facebook'] ?? '#'); ?>"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?php echo htmlspecialchars($company['twitter'] ?? '#'); ?>"><i class="fab fa-twitter"></i></a>
                        <a href="<?php echo htmlspecialchars($company['instagram'] ?? '#'); ?>"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="contact-form" data-animate="slideInRight">
                    <form action="contact_submit.php" method="POST">
                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo htmlspecialchars($company['name'] ?? 'Sims-Tech Zambia'); ?></h3>
                    <p><?php echo htmlspecialchars($company['tagline'] ?? 'We sale New and Preowned Laptops from UK and Provide Repair Service for all types of Computers and Phones'); ?></p>
                    <div class="social-links">
                        <a href="<?php echo htmlspecialchars($company['facebook'] ?? '#'); ?>"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?php echo htmlspecialchars($company['twitter'] ?? '#'); ?>"><i class="fab fa-twitter"></i></a>
                        <a href="<?php echo htmlspecialchars($company['instagram'] ?? '#'); ?>"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#products">Products</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Our Services</h3>
                    <ul class="footer-links">
                        <li><a href="#services">Laptop Repair</a></li>
                        <li><a href="#services">Phone Repair</a></li>
                        <li><a href="#services">Passport Photos</a></li>
                        <li><a href="#services">Printing Services</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['address'] ?? 'Lusaka, Zambia'); ?></li>
                        <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($company['phone'] ?? '0979145428'); ?> / <?php echo htmlspecialchars($company['mobile'] ?? '0968745131'); ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($company['email'] ?? 'info@actechnology.co.zm'); ?></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company['name'] ?? 'Sims-Tech Zambia'); ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="assets/js/main.js"></script>
    <script>
        // Scroll to top functionality
        const scrollTopBtn = document.getElementById('scrollTop');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
        
        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Category filter
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                document.querySelectorAll('.product-card').forEach(card => {
                    if (category === 'all' || card.dataset.category === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
