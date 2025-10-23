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
          <button id="btnLacak"><i class="fa-solid fa-magnifying-glass"></i> Lacak Paket</button>
          
          <!-- Alert message -->
          <div id="alertLacak" style="display:none; margin-top:20px; padding:15px; border-radius:8px; font-size:14px;"></div>
          
          <!-- Result display -->
          <div id="resultLacak" style="display:none; margin-top:30px; background:#f8f9fa; padding:25px; border-radius:12px; border-left:4px solid var(--primary-green);">
            <h4 style="margin-bottom:20px; color:var(--primary-green); font-size:18px;">
              <i class="fa-solid fa-circle-check"></i> Informasi Pengiriman
            </h4>
            <table style="width:100%; border-collapse: collapse;">
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057; width:40%;">No. Resi</td>
                <td style="padding:12px 0; color:#212529;" id="displayResi">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Nama Pengirim</td>
                <td style="padding:12px 0; color:#212529;" id="displayPengirim">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Nama Penerima</td>
                <td style="padding:12px 0; color:#212529;" id="displayPenerima">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Asal</td>
                <td style="padding:12px 0; color:#212529;" id="displayAsal">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Tujuan</td>
                <td style="padding:12px 0; color:#212529;" id="displayTujuan">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Total Tarif</td>
                <td style="padding:12px 0; color:#212529; font-weight:600;" id="displayTarif">-</td>
              </tr>
              <tr>
                <td style="padding:12px 0; font-weight:600; color:#495057;">Status</td>
                <td style="padding:12px 0;" id="displayStatus">
                  <span style="padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; text-transform: uppercase;">-</span>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Form Cek Ongkir -->
      <div class="tab-content" id="tab-ongkir">
        <div class="form-card">
          <h3><i class="fa-solid fa-calculator"></i> Cek Ongkir</h3>
          <label for="cabangAsal">Cabang Asal</label>
          <select id="cabangAsal" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:14px; margin-bottom:15px;">
            <option value="">-- Pilih Cabang Asal --</option>
          </select>
          <label for="cabangTujuan">Cabang Tujuan</label>
          <select id="cabangTujuan" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:14px; margin-bottom:15px;">
            <option value="">-- Pilih Cabang Tujuan --</option>
          </select>
          <label for="beratBarang">Berat (Kg)</label>
          <input type="number" id="beratBarang" placeholder="Contoh: 2" step="0.1" min="0.1" />
          <button id="btnHitungOngkir"><i class="fa-solid fa-calculator"></i> Hitung Ongkir</button>
          
          <!-- Alert message -->
          <div id="alertOngkir" style="display:none; margin-top:20px; padding:15px; border-radius:8px; font-size:14px;"></div>
          
          <!-- Result display -->
          <div id="resultOngkir" style="display:none; margin-top:30px; background:#f8f9fa; padding:25px; border-radius:12px; border-left:4px solid var(--primary-green);">
            <h4 style="margin-bottom:20px; color:var(--primary-green); font-size:18px;">
              <i class="fa-solid fa-circle-check"></i> Hasil Perhitungan Ongkir
            </h4>
            <table style="width:100%; border-collapse: collapse;">
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057; width:40%;">Dari</td>
                <td style="padding:12px 0; color:#212529;" id="displayAsalOngkir">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Ke</td>
                <td style="padding:12px 0; color:#212529;" id="displayTujuanOngkir">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Berat Barang</td>
                <td style="padding:12px 0; color:#212529;" id="displayBeratOngkir">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Tarif Dasar</td>
                <td style="padding:12px 0; color:#212529;" id="displayTarifDasar">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Batas Berat Dasar</td>
                <td style="padding:12px 0; color:#212529;" id="displayBatasBerat">-</td>
              </tr>
              <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px 0; font-weight:600; color:#495057;">Tarif Tambahan/Kg</td>
                <td style="padding:12px 0; color:#212529;" id="displayTarifTambahan">-</td>
              </tr>
              <tr>
                <td style="padding:12px 0; font-weight:600; color:#495057; font-size:16px;">Total Ongkir</td>
                <td style="padding:12px 0; color:var(--primary-green); font-weight:700; font-size:20px;" id="displayTotalOngkir">-</td>
              </tr>
            </table>
          </div>
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

    // Track package functionality
    const btnLacak = document.getElementById('btnLacak');
    const inputResi = document.getElementById('resi');
    const alertLacak = document.getElementById('alertLacak');
    const resultLacak = document.getElementById('resultLacak');

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
      statusElement.textContent = data.status;
      
      // Set status color based on status value using switch case
      let backgroundColor, textColor;
      
      switch(data.status.toLowerCase()) {
        case 'dalam proses':
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
        case 'selesai':
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
      
      resultLacak.style.display = 'block';
    }

    // Track button click event
    btnLacak.addEventListener('click', function() {
      const noResi = inputResi.value.trim();
      
      // Hide previous results
      hideAlert();
      resultLacak.style.display = 'none';
      
      if (!noResi) {
        showAlert('Nomor resi tidak boleh kosong', 'error');
        return;
      }
      
      // Show loading state
      btnLacak.disabled = true;
      btnLacak.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari...';
      
      // Send AJAX request
      fetch('utils/cekResi.php?no_resi=' + encodeURIComponent(noResi))
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            hideAlert();
            displayResult(data.data);
          } else {
            showAlert(data.message, 'error');
          }
        })
        .catch(error => {
          showAlert('Terjadi kesalahan saat melacak paket. Silakan coba lagi.', 'error');
          console.error('Error:', error);
        })
        .finally(() => {
          btnLacak.disabled = false;
          btnLacak.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Lacak Paket';
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
        showAlertOngkir('Silakan pilih cabang asal', 'error');
        return;
      }
      
      if (!idTujuan) {
        showAlertOngkir('Silakan pilih cabang tujuan', 'error');
        return;
      }
      
      if (!berat || parseFloat(berat) <= 0) {
        showAlertOngkir('Berat harus lebih dari 0 kg', 'error');
        return;
      }
      
      // Show loading state
      btnHitungOngkir.disabled = true;
      btnHitungOngkir.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menghitung...';
      
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
          showAlertOngkir('Terjadi kesalahan saat menghitung ongkir. Silakan coba lagi.', 'error');
          console.error('Error:', error);
        })
        .finally(() => {
          btnHitungOngkir.disabled = false;
          btnHitungOngkir.innerHTML = '<i class="fa-solid fa-calculator"></i> Hitung Ongkir';
        });
    });

    // Allow Enter key to trigger calculation
    beratBarang.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        btnHitungOngkir.click();
      }
    });
  </script>
  
<?php
    include 'templates/footer.php';
?>