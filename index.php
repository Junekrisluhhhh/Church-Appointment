<?php
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'appointment_system');
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    // Fetch user by email and verify password using password_verify()
    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (isset($user['password']) && password_verify($password, $user['password'])) {
            // authentication success
            $_SESSION['user'] = $user;
            $role = $_SESSION['user']['role'];
            if ($role === 'admin') header('Location: admin_dashboard.php');
            elseif ($role === 'staff') header('Location: staff_dashboard.php');
            else header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password!';
        }
    } else {
        $error = 'Invalid email or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Church Appointment System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

/* BODY */
body {
    min-height: 100vh;
    background: url("/church_appointment/image/church.jpg") center/cover fixed;
    color: #fff;
    display: flex;
    flex-direction: column;
}
body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .35);
    z-index: 0;
}

/* HEADER */
.header {
    text-align: center;
    padding: 2rem;
    font-size: 2.2rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-shadow: 1px 1px 6px rgba(0, 0, 0, 0.7);
    z-index: 1;
}

/* ERROR */
.error {
    max-width: 480px;
    margin: 1rem auto;
    padding: 1rem;
    background: rgba(231, 76, 60, .85);
    border-radius: 10px;
    text-align: center;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .4);
    z-index: 1;
}

/* MAIN CONTAINER */
.main-container {
    display: flex;
    justify-content: flex-end; /* push content to the right side */
    align-items: flex-start;
    gap: 2rem;
    padding: 2rem;
    min-height: 70vh; /* keep gallery below the fold */
    z-index: 1;
}

/* LEFT COLUMN (Books + Gallery) - now sized to act as the info-book column */
.left-column {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    flex: 0 0 340px; /* fixed narrower column */
    max-width: 340px;
    order: 1;
    align-self: flex-start;
}

/* MINI BOOK */
.info-text {
    width: 100%;
    background: rgba(0, 0, 0, .65);
    padding: 2rem;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, .5);
    line-height: 1.7;
    position: relative;
    margin-left: -80px;
}

/* BOOK PAGES */
.info-text {
    width: 420px; /* slightly smaller than the form */
    background: rgba(0, 0, 0, .65);
    padding: 1.6rem;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
    line-height: 1.6;
    position: relative;
    overflow: hidden; /* contain sliding pages */
    min-height: 320px; /* taller than before, similar to form but a bit smaller */
}

.book-page {
    position: absolute;
    inset: 0 0 0 0;
    padding: 0 0 2rem 0;
    opacity: 0;
    transform: translateX(10px);
    transition: transform 0.6s ease, opacity 0.6s ease;
}
.book-page.active {
    opacity: 1;
    transform: translateX(0);
    position: relative;
}

/* BOOK CONTROLS */
.book-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    transition: transform .18s ease, opacity .18s ease;
}
.book-controls button {
    background: rgba(255, 255, 255, .15);
    border: none;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    cursor: pointer;
}
.book-controls button:hover {
    background: rgba(255, 255, 255, .25);
}
#pageIndicator {
    font-size: .8rem;
    opacity: .9;
}

/* hide controls until hover, then "pop" them */
.info-text .book-controls {
    opacity: 0;
    transform: scale(0.92);
    pointer-events: none;
}
.info-text:hover .book-controls {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

/* MINI BOOK ANIMATION */
@keyframes fadeSlide {
    from {opacity: 0; transform: translateX(10px);}
    to {opacity: 1; transform: translateX(0);}
}

/* IMAGE GALLERY BELOW BOOKS */
.image-gallery {
    display: grid;
    grid-template-columns: repeat(2, 1fr); /* 2 images per row */
    gap: 4rem;
    margin-top: 4rem;
}
.image-gallery img {
    width: 100%;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.5);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.image-gallery img:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
}

/* LOGIN FORM ON RIGHT - UPDATED */
.form-container {
    max-width: 400px; /* form remains slightly wider */
    width: 100%;
    background: rgba(0, 0, 0, 0.85); /* slightly darker for contrast */
    padding: 3rem 2.5rem; /* more padding */
    border-radius: 20px; /* bigger, smoother radius */
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.7); /* stronger shadow for depth */
    border: 1px solid rgba(255, 255, 255, 0.15);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: stretch;
    order: 2;
    margin-left: 0;
}

/* FORM FIELDS */
.form-container form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.form-container label {
    font-weight: 600;
    color: #e0e7ff;
    font-size: 1rem;
}
.form-container input {
    width: 100%;
    padding: 1rem 1.2rem; /* bigger input fields */
    border: none;
    border-radius: 14px; /* smoother radius */
    outline: none;
    font-size: 1.05rem;
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.form-container input:focus {
    background: rgba(255, 255, 255, 0.3);
}

/* PASSWORD CONTAINER */
.password-container {
    position: relative;
}
.password-container input {
    padding-right: 3rem; /* room for the toggle icon */
}
.password-container #togglePassword {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.1rem;
    color: #e6eefc;
    cursor: pointer;
    user-select: none;
    z-index: 2;
}

/* BUTTON */
button[type="submit"] {
    padding: 1rem 1.2rem; /* bigger button */
    background: linear-gradient(135deg,#6fc1ff,#1a8cff);
    color: #fff;
    font-size: 1.05rem;
    font-weight: 600;
    border: none;
    border-radius: 16px; /* bigger radius */
    cursor: pointer;
    transition: all 0.3s ease;
}
button[type="submit"]:hover {
    background: linear-gradient(135deg,#1a8cff,#6fc1ff);
    box-shadow: 0 10px 20px rgba(0,0,0,0.4);
}

/* LINKS */
.form-container a {
    display: block;
    text-decoration: none;
    color: #a0d8ff;
    margin-top: .5rem;
    font-size: 0.95rem;
}
.form-container a:hover {
    color: #fff;
}

/* RESPONSIVE ADJUSTMENT */
@media(max-width: 900px) {
    .form-container {
        max-width: 90%;
        padding: 2rem;
        margin: 1rem auto;
    }
    .left-column {
        flex: 1 1 auto;
        max-width: none;
        order: 0;
    }
    .info-text {
        width: 100%;
        min-height: 260px;
    }
}

/* FOOTER */
footer {
    width: 100%;
    background: rgba(0, 0, 0, 0.7); /* semi-transparent dark */
    backdrop-filter: blur(5px); /* soft blur for elegance */
    color: #f1f1f1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem 1rem;
    gap: 1.5rem;
    border-top: 2px solid rgba(255, 255, 255, 0.15);
}

/* CONTACT SECTION */
footer .contact-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
}
footer .contact-section p {
    margin: 0;
    font-size: 0.95rem;
    color: #d1e0ff;
}

/* DEVELOPER CREDITS */
.developer-credits {
    width: 100%;
    max-width: 600px;
    text-align: center;
    padding: 1rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}
.footer-title {
    font-size: 12px;
    color: #aaa;
    margin-bottom: 8px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* DEVELOPER CAROUSEL */
.developer-carousel {
    overflow: hidden;
    position: relative;
    width: 100%;
    height: 28px; /* taller for better visibility */
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
}
.carousel-container {
    display: flex;
    transition: transform 0.5s ease;
}
.carousel-slide {
    min-width: 100%;
    text-align: center;
    font-weight: 500;
    color: #f1f1f1;
    line-height: 28px;
}

/* OPTIONAL HOVER EFFECTS FOR LINKS */
footer a {
    color: #80c8ff;
    text-decoration: none;
    transition: color 0.3s;
}
footer a:hover {
    color: #ffffff;
}

/* RESPONSIVE */
@media(max-width:900px) {
    footer {
        padding: 2rem 1rem;
    }
    .developer-credits {
        padding: 0.8rem 0;
    }
    .developer-carousel {
        height: 24px;
    }
}

</style>
</head>
<body>

<div class="header">Church Appointment System</div>

<?php if(!empty($error)): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="main-container">

    <!-- LEFT COLUMN: BOOK + IMAGE GALLERY -->
    <div class="left-column">

        <!-- MINI BOOK -->
        <div class="info-text book">
            <div class="book-page active">
                <h3>Welcome to St. Therese Church</h3>
                <p>
                    St. Therese Church in Lahug, Cebu City is dedicated to St. Thérèse of the Child Jesus, 
                    affectionately known as “The Little Flower.” The church has been a spiritual haven for 
                    thousands of parishioners, offering a peaceful atmosphere for prayer, reflection, and 
                    community gatherings. Our mission is to nurture faith, provide guidance, and foster 
                    charitable activities that benefit the local community.
                </p>
            </div>
            <div class="book-page">
                <h3>History</h3>
                <p>
                    Established decades ago, St. Therese Church was founded to serve the growing Catholic 
                    population in Lahug. Over the years, it has hosted countless sacraments, celebrations, 
                    and community events. The church architecture combines traditional design with modern 
                    comforts, symbolizing our respect for heritage while embracing contemporary worship needs.
                </p>
            </div>
            <div class="book-page">
                <h3>Services</h3>
                <p>
                    The church provides regular Holy Masses, Baptism, Weddings, Funerals, and Pre-Cana 
                    seminars. We also conduct catechism classes, youth group activities, and spiritual retreats 
                    to strengthen the faith of our parishioners. Each service is guided by compassion, 
                    community, and a commitment to spiritual growth.
                </p>
            </div>
            <div class="book-page">
                <h3>Community Mission</h3>
                <p>
                    Beyond religious activities, St. Therese Church actively participates in community outreach, 
                    charity drives, and educational programs. Our parishioners are encouraged to volunteer, 
                    share their talents, and support local families in need. We believe that faith is best lived 
                    through love and service to others.
                </p>
            </div>
            <div class="book-page">
                <h3>Location Map</h3>
                <iframe src="https://www.google.com/maps?q=St.+Therese+Church+Lahug+Cebu&output=embed" 
                    width="100%" height="220" style="border:none;border-radius:12px;"></iframe>
            </div>

            <div class="book-controls">
                <button type="button" onclick="prevPage()">◀ Prev</button>
                <span id="pageIndicator">1 / 5</span>
                <button type="button" onclick="nextPage()">Next ▶</button>
            </div>
        </div>

    </div>

    <!-- LOGIN FORM -->
    <div class="form-container">
        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" required>
                <i id="togglePassword" class="fa fa-eye"></i>
            </div>
            <button type="submit">Login</button>
            <a href="register.php">Create an account</a>
            <a href="forgot_password.php">Forgot password?</a>
        </form>
    </div>

</div>

<!-- IMAGE GALLERY (moved below main content so user scrolls to it) -->
<div style="padding:2rem; max-width:1100px; margin: 0 auto;">
    <div class="image-gallery" style="margin-top:1.5rem;">
        <img src="/church_appointment/image/night.jpeg" alt="Night View">
        <img src="/church_appointment/image/pader.jpg" alt="Pader">
        <img src="/church_appointment/image/Father.jpg" alt="Father">
        <img src="/church_appointment/image/fafa.jpg" alt="Fafa">
        <img src="/church_appointment/image/mister.jpg" alt="Mister">
        <img src="/church_appointment/image/new.jpg" alt="New">
        <img src="/church_appointment/image/blessing.jpg" alt="Blessing">
        <img src="/church_appointment/image/fathers.jpg" alt="fathers">
    </div>
</div>

<footer>
    <div class="contact-section">
        <p>📍 Edison cor. Pasteur Sts., Lahug, Cebu City</p>
        <p>📞 (032) 233-4964 | 0915 120 1783 (Globe)</p>
        <p>✉️ teresitasanta689@yahoo.com</p>
    </div>
    <div class="developer-credits">
        <div class="footer-title">Developed by</div>
        <div class="developer-carousel">
            <div class="carousel-container" id="carouselTrack">
                <div class="carousel-slide">Rivera Stella Grace</div>
                <div class="carousel-slide">Mangyao June Chrysler</div>
                <div class="carousel-slide">Gensis Nina Carla</div>
                <div class="carousel-slide">Clemenia Reynaldo</div>
                <div class="carousel-slide">Gonzales Jessa</div>
                <div class="carousel-slide">Taguik Jessica</div>
            </div>
        </div>
    </div>
</footer>

<script>
// MINI BOOK SLIDESHOW
let currentPage = 0;
const pages = document.querySelectorAll('.book-page');
const indicator = document.getElementById('pageIndicator');
const infoText = document.querySelector('.info-text');
function showPage(i){
    pages.forEach((p, idx)=>{
        p.classList.toggle('active', idx===i);
    });
    indicator.textContent = `${i+1} / ${pages.length}`;
}
function nextPage(){ currentPage = (currentPage+1) % pages.length; showPage(currentPage); }
function prevPage(){ currentPage = (currentPage-1 + pages.length) % pages.length; showPage(currentPage); }
showPage(currentPage);

// autoplay every 5s, pause on hover
let autoSlide = setInterval(nextPage, 5000);
if (infoText) {
    infoText.addEventListener('mouseenter', ()=>{
        clearInterval(autoSlide);
    });
    infoText.addEventListener('mouseleave', ()=>{
        clearInterval(autoSlide);
        autoSlide = setInterval(nextPage, 5000);
    });
}

// wire prev/next buttons to reset timer when used
const prevBtn = document.querySelector('.book-controls button[onclick="prevPage()"]');
const nextBtn = document.querySelector('.book-controls button[onclick="nextPage()"]');
if (prevBtn) prevBtn.addEventListener('click', ()=>{ prevPage(); clearInterval(autoSlide); autoSlide = setInterval(nextPage,5000); });
if (nextBtn) nextBtn.addEventListener('click', ()=>{ nextPage(); clearInterval(autoSlide); autoSlide = setInterval(nextPage,5000); });

// PASSWORD TOGGLE (swap eye icon)
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        togglePassword.classList.toggle('fa-eye');
        togglePassword.classList.toggle('fa-eye-slash');
    });
}

// DEVELOPER CAROUSEL
let index=0;
const track=document.getElementById('carouselTrack');
const total = track ? track.children.length : 0;
if (track && total) setInterval(()=>{ index=(index+1)%total; track.style.transform=`translateX(-${index*100}%)`; },3000);
</script>
</body>
</html>
