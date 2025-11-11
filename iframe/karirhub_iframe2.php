<?php
// iframe-karirhub.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Karirhub Kemnaker</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Poppins', Arial, sans-serif;
      background: #f9f9f9;
    }

    .iframe-container {
      position: relative;
      width: 100%;
      max-width: 600px;
      margin: 25px auto;
      border-radius: 20px;
      overflow: hidden;
      background: linear-gradient(135deg, #388FE8 0%, #42bba8 60%, #FB7E38 100%);
      padding: 3px;
      cursor: pointer;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .iframe-container:hover {
      transform: translateY(-6px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    }

    .iframe-inner {
      background: linear-gradient(135deg, #ffffff 0%, #f7f9fb 100%);
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 25px;
      gap: 20px;
    }

    /* Logo di kiri dengan efek timbul */
    .iframe-inner img {
      width: 150px;
      height: auto;
      flex-shrink: 0;
      filter:
        drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.25))
        drop-shadow(-2px -2px 4px rgba(255, 255, 255, 0.9));
      transition: transform 0.4s ease, filter 0.4s ease;
    }

    .iframe-container:hover img {
      transform: scale(1.08);
      filter:
        drop-shadow(3px 3px 6px rgba(0, 0, 0, 0.3))
        drop-shadow(-3px -3px 6px rgba(255, 255, 255, 0.95));
    }

    /* Bagian teks di kanan */
    .iframe-text {
      flex: 1;
      text-align: left;
    }

    .iframe-text h3 {
      color: #388FE8;
      margin: 0 0 8px 0;
      font-size: 20px;
      font-weight: 600;
    }

    .iframe-text p {
      color: #444;
      font-size: 14px;
      line-height: 1.6;
      margin: 0;
    }

    @media (max-width: 600px) {
      .iframe-inner {
        flex-direction: column;
        text-align: center;
      }
      .iframe-text {
        text-align: center;
      }
      .iframe-inner img {
        width: 130px;
      }
    }
  </style>
</head>
<body>
  <div
    class="iframe-container"
    onclick="window.open('https://karirhub.kemnaker.go.id/lowongan-dalam-negeri/lowongan', '_blank')"
  >
    <div class="iframe-inner">
      <img
        src="https://karirhub.kemnaker.go.id/assets/images/logo/products/karirhub-lower.svg"
        alt="Karirhub Kemnaker Logo"
      />
      <div class="iframe-text">
        <h3><b>Karirhub Kemnaker</b></h3>
        <p>Portal resmi Kemnaker yang menghubungkan pencari kerja dan pemberi kerja di seluruh Indonesia dalam satu ekosistem tenaga kerja terintegrasi.</p>
      </div>
    </div>
  </div>
</body>
</html>
