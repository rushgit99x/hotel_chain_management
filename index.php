<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'templates/header.php';
?>

<section class="header" id="home">
    <nav>
        <div class="nav__bar">
            <div class="logo">
                <a href="index.php"><img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" /></a>
            </div>
            <div class="nav__menu__btn" id="menu-btn">
                <i class="ri-menu-line"></i>
            </div>
            <ul class="nav__links" id="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#service">Services</a></li>
                <li><a href="#explore">Explore</a></li>
                <li><a href="#contact">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] == 'super_admin'): ?>
                        <li><a href="admin_portal.php">Admin Portal</a></li>
                    <?php elseif ($_SESSION['role'] == 'manager'): ?>
                        <li><a href="manager_portal.php">Manager Portal</a></li>
                    <?php else: ?>
                        <li><a href="customer_dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="auth.php?logout=1">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                    <li><a href="superadmin_register.php">Super Admin Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <div class="section__container header__container">
        <p>Simple - Unique - Friendly</p>
        <h1>Make Yourself At Home<br />In Our <span>Hotel</span>.</h1>
    </div>
</section>

<section class="booking">
    <div class="section__container booking__container">
        <form class="booking__form">
            <div class="input__group">
                <label for="check-in">CHECK-IN</label>
                <input type="date" id="check-in" />
            </div>
            <div class="input__group">
                <label for="check-out">CHECK-OUT</label>
                <input type="date" id="check-out" />
            </div>
            <div class="input__group">
                <label for="guest">GUEST</label>
                <input type="number" id="guest" min="1" />
            </div>
            <button type="submit" class="btn">CHECK OUT</button>
        </form>
    </div>
</section>

<section class="section__container about__container" id="about">
    <div class="about__image">
        <img src="/hotel_chain_management/assets/images/about.jpg?v=<?php echo time(); ?>" alt="about" />
    </div>
    <div class="about__content">
        <p class="section__subheader">ABOUT US</p>
        <h2 class="section__header">The Best Holidays Start Here!</h2>
        <p class="section__description">
            With a focus on quality accommodations, personalized experiences, and
            seamless booking, our platform is dedicated to ensuring that every
            traveler embarks on their dream holiday with confidence and excitement.
        </p>
        <a href="#" class="btn about__btn">Read More</a>
    </div>
</section>

<section class="section__container room__container">
    <p class="section__subheader">OUR LIVING ROOM</p>
    <h2 class="section__header">The Most Memorable Rest Time Starts Here.</h2>
    <div class="room__grid">
        <div class="room__card">
            <div class="room__card__image">
                <img src="/hotel_chain_management/assets/images/room-1.jpg?v=<?php echo time(); ?>" alt="room" />
                <div class="room__card__icons">
                    <span><i class="ri-heart-fill"></i></span>
                    <span><i class="ri-paint-fill"></i></span>
                    <span><i class="ri-shield-star-line"></i></span>
                </div>
            </div>
            <div class="room__card__details">
                <h4>Deluxe Ocean View</h4>
                <p>Bask in luxury with breathtaking ocean views from your private suite.</p>
                <h5>Starting from <span>$299/night</span></h5>
                <a href="#" class="btn">Book Now</a>
            </div>
        </div>
        <div class="room__card">
            <div class="room__card__image">
                <img src="/hotel_chain_management/assets/images/room-2.jpg?v=<?php echo time(); ?>" alt="room" />
                <div class="room__card__icons">
                    <span><i class="ri-heart-fill"></i></span>
                    <span><i class="ri-paint-fill"></i></span>
                    <span><i class="ri-shield-star-line"></i></span>
                </div>
            </div>
            <div class="room__card__details">
                <h4>Executive Cityscape Room</h4>
                <p>Experience urban elegance and modern comfort in the heart of the city.</p>
                <h5>Starting from <span>$199/night</span></h5>
                <a href="#" class="btn">Book Now</a>
            </div>
        </div>
        <div class="room__card">
            <div class="room__card__image">
                <img src="/hotel_chain_management/assets/images/room-3.jpg?v=<?php echo time(); ?>" alt="room" />
                <div class="room__card__icons">
                    <span><i class="ri-heart-fill"></i></span>
                    <span><i class="ri-paint-fill"></i></span>
                    <span><i class="ri-shield-star-line"></i></span>
                </div>
            </div>
            <div class="room__card__details">
                <h4>Family Garden Retreat</h4>
                <p>Spacious and inviting, perfect for creating cherished memories with loved ones.</p>
                <h5>Starting from <span>$249/night</span></h5>
                <a href="#" class="btn">Book Now</a>
            </div>
        </div>
    </div>
</section>

<section class="service" id="service">
    <div class="section__container service__container">
        <div class="service__content">
            <p class="section__subheader">SERVICES</p>
            <h2 class="section__header">Strive Only For The Best.</h2>
            <ul class="service__list">
                <li>
                    <span><i class="ri-shield-star-line"></i></span>
                    High Class Security
                </li>
                <li>
                    <span><i class="ri-24-hours-line"></i></span>
                    24 Hours Room Service
                </li>
                <li>
                    <span><i class="ri-headphone-line"></i></span>
                    Conference Room
                </li>
                <li>
                    <span><i class="ri-map-2-line"></i></span>
                    Tourist Guide Support
                </li>
            </ul>
        </div>
    </div>
</section>

<section class="section__container banner__container">
    <div class="banner__content">
        <div class="banner__card">
            <h4>25+</h4>
            <p>Properties Available</p>
        </div>
        <div class="banner__card">
            <h4>350+</h4>
            <p>Bookings Completed</p>
        </div>
        <div class="banner__card">
            <h4>600+</h4>
            <p>Happy Customers</p>
        </div>
    </div>
</section>

<section class="explore" id="explore">
    <p class="section__subheader">EXPLORE</p>
    <h2 class="section__header">What's New Today.</h2>
    <div class="explore__bg">
        <div class="explore__content">
            <p>10th MAR 2023</p>
            <h4>A New Menu Is Available In Our Hotel.</h4>
            <a href="#" class="btn">Continue</a>
        </div>
    </div>
</section>

<section class="section__container footer__container" id="contact">
    <div class="footer__col">
        <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="logo" />
        <p class="section__description">
            Discover a world of comfort, luxury, and adventure as you explore
            our curated selection of hotels, making every moment of your getaway
            truly extraordinary.
        </p>
        <a href="#" class="btn">Book Now</a>
    </div>
    <div class="footer__col">
        <h4>QUICK LINKS</h4>
        <ul class="footer__links">
            <li><a href="#">Browse Destinations</a></li>
            <li><a href="#">Special Offers & Packages</a></li>
            <li><a href="#">Room Types & Amenities</a></li>
            <li><a href="#">Customer Reviews & Ratings</a></li>
            <li><a href="#">Travel Tips & Guides</a></li>
        </ul>
    </div>
    <div class="footer__col">
        <h4>OUR SERVICES</h4>
        <ul class="footer__links">
            <li><a href="#">Concierge Assistance</a></li>
            <li><a href="#">Flexible Booking Options</a></li>
            <li><a href="#">Airport Transfers</a></li>
            <li><a href="#">Wellness & Recreation</a></li>
        </ul>
    </div>
    <div class="footer__col">
        <h4>CONTACT US</h4>
        <ul class="footer__links">
            <li><a href="mailto:info@hotelchain.com">info@hotelchain.com</a></li>
        </ul>
        <div class="footer__socials">
            <a href="#"><img src="/hotel_chain_management/assets/images/facebook.png?v=<?php echo time(); ?>" alt="facebook" /></a>
            <a href="#"><img src="/hotel_chain_management/assets/images/instagram.png?v=<?php echo time(); ?>" alt="instagram" /></a>
            <a href="#"><img src="/hotel_chain_management/assets/images/twitter.png?v=<?php echo time(); ?>" alt="twitter" /></a>
            <a href="#"><img src="/hotel_chain_management/assets/images/linkedin.png?v=<?php echo time(); ?>" alt="linkedin" /></a>
        </div>
    </div>
</section>

<div class="footer__bar">
    Copyright © 2025. All rights reserved.
</div>

<?php include 'templates/footer.php'; ?>