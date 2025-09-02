<?php
// Simple file upload test
echo "<h2>File Upload Test</h2>";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Request Received</h3>";
    
    // Check if files were uploaded
    if (isset($_FILES['test_file'])) {
        echo "<p>✓ FILES array exists</p>";
        echo "<p>Upload error code: " . $_FILES['test_file']['error'] . "</p>";
        
        if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
            echo "<p>✓ File uploaded successfully</p>";
            echo "<p>File name: " . $_FILES['test_file']['name'] . "</p>";
            echo "<p>File size: " . $_FILES['test_file']['size'] . " bytes</p>";
            echo "<p>Temporary file: " . $_FILES['test_file']['tmp_name'] . "</p>";
            
            // Try to move the file
            $upload_dir = __DIR__ . '/paskerid/public/documents/';
            $filename = 'test_' . time() . '.txt';
            $destination = $upload_dir . $filename;
            
            echo "<p>Destination: " . $destination . "</p>";
            echo "<p>Directory exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "</p>";
            echo "<p>Directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";
            
            if (move_uploaded_file($_FILES['test_file']['tmp_name'], $destination)) {
                echo "<p style='color: green;'>✓ File moved successfully to: " . $destination . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to move file</p>";
                echo "<p>Error: " . error_get_last()['message'] . "</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Upload failed with error code: " . $_FILES['test_file']['error'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No files uploaded</p>";
    }
    
    echo "<hr>";
}

// Display PHP upload settings
echo "<h3>PHP Upload Settings</h3>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "</p>";

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/paskerid/public/documents/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "<p>Created directory: " . $upload_dir . "</p>";
} else {
    echo "<p>Directory exists: " . $upload_dir . "</p>";
}

// Set permissions
chmod($upload_dir, 0777);
echo "<p>Directory permissions set to 777</p>";
echo "<p>Directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";
?>

<form method="post" enctype="multipart/form-data">
    <h3>Test File Upload</h3>
    <input type="file" name="test_file" required>
    <br><br>
    <input type="submit" value="Upload Test File">
</form>
