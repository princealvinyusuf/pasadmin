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

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function stripHtml(html) {
  return String(html || "")
    .replace(/<style[\s\S]*?<\/style>/gi, " ")
    .replace(/<script[\s\S]*?<\/script>/gi, " ")
    .replace(/<[^>]+>/g, " ")
    .replace(/\s+/g, " ")
    .trim();
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
  const textTemplate = String(payload.textTemplate || "");
  const htmlTemplate = String(payload.htmlTemplate || "");
  const batchSize = Math.max(1, Number(payload.batchSize || 20));
  const delayMs = Math.max(0, Number(payload.delayMs || 1500));
  const batchDelayMs = Math.max(0, Number(payload.batchDelayMs || 5000));

  if (!smtp.user || !smtp.pass) {
    console.error("ERROR: SMTP username/password is required");
    process.exit(1);
  }
  if (!subjectTemplate || (!textTemplate && !htmlTemplate)) {
    console.error("ERROR: Email subject and at least one body template are required");
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

  const totalBatches = Math.max(1, Math.ceil(recipients.length / batchSize));

  for (let i = 0; i < recipients.length; i += 1) {
    const recipient = recipients[i];
    if (i > 0) {
      if (i % batchSize === 0 && batchDelayMs > 0) {
        const batchNo = Math.floor(i / batchSize);
        console.log(`BATCH_DELAY:\t${batchNo}/${totalBatches}\t${batchDelayMs}`);
        await sleep(batchDelayMs);
      } else if (delayMs > 0) {
        await sleep(delayMs);
      }
    }

    try {
      const finalSubject = applyTemplate(subjectTemplate, recipient);
      const finalHtml = htmlTemplate ? applyTemplate(htmlTemplate, recipient) : "";
      const finalText = textTemplate
        ? applyTemplate(textTemplate, recipient)
        : stripHtml(finalHtml);
      await transporter.sendMail({
        from: `"${smtp.fromName || "Notification"}" <${smtp.fromEmail || smtp.user}>`,
        to: recipient.email,
        subject: finalSubject,
        text: finalText,
        html: finalHtml || undefined,
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
  console.log(`RESULT_JSON:\t${JSON.stringify({ sentCount, failedCount: failed.length, total: recipients.length, batchSize, delayMs, batchDelayMs })}`);
  process.exit(failed.length > 0 ? 2 : 0);
}

main().catch((error) => {
  console.error(`ERROR: ${error.message}`);
  process.exit(1);
});
