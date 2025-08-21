<?php
// Handle form submission
$result = null;
$failedList = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numbers = array_filter(array_map('trim', explode("\n", $_POST['numbers'])));
    $receiverNames = array_map('trim', explode("\n", $_POST['receiverName']));
    $jobPosition = $_POST['jobPosition'];
    $dateAndTime = $_POST['dateAndTime'];
    $location = $_POST['location'];
    $linkOne = $_POST['linkOne'];
    $linkTwo = $_POST['linkTwo'];
    $messageBody = $_POST['messageBody'];

    $broadcast = [];
    foreach ($numbers as $i => $number) {
        $name = isset($receiverNames[$i]) ? $receiverNames[$i] : '';
        $msg = str_replace(
            [
                '{$variable_receiverName}', '{variable_receiverName}',
                '{$variable_jobPosition}', '{variable_jobPosition}',
                '{$dateAndTime}', '{dateAndTime}',
                '{$location}', '{location}',
                '{$linkOne}', '{linkOne}',
                '{$linkTwo}', '{linkTwo}'
            ],
            [
                $name, $name,
                $jobPosition, $jobPosition,
                $dateAndTime, $dateAndTime,
                $location, $location,
                $linkOne, $linkOne,
                $linkTwo, $linkTwo
            ],
            $messageBody
        );
        $broadcast[] = [
            'number' => $number,
            'receiver_name' => $name,
            'message' => $msg
        ];
    }
    // Write to broadcast.json
    file_put_contents(__DIR__ . '/broadcast.json', json_encode($broadcast, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Run send_broadcast.js
    $output = [];
    $cmd = 'cd ' . escapeshellarg(__DIR__ . '/whatsapp-sender') . ' && LD_LIBRARY_PATH= node send_broadcast.js 2>&1';
    exec($cmd, $output, $exitCode);
    $result = [
        'output' => $output,
        'exitCode' => $exitCode
    ];

    // Parse output for failed sends
    foreach ($output as $line) {
        if (strpos($line, 'FAILED:') === 0) {
            // Format: FAILED: <number> <receiver_name> <error>
            $parts = explode(' ', $line, 4);
            $failedList[] = [
                'number' => $parts[1] ?? '',
                'receiver_name' => $parts[2] ?? '',
                'error' => $parts[3] ?? ''
            ];
        }
    }
}

// Default message template
$defaultMessage = "Kepada Yth. \n{variable_receiverName}\n\nDi Tempat\n\nDengan hormat,\n\nPusat Pasar Kerja Kementerian Ketenagakerjaan Republik Indonesia ingin menginformasikan Sdra/i untuk berpartisipasi dalam kegiatan Open Recruitment dengan posisi {variable_jobPosition} di Pusat Pasar Kerja Kementerian Ketenagakerjaan RI : \n\n📅 Tanggal & Waktu : {dateAndTime} \n📍 Tempat: {location} \n\nBagi Sdra/i yang tertarik untuk melamar lowongan ini, berikut kami lampirkan link lamaran dan pendaftaran.\n\n▶️ Step 1 : {linkOne}\n\n▶️ Step 2 : {linkTwo}\n\nDemikian informasi ini kami sampaikan. Atas perhatian dan partisipasi Sdra/i kami ucapkan terima kasih.\n\nSalam sehat,\nPusat Pasar Kerja\n📞 Hotline: 0811-8712-019\n📍 Jl. Gatot Subroto Kav. 44, Setiabudi, Jakarta Selatan 1293";
if (!isset($messageBody)) $messageBody = $defaultMessage;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Broadcast Sender</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* body {
            font-family: 'Roboto', Arial, sans-serif;
            background: #f4f6fb;
            margin: 0;
            padding: 0;
        } */
        /* .container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 32px 40px 40px 40px;
        } */
        h1 {
            text-align: center;
            font-weight: 700;
            margin-bottom: 32px;
            color: #2d3748;
        }
        label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1rem;
            background: #f9fafb;
            transition: border 0.2s;
        }
        input[type="text"]:focus, textarea:focus {
            border-color: #3182ce;
            outline: none;
        }
        .row {
            display: flex;
            gap: 24px;
            margin-bottom: 0;
        }
        .row > div {
            flex: 1;
        }
        .btn {
            display: block;
            width: 220px;
            margin: 32px auto 0 auto;
            padding: 16px 0;
            background: linear-gradient(90deg, #3182ce 0%, #00b894 100%);
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
            border: none;
            border-radius: 32px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(49,130,206,0.08);
            transition: background 0.2s;
        }
        .btn:hover {
            background: linear-gradient(90deg, #00b894 0%, #3182ce 100%);
        }
        .result {
            margin-top: 32px;
            background: #e6fffa;
            border: 1px solid #b2f5ea;
            border-radius: 8px;
            padding: 20px;
            color: #234e52;
            font-size: 1rem;
            white-space: pre-wrap;
        }
        .csv-upload {
            margin-bottom: 24px;
            background: #f1f5f9;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 18px 18px 8px 18px;
        }
        .csv-upload label {
            margin-bottom: 0;
        }
        .failed-list {
            margin-top: 18px;
            background: #fff5f5;
            border: 1px solid #feb2b2;
            border-radius: 8px;
            padding: 16px;
            color: #c53030;
            font-size: 1rem;
        }
        @media (max-width: 700px) {
            .container { padding: 16px; }
            .row { flex-direction: column; gap: 0; }
        }
    </style>
    <!-- SheetJS for Excel parsing -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body>
<?php include 'navbar.php'; ?>
<!-- End Navigation Bar -->
<div class="container">
    <h1>WhatsApp Broadcast Sender</h1>
    <form method="post" enctype="multipart/form-data" id="broadcastForm">
        <div class="csv-upload">
            <label for="csvFile">Import from CSV or Excel (receiver_name, destination_numbers):</label>
            <input type="file" id="csvFile" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
            <span style="font-size:0.95em;color:#718096;">(first row can be header, columns: receiver_name, destination_numbers)</span>
        </div>
        <label for="numbers">WhatsApp destination numbers <span style="font-weight:400; color:#718096;">(one per line, e.g. 6281234567890)</span></label>
        <textarea id="numbers" name="numbers" rows="3" required placeholder="6281234567890\n6289876543210"></textarea>

        <div class="row">
            <div>
                <label for="receiverName">Variable to change receiver name</label>
                <textarea id="receiverName" name="receiverName" rows="3" required placeholder="Arifa\nAlvin\nVigo"></textarea>
                <span style="font-size:0.95em;color:#718096;">(one per line, in order with numbers)</span>
            </div>
            <div>
                <label for="location">Variable to change Location</label>
                <input type="text" id="location" name="location" required placeholder="Lokasi">
            </div>
        </div>
        <div class="row">
            <div>
                <label for="jobPosition">Variable to change job position</label>
                <input type="text" id="jobPosition" name="jobPosition" required placeholder="Posisi Pekerjaan">
            </div>
            <div>
                <label for="linkOne">Variable to change Link 1</label>
                <input type="text" id="linkOne" name="linkOne" required placeholder="https://link1.com">
            </div>
        </div>
        <div class="row">
            <div>
                <label for="dateAndTime">Variable to change Date and Time</label>
                <input type="text" id="dateAndTime" name="dateAndTime" required placeholder="Tanggal & Waktu">
            </div>
            <div>
                <label for="linkTwo">Variable to change Link 2</label>
                <input type="text" id="linkTwo" name="linkTwo" required placeholder="https://link2.com">
            </div>
        </div>
        <label for="messageBody">Messages Body</label>
        <textarea id="messageBody" name="messageBody" rows="10" required><?php echo htmlspecialchars($messageBody); ?></textarea>
        <button class="btn" type="submit">Execute</button>
    </form>
    <?php if ($result): ?>
        <div class="result">
            <strong>Broadcast Result:</strong><br>
            <?php
            echo "Exit Code: " . $result['exitCode'] . "\n";
            echo htmlspecialchars(implode("\n", $result['output']));
            ?>
        </div>
        <?php if (!empty($failedList)): ?>
            <div class="failed-list">
                <strong>Failed to send to:</strong><br>
                <ul>
                <?php foreach ($failedList as $fail): ?>
                    <li><b><?php echo htmlspecialchars($fail['receiver_name']); ?></b> (<?php echo htmlspecialchars($fail['number']); ?>): <?php echo htmlspecialchars($fail['error']); ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
// CSV/Excel import logic
const csvFileInput = document.getElementById('csvFile');
const numbersTextarea = document.getElementById('numbers');
const receiverNameTextarea = document.getElementById('receiverName');

csvFileInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext === 'xlsx') {
        // Excel file
        const reader = new FileReader();
        reader.onload = function(event) {
            const data = new Uint8Array(event.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            const json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            let numbers = [];
            let names = [];
            let colNameIdx = 0, colNumberIdx = 1;
            // Detect header
            if (json.length > 0 &&
                (String(json[0][0]).toLowerCase().includes('receiver') || String(json[0][1]).toLowerCase().includes('destination'))
            ) {
                // Try to find correct columns
                json[0].forEach((col, idx) => {
                    if (String(col).toLowerCase().includes('receiver')) colNameIdx = idx;
                    if (String(col).toLowerCase().includes('destination')) colNumberIdx = idx;
                });
                json.shift(); // Remove header
            }
            json.forEach(row => {
                if (row.length >= 2) {
                    names.push((row[colNameIdx] || '').toString().trim());
                    numbers.push((row[colNumberIdx] || '').toString().trim());
                }
            });
            receiverNameTextarea.value = names.join('\n');
            numbersTextarea.value = numbers.join('\n');
        };
        reader.readAsArrayBuffer(file);
    } else {
        // CSV or TSV
        const reader = new FileReader();
        reader.onload = function(event) {
            const text = event.target.result;
            const lines = text.split(/\r?\n/).filter(line => line.trim() !== '');
            let numbers = [];
            let names = [];
            let hasHeader = false;
            if (lines.length > 0 &&
                (lines[0].toLowerCase().includes('receiver_name') || lines[0].toLowerCase().includes('destination_numbers'))
            ) {
                hasHeader = true;
            }
            for (let i = hasHeader ? 1 : 0; i < lines.length; i++) {
                const cols = lines[i].split(',');
                if (cols.length < 2) continue;
                // Support tab-separated as well
                if (cols.length === 1 && lines[i].includes('\t')) {
                    const tcols = lines[i].split('\t');
                    if (tcols.length >= 2) {
                        names.push(tcols[0].trim());
                        numbers.push(tcols[1].trim());
                    }
                } else {
                    names.push(cols[0].trim());
                    numbers.push(cols[1].trim());
                }
            }
            receiverNameTextarea.value = names.join('\n');
            numbersTextarea.value = numbers.join('\n');
        };
        reader.readAsText(file);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
