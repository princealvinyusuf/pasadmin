const nodemailer = require("nodemailer");

function readStdin() {
  return new Promise((resolve, reject) => {
    let data = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      data += chunk;
    });
    process.stdin.on("end", () => resolve(data));
    process.stdin.on("error", reject);
  });
}

function applyTemplate(template, recipient) {
  return template
    .replace(/\{name\}/g, recipient.name || "")
    .replace(/\{email\}/g, recipient.email || "");
}

async function main() {
  let payload;
  try {
    const raw = await readStdin();
    payload = JSON.parse(raw || "{}");
  } catch (error) {
    console.error(`ERROR: Invalid input payload (${error.message})`);
    process.exit(1);
  }

  const smtp = payload.smtp || {};
  const recipients = Array.isArray(payload.recipients) ? payload.recipients : [];
  const subjectTemplate = String(payload.subject || "");
  const messageTemplate = String(payload.message || "");

  if (!smtp.user || !smtp.pass) {
    console.error("ERROR: SMTP username/password is required");
    process.exit(1);
  }
  if (!subjectTemplate || !messageTemplate) {
    console.error("ERROR: Email subject and message are required");
    process.exit(1);
  }
  if (recipients.length === 0) {
    console.error("ERROR: No recipients to send");
    process.exit(1);
  }

  const transporter = nodemailer.createTransport({
    host: smtp.host || "smtp.gmail.com",
    port: Number(smtp.port || 465),
    secure: Number(smtp.port || 465) === 465,
    auth: {
      user: smtp.user,
      pass: smtp.pass,
    },
  });

  let sentCount = 0;
  const failed = [];

  for (const recipient of recipients) {
    try {
      const finalSubject = applyTemplate(subjectTemplate, recipient);
      const finalMessage = applyTemplate(messageTemplate, recipient);
      await transporter.sendMail({
        from: `"${smtp.fromName || "Notification"}" <${smtp.fromEmail || smtp.user}>`,
        to: recipient.email,
        subject: finalSubject,
        text: finalMessage,
      });
      sentCount += 1;
      console.log(`SENT:\t${recipient.email}\t${recipient.name || ""}`);
    } catch (error) {
      const errMsg = (error && error.message ? error.message : "unknown_error").replace(/\s+/g, " ").trim();
      failed.push({ email: recipient.email, name: recipient.name || "", error: errMsg });
      console.log(`FAILED:\t${recipient.email}\t${recipient.name || ""}\t${errMsg}`);
    }
  }

  console.log(`SUMMARY: sent=${sentCount} failed=${failed.length} total=${recipients.length}`);
  process.exit(failed.length > 0 ? 2 : 0);
}

main().catch((error) => {
  console.error(`ERROR: ${error.message}`);
  process.exit(1);
});
