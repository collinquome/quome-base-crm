/**
 * Gmail email signature scraper.
 */

(function () {
  const contact = { source: 'gmail' };

  // Try to find the currently open email's sender
  const senderEl = document.querySelector('.gD [email]');
  if (senderEl) {
    contact.email = senderEl.getAttribute('email');
    contact.name = senderEl.getAttribute('name') || senderEl.textContent.trim();
  }

  // Try to parse the email body for signature info
  const emailBody = document.querySelector('.a3s.aiL');
  if (emailBody) {
    const text = emailBody.innerText || '';

    // Phone patterns in signature area (last ~500 chars)
    const sigArea = text.slice(-500);
    const phoneRegex = /(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/g;
    const phones = sigArea.match(phoneRegex);
    if (phones?.length) {
      contact.phone = phones[0];
    }

    // Job title heuristic: line before or after the name in signature
    const lines = sigArea.split('\n').map((l) => l.trim()).filter(Boolean);
    if (lines.length >= 2) {
      // Often: Name\nTitle\nCompany
      for (let i = 0; i < Math.min(lines.length, 4); i++) {
        const line = lines[i];
        if (line.includes('|') || line.includes(',')) {
          const parts = line.split(/[|,]/).map((p) => p.trim());
          if (parts.length >= 2 && !parts[0].includes('@')) {
            contact.jobTitle = contact.jobTitle || parts[0];
            contact.organization = contact.organization || parts[1];
          }
        }
      }
    }
  }

  window.__crmScrapedContact = contact;
})();
