<?php if(isset($_GET['error']) && $_GET['error'] == 'unauthorized'){
    $type = "danger";
    $message = "You are not authorized to access that page. Please log in with appropriate credentials.";
    include 'components/alert.php';
}?>


<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cendana Lintas Kargo</title>

  <link rel="stylesheet" href="Bootstrap/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
  <link rel="icon" href="assets/favicon.ico" />
</head>

<body>
  <!-- ===== PROGRESS BAR ===== -->
  <div class="scroll-progress"></div>

  <!-- ===== HEADER ===== -->
  <header>
    <div class="container header-content">
      <div class="logo-section">
        <a href="auth/login">
          <img src="assets/logo.jpg" alt="Logo Cendana Lintas Kargo" class="logo" />
        </a>
        <h1>Cendana Lintas Kargo</h1>
      </div>

      <nav>
        <ul class="nav-links">
          <li><a href="#hero" class="active">Beranda</a></li>
          <li><a href="#lacakOngkir">Lacak / Ongkir</a></li>
          <li><a href="#layanan">Layanan Kami</a></li>
          <li><a href="#kontak">Kontak</a></li>
        </ul>
      </nav>

      <!-- Tombol Bahasa -->
      <div class="lang-switch">
        <button id="lang-id" class="active">🇮🇩</button>
        <button id="lang-en">🇬🇧</button>
      </div>
    </div>
  </header>

  <!-- ===== HERO ===== -->
  <section id="hero" class="hero">
    <div class="hero-overlay">
      <div class="hero-text reveal">
        <h2 id="heroTitle">Solusi Pengiriman Cepat, Aman, dan Terpercaya</h2>
        <p id="heroText">Kami melayani pengiriman barang ke seluruh Indonesia dengan tarif bersahabat dan layanan terbaik.</p>
      </div>
    </div>
  </section>

  <!-- ===== LACAK & CEK ONGKIR ===== -->
  <section id="lacakOngkir" class="reveal" style="background:#fff;padding:80px 20px;">
    <div class="container" style="flex-direction:column;align-items:center;">
      <div class="tab-header" style="display:flex;gap:10px;margin-bottom:30px;flex-wrap:wrap;justify-content:center;">
        <button class="tab-btn active" data-tab="lacak">
          <i class="fa-solid fa-truck-fast"></i> Lacak Paket
        </button>
        <button class="tab-btn" data-tab="ongkir">
          <i class="fa-solid fa-calculator"></i> Cek Ongkir
        </button>
      </div>

      <!-- Form Lacak Paket -->
      <div class="tab-content active" id="tab-lacak">
        <div class="form-card">
          <h3><i class="fa-solid fa-truck-fast"></i> Lacak Paket</h3>
          <label for="resi">Nomor Resi</label>
          <input type="text" id="resi" placeholder="Masukkan nomor resi Anda..." />
          <button><i class="fa-solid fa-magnifying-glass"></i> Lacak Paket</button>
        </div>
      </div>

      <!-- Form Cek Ongkir -->
      <div class="tab-content" id="tab-ongkir">
        <div class="form-card">
          <h3><i class="fa-solid fa-calculator"></i> Cek Ongkir</h3>
          <label for="asal">Kota Asal</label>
          <input type="text" id="asal" placeholder="Contoh: Jakarta" />
          <label for="tujuan">Kota Tujuan</label>
          <input type="text" id="tujuan" placeholder="Contoh: Surabaya" />
          <label for="berat">Berat (Kg)</label>
          <input type="number" id="berat" placeholder="Contoh: 2" />
          <button><i class="fa-solid fa-calculator"></i> Hitung Ongkir</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== WHY US ===== -->
  <section class="why-us reveal">
    <div class="container-narrow">
      <h2 id="whyTitle">Mengapa Memilih Cendana Lintas Kargo?</h2>
      <p id="whyText">
        Kami berkomitmen memberikan layanan terbaik dengan pengiriman tepat waktu,
        sistem pelacakan canggih, serta tarif transparan dan bersahabat untuk seluruh pelanggan kami.
      </p>
    </div>
  </section>

  <!-- ===== LAYANAN ===== -->
  <section id="layanan" class="services reveal">
    <h2 id="layananTitle">Layanan Kami</h2>
    <div class="service-container">
      <div class="service-card">
        <div class="card-inner">
          <div class="card-front">
            <i class="fa-solid fa-bolt fa-3x" style="color: var(--accent-yellow); margin-bottom: 14px;"></i>
            <h3 id="layanan1">Pengiriman Cepat</h3>
          </div>
          <div class="card-back">
            <p id="layanan1desc">Barang Anda dikirim dengan estimasi waktu akurat dan pengantaran cepat serta aman.</p>
          </div>
        </div>
      </div>

      <div class="service-card">
        <div class="card-inner">
          <div class="card-front">
            <i class="fa-solid fa-shield-halved fa-3x" style="color: var(--accent-yellow); margin-bottom: 14px;"></i>
            <h3 id="layanan2">Aman & Terpercaya</h3>
          </div>
          <div class="card-back">
            <p id="layanan2desc">Keamanan paket Anda menjadi prioritas kami dengan sistem tracking real-time.</p>
          </div>
        </div>
      </div>

      <div class="service-card">
        <div class="card-inner">
          <div class="card-front">
            <i class="fa-solid fa-coins fa-3x" style="color: var(--accent-yellow); margin-bottom: 14px;"></i>
            <h3 id="layanan3">Tarif Terjangkau</h3>
          </div>
          <div class="card-back">
            <p id="layanan3desc">Nikmati tarif pengiriman hemat tanpa mengorbankan kualitas layanan kami.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== CTA ===== -->
  <section class="cta">
    <div class="cta-content">
      <h2 id="ctaTitle">Kirim Barang Sekarang Bersama Kami!</h2>
      <p id="ctaText">Keamanan, kecepatan, dan kepuasan pelanggan adalah prioritas utama kami.</p>
    </div>
  </section>

  <!-- ===== FOOTER ===== -->
  <footer id="kontak">
    <div class="footer-container">
      <div class="footer-about reveal">
        <h3>Cendana Lintas Kargo</h3>
        <p>Partner logistik terpercaya untuk setiap pengiriman Anda, cepat, aman, dan hemat.</p>
      </div>
      <div class="footer-links reveal">
        <h4>Navigasi</h4>
        <ul>
          <li><a href="#hero">Beranda</a></li>
          <li><a href="#lacakOngkir">Lacak / Ongkir</a></li>
          <li><a href="#layanan">Layanan</a></li>
          <li><a href="#kontak">Kontak</a></li>
        </ul>
      </div>
      <div class="footer-contact reveal">
        <h4>Hubungi Kami</h4>
        <p><i class="fa-solid fa-envelope"></i> info@cendanakargo.com</p>
        <p><i class="fa-solid fa-phone"></i> (0541) 123456</p>
        <p><i class="fa-solid fa-location-dot"></i> Jl. Cendana Raya No. 88, Samarinda</p>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© 2025 Cendana Lintas Kargo. Semua Hak Dilindungi.</p>
    </div>
  </footer>

  <!-- ===== BACK TO TOP ===== -->
  <button id="backToTop"><i class="fa-solid fa-arrow-up"></i></button>

  <!-- ===== JAVASCRIPT ===== -->
  <script>
    // Scroll progress bar
    window.addEventListener('scroll', () => {
      const scrollTop = window.scrollY;
      const docHeight = document.body.scrollHeight - window.innerHeight;
      const progress = (scrollTop / docHeight) * 100;
      document.querySelector('.scroll-progress').style.width = progress + '%';
    });

    // Back to top
    const backToTop = document.getElementById('backToTop');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) backToTop.classList.add('show');
      else backToTop.classList.remove('show');
    });
    backToTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // Reveal animation
    const reveals = document.querySelectorAll('.reveal');
    function revealOnScroll() {
      const windowH = window.innerHeight;
      for (let el of reveals) {
        const top = el.getBoundingClientRect().top;
        if (top < windowH - 120) el.classList.add('active');
        else el.classList.remove('active');
      }
    }
    window.addEventListener('scroll', revealOnScroll);
    revealOnScroll();

    // Navbar shrink
    window.addEventListener('scroll', () => {
      const header = document.querySelector('header');
      if (window.scrollY > 60) header.classList.add('scrolled');
      else header.classList.remove('scrolled');
    });

    // Ambil semua link navbar
    const navLinks = document.querySelectorAll('.nav-links a');

    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        // Hapus active di semua
        navLinks.forEach(l => l.classList.remove('active'));
        // Tambah active di yang diklik
        link.classList.add('active');
      });
    });

    // Tab switch (Lacak Paket / Cek Ongkir)
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        tabContents.forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
      });
    });

    // Bilingual system
    const translations = {
      id: {
        heroTitle: "Solusi Pengiriman Cepat, Aman, dan Terpercaya",
        heroText: "Kami melayani pengiriman barang ke seluruh Indonesia dengan tarif bersahabat dan layanan terbaik.",
        whyTitle: "Mengapa Memilih Cendana Lintas Kargo?",
        whyText: "Kami berkomitmen memberikan layanan terbaik dengan pengiriman tepat waktu, sistem pelacakan canggih, serta tarif transparan dan bersahabat untuk seluruh pelanggan kami.",
        layananTitle: "Layanan Kami",
        layanan1: "Pengiriman Cepat",
        layanan1desc: "Barang Anda dikirim dengan estimasi waktu akurat dan pengantaran cepat serta aman.",
        layanan2: "Aman & Terpercaya",
        layanan2desc: "Keamanan paket Anda menjadi prioritas kami dengan sistem tracking real-time.",
        layanan3: "Tarif Terjangkau",
        layanan3desc: "Nikmati tarif pengiriman hemat tanpa mengorbankan kualitas layanan kami.",
        ctaTitle: "Kirim Barang Sekarang Bersama Kami!",
        ctaText: "Keamanan, kecepatan, dan kepuasan pelanggan adalah prioritas utama kami."
      },
      en: {
        heroTitle: "Fast, Safe, and Reliable Shipping Solutions",
        heroText: "We deliver goods across Indonesia with affordable rates and trusted service.",
        whyTitle: "Why Choose Cendana Lintas Kargo?",
        whyText: "We provide on-time delivery, advanced tracking systems, and transparent rates for all customers.",
        layananTitle: "Our Services",
        layanan1: "Fast Delivery",
        layanan1desc: "Your goods are shipped quickly and safely with accurate estimates.",
        layanan2: "Secure & Trusted",
        layanan2desc: "Your package safety is our top priority with real-time tracking.",
        layanan3: "Affordable Rates",
        layanan3desc: "Enjoy low-cost delivery without sacrificing quality.",
        ctaTitle: "Ship With Us Now!",
        ctaText: "Security, speed, and satisfaction are our top priorities."
      }
    };

    function changeLanguage(lang) {
      const t = translations[lang];
      for (let key in t) {
        const el = document.getElementById(key);
        if (el) el.textContent = t[key];
      }
    }

    document.getElementById("lang-id").addEventListener("click", () => {
      changeLanguage("id");
      document.getElementById("lang-id").classList.add("active");
      document.getElementById("lang-en").classList.remove("active");
    });
    document.getElementById("lang-en").addEventListener("click", () => {
      changeLanguage("en");
      document.getElementById("lang-en").classList.add("active");
      document.getElementById("lang-id").classList.remove("active");
    });
  </script>
  
<?php
    include 'templates/footer.php';
?>