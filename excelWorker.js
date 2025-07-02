// excelWorker.js
// Web Worker for parsing Excel (.xlsx) and CSV files using SheetJS

// Load SheetJS from CDN
importScripts('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js');

self.onmessage = function(e) {
    const { fileData, fileType } = e.data;
    try {
        let jobs = [];
        if (fileType === 'xlsx') {
            const data = new Uint8Array(fileData);
            const workbook = XLSX.read(data, { type: 'array' });
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            jobs = XLSX.utils.sheet_to_json(worksheet);
        } else if (fileType === 'csv') {
            const text = new TextDecoder('utf-8').decode(fileData);
            // SheetJS can parse CSV text
            const worksheet = XLSX.read(text, { type: 'string' }).Sheets.Sheet1;
            jobs = XLSX.utils.sheet_to_json(worksheet);
        } else {
            throw new Error('Unsupported file type: ' + fileType);
        }
        self.postMessage({ success: true, jobs });
    } catch (err) {
        self.postMessage({ success: false, error: err.message });
    }
}; 