const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');

const { Boom } = require('@hapi/boom');
const P = require('pino');
const fs = require('fs');
const path = require('path');

async function start() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');
    const { version } = await fetchLatestBaileysVersion();

const qrcode = require('qrcode-terminal');

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
    qrcode.generate(qr, { small: true });
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

            // Wait until user data is available
            const me = sock.user;
            if (!me || !me.id) {
                console.error('❌ Authentication not complete, user ID is missing.');
                return;
            }

const groups = await sock.groupFetchAllParticipating();
for (const [jid, group] of Object.entries(groups)) {
    console.log(`📣 Group Name: ${group.subject}`);
    console.log(`🔑 Group JID: ${jid}`);
}



            // 🟢 Send Excel file once connected and authenticated
            const jid = '120363400882618232@g.us'; // replace with real number
            try {
                const filePath = path.join(__dirname, '../downloads/jobs_backup.xlsx');
                const buffer = fs.readFileSync(filePath);

                await sock.sendMessage(jid, {
                    document: buffer,
                    fileName: 'jobs_backup.xlsx',
                    mimetype: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });
                console.log('✅ Excel file sent!');
            } catch (err) {
                console.error('❌ Failed to send file:', err);
            }

            process.exit(0); // Exit after sending
        }
    });
}

start();
