<?php
// iframe-karirhub.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            max-width: 460px; /* ✅ sedikit dilebarkan agar logo besar tetap proporsional */
            margin: 25px auto;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #388FE8, #42bba8, #FB7E38);
            padding: 3px;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .iframe-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }

        .iframe-inner {
            background: white;
            border-radius: 18px;
            text-align: center;
            padding: 35px 24px; /* ✅ tambahan ruang untuk logo besar */
        }

        /* ✨ Logo Karirhub diperbesar dengan efek timbul */
        .iframe-inner img {
            width: 300px; /* ✅ diperbesar dari 120px menjadi 180px */
            height: auto;
            margin-bottom: 20px;
            filter:
                drop-shadow(3px 3px 5px rgba(0, 0, 0, 0.35))
                drop-shadow(-3px -3px 5px rgba(255, 255, 255, 0.6));
            transition: transform 0.4s ease, filter 0.4s ease;
        }

        /* Efek saat hover */
        .iframe-container:hover img {
            transform: scale(1.07);
            filter:
                drop-shadow(4px 4px 6px rgba(0, 0, 0, 0.35))
                drop-shadow(-3px -3px 6px rgba(255, 255, 255, 0.75));
        }

        .iframe-inner p {
            color: #555;
            font-size: 15px;
            line-height: 1.7;
            margin-top: 10px;
            max-width: 360px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 480px) {
            .iframe-container {
                max-width: 90%;
            }
            .iframe-inner {
                padding: 28px 18px;
            }
            .iframe-inner img {
                width: 180px; /* tetap proporsional di mobile */
            }
        }
    </style>
</head>
<body>
    <div class="iframe-container" onclick="window.open('https://karirhub.kemnaker.go.id/lowongan-dalam-negeri/lowongan', '_blank')">
        <div class="iframe-inner">
            <img src="https://karirhub.kemnaker.go.id/assets/images/logo/products/karirhub-lower.svg" alt="Karirhub Kemnaker Logo">
            <p><b>Wujudkan karier impianmu bersama Karirhub Kemnaker.</b></p>
        </div>
    </div>
</body>
</html>
