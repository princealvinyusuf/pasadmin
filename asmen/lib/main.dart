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

  bool _scanFromBmn = false;
  bool _isProcessingScan = false;
  String _lastScan = '';
  String? _lastError;
  Map<String, String>? _assetDetails;

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
    if (_scanFromBmn && decodedText.startsWith('#')) {
      decodedText = decodedText.substring(1);
    }

    setState(() {
      _isProcessingScan = true;
      _lastScan = decodedText;
      _lastError = null;
    });

    final Uri? apiUri = _buildApiUri(decodedText);
    if (apiUri == null) {
      setState(() {
        _isProcessingScan = false;
      });
      return;
    }

    try {
      final http.Response response = await http.get(apiUri);
      final Map<String, dynamic>? payload = jsonDecode(response.body) as Map<String, dynamic>?;
      if (response.statusCode != 200 || payload == null || payload['ok'] != true) {
        final String message = payload?['message']?.toString() ?? 'Failed to load asset details.';
        _showSnackBar(message);
        if (mounted) {
          setState(() {
            _lastError = message;
            _assetDetails = null;
          });
        }
      } else {
        final Map<String, dynamic> rawAsset =
            (payload['asset'] as Map<String, dynamic>? ?? <String, dynamic>{});
        final Map<String, String> cleanedAsset = <String, String>{};
        rawAsset.forEach((String key, dynamic value) {
          cleanedAsset[key] = value?.toString() ?? '';
        });
        if (mounted) {
          setState(() {
            _assetDetails = cleanedAsset;
            _lastError = null;
          });
        }
      }
    } catch (_) {
      _showSnackBar('Unable to fetch asset details.');
      if (mounted) {
        setState(() {
          _lastError = 'Unable to fetch asset details.';
          _assetDetails = null;
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

  Uri? _buildApiUri(String decodedText) {
    if (decodedText.contains('asmen_qr.php') || decodedText.contains('asmen_qr_api.php')) {
      final Uri? scannedUri = Uri.tryParse(decodedText);
      final String? s = scannedUri?.queryParameters['s'];
      if (scannedUri == null || s == null || s.isEmpty) {
        _showSnackBar('QR URL is missing parameter s.');
        return null;
      }

      if (scannedUri.path.endsWith('asmen_qr_api.php')) {
        return scannedUri.replace(queryParameters: <String, String>{'s': s});
      }

      final String apiPath = scannedUri.path.replaceAll('asmen_qr.php', 'asmen_qr_api.php');
      return scannedUri.replace(
        path: apiPath,
        queryParameters: <String, String>{'s': s},
      );
    }

    if (_registerPattern.hasMatch(decodedText) || _legacySecretPattern.hasMatch(decodedText)) {
      final String baseInput = _baseUrlController.text.trim();
      if (baseInput.isEmpty) {
        _showSnackBar('Set your AsMen backend URL first.');
        return null;
      }

      final String normalizedBaseInput = baseInput.endsWith('/') ? baseInput : '$baseInput/';
      final Uri? base = Uri.tryParse(normalizedBaseInput);
      if (base == null || !base.hasScheme || !base.hasAuthority) {
        _showSnackBar('Base URL must be a full URL, example: https://domain.com/asmen_feature/');
        return null;
      }

      final Uri resolved = base.resolve('asmen_qr_api.php').replace(
        queryParameters: <String, String>{'s': decodedText},
      );
      return resolved;
    }

    _showSnackBar('QR not recognized for AsMen');
    return null;
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
    final Iterable<MapEntry<String, String>> entries = _assetDetails?.entries ?? const <MapEntry<String, String>>[];

    return Scaffold(
      appBar: AppBar(title: const Text('AsMen QR Scanner')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Scan from BMN QR'),
                value: _scanFromBmn,
                onChanged: (bool value) {
                  setState(() {
                    _scanFromBmn = value;
                  });
                },
              ),
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
              const SizedBox(height: 8),
              Expanded(
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: _buildDetailsBody(entries),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetailsBody(Iterable<MapEntry<String, String>> entries) {
    if (_lastError != null) {
      return Center(
        child: Text(
          _lastError!,
          style: const TextStyle(color: Colors.red),
          textAlign: TextAlign.center,
        ),
      );
    }

    if (_assetDetails == null) {
      return const Center(
        child: Text('Scan a QR code to show asset detail.'),
      );
    }

    if (_assetDetails!.isEmpty) {
      return const Center(
        child: Text('No detail fields returned by API.'),
      );
    }

    return ListView(
      children: entries
          .map(
            (MapEntry<String, String> entry) => Padding(
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
            ),
          )
          .toList(),
    );
  }

  String _labelize(String key) {
    final List<String> words = key.split('_').where((String w) => w.isNotEmpty).toList();
    return words
        .map((String w) => '${w[0].toUpperCase()}${w.substring(1)}')
        .join(' ');
  }
}
