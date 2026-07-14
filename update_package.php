<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!current_user_can('update_package_view') && !current_user_can('manage_settings')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function read_json_file(string $path): ?array {
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function fetch_json_url(string $url): ?array {
    $json = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'pasadmin-update-package/1.0',
        ]);
        $res = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($res) && $httpCode >= 200 && $httpCode < 300) {
            $json = $res;
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\nUser-Agent: pasadmin-update-package/1.0\r\n",
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        if (is_string($res)) {
            $json = $res;
        }
    }

    if (!is_string($json) || $json === '') {
        return null;
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

function normalize_version(string $version): string {
    $v = trim($version);
    $v = ltrim($v, 'vV');
    $v = preg_replace('/\+.*$/', '', $v) ?? $v;
    $v = preg_replace('/^dev-/', '', $v) ?? $v;
    return $v;
}

function version_can_compare(string $version): bool {
    return preg_match('/^\d+(\.\d+)*([.-][A-Za-z0-9]+)?$/', $version) === 1;
}

function dependency_status(?string $installed, ?string $latest): string {
    if ($installed === null || $installed === '') {
        return 'Installed version not detected';
    }
    if ($latest === null || $latest === '') {
        return 'Latest version not detected';
    }

    $normalizedInstalled = normalize_version($installed);
    $normalizedLatest = normalize_version($latest);

    if (version_can_compare($normalizedInstalled) && version_can_compare($normalizedLatest)) {
        $cmp = version_compare($normalizedInstalled, $normalizedLatest);
        if ($cmp < 0) {
            return 'Update available';
        }
        if ($cmp === 0) {
            return 'Up to date';
        }
        return 'Installed newer than latest tag';
    }

    if ($normalizedInstalled === $normalizedLatest) {
        return 'Up to date';
    }

    return 'Check manually';
}

function detect_project_root(): array {
    $candidates = [];
    $pasadminDir = __DIR__;
    $oneUp = dirname($pasadminDir);
    $twoUp = dirname($oneUp);

    $candidates[] = $oneUp;
    $candidates[] = $twoUp;

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) : '';
    if ($docRoot !== '') {
        $candidates[] = $docRoot;
        $candidates[] = dirname($docRoot);
    }

    $seen = [];
    $ordered = [];
    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        $path = is_string($real) ? $real : $candidate;
        if ($path === '' || isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;
        $ordered[] = $path;
    }

    $bestPath = $oneUp;
    $bestScore = -1;
    foreach ($ordered as $path) {
        $score = 0;
        if (is_file($path . DIRECTORY_SEPARATOR . 'composer.json')) {
            $score += 2;
        }
        if (is_file($path . DIRECTORY_SEPARATOR . 'package.json')) {
            $score += 2;
        }
        if (is_dir($path . DIRECTORY_SEPARATOR . 'app')) {
            $score += 1;
        }
        if (is_file($path . DIRECTORY_SEPARATOR . 'artisan')) {
            $score += 1;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestPath = $path;
        }
    }

    return [
        'path' => $bestPath,
        'score' => $bestScore,
        'candidates' => $ordered,
    ];
}

$scan = isset($_GET['scan']) && $_GET['scan'] === '1';
$rootDetect = detect_project_root();
$rootPath = (string)$rootDetect['path'];

$composerJsonPath = $rootPath . DIRECTORY_SEPARATOR . 'composer.json';
$composerLockPath = $rootPath . DIRECTORY_SEPARATOR . 'composer.lock';
$packageJsonPath = $rootPath . DIRECTORY_SEPARATOR . 'package.json';
$packageLockPath = $rootPath . DIRECTORY_SEPARATOR . 'package-lock.json';

$composerJson = read_json_file($composerJsonPath);
$composerLock = read_json_file($composerLockPath);
$packageJson = read_json_file($packageJsonPath);
$packageLock = read_json_file($packageLockPath);

$composerRows = [];
$npmRows = [];
$composerUpdateCount = 0;
$npmUpdateCount = 0;
$warnings = [];

if (!is_file($composerJsonPath) && !is_file($packageJsonPath)) {
    $warnings[] = 'Could not detect Laravel root automatically. Configure web root to project root or place pasadmin under the same root.';
}

if ($scan) {
    if ($composerJson === null) {
        $warnings[] = 'composer.json not found or unreadable.';
    } else {
        $composerRequires = [];
        foreach ((array)($composerJson['require'] ?? []) as $name => $constraint) {
            if ($name === 'php' || strpos($name, 'ext-') === 0 || strpos($name, 'lib-') === 0) {
                continue;
            }
            $composerRequires[$name] = ['constraint' => (string) $constraint, 'is_dev' => false];
        }
        foreach ((array)($composerJson['require-dev'] ?? []) as $name => $constraint) {
            $composerRequires[$name] = ['constraint' => (string) $constraint, 'is_dev' => true];
        }

        $installedComposer = [];
        if (is_array($composerLock)) {
            foreach ((array)($composerLock['packages'] ?? []) as $pkg) {
                if (!isset($pkg['name'])) {
                    continue;
                }
                $installedComposer[(string) $pkg['name']] = (string)($pkg['version'] ?? '');
            }
            foreach ((array)($composerLock['packages-dev'] ?? []) as $pkg) {
                if (!isset($pkg['name'])) {
                    continue;
                }
                $installedComposer[(string) $pkg['name']] = (string)($pkg['version'] ?? '');
            }
        } else {
            $warnings[] = 'composer.lock not found, installed Composer versions may be unavailable.';
        }

        foreach ($composerRequires as $name => $meta) {
            $installed = $installedComposer[$name] ?? null;
            $latest = null;

            $packagist = fetch_json_url('https://repo.packagist.org/p2/' . rawurlencode($name) . '.json');
            if (is_array($packagist) && isset($packagist['packages'][$name]) && is_array($packagist['packages'][$name])) {
                foreach ($packagist['packages'][$name] as $release) {
                    if (!is_array($release)) {
                        continue;
                    }
                    $candidate = (string)($release['version'] ?? '');
                    if ($candidate === '' || stripos($candidate, 'dev-') === 0) {
                        continue;
                    }
                    $latest = $candidate;
                    break;
                }
                if ($latest === null && !empty($packagist['packages'][$name][0]['version'])) {
                    $latest = (string)$packagist['packages'][$name][0]['version'];
                }
            }

            $status = dependency_status($installed, $latest);
            if ($status === 'Update available') {
                $composerUpdateCount++;
            }

            $composerRows[] = [
                'name' => $name,
                'constraint' => (string)$meta['constraint'],
                'installed' => $installed,
                'latest' => $latest,
                'status' => $status,
                'update_command' => 'composer update ' . $name . ' --with-all-dependencies',
            ];
        }
    }

    if ($packageJson === null) {
        $warnings[] = 'package.json not found or unreadable.';
    } else {
        $npmDeps = [];
        foreach ((array)($packageJson['dependencies'] ?? []) as $name => $constraint) {
            $npmDeps[$name] = ['constraint' => (string)$constraint, 'is_dev' => false];
        }
        foreach ((array)($packageJson['devDependencies'] ?? []) as $name => $constraint) {
            $npmDeps[$name] = ['constraint' => (string)$constraint, 'is_dev' => true];
        }

        $installedNpm = [];
        if (is_array($packageLock)) {
            if (isset($packageLock['packages']) && is_array($packageLock['packages'])) {
                foreach ($packageLock['packages'] as $pkgPath => $pkgMeta) {
                    if (!is_string($pkgPath) || strpos($pkgPath, 'node_modules/') !== 0 || !is_array($pkgMeta)) {
                        continue;
                    }
                    $name = substr($pkgPath, strlen('node_modules/'));
                    if ($name === '' || strpos($name, '/node_modules/') !== false) {
                        continue;
                    }
                    $installedNpm[$name] = (string)($pkgMeta['version'] ?? '');
                }
            } elseif (isset($packageLock['dependencies']) && is_array($packageLock['dependencies'])) {
                foreach ($packageLock['dependencies'] as $name => $meta) {
                    if (!is_array($meta)) {
                        continue;
                    }
                    $installedNpm[(string)$name] = (string)($meta['version'] ?? '');
                }
            }
        } else {
            $warnings[] = 'package-lock.json not found, installed npm versions may be unavailable.';
        }

        foreach ($npmDeps as $name => $meta) {
            $installed = $installedNpm[$name] ?? null;
            $latest = null;

            $registry = fetch_json_url('https://registry.npmjs.org/' . rawurlencode($name));
            if (is_array($registry) && isset($registry['dist-tags']['latest'])) {
                $latest = (string)$registry['dist-tags']['latest'];
            }

            $status = dependency_status($installed, $latest);
            if ($status === 'Update available') {
                $npmUpdateCount++;
            }

            $installCmd = $meta['is_dev']
                ? ('npm install ' . $name . '@latest --save-dev')
                : ('npm install ' . $name . '@latest');

            $npmRows[] = [
                'name' => $name,
                'constraint' => (string)$meta['constraint'],
                'installed' => $installed,
                'latest' => $latest,
                'status' => $status,
                'update_command' => $installCmd,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Package</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .cmd { white-space: pre-wrap; font-size: 0.9rem; }
        .small-code { font-size: 0.8rem; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h2 class="mb-2 mb-md-0">Update Package</h2>
        <div class="text-muted small">Root project: <?php echo htmlspecialchars($rootPath); ?></div>
    </div>

    <?php if (!$scan): ?>
        <div class="alert alert-info">
            Click <strong>Scan Updates</strong> to check Composer and npm package updates from registry metadata.
        </div>
    <?php endif; ?>

    <?php foreach ($warnings as $warning): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
    <?php endforeach; ?>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-primary" href="update_package?scan=1"><i class="bi bi-arrow-repeat me-1"></i>Scan Updates</a>
            <?php if ($scan): ?>
                <a class="btn btn-outline-secondary" href="update_package"><i class="bi bi-x-circle me-1"></i>Clear Scan</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($scan): ?>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-2">Composer</h5>
                        <div class="display-6 mb-1"><?php echo count($composerRows); ?></div>
                        <div class="text-muted">Dependencies scanned</div>
                        <div class="mt-2">
                            <span class="badge text-bg-warning"><?php echo $composerUpdateCount; ?> update(s) available</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-2">npm</h5>
                        <div class="display-6 mb-1"><?php echo count($npmRows); ?></div>
                        <div class="text-muted">Dependencies scanned</div>
                        <div class="mt-2">
                            <span class="badge text-bg-warning"><?php echo $npmUpdateCount; ?> update(s) available</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">Recommended Commands (XAMPP Linux Laravel Server)</div>
        <div class="card-body">
            <p class="text-muted mb-2">Run from your Laravel project root (example: <code>/opt/lampp/htdocs/paskerid</code>).</p>
            <pre class="cmd bg-dark text-light p-3 rounded mb-2">cd <?php echo htmlspecialchars($rootPath); ?>
composer outdated
npm outdated</pre>
            <pre class="cmd bg-dark text-light p-3 rounded mb-2"># update all Composer dependencies
composer update --with-all-dependencies

# update all npm dependencies (respecting package.json ranges)
npm update</pre>
            <pre class="cmd bg-dark text-light p-3 rounded mb-0"># after update (Laravel)
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force</pre>
        </div>
    </div>

    <?php if ($scan): ?>
        <div class="card mb-3">
            <div class="card-header">Composer Dependency Scan</div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Constraint</th>
                            <th>Installed</th>
                            <th>Latest</th>
                            <th>Status</th>
                            <th>Command</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($composerRows as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['name']); ?></code></td>
                                <td><code><?php echo htmlspecialchars($row['constraint']); ?></code></td>
                                <td><?php echo htmlspecialchars((string)($row['installed'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['latest'] ?? '-')); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Update available'): ?>
                                        <span class="badge text-bg-warning"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php elseif ($row['status'] === 'Up to date'): ?>
                                        <span class="badge text-bg-success"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="small-code"><?php echo htmlspecialchars($row['update_command']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">npm Dependency Scan</div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Constraint</th>
                            <th>Installed</th>
                            <th>Latest</th>
                            <th>Status</th>
                            <th>Command</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($npmRows as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['name']); ?></code></td>
                                <td><code><?php echo htmlspecialchars($row['constraint']); ?></code></td>
                                <td><?php echo htmlspecialchars((string)($row['installed'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['latest'] ?? '-')); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Update available'): ?>
                                        <span class="badge text-bg-warning"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php elseif ($row['status'] === 'Up to date'): ?>
                                        <span class="badge text-bg-success"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="small-code"><?php echo htmlspecialchars($row['update_command']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
