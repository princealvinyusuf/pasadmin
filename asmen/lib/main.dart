import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:url_launcher/url_launcher.dart';

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
  final TextEditingController _baseUrlController = TextEditingController();

  bool _scanFromBmn = false;
  bool _isProcessingScan = false;
  String _lastScan = '';

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
    });

    final Uri? targetUri = _buildTargetUri(decodedText);
    if (targetUri == null) {
      _showSnackBar('QR not recognized for AsMen');
      setState(() {
        _isProcessingScan = false;
      });
      return;
    }

    final bool launched = await launchUrl(targetUri, mode: LaunchMode.inAppBrowserView);
    if (!launched && mounted) {
      _showSnackBar('Could not open ${targetUri.toString()}');
    }

    if (mounted) {
      setState(() {
        _isProcessingScan = false;
      });
    }
  }

  Uri? _buildTargetUri(String decodedText) {
    if (decodedText.contains('asmen_qr.php')) {
      return Uri.tryParse(decodedText);
    }

    if (_registerPattern.hasMatch(decodedText) || _legacySecretPattern.hasMatch(decodedText)) {
      final String baseInput = _baseUrlController.text.trim();
      if (baseInput.isEmpty) {
        _showSnackBar('Set your AsMen backend URL first.');
        return null;
      }

      final Uri? base = Uri.tryParse(baseInput);
      if (base == null || !base.hasScheme || !base.hasAuthority) {
        _showSnackBar('Base URL must be a full URL, example: https://domain.com/asmen_feature/');
        return null;
      }

      final Uri resolved = base.resolve('asmen_qr.php').replace(
        queryParameters: <String, String>{'s': decodedText},
      );
      return resolved;
    }

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
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: MobileScanner(
                    controller: _scannerController,
                    onDetect: _handleBarcodeCapture,
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
            ],
          ),
        ),
      ),
    );
  }
}
