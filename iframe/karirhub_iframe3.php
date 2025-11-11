<?php
// iframe-karirhub.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Karirhub Kemnaker Card</title>
<style>
  body {
    margin: 0;
    padding: 0;
    font-family: 'Poppins', Arial, sans-serif;
    background: transparent;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
  }

  .card {
    position: relative;
    width: 320px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    padding: 28px 24px 36px 24px; /* extra space for footer */
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
  }

  .card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  }

  .card img {
    display: block;
    width: 230px; /* logo besar */
    margin: 0 auto 20px auto;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));
    transition: transform 0.4s ease;
  }

  .card:hover img {
    transform: scale(1.05);
  }

  .card p {
    font-size: 15px;
    line-height: 1.6;
    color: #444;
    margin: 0;
  }

  /* Footer kecil di kanan bawah */
  .card-footer {
    position: absolute;
    bottom: 10px;
    right: 16px;
    font-size: 11px;
    color: #888;
    font-weight: 500;
  }

  @media (max-width: 480px) {
    .card {
      width: 90%;
      padding: 24px 18px 32px 18px;
    }
    .card img {
      width: 180px;
    }
  }
</style>
</head>
<body>
  <div class="card"
       onclick="window.open('https://karirhub.kemnaker.go.id/lowongan-dalam-negeri/lowongan', '_blank')">
    <img src="https://karirhub.kemnaker.go.id/assets/images/logo/products/karirhub-lower.svg"
         alt="Karirhub Logo">

    <p>Portal resmi Kemnaker yang menghubungkan pencari kerja dan pemberi kerja di seluruh Indonesia dalam satu ekosistem tenaga kerja terintegrasi.</p>

    <div class="card-footer">Karirhub oleh Kemnaker</div>
  </div>
</body>
</html>
