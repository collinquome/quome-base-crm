/**
 * CRM Lead Clipper - Service Worker
 *
 * Handles:
 * - API communication with CRM backend
 * - Auth token management
 * - Message passing between popup and content scripts
 */

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'SCRAPE_PAGE') {
    handleScrape(sender.tab?.id).then(sendResponse);
    return true; // keep channel open for async
  }

  if (message.type === 'CREATE_CONTACT') {
    handleCreateContact(message.data).then(sendResponse);
    return true;
  }

  if (message.type === 'GET_AUTH') {
    chrome.storage.local.get(['token', 'apiUrl'], sendResponse);
    return true;
  }
});

async function handleScrape(tabId) {
  if (!tabId) return { success: false, error: 'No active tab' };

  try {
    const results = await chrome.scripting.executeScript({
      target: { tabId },
      func: scrapeCurrentPage,
    });

    return { success: true, contact: results?.[0]?.result || null };
  } catch (err) {
    return { success: false, error: err.message };
  }
}

function scrapeCurrentPage() {
  const contact = {};

  // Try structured data (schema.org)
  const ldScripts = document.querySelectorAll('script[type="application/ld+json"]');
  for (const script of ldScripts) {
    try {
      const data = JSON.parse(script.textContent);
      if (data['@type'] === 'Person') {
        contact.name = data.name;
        contact.email = data.email;
        contact.phone = data.telephone;
        contact.jobTitle = data.jobTitle;
        contact.organization = data.worksFor?.name;
      }
    } catch {
      // ignore parse errors
    }
  }

  // Try meta tags
  const ogTitle = document.querySelector('meta[property="og:title"]');
  if (ogTitle && !contact.name) {
    contact.name = ogTitle.content;
  }

  // Scan for email patterns in page text
  if (!contact.email) {
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
    const bodyText = document.body?.innerText || '';
    const emails = bodyText.match(emailRegex);
    if (emails?.length) {
      contact.email = emails[0];
    }
  }

  // Scan for phone patterns
  if (!contact.phone) {
    const phoneRegex = /(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/g;
    const bodyText = document.body?.innerText || '';
    const phones = bodyText.match(phoneRegex);
    if (phones?.length) {
      contact.phone = phones[0];
    }
  }

  window.__crmScrapedContact = contact;
  return contact;
}

async function handleCreateContact(data) {
  const { token, apiUrl } = await chrome.storage.local.get(['token', 'apiUrl']);

  if (!token || !apiUrl) {
    return { success: false, error: 'Not authenticated' };
  }

  try {
    const res = await fetch(`${apiUrl}/api/v1/contacts`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });

    const body = await res.json();

    if (res.ok) {
      return { success: true, contact: body.data };
    }
    return { success: false, error: body.message || 'Failed to create contact' };
  } catch (err) {
    return { success: false, error: err.message };
  }
}
