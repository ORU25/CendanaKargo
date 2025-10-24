<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
        />
        <title>Surat Jalan Barang - Full A4 Landscape</title>
        <style>
        /* === RESET === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            margin: 0;
            padding: 0;
        }

        /* === PRINT PAGE SETUP === */
        @page {
            size: A4 landscape;
            margin: 0;
        }

        /* === CONTAINER: dua kolom landscape === */
        .page-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            width: 100%;
            height: 210mm;
            margin: 0;
            padding: 0;
            page-break-after: always;
            background: white;
        }

        /* kolom kanan kosong */
        .empty-column {
            background: white;
            border: none;
        }


        .surat-jalan {
            border: 2px solid black;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 8px;
            background: white;
        }

        /* Header / Logo */
        .header {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }
        .logo img { width: 48px; height: auto; }
        .header-text { text-align: center; flex: 1 1 auto; }
        .header-text h1 {
            font-size: 14px; color: red; font-weight: bold; margin-bottom: 2px;
        }
        .header-text h2 {
            font-size: 12px; color: black; font-weight: bold; text-decoration: underline; margin-bottom: 2px;
        }
        .header-text p { font-size: 11px; color: red; margin: 0; }

        /* form info (tgl / dari / tujuan) */
        .form-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin-top: 6px;
            margin-bottom: 6px;
            font-size: 10px;
            flex: 0 0 auto;
        }
        .form-info label { font-weight: bold; min-width: 40px; }
        .form-info input {
            border: none;
            border-bottom: 1px solid #333;
            padding: 2px;
            font-size: 10px;
        }

        /* TABLE SECTION */
        .table-section {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            margin: 4px 0;
            min-height: 0;
        }

        /* table */
        .surat-jalan table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            height: 100%;
            table-layout: fixed;
        }

        .surat-jalan th {
            border: 2px solid black;
            padding: 6px 4px;
            background: #fff;
            color: black;
            font-weight: bold;
            text-align: left;
        }

        .surat-jalan td {
            border: 1px solid black;
            padding: 6px 4px;
            vertical-align: middle;
        }

        .surat-jalan thead { display: table-header-group; }
        .surat-jalan tbody { display: table-row-group; }

        /* Footer (signature area) */
        .footer-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 6px;
            flex: 0 0 auto;
            font-size: 9px;
        }
        .footer-item { text-align: center; }
        .footer-item label { display: block; font-weight: bold; margin-bottom: 18px; }

        .signature-line {
            border-bottom: 1px solid #333;
            margin: 0 12px;
            padding-top: 10px;
        }

        /* Hide control panel */
        .control-panel-wrapper { display: none; }
        @media print {
            body { margin: 0; padding: 0; background: white; }
            .page-container { height: 210mm; margin: 0; padding: 0; }
            .surat-jalan { padding: 8px; box-sizing: border-box; }
            @page { size: A4 landscape; margin: 0; }
        }
        </style>
    </head>
    <body>
        <!-- control panel (hidden on print) -->
        <div class="control-panel-wrapper">
        <div class="card border-primary" style="margin:8px;">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Pengaturan Data</h5></div>
            <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                <label for="dataCount" class="form-label">Jumlah Data</label>
                <input type="number" class="form-control" id="dataCount" value="1" min="1" max="100" />
                </div>
                <div class="col-md-6">
                <label for="startResi" class="form-label">Nomor Resi Awal</label>
                <input type="number" class="form-control" id="startResi" value="154701" />
                </div>
                <div class="col-md-12 d-flex align-items-end gap-2">
                <button class="btn btn-primary flex-fill" onclick="generateSuratJalan()">Generate</button>
                <button class="btn btn-primary flex-fill" onclick="window.print()">Print</button>
                </div>
            </div>
            </div>
        </div>
        </div>

        <!-- container tempat surat akan dibuat -->
        <div id="container"></div>

        <script>
        function generateSuratJalan() {
            const dataCount = parseInt(document.getElementById('dataCount').value) || 0;
            const startResi = parseInt(document.getElementById('startResi').value) || 154701;
            const container = document.getElementById('container');
            container.innerHTML = '';
            let resiNumber = startResi;

            for (let i = 0; i < dataCount; i++) {
            const pageDiv = document.createElement('div');
            pageDiv.className = 'page-container';

            // surat di kolom kiri
            const suratJalan = createSuratJalan(resiNumber, 18);
            pageDiv.innerHTML = suratJalan;

            // kolom kanan kosong
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'empty-column';
            pageDiv.appendChild(emptyDiv);

            container.appendChild(pageDiv);
            resiNumber++;
            }
        }

        // createSuratJalan: membuat satu kartu surat
        function createSuratJalan(resiNumber, rowCount) {
            let rows = '';
            for (let r = 0; r < rowCount; r++) {
            rows += `<tr><td></td><td></td><td></td><td></td></tr>`;
            }

            return `
            <div class="surat-jalan">
                <div class="header">
                <div class="logo"><img src="../../assets/logo.jpg" alt="Logo"></div>
                <div class="header-text">
                    <h1>PT. CENDANA LINTAS KARGO</h1>
                    <h2>SURAT JALAN BARANG</h2>
                    <p>NO. : ${String(resiNumber).padStart(7, '0')}</p>
                </div>
                </div>

                <div class="form-info">
                <div><label>TGL :</label><input type="text"></div>
                <div><label>DARI :</label><input type="text"></div>
                <div><label>TUJUAN :</label><input type="text"></div>
                </div>

                <div class="table-section">
                <table>
                    <thead>
                    <tr>
                        <th style="width:8%;">NO</th>
                        <th style="width:20%;">NO. RESI</th>
                        <th style="width:52%;">NAMA BARANG</th>
                        <th style="width:20%;">BANYAKNYA</th>
                    </tr>
                    </thead>
                    <tbody>
                    ${rows}
                    </tbody>
                </table>
                </div>

                <div class="footer-section">
                <div class="footer-item">
                    <label>PENGIRIM</label>
                    <div class="signature-line"></div>
                </div>
                <div class="footer-item">
                    <label>SUPIR</label>
                    <div class="signature-line"></div>
                </div>
                <div class="footer-item">
                    <label>PENERIMA</label>
                    <div class="signature-line"></div>
                </div>
                </div>
            </div>
            `;
        }

        generateSuratJalan();
        </script>
    </body>
    </html>