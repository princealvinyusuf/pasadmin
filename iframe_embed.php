<?php
// This page does not require authentication as it's meant to be embedded externally
require_once __DIR__ . '/db.php';

// Get slug from query parameter
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    http_response_code(404);
    die('iFrame not found');
}

// Fetch iframe by slug
$stmt = $conn->prepare("SELECT * FROM iframes WHERE slug = ? AND is_active = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$iframe = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$iframe) {
    http_response_code(404);
    die('iFrame not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($iframe['title']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .iframe-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .logo-container {
            margin-bottom: 30px;
        }
        
        .logo-container img {
            max-width: 200px;
            max-height: 100px;
            height: auto;
            width: auto;
            object-fit: contain;
        }
        
        .title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        
        .description {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .external-link {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .external-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            color: white;
            text-decoration: none;
        }
        
        .external-link:active {
            transform: translateY(0);
        }
        
        @media (max-width: 600px) {
            .iframe-container {
                padding: 30px 20px;
            }
            
            .title {
                font-size: 24px;
            }
            
            .description {
                font-size: 14px;
            }
            
            .external-link {
                padding: 12px 24px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="iframe-container">
        <div class="logo-container">
            <?php if (!empty($iframe['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($iframe['logo_url']); ?>" alt="<?php echo htmlspecialchars($iframe['title']); ?> Logo">
            <?php endif; ?>
        </div>
        
        <h1 class="title"><?php echo htmlspecialchars($iframe['title']); ?></h1>
        
        <?php if (!empty($iframe['description'])): ?>
            <p class="description"><?php echo nl2br(htmlspecialchars($iframe['description'])); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($iframe['external_link'])): ?>
            <a href="<?php echo htmlspecialchars($iframe['external_link']); ?>" class="external-link" target="_blank" rel="noopener noreferrer">
                Visit Website
            </a>
        <?php endif; ?>
    </div>
</body>
</html>

