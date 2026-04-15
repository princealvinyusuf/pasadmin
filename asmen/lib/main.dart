import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:mobile_scanner/mobile_scanner.dart';

void main() {
  runApp(const AsmenApp());
}

class AsmenApp extends StatelessWidget {
  const AsmenApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'AsMen QR Scanner',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
      ),
      home: const AsmenQrScannerPage(),
    );
  }
}

class AsmenQrScannerPage extends StatefulWidget {
  const AsmenQrScannerPage({super.key});

  @override
  State<AsmenQrScannerPage> createState() => _AsmenQrScannerPageState();
}

class _AsmenQrScannerPageState extends State<AsmenQrScannerPage> {
  static final RegExp _registerPattern = RegExp(r'^[\w\-\.\/]+$', caseSensitive: false);
  static final RegExp _legacySecretPattern = RegExp(r'^[a-f0-9]{16,64}$', caseSensitive: false);

  final MobileScannerController _scannerController = MobileScannerController(
    facing: CameraFacing.back,
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  final TextEditingController _baseUrlController = TextEditingController(
    text: 'https://paskerid.kemnaker.go.id/pasadmin/asmen_feature',
  );

  bool _isProcessingScan = false;
  String _lastScan = '';
  String? _lastError;

  @override
  void dispose() {
    _scannerController.dispose();
    _baseUrlController.dispose();
    super.dispose();
  }

  Future<void> _handleBarcodeCapture(BarcodeCapture capture) async {
    if (_isProcessingScan) {
      return;
    }

    final String? value = capture.barcodes.firstOrNull?.rawValue;
    if (value == null || value.trim().isEmpty) {
      return;
    }

    String decodedText = value.trim();
    if (decodedText.startsWith('#')) {
      decodedText = decodedText.substring(1);
    }

    setState(() {
      _isProcessingScan = true;
      _lastScan = decodedText;
      _lastError = null;
    });

    final List<Uri> apiUris = _buildApiUris(decodedText);
    if (apiUris.isEmpty) {
      setState(() {
        _isProcessingScan = false;
      });
      return;
    }

    try {
      String lastFailure = 'Unable to fetch asset details.';
      for (final Uri apiUri in apiUris) {
        final http.Response response = await http.get(apiUri);
        Map<String, dynamic>? payload;
        try {
          payload = jsonDecode(response.body) as Map<String, dynamic>?;
        } catch (_) {
          payload = null;
        }

        if (response.statusCode == 200 && payload != null && payload['ok'] == true) {
          final Map<String, dynamic> rawAsset =
              (payload['asset'] as Map<String, dynamic>? ?? <String, dynamic>{});
          final Map<String, String> cleanedAsset = <String, String>{};
          rawAsset.forEach((String key, dynamic value) {
            cleanedAsset[key] = value?.toString() ?? '';
          });
          if (!mounted) {
            return;
          }

          setState(() {
            _lastError = null;
          });

          await _scannerController.stop();
          if (!mounted) {
            return;
          }
          await Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (BuildContext context) => AsmenAssetDetailPage(
                assetDetails: cleanedAsset,
                scannedValue: decodedText,
              ),
            ),
          );
          if (mounted) {
            await _scannerController.start();
          }
          return;
        }

        final String apiMessage = payload?['message']?.toString() ?? 'HTTP ${response.statusCode}';
        lastFailure = '$apiMessage (${apiUri.toString()})';
      }

      _showSnackBar(lastFailure);
      if (mounted) {
        setState(() {
          _lastError = lastFailure;
        });
      }
    } catch (_) {
      _showSnackBar('Unable to fetch asset details.');
      if (mounted) {
        setState(() {
          _lastError = 'Unable to fetch asset details.';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isProcessingScan = false;
        });
      }
    }
  }

  List<Uri> _buildApiUris(String decodedText) {
    if (decodedText.contains('asmen_qr')) {
      final Uri? scannedUri = Uri.tryParse(decodedText);
      final String? s = scannedUri?.queryParameters['s'];
      if (scannedUri == null || s == null || s.isEmpty) {
        _showSnackBar('QR URL is missing parameter s.');
        return <Uri>[];
      }

      if (scannedUri.path.endsWith('asmen_qr_api.php') || scannedUri.path.endsWith('asmen_qr_api')) {
        final Uri noExt = scannedUri.replace(
          path: scannedUri.path.replaceAll('asmen_qr_api.php', 'asmen_qr_api'),
          queryParameters: <String, String>{'s': s},
        );
        final Uri withExt = scannedUri.replace(
          path: scannedUri.path.replaceAll('asmen_qr_api', 'asmen_qr_api.php'),
          queryParameters: <String, String>{'s': s},
        );
        return <Uri>[noExt, withExt];
      }

      final String apiPathNoExt = scannedUri.path
          .replaceAll('asmen_qr.php', 'asmen_qr_api')
          .replaceAll('asmen_qr', 'asmen_qr_api');
      final String apiPathWithExt = apiPathNoExt.replaceAll('asmen_qr_api', 'asmen_qr_api.php');
      return <Uri>[
        scannedUri.replace(
          path: apiPathNoExt,
          queryParameters: <String, String>{'s': s},
        ),
        scannedUri.replace(
          path: apiPathWithExt,
          queryParameters: <String, String>{'s': s},
        ),
      ];
    }

    if (_registerPattern.hasMatch(decodedText) || _legacySecretPattern.hasMatch(decodedText)) {
      final String baseInput = _baseUrlController.text.trim();
      if (baseInput.isEmpty) {
        _showSnackBar('Set your AsMen backend URL first.');
        return <Uri>[];
      }

      final String normalizedBaseInput = baseInput.endsWith('/') ? baseInput : '$baseInput/';
      final Uri? base = Uri.tryParse(normalizedBaseInput);
      if (base == null || !base.hasScheme || !base.hasAuthority) {
        _showSnackBar('Base URL must be a full URL, example: https://domain.com/asmen_feature/');
        return <Uri>[];
      }

      final Uri withoutExtension = base.resolve('asmen_qr_api').replace(
        queryParameters: <String, String>{'s': decodedText},
      );
      final Uri withExtension = base.resolve('asmen_qr_api.php').replace(
        queryParameters: <String, String>{'s': decodedText},
      );
      return <Uri>[withoutExtension, withExtension];
    }

    _showSnackBar('QR not recognized for AsMen');
    return <Uri>[];
  }

  void _showSnackBar(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('AsMen QR Scanner')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              TextField(
                controller: _baseUrlController,
                keyboardType: TextInputType.url,
                decoration: const InputDecoration(
                  border: OutlineInputBorder(),
                  labelText: 'AsMen Backend URL',
                  hintText: 'https://domain.com/asmen_feature/',
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 260,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Stack(
                    children: <Widget>[
                      MobileScanner(
                        controller: _scannerController,
                        onDetect: _handleBarcodeCapture,
                      ),
                      if (_isProcessingScan)
                        const ColoredBox(
                          color: Color(0x66000000),
                          child: Center(child: CircularProgressIndicator()),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                _lastScan.isEmpty ? 'Last scan: -' : 'Last scan: $_lastScan',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              if (_lastError != null) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  _lastError!,
                  style: const TextStyle(color: Colors.red),
                ),
              ] else ...<Widget>[
                const SizedBox(height: 8),
                const Text('After successful scan, detail page will open automatically.'),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class AsmenAssetDetailPage extends StatelessWidget {
  const AsmenAssetDetailPage({
    required this.assetDetails,
    required this.scannedValue,
    super.key,
  });

  final Map<String, String> assetDetails;
  final String scannedValue;

  @override
  Widget build(BuildContext context) {
    final List<_DetailSection> sections = _buildSections(assetDetails);

    return Scaffold(
      appBar: AppBar(title: const Text('Asset Detail')),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: <Widget>[
                  Card(
                    child: ListTile(
                      title: const Text('Scanned Value'),
                      subtitle: Text(scannedValue),
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...sections.map(( _DetailSection section) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Card(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                section.title,
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              const SizedBox(height: 10),
                              ...section.entries.map((MapEntry<String, String> entry) {
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: <Widget>[
                                      Text(
                                        _labelize(entry.key),
                                        style: const TextStyle(fontWeight: FontWeight.w600),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(entry.value.isEmpty ? '-' : entry.value),
                                    ],
                                  ),
                                );
                              }),
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.qr_code_scanner),
                  label: const Text('Retake Scan'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailSection {
  const _DetailSection(this.title, this.entries);

  final String title;
  final List<MapEntry<String, String>> entries;
}

List<_DetailSection> _buildSections(Map<String, String> details) {
  const Set<String> locationKeys = <String>{
    'alamat',
    'rt_rw',
    'kelurahan_desa',
    'kecamatan',
    'kab_kota',
    'kode_kab_kota',
    'provinsi',
    'kode_provinsi',
    'kode_pos',
    'lokasi_ruang',
  };

  const Set<String> serviceKeys = <String>{
    'service_interval_months',
    'last_service_date',
    'next_service_date',
    'service_priority',
    'service_reason',
  };

  final List<MapEntry<String, String>> general = <MapEntry<String, String>>[];
  final List<MapEntry<String, String>> location = <MapEntry<String, String>>[];
  final List<MapEntry<String, String>> service = <MapEntry<String, String>>[];
  final List<MapEntry<String, String>> other = <MapEntry<String, String>>[];

  for (final MapEntry<String, String> entry in details.entries) {
    if (locationKeys.contains(entry.key)) {
      location.add(entry);
    } else if (serviceKeys.contains(entry.key)) {
      service.add(entry);
    } else if (entry.key.startsWith('nilai_') ||
        entry.key.startsWith('luas_') ||
        entry.key.startsWith('tanggal_')) {
      other.add(entry);
    } else {
      general.add(entry);
    }
  }

  final List<_DetailSection> sections = <_DetailSection>[
    if (general.isNotEmpty) _DetailSection('General Info', general),
    if (location.isNotEmpty) _DetailSection('Location', location),
    if (service.isNotEmpty) _DetailSection('Service', service),
    if (other.isNotEmpty) _DetailSection('Other Fields', other),
  ];

  return sections;
}

String _labelize(String key) {
  final List<String> words = key.split('_').where((String w) => w.isNotEmpty).toList();
  return words.map((String w) => '${w[0].toUpperCase()}${w.substring(1)}').join(' ');
}
