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

async function start() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        logger: P({ level: 'silent' }),
        printQRInTerminal: true,
        auth: state
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('📱 Scan this QR code with your WhatsApp app:');
            require('qrcode-terminal').generate(qr, { small: true });
        }

        if (connection === 'close') {
            const shouldReconnect = lastDisconnect?.error instanceof Boom &&
                lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut;
            console.log('❌ Connection closed. Reconnecting:', shouldReconnect);
            if (shouldReconnect) {
                start();
            }
        }

        if (connection === 'open') {
            console.log('✅ Connected to WhatsApp');
            // Read broadcast.json
            const broadcastPath = path.join(__dirname, '../broadcast.json');
            if (!fs.existsSync(broadcastPath)) {
                console.error('❌ broadcast.json not found!');
                process.exit(1);
            }
            let data;
            try {
                data = JSON.parse(fs.readFileSync(broadcastPath, 'utf8'));
            } catch (err) {
                console.error('❌ Failed to read broadcast.json:', err);
                process.exit(1);
            }
            if (!Array.isArray(data)) {
                console.error('❌ broadcast.json must be an array of {number, message}');
                process.exit(1);
            }
            for (const item of data) {
                const number = item.number.replace(/[^0-9]/g, '');
                const jid = number.includes('@s.whatsapp.net') ? number : number + '@s.whatsapp.net';
                const receiverName = item.receiver_name || '';
                try {
                    const isRegistered = await sock.onWhatsApp(jid);
                    if (!isRegistered || !isRegistered[0]?.exists) {
                        console.error(`FAILED: ${number} ${receiverName} Not a WhatsApp number`);
                        continue;
                    }
                    await sock.sendMessage(jid, { text: item.message });
                    console.log(`SUCCESS: ${number} ${receiverName}`);
                } catch (err) {
                    console.error(`FAILED: ${number} ${receiverName} ${err && err.message ? err.message : err}`);
                }
            }
            process.exit(0);
        }
    });
}

start(); 
