// ─────────────────────────────────────────────────────────────
// TRAINING DASHBOARD — Google Apps Script
// ─────────────────────────────────────────────────────────────
// HOW TO DEPLOY:
//   1. Open your Google Sheet
//   2. Extensions → Apps Script
//   3. Delete any existing code, paste this entire file
//   4. Click Deploy → New Deployment
//   5. Type: Web App
//   6. Execute as: Me
//   7. Who has access: Anyone
//   8. Click Deploy → Copy the Web App URL
//   9. Paste that URL into config.js as MATTRICS_CONFIG.SHEET_URL
// ─────────────────────────────────────────────────────────────

function doGet(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const data  = sheet.getDataRange().getValues();

  if (data.length < 2) {
    return jsonResponse({ error: "Sheet is empty or has no data rows." });
  }

  const headers = data[0].map(h => String(h).trim());
  const rows    = data.slice(1)
    .filter(row => row.some(cell => cell !== "" && cell !== null))
    .map(row => {
      const obj = {};
      headers.forEach((h, i) => {
        let val = row[i];
        if (val instanceof Date) {
          val = val.toISOString();
        }
        obj[h] = val === null || val === undefined ? "" : String(val);
      });
      return obj;
    });

  return jsonResponse({ headers, rows, count: rows.length });
}

function jsonResponse(data) {
  // NOTE: Apps Script Web Apps deployed as "Anyone" automatically handle
  // CORS for browser requests. If you still get CORS errors when opening
  // the HTML as a local file, make sure you created a NEW deployment
  // (not updated an existing one) after pasting this code.
  const output = ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
  return output;
}
