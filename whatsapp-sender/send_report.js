const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion
} = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const P = require('pino');
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const TARGET_URL = process.env.REPORT_URL || 'https://paskerid.kemnaker.go.id/paskerid/public/';
const OUTPUT_DIR = path.join(__dirname, '../downloads');
const OUTPUT_FILE = path.join(OUTPUT_DIR, 'paskerid_report.png');

async function captureScreenshot() {
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
    const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH || process.env.CHROMIUM_PATH || undefined;
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
        executablePath
    });
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1366, height: 768, deviceScaleFactor: 1 });
        await page.goto(TARGET_URL, { waitUntil: 'networkidle2', timeout: 120000 });
        try {
            await page.waitForSelector('body', { timeout: 15000 });
        } catch (_) {}
        await new Promise(resolve => setTimeout(resolve, 3000));
        await page.screenshot({ path: OUTPUT_FILE, fullPage: true });
        return OUTPUT_FILE;
    } finally {
        await browser.close();
    }
}

async function sendToWhatsAppGroup(imagePath) {
    const groupJid = process.env.WA_GROUP_JID; // e.g. 1203634xxxxxx@g.us
    if (!groupJid) {
        throw new Error('WA_GROUP_JID env var is required');
    }

    const { state, saveCreds } = await useMultiFileAuthState(path.join(__dirname, 'auth_info_baileys'));
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        logger: P({ level: 'silent' }),
        printQRInTerminal: true,
        auth: state
    });

    sock.ev.on('creds.update', saveCreds);

    return new Promise((resolve, reject) => {
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;
            if (connection === 'close') {
                const shouldReconnect = lastDisconnect?.error instanceof Boom &&
                    lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut;
                if (shouldReconnect) return; // let Baileys reconnect (but our process will exit by timeout)
                reject(new Error('Connection closed'));
            }
            if (connection === 'open') {
                try {
                    const buffer = fs.readFileSync(imagePath);
                    await sock.sendMessage(groupJid, {
                        image: buffer,
                        caption: `PaskerID Report\nURL: ${TARGET_URL}\nTime: ${new Date().toLocaleString()}`
                    });
                    resolve();
                } catch (err) {
                    reject(err);
                } finally {
                    setTimeout(() => process.exit(0), 500);
                }
            }
        });
    });
}

(async () => {
    try {
        const imagePath = await captureScreenshot();
        await sendToWhatsAppGroup(imagePath);
        console.log('✅ Report sent to WhatsApp group');
    } catch (err) {
        console.error('❌ Failed to send report:', err && err.message ? err.message : err);
        process.exit(1);
    }
})();


