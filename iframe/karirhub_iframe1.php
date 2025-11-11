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
            max-width: 420px;
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
            padding: 25px 20px;
        }

        /* ✨ Efek timbul pada logo SVG */
        .iframe-inner img {
            width: 120px;
            height: auto;
            margin-bottom: 15px;
            filter:
                drop-shadow(2px 2px 3px rgba(0, 0, 0, 0.3)) /* bayangan bawah */
                drop-shadow(-2px -2px 3px rgba(255, 255, 255, 0.6)); /* sorotan atas */
            transition: transform 0.4s ease, filter 0.4s ease;
        }

        /* Saat hover — logo tampak lebih timbul */
        .iframe-container:hover img {
            transform: scale(1.05);
            filter:
                drop-shadow(3px 3px 5px rgba(0, 0, 0, 0.35))
                drop-shadow(-3px -3px 4px rgba(255, 255, 255, 0.7));
        }

        .iframe-inner h3 {
            color: #388FE8;
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .iframe-inner p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 8px;
        }

        @media (max-width: 480px) {
            .iframe-container {
                max-width: 90%;
            }
            .iframe-inner img {
                width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="iframe-container" onclick="window.open('https://karirhub.kemnaker.go.id/lowongan-dalam-negeri/lowongan', '_blank')">
        <div class="iframe-inner">
            <img src="https://karirhub.kemnaker.go.id/assets/images/logo/products/karirhub-lower.svg" alt="Karirhub Kemnaker Logo">
            <!--<h3>Karirhub Kemnaker</h3>-->
            <p>Portal resmi Kemnaker yang menghubungkan pencari kerja dan pemberi kerja di seluruh Indonesia dalam satu ekosistem tenaga kerja terintegrasi.</p>
        </div>
    </div>
</body>
</html>
