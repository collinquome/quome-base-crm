/**
 * Generic page scraper - extracts contact info from any web page.
 */

(function () {
  const contact = {};

  // Schema.org structured data
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
      // skip
    }
  }

  // Email from mailto links
  if (!contact.email) {
    const mailto = document.querySelector('a[href^="mailto:"]');
    if (mailto) {
      contact.email = mailto.href.replace('mailto:', '').split('?')[0];
    }
  }

  // Email from page text
  if (!contact.email) {
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
    const bodyText = document.body?.innerText || '';
    const emails = bodyText.match(emailRegex);
    if (emails?.length) {
      contact.email = emails[0];
    }
  }

  // Phone from tel links
  if (!contact.phone) {
    const tel = document.querySelector('a[href^="tel:"]');
    if (tel) {
      contact.phone = tel.href.replace('tel:', '');
    }
  }

  // Social links
  const socialLinks = [];
  const socialDomains = ['linkedin.com', 'twitter.com', 'x.com', 'facebook.com', 'github.com'];
  document.querySelectorAll('a[href]').forEach((a) => {
    for (const domain of socialDomains) {
      if (a.href.includes(domain)) {
        socialLinks.push(a.href);
        break;
      }
    }
  });
  if (socialLinks.length) {
    contact.socialLinks = socialLinks;
  }

  window.__crmScrapedContact = contact;
})();
