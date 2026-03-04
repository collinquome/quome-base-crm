/**
 * Twitter/X profile scraper.
 */

(function () {
  const contact = { source: 'twitter' };

  // Display name
  const nameEl = document.querySelector('[data-testid="UserName"] span span');
  if (nameEl) {
    contact.name = nameEl.textContent.trim();
  }

  // Handle
  const handleEl = document.querySelector('[data-testid="UserName"] div[dir="ltr"] span');
  if (handleEl) {
    contact.handle = handleEl.textContent.trim();
  }

  // Bio
  const bioEl = document.querySelector('[data-testid="UserDescription"]');
  if (bioEl) {
    contact.bio = bioEl.textContent.trim();
  }

  // Location
  const locationEl = document.querySelector('[data-testid="UserProfileHeader_Items"] [data-testid="UserLocation"]');
  if (locationEl) {
    contact.location = locationEl.textContent.trim();
  }

  // Website
  const linkEl = document.querySelector('[data-testid="UserProfileHeader_Items"] a[href*="t.co"]');
  if (linkEl) {
    contact.website = linkEl.textContent.trim();
  }

  contact.profileUrl = window.location.href.split('?')[0];

  window.__crmScrapedContact = contact;
})();
