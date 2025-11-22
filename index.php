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
  
  <!-- Cloudflare Turnstile CAPTCHA -->
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>

<body>
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
          <li><a href="#hero" class="active" id="navBeranda">Beranda</a></li>
          <li><a href="#lacakOngkir" id="navLacakOngkir">Lacak / Ongkir</a></li>
          <li><a href="#layanan" id="navLayanan">Layanan Kami</a></li>
          <li><a href="#kontak" id="navKontak">Kontak</a></li>
        </ul>
      </nav>

      <!-- Tombol Bahasa -->
      <div class="lang-switch">
        <button id="lang-id" class="active">🇮🇩</button>
        <button id="lang-en">EN</button>
      </div>
    </div>
  </header>

  <!-- ===== HERO SLIDER ===== -->
  <section id="hero" class="hero-slider">
    <!-- Background Slider -->
    <div class="slider-container">
      <div class="slide active"></div>
      <div class="slide"></div>
      <div class="slide"></div>
    </div>
    
    <!-- Static Overlay -->
    <div class="hero-overlay"></div>
    
    <!-- Static Content -->
    <div class="hero-content">
      <h2 id="heroTitle">Solusi Pengiriman Cepat, Aman, dan Terpercaya</h2>
      <p id="heroText">Kami melayani pengiriman barang ke seluruh Indonesia dengan tarif bersahabat dan layanan terbaik.</p>
    </div>
    
    <!-- Dots Indicator -->
    <div class="slider-dots">
      <span class="dot active" onclick="currentSlide(0)"></span>
      <span class="dot" onclick="currentSlide(1)"></span>
      <span class="dot" onclick="currentSlide(2)"></span>
    </div>
  </section>

  <!-- ===== LACAK & CEK ONGKIR ===== -->
  <section id="lacakOngkir" class="reveal" style="background:#fff;padding:80px 20px;">
    <div class="container" style="flex-direction:column;align-items:center;">
      <div class="tab-header" style="display:flex;gap:10px;margin-bottom:30px;flex-wrap:wrap;justify-content:center;">
        <button class="tab-btn active" data-tab="lacak">
          <i class="fa-solid fa-truck-fast"></i> <span id="tabLacak">Lacak Paket</span>
        </button>
        <button class="tab-btn" data-tab="ongkir">
          <i class="fa-solid fa-calculator"></i> <span id="tabOngkir">Cek Ongkir</span>
        </button>
      </div>

      <!-- Form Lacak Paket -->
      <div class="tab-content active" id="tab-lacak">
        <div class="form-card">
          <h3><i class="fa-solid fa-truck-fast"></i> <span id="headingLacak">Lacak Paket</span></h3>
          <label for="resi" id="labelResi">Nomor Resi</label>
          <input type="text" id="resi" placeholder="Masukkan nomor resi Anda..." />
          
          <!-- Cloudflare Turnstile Widget -->
          <div style="margin: 20px 0; display: flex; justify-content: center;">
            <div class="cf-turnstile" 
                 data-sitekey="0x4AAAAAACAg1S8rwiBokGwN" 
                 data-callback="onTurnstileSuccess"
                 data-theme="light"></div>
          </div>
          
          <button id="btnLacak" disabled style="opacity: 0.5; cursor: not-allowed;"><i class="fa-solid fa-magnifying-glass"></i> <span id="btnLacakText">Lacak Paket</span></button>
          
          <!-- Alert message -->
          <div id="alertLacak" style="display:none; margin-top:20px; padding:15px; border-radius:8px; font-size:14px;"></div>
          
          <!-- Result display -->
          <div id="resultLacak" style="display:none; margin-top:30px; max-width:900px; margin-left:auto; margin-right:auto;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
              <!-- Detail Pengiriman -->
              <div style="background:#f8f9fa; padding:25px; border-radius:12px; border-left:4px solid var(--primary-green);">
                <h4 style="margin-bottom:20px; color:var(--primary-green); font-size:18px;">
                  <i class="fa-solid fa-circle-check"></i> <span id="infoLacak">Informasi Pengiriman</span>
                </h4>
                <table style="width:100%; border-collapse: collapse;">
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px 0; font-weight:600; color:#495057; width:40%;" id="labelNoResi">No. Resi</td>
                    <td style="padding:12px 0; color:#212529;" id="displayResi">-</td>
                  </tr>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelPengirim">Nama Pengirim</td>
                    <td style="padding:12px 0; color:#212529;" id="displayPengirim">-</td>
                  </tr>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelPenerima">Nama Penerima</td>
                    <td style="padding:12px 0; color:#212529;" id="displayPenerima">-</td>
                  </tr>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelAsal">Asal</td>
                    <td style="padding:12px 0; color:#212529;" id="displayAsal">-</td>
                  </tr>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelTujuan">Tujuan</td>
                    <td style="padding:12px 0; color:#212529;" id="displayTujuan">-</td>
                  </tr>
                  <tr style="border-bottom:1px solid #dee2e6;">
                    <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelTotalTarif">Total Tarif</td>
                    <td style="padding:12px 0; color:#212529; font-weight:600;" id="displayTarif">-</td>
                  </tr>
                  <tr>
                    <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelStatus">Status</td>
                    <td style="padding:12px 0;" id="displayStatus">
                      <span style="padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; text-transform: uppercase;">-</span>
                    </td>
                  </tr>
                </table>
              </div>
              
              <!-- Google Maps Lokasi Tujuan -->
              <div style="background:#f8f9fa; padding:25px; border-radius:12px; border-left:4px solid var(--primary-green);">
                <h4 style="margin-bottom:15px; color:var(--primary-green); font-size:18px;">
                  <i class="fa-solid fa-location-dot"></i> Lokasi Cabang Tujuan
                </h4>
                <div id="mapInfo" style="margin-bottom:10px; padding:10px; background:#fff; border-radius:8px; border:1px solid #dee2e6;">
                  <p style="margin:0; font-size:14px; color:#495057;"><strong id="displayTujuanMap">-</strong></p>
                  <p style="margin:5px 0 0 0; font-size:13px; color:#6c757d;" id="displayAlamatTujuan">-</p>
                  <p style="margin:5px 0 0 0; font-size:13px; color:#6c757d;">
                    <i class="fa-solid fa-phone"></i> <span id="displayTelpTujuan">-</span>
                  </p>
                </div>
                <iframe 
                  id="googleMap"
                  width="100%" 
                  height="300" 
                  style="border:0; border-radius:8px;" 
                  loading="lazy" 
                  allowfullscreen
                  referrerpolicy="no-referrer-when-downgrade">
                </iframe>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Form Cek Ongkir -->
      <div class="tab-content" id="tab-ongkir">
        <div class="form-card">
          <h3><i class="fa-solid fa-calculator"></i> <span id="headingOngkir">Cek Ongkir</span></h3>
          <label for="cabangAsal" id="labelCabangAsal">Cabang Asal</label>
          <select id="cabangAsal" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:14px; margin-bottom:15px;">
            <option value="">-- Pilih Cabang Asal --</option>
          </select>
          <label for="cabangTujuan" id="labelCabangTujuan">Cabang Tujuan</label>
          <select id="cabangTujuan" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:14px; margin-bottom:15px;">
            <option value="">-- Pilih Cabang Tujuan --</option>
          </select>
          <label for="beratBarang" id="labelBerat">Berat (Kg)</label>
          <input type="number" id="beratBarang" placeholder="Contoh: 2" step="0.1" min="0.1" />
          <button id="btnHitungOngkir"><i class="fa-solid fa-calculator"></i> <span id="btnHitungText">Hitung Ongkir</span></button>
          
          <!-- Alert message -->
          <div id="alertOngkir" style="display:none; margin-top:20px; padding:15px; border-radius:8px; font-size:14px;"></div>
          
          <!-- Result display -->
          <div id="resultOngkir" style="display:none; margin-top:30px; background:#f8f9fa; padding:25px; border-radius:12px; border-left:4px solid var(--primary-green);">
            <h4 style="margin-bottom:20px; color:var(--primary-green); font-size:18px;">
              <i class="fa-solid fa-circle-check"></i> <span id="infoOngkir">Hasil Perhitungan Ongkir</span>
            </h4>
            <table style="width:100%; border-collapse: collapse;">
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057; width:40%;" id="labelDari">Dari</td>
                <td style="padding:12px 0; color:#212529;" id="displayAsalOngkir">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelKe">Ke</td>
                <td style="padding:12px 0; color:#212529;" id="displayTujuanOngkir">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelBeratBarang">Berat Barang</td>
                <td style="padding:12px 0; color:#212529;" id="displayBeratOngkir">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelTarifDasar">Tarif Dasar</td>
                <td style="padding:12px 0; color:#212529;" id="displayTarifDasar">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelBatasBerat">Batas Berat Dasar</td>
                <td style="padding:12px 0; color:#212529;" id="displayBatasBerat">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;" id="labelTarifTambahan">Tarif Tambahan/Kg</td>
                <td style="padding:12px 0; color:#212529;" id="displayTarifTambahan">-</td>
              </tr>
              <tr>
                <td style="padding:12px 0; font-weight:600; color:#495057; font-size:16px;" id="labelTotalOngkir">Total Ongkir</td>
                <td style="padding:12px 0; color:var(--primary-green); font-weight:700; font-size:20px;" id="displayTotalOngkir">-</td>
              </tr>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== WHY US ===== -->
  <section class="why-us reveal" id="layanan">
    <div class="container-narrow">
      <h2 id="whyTitle">Mengapa Memilih Cendana Lintas Kargo?</h2>
      <p id="whyText">
        Kami berkomitmen memberikan layanan terbaik dengan pengiriman tepat waktu,
        sistem pelacakan canggih, serta tarif transparan dan bersahabat untuk seluruh pelanggan kami.
      </p>
    </div>
  </section>

  <!-- ===== LAYANAN ===== -->
  <section class="services reveal">
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

  <!-- ===== FOOTER / KONTAK ===== -->
  <footer id="kontak">
    <div class="footer-container">
      <div class="footer-brand">
        <img src="assets/clk.png" alt="Logo Cendana Lintas Kargo" class="footer-logo" />
        <div>
          <h3>Cendana Lintas Kargo</h3>
          <p id="footerDesc">Partner logistik terpercaya untuk setiap pengiriman Anda, cepat, aman, dan hemat.</p>
        </div>
      </div>

      <div class="footer-contact">
        <h4 id="footerContactTitle">Hubungi Kami</h4>
        <p>📧 info@cendanakargo.com</p>
        <p>📞 (0541) 123456</p>
        <p>📍<span id="footerAddress">Jl. Cendana No. 88, Samarinda</span></p>
      </div>
    </div>
    <div class="footer-bottom">
      <span id="footerCopyright">© 2025 Cendana Lintas Kargo. Semua Hak Dilindungi.</span>
    </div>
  </footer>

  <!-- ===== JAVASCRIPT ===== -->
  <script>
    // ========== REVEAL ANIMATION ==========
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

    // ========== NAVBAR SHRINK ==========
    window.addEventListener('scroll', () => {
      const header = document.querySelector('header');
      if (window.scrollY > 60) header.classList.add('scrolled');
      else header.classList.remove('scrolled');
    });

    // ========== NAVBAR ACTIVE & SCROLL SPY ==========
    const navLinks = document.querySelectorAll('.nav-links a');

    // Click handler
    navLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
      });
    });

// ========== NAVBAR ACTIVE & SCROLL SPY ==========
    // Pastikan navLinks sudah diambil di atas: const navLinks = document.querySelectorAll('.nav-links a');

    // Click handler (Tinggalkan ini, tapi kita akan prioritaskan ScrollSpy)
    navLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        // Kode ini memastikan link aktif saat diklik (walaupun sebentar)
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
      });
    });

    // Scroll spy - auto update active nav
    function updateActiveNav() {
        // 1. Ambil semua section DAN footer yang punya ID
        // Pastikan Anda menangkap ID section layanan yang bergerak: #layanan-slider
        const allSections = document.querySelectorAll('section[id], footer[id]'); 
        const scrollY = window.scrollY;
        
        // Offset penyesuaian (tinggi header/nav)
        const offset = 120; 
        
        // Hapus kelas 'active' dari semua link di awal
        navLinks.forEach(link => link.classList.remove('active'));

        // Dapatkan total tinggi yang dapat di-scroll
        const scrollHeight = document.body.scrollHeight - window.innerHeight;

        // 2. Cek jika user di paling bawah (untuk #kontak)
        if (scrollY >= scrollHeight - 10) {
            // Asumsi tautan ke kontak punya ID 'navKontak'
            const kontakLink = document.querySelector(`.nav-links a[href="#kontak"]`);
            if (kontakLink) kontakLink.classList.add('active');
            return; 
        }
        
        let foundActive = false;

        // 3. Cek semua section
        allSections.forEach(section => {
            const sectionTop = section.offsetTop - offset;
            const sectionHeight = section.offsetHeight;
            let sectionId = section.getAttribute('id'); // ID asli section
            
            // Perbaiki ID untuk tautan navbar (Layanan Kami)
            // Jika ID section adalah 'layanan-slider' atau 'layanan', gunakan ID '#layanan' untuk navigasi
            if (sectionId === 'layanan-slider' || sectionId === 'layanan') {
                sectionId = 'layanan'; 
            }
            // Catatan: Asumsi ID section Hero Anda adalah 'hero' atau 'beranda'

            // Jika scroll berada di dalam jangkauan section
            if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) {
                // Cari tautan navbar yang sesuai dengan ID yang sudah disesuaikan
                const activeLink = document.querySelector(`.nav-links a[href="#${sectionId}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                    foundActive = true;
                }
            }
        });
        
        // 4. Jika di paling atas dan belum ada yang aktif, aktifkan Beranda
        if (scrollY < offset || !foundActive) {
            // Asumsi tautan ke beranda punya ID 'navBeranda' atau href="#beranda"
            const berandaLink = document.querySelector(`.nav-links a[href="#beranda"]`);
            if (berandaLink) berandaLink.classList.add('active');
        }
    }

    // Listener
    window.addEventListener('scroll', updateActiveNav);
    updateActiveNav();

    // ========== TAB SWITCH (LACAK/ONGKIR) ==========
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

    // ========== HERO SLIDER ==========
    let currentSlideIndex = 0;
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    let autoSlideInterval;

    function showSlide(index) {
      slides.forEach((slide, i) => {
        slide.classList.remove('active');
        dots[i].classList.remove('active');
      });
      
      if (index >= slides.length) {
        currentSlideIndex = 0;
      } else if (index < 0) {
        currentSlideIndex = slides.length - 1;
      } else {
        currentSlideIndex = index;
      }
      
      slides[currentSlideIndex].classList.add('active');
      dots[currentSlideIndex].classList.add('active');
    }

    function changeSlide(direction) {
      showSlide(currentSlideIndex + direction);
      resetAutoSlide();
    }

    function currentSlide(index) {
      showSlide(index);
      resetAutoSlide();
    }

    function autoSlide() {
      showSlide(currentSlideIndex + 1);
    }

    function startAutoSlide() {
      autoSlideInterval = setInterval(autoSlide, 5000); // Change slide every 5 seconds
    }

    function resetAutoSlide() {
      clearInterval(autoSlideInterval);
      startAutoSlide();
    }

    // Start auto-slide on page load
    startAutoSlide();

    // Pause auto-slide on hover
    const heroSlider = document.querySelector('.hero-slider');
    heroSlider.addEventListener('mouseenter', () => {
      clearInterval(autoSlideInterval);
    });
    heroSlider.addEventListener('mouseleave', () => {
      startAutoSlide();
    });

    // ========== BILINGUAL SYSTEM ==========
    const translations = {
      id: {
        navBeranda: "Beranda",
        navLacakOngkir: "Lacak / Ongkir",
        navLayanan: "Layanan Kami",
        navKontak: "Kontak",
        heroTitle: "Solusi Pengiriman Cepat, Aman, dan Terpercaya",
        heroText: "Kami melayani pengiriman barang ke seluruh Indonesia dengan tarif bersahabat dan layanan terbaik.",
        tabLacak: "Lacak Paket",
        tabOngkir: "Cek Ongkir",
        headingLacak: "Lacak Paket",
        labelResi: "Nomor Resi",
        btnLacakText: "Lacak Paket",
        infoLacak: "Informasi Pengiriman",
        labelNoResi: "No. Resi",
        labelPengirim: "Nama Pengirim",
        labelPenerima: "Nama Penerima",
        labelAsal: "Asal",
        labelTujuan: "Tujuan",
        labelTotalTarif: "Total Tarif",
        labelStatus: "Status",
        headingOngkir: "Cek Ongkir",
        labelCabangAsal: "Cabang Asal",
        labelCabangTujuan: "Cabang Tujuan",
        labelBerat: "Berat (Kg)",
        btnHitungText: "Hitung Ongkir",
        infoOngkir: "Hasil Perhitungan Ongkir",
        labelDari: "Dari",
        labelKe: "Ke",
        labelBeratBarang: "Berat Barang",
        labelTarifDasar: "Tarif Dasar",
        labelBatasBerat: "Batas Berat Dasar",
        labelTarifTambahan: "Tarif Tambahan/Kg",
        labelTotalOngkir: "Total Ongkir",
        whyTitle: "Mengapa Memilih Cendana Lintas Kargo?",
        whyText: "Kami berkomitmen memberikan layanan terbaik dengan pengiriman tepat waktu, sistem pelacakan canggih, serta tarif transparan dan bersahabat untuk seluruh pelanggan kami.",
        layanan1: "Pengiriman Cepat",
        layanan1desc: "Barang Anda dikirim dengan estimasi waktu akurat dan pengantaran cepat serta aman.",
        layanan2: "Aman & Terpercaya",
        layanan2desc: "Keamanan paket Anda menjadi prioritas kami dengan sistem tracking real-time.",
        layanan3: "Tarif Terjangkau",
        layanan3desc: "Nikmati tarif pengiriman hemat tanpa mengorbankan kualitas layanan kami.",
        ctaTitle: "Kirim Barang Sekarang Bersama Kami!",
        ctaText: "Keamanan, kecepatan, dan kepuasan pelanggan adalah prioritas utama kami.",
        alertCaptchaRequired: "Silakan selesaikan verifikasi CAPTCHA terlebih dahulu",
        alertResiKosong: "Nomor resi tidak boleh kosong",
        alertResiNotFound: "Nomor resi tidak ditemukan",
        alertResiError: "Terjadi kesalahan saat melacak paket. Silakan coba lagi.",
        alertSearching: "Mencari...",
        alertAsalKosong: "Silakan pilih cabang asal",
        alertTujuanKosong: "Silakan pilih cabang tujuan",
        alertCabangSama: "Cabang asal dan tujuan tidak boleh sama",
        alertBeratKosong: "Berat harus lebih dari 0 kg",
        alertOngkirError: "Terjadi kesalahan saat menghitung ongkir. Silakan coba lagi.",
        alertCalculating: "Menghitung...",
        statusDalamProses: "Dalam Proses",
        statusDalamPengiriman: "Dalam Pengiriman",
        statusSampaiTujuan: "Sampai Tujuan",
        statusSelesai: "Selesai",
        statusDibatalkan: "Dibatalkan",
        footerDesc: "Partner logistik terpercaya untuk setiap pengiriman Anda, cepat, aman, dan hemat.",
        footerContactTitle: "Hubungi Kami",
        footerAddress: "Jl. Cendana No. 88, Samarinda",
        footerCopyright: "© 2025 Cendana Lintas Kargo. Semua Hak Dilindungi.",
        btnWhatsApp: "Chat Admin"
      },
      en: {
        navBeranda: "Home",
        navLacakOngkir: "Track / Shipping Cost",
        navLayanan: "Our Services",
        navKontak: "Contact",
        heroTitle: "Fast, Safe, and Reliable Shipping Solutions",
        heroText: "We deliver goods across Indonesia with affordable rates and trusted service.",
        tabLacak: "Track Package",
        tabOngkir: "Shipping Cost",
        headingLacak: "Track Package",
        labelResi: "Tracking Number",
        btnLacakText: "Track Package",
        infoLacak: "Shipment Information",
        labelNoResi: "Tracking No.",
        labelPengirim: "Sender Name",
        labelPenerima: "Receiver Name",
        labelAsal: "Origin",
        labelTujuan: "Destination",
        labelTotalTarif: "Total Cost",
        labelStatus: "Status",
        headingOngkir: "Shipping Cost",
        labelCabangAsal: "Origin Branch",
        labelCabangTujuan: "Destination Branch",
        labelBerat: "Weight (Kg)",
        btnHitungText: "Calculate Cost",
        infoOngkir: "Shipping Cost Result",
        labelDari: "From",
        labelKe: "To",
        labelBeratBarang: "Package Weight",
        labelTarifDasar: "Base Rate",
        labelBatasBerat: "Base Weight Limit",
        labelTarifTambahan: "Additional Rate/Kg",
        labelTotalOngkir: "Total Cost",
        whyTitle: "Why Choose Cendana Lintas Kargo?",
        whyText: "We provide on-time delivery, advanced tracking systems, and transparent rates for all customers.",
        layanan1: "Fast Delivery",
        layanan1desc: "Your goods are shipped quickly and safely with accurate estimates.",
        layanan2: "Secure & Trusted",
        layanan2desc: "Your package safety is our top priority with real-time tracking.",
        layanan3: "Affordable Rates",
        layanan3desc: "Enjoy low-cost delivery without sacrificing quality.",
        ctaTitle: "Ship With Us Now!",
        ctaText: "Security, speed, and satisfaction are our top priorities.",
        alertCaptchaRequired: "Please complete the CAPTCHA verification first",
        alertResiKosong: "Tracking number cannot be empty",
        alertResiNotFound: "Tracking number not found",
        alertResiError: "An error occurred while tracking the package. Please try again.",
        alertResiNotFound: "Nomor resi tidak ditemukan",
        alertResiError: "Terjadi kesalahan saat melacak paket. Silakan coba lagi.",
        alertSearching: "Mencari...",
        // Alert Messages - Cek Ongkir
        alertAsalKosong: "Silakan pilih cabang asal",
        alertTujuanKosong: "Silakan pilih cabang tujuan",
        alertCabangSama: "Cabang asal dan tujuan tidak boleh sama",
        alertBeratKosong: "Berat harus lebih dari 0 kg",
        alertOngkirError: "Terjadi kesalahan saat menghitung ongkir. Silakan coba lagi.",
        alertCalculating: "Menghitung...",
        // Status Tracking
        statusDalamProses: "Dalam Proses",
        statusDalamPengiriman: "Dalam Pengiriman",
        statusSampaiTujuan: "Sampai Tujuan",
        statusSelesai: "Selesai",
        statusDibatalkan: "Dibatalkan",
        // Footer
        footerDesc: "Partner logistik terpercaya untuk setiap pengiriman Anda, cepat, aman, dan hemat.",
        footerContactTitle: "Hubungi Kami",
        footerAddress: "Jl. Cendana No. 88, Samarinda",
        footerCopyright: "© 2025 Cendana Lintas Kargo. Semua Hak Dilindungi.",
        btnWhatsApp: "Chat Admin"
      },
      en: {
        // Navbar
        navBeranda: "Home",
        navLacakOngkir: "Track / Shipping Cost",
        navLayanan: "Our Services",
        navKontak: "Contact",
        // Hero
        heroTitle: "Fast, Safe, and Reliable Shipping Solutions",
        heroText: "We deliver goods across Indonesia with affordable rates and trusted service.",
        // Tab
        tabLacak: "Track Package",
        tabOngkir: "Shipping Cost",
        // Form Lacak Paket
        headingLacak: "Track Package",
        labelResi: "Tracking Number",
        btnLacakText: "Track Package",
        infoLacak: "Shipment Information",
        labelNoResi: "Tracking No.",
        labelPengirim: "Sender Name",
        labelPenerima: "Receiver Name",
        labelAsal: "Origin",
        labelTujuan: "Destination",
        labelTotalTarif: "Total Cost",
        labelStatus: "Status",
        // Form Cek Ongkir
        headingOngkir: "Shipping Cost",
        labelCabangAsal: "Origin Branch",
        labelCabangTujuan: "Destination Branch",
        labelBerat: "Weight (Kg)",
        btnHitungText: "Calculate Cost",
        infoOngkir: "Shipping Cost Result",
        labelDari: "From",
        labelKe: "To",
        labelBeratBarang: "Package Weight",
        labelTarifDasar: "Base Rate",
        labelBatasBerat: "Base Weight Limit",
        labelTarifTambahan: "Additional Rate/Kg",
        labelTotalOngkir: "Total Cost",
        // Why Us
        whyTitle: "Why Choose Cendana Lintas Kargo?",
        whyText: "We provide on-time delivery, advanced tracking systems, and transparent rates for all customers.",
        // Layanan
        layanan1: "Fast Delivery",
        layanan1desc: "Your goods are shipped quickly and safely with accurate estimates.",
        layanan2: "Secure & Trusted",
        layanan2desc: "Your package safety is our top priority with real-time tracking.",
        layanan3: "Affordable Rates",
        layanan3desc: "Enjoy low-cost delivery without sacrificing quality.",
        // CTA
        ctaTitle: "Ship With Us Now!",
        ctaText: "Security, speed, and satisfaction are our top priorities.",
        // Alert Messages - Lacak Resi
        alertCaptchaRequired: "Please complete the CAPTCHA verification first",
        alertResiKosong: "Tracking number cannot be empty",
        alertResiNotFound: "Tracking number not found",
        alertResiError: "An error occurred while tracking the package. Please try again.",
        alertSearching: "Searching...",
        // Alert Messages - Cek Ongkir
        alertAsalKosong: "Please select origin branch",
        alertTujuanKosong: "Please select destination branch",
        alertCabangSama: "Origin and destination branches cannot be the same",
        alertBeratKosong: "Weight must be greater than 0 kg",
        alertOngkirError: "An error occurred while calculating shipping cost. Please try again.",
        alertCalculating: "Calculating...",
        // Status Tracking
        statusDalamProses: "In Process",
        statusDalamPengiriman: "In Transit",
        statusSampaiTujuan: "Arrived",
        statusSelesai: "Completed",
        statusDibatalkan: "Cancelled",
        // Footer
        footerDesc: "Your trusted logistics partner for every delivery, fast, safe, and affordable.",
        footerContactTitle: "Contact Us",
        footerAddress: "Jl. Cendana No. 88, Samarinda",
        footerCopyright: "© 2025 Cendana Lintas Kargo. All Rights Reserved.",
        btnWhatsApp: "Chat Admin"
      }
    };

    // Current language
    let currentLang = 'id';

    function changeLanguage(lang) {
      currentLang = lang;
      const t = translations[lang];
      for (let key in t) {
        const el = document.getElementById(key);
        if (el) el.textContent = t[key];
      }
    }

    function getCurrentTranslation(key) {
      return translations[currentLang][key] || key;
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

    // Track package functionality
    const btnLacak = document.getElementById('btnLacak');
    const inputResi = document.getElementById('resi');
    const alertLacak = document.getElementById('alertLacak');
    const resultLacak = document.getElementById('resultLacak');
    
    // Cloudflare Turnstile verification
    let isCaptchaVerified = false;
    let captchaToken = '';
    
    // Callback when Turnstile verification succeeds
    window.onTurnstileSuccess = function(token) {
      isCaptchaVerified = true;
      captchaToken = token;
      btnLacak.disabled = false;
      btnLacak.style.opacity = '1';
      btnLacak.style.cursor = 'pointer';
      
      // Hide verification message
      const verifyMsg = btnLacak.nextElementSibling;
      if (verifyMsg && verifyMsg.tagName === 'P') {
        verifyMsg.style.display = 'none';
      }
    };

    // Function to show alert
    function showAlert(message, type) {
      alertLacak.style.display = 'block';
      alertLacak.textContent = message;
      if (type === 'error') {
        alertLacak.style.backgroundColor = '#f8d7da';
        alertLacak.style.color = '#721c24';
        alertLacak.style.border = '1px solid #f5c6cb';
      } else if (type === 'success') {
        alertLacak.style.backgroundColor = '#d4edda';
        alertLacak.style.color = '#155724';
        alertLacak.style.border = '1px solid #c3e6cb';
      }
    }

    // Function to hide alert
    function hideAlert() {
      alertLacak.style.display = 'none';
    }

    // Function to display result
    function displayResult(data) {
      document.getElementById('displayResi').textContent = data.no_resi;
      document.getElementById('displayPengirim').textContent = data.nama_pengirim;
      document.getElementById('displayPenerima').textContent = data.nama_penerima;
      document.getElementById('displayAsal').textContent = data.asal;
      document.getElementById('displayTujuan').textContent = data.tujuan;
      document.getElementById('displayTarif').textContent = 'Rp ' + data.total_tarif;
      
      const statusElement = document.getElementById('displayStatus').querySelector('span');
      
      // Translate status based on current language
      let translatedStatus = data.status;
      switch(data.status.toLowerCase()) {
        case 'bkd':
          translatedStatus = getCurrentTranslation('statusDalamProses');
          break;
        case 'dalam pengiriman':
          translatedStatus = getCurrentTranslation('statusDalamPengiriman');
          break;
        case 'sampai tujuan':
          translatedStatus = getCurrentTranslation('statusSampaiTujuan');
          break;
        case 'pod':
          translatedStatus = getCurrentTranslation('statusSelesai');
          break;
        case 'dibatalkan':
          translatedStatus = getCurrentTranslation('statusDibatalkan');
          break;
      }
      
      statusElement.textContent = translatedStatus;
      
      // Set status color based on status value using switch case
      let backgroundColor, textColor;
      
      switch(data.status.toLowerCase()) {
        case 'bkd':
          // warning - kuning
          backgroundColor = '#fff3cd';
          textColor = '#856404';
          break;
        case 'dalam pengiriman':
          // primary - biru
          backgroundColor = '#cce5ff';
          textColor = '#004085';
          break;
        case 'sampai tujuan':
          // info - cyan/biru muda
          backgroundColor = '#d1ecf1';
          textColor = '#0c5460';
          break;
        case 'pod':
          // success - hijau
          backgroundColor = '#d4edda';
          textColor = '#155724';
          break;
        case 'dibatalkan':
          // danger - merah
          backgroundColor = '#f8d7da';
          textColor = '#721c24';
          break;
        default:
          // default - abu-abu
          backgroundColor = '#e2e3e5';
          textColor = '#383d41';
      }
      
      statusElement.style.backgroundColor = backgroundColor;
      statusElement.style.color = textColor;
      
      // Display destination branch info and map
      document.getElementById('displayTujuanMap').textContent = data.tujuan;
      document.getElementById('displayAlamatTujuan').textContent = data.alamat_tujuan || 'Alamat tidak tersedia';
      document.getElementById('displayTelpTujuan').textContent = data.telp_tujuan || '-';
      
      // Set Google Maps iframe source
      const alamatEncoded = encodeURIComponent(data.alamat_tujuan || data.tujuan);
      // Alternative: Using iframe
      const mapUrlNoAPI = `https://maps.google.com/maps?q=${alamatEncoded}&output=embed`;
      
      document.getElementById('googleMap').src = mapUrlNoAPI;
      
      resultLacak.style.display = 'block';
    }

    // Track button click event
    btnLacak.addEventListener('click', function() {
      const noResi = inputResi.value.trim();
      
      // Hide previous results
      hideAlert();
      resultLacak.style.display = 'none';
      
      // Check CAPTCHA verification
      if (!isCaptchaVerified) {
        showAlert(getCurrentTranslation('alertCaptchaRequired'), 'error');
        return;
      }
      
      if (!noResi) {
        showAlert(getCurrentTranslation('alertResiKosong'), 'error');
        return;
      }
      
      // Show loading state
      btnLacak.disabled = true;
      btnLacak.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + getCurrentTranslation('alertSearching');
      
      // Send AJAX request with CAPTCHA token
      fetch('utils/cekResi.php?no_resi=' + encodeURIComponent(noResi) + '&captcha_token=' + encodeURIComponent(captchaToken))
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            hideAlert();
            displayResult(data.data);
          } else {
            // Translate error message based on error code
            let errorMessage = data.message;
            if (data.error_code === 'RESI_NOT_FOUND') {
              errorMessage = getCurrentTranslation('alertResiNotFound');
            }
            showAlert(errorMessage, 'error');
          }
        })
        .catch(error => {
          showAlert(getCurrentTranslation('alertResiError'), 'error');
          console.error('Error:', error);
        })
        .finally(() => {
          // Reset CAPTCHA for next search
          isCaptchaVerified = false;
          captchaToken = '';
          btnLacak.disabled = true;
          btnLacak.style.opacity = '0.5';
          btnLacak.style.cursor = 'not-allowed';
          btnLacak.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> ' + getCurrentTranslation('btnLacakText');
          
          // Reset Cloudflare Turnstile widget
          if (typeof turnstile !== 'undefined') {
            turnstile.reset();
          }
        });
    });

    // Allow Enter key to trigger search
    inputResi.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        btnLacak.click();
      }
    });

    // ===== CEK ONGKIR FUNCTIONALITY =====
    const btnHitungOngkir = document.getElementById('btnHitungOngkir');
    const cabangAsal = document.getElementById('cabangAsal');
    const cabangTujuan = document.getElementById('cabangTujuan');
    const beratBarang = document.getElementById('beratBarang');
    const alertOngkir = document.getElementById('alertOngkir');
    const resultOngkir = document.getElementById('resultOngkir');

    // Function to show alert for ongkir
    function showAlertOngkir(message, type) {
      alertOngkir.style.display = 'block';
      alertOngkir.textContent = message;
      if (type === 'error') {
        alertOngkir.style.backgroundColor = '#f8d7da';
        alertOngkir.style.color = '#721c24';
        alertOngkir.style.border = '1px solid #f5c6cb';
      } else if (type === 'success') {
        alertOngkir.style.backgroundColor = '#d4edda';
        alertOngkir.style.color = '#155724';
        alertOngkir.style.border = '1px solid #c3e6cb';
      }
    }

    // Function to hide alert for ongkir
    function hideAlertOngkir() {
      alertOngkir.style.display = 'none';
    }

    // Function to load branches
    function loadBranches() {
      fetch('utils/cekOngkir.php?action=get_branches')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Clear existing options except the first one
            cabangAsal.innerHTML = '<option value="">-- Pilih Cabang Asal --</option>';
            cabangTujuan.innerHTML = '<option value="">-- Pilih Cabang Tujuan --</option>';
            
            // Add branches to both dropdowns
            data.data.forEach(branch => {
              const optionAsal = document.createElement('option');
              optionAsal.value = branch.id;
              optionAsal.textContent = branch.nama + ' (' + branch.kode + ')';
              cabangAsal.appendChild(optionAsal);
              
              const optionTujuan = document.createElement('option');
              optionTujuan.value = branch.id;
              optionTujuan.textContent = branch.nama + ' (' + branch.kode + ')';
              cabangTujuan.appendChild(optionTujuan);
            });
          }
        })
        .catch(error => {
          console.error('Error loading branches:', error);
        });
    }

    // Load branches when page loads
    loadBranches();

    // Function to display ongkir result
    function displayOngkirResult(data) {
      document.getElementById('displayAsalOngkir').textContent = data.cabang_asal;
      document.getElementById('displayTujuanOngkir').textContent = data.cabang_tujuan;
      document.getElementById('displayBeratOngkir').textContent = data.berat + ' Kg';
      document.getElementById('displayTarifDasar').textContent = 'Rp ' + data.tarif_dasar;
      document.getElementById('displayBatasBerat').textContent = data.batas_berat_dasar + ' Kg';
      document.getElementById('displayTarifTambahan').textContent = 'Rp ' + data.tarif_tambahan_perkg;
      document.getElementById('displayTotalOngkir').textContent = 'Rp ' + data.total_tarif;
      
      resultOngkir.style.display = 'block';
    }

    // Calculate button click event
    btnHitungOngkir.addEventListener('click', function() {
      const idAsal = cabangAsal.value;
      const idTujuan = cabangTujuan.value;
      const berat = beratBarang.value;
      
      // Hide previous results
      hideAlertOngkir();
      resultOngkir.style.display = 'none';
      
      // Validation
      if (!idAsal) {
        showAlertOngkir(getCurrentTranslation('alertAsalKosong'), 'error');
        return;
      }
      
      if (!idTujuan) {
        showAlertOngkir(getCurrentTranslation('alertTujuanKosong'), 'error');
        return;
      }
      
      if (idAsal === idTujuan) {
        showAlertOngkir(getCurrentTranslation('alertCabangSama'), 'error');
        return;
      }
      
      if (!berat || parseFloat(berat) <= 0) {
        showAlertOngkir(getCurrentTranslation('alertBeratKosong'), 'error');
        return;
      }
      
      // Show loading state
      btnHitungOngkir.disabled = true;
      btnHitungOngkir.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + getCurrentTranslation('alertCalculating');
      
      // Send AJAX request
      const url = `utils/cekOngkir.php?action=calculate&id_cabang_asal=${idAsal}&id_cabang_tujuan=${idTujuan}&berat=${berat}`;
      
      fetch(url)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            hideAlertOngkir();
            displayOngkirResult(data.data);
          } else {
            showAlertOngkir(data.message, 'error');
          }
        })
        .catch(error => {
          showAlertOngkir(getCurrentTranslation('alertOngkirError'), 'error');
          console.error('Error:', error);
        })
        .finally(() => {
          btnHitungOngkir.disabled = false;
          btnHitungOngkir.innerHTML = '<i class="fa-solid fa-calculator"></i> ' + getCurrentTranslation('btnHitungText');
        });
    });

    // Allow Enter key to trigger calculation
    beratBarang.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        btnHitungOngkir.click();
      }
    });
  </script>
</body>
</html>
