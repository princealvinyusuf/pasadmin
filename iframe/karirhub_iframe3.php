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

  /* Card dengan gradient border biru khas Karirhub */
  .card {
    position: relative;
    width: 340px;
    border-radius: 20px;
    padding: 2.5px; /* ketebalan border tipis dan elegan */
    background: linear-gradient(135deg, #388FE8, #4FA3F7, #9CC2FF);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
  }

  .card-content {
    background: #ffffff;
    border-radius: 18px;
    padding: 28px 22px 36px 22px;
    text-align: center;
  }

  .card img {
    display: block;
    width: 220px;
    margin: 0 auto 18px auto;
    filter:
      drop-shadow(2px 2px 3px rgba(0, 0, 0, 0.25))
      drop-shadow(-2px -2px 3px rgba(255, 255, 255, 0.5));
    transition: transform 0.4s ease, filter 0.4s ease;
  }

  .card:hover img {
    transform: scale(1.05);
    filter:
      drop-shadow(3px 3px 6px rgba(0, 0, 0, 0.3))
      drop-shadow(-3px -3px 4px rgba(255, 255, 255, 0.6));
  }

  .card p {
    font-size: 15px;
    line-height: 1.6;
    color: #333;
    margin: 0;
  }

  .card-footer {
    position: absolute;
    bottom: 10px;
    right: 16px;
    font-size: 11px;
    color: #696969;
    font-weight: 500;
    letter-spacing: 0.2px;
  }

  @media (max-width: 480px) {
    .card {
      width: 90%;
    }
    .card img {
      width: 170px;
    }
  }
</style>
</head>
<body>
  <div class="card" onclick="window.open('https://karirhub.kemnaker.go.id/lowongan-dalam-negeri/lowongan', '_blank')">
    <div class="card-content">
      <img src="https://karirhub.kemnaker.go.id/assets/images/logo/products/karirhub-lower.svg"
           alt="Karirhub Kemnaker Logo">
      <p>Portal resmi Kemnaker yang menghubungkan pencari kerja dan pemberi kerja di seluruh Indonesia dalam satu ekosistem tenaga kerja terintegrasi.</p>
      <div class="card-footer">Karirhub oleh Kemnaker</div>
    </div>
  </div>
</body>
</html>