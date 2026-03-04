/**
 * LinkedIn profile scraper.
 */

(function () {
  const contact = { source: 'linkedin' };

  // Profile name
  const nameEl = document.querySelector('.text-heading-xlarge') ||
    document.querySelector('h1.top-card-layout__title');
  if (nameEl) {
    contact.name = nameEl.textContent.trim();
  }

  // Headline / job title
  const headlineEl = document.querySelector('.text-body-medium.break-words') ||
    document.querySelector('.top-card-layout__headline');
  if (headlineEl) {
    contact.jobTitle = headlineEl.textContent.trim();
  }

  // Location
  const locationEl = document.querySelector('.text-body-small.inline.t-black--light.break-words') ||
    document.querySelector('.top-card__subline-item');
  if (locationEl) {
    contact.location = locationEl.textContent.trim();
  }

  // Profile URL
  contact.profileUrl = window.location.href.split('?')[0];

  // Company from experience section (first entry)
  const companyEl = document.querySelector('.experience-group-header__company') ||
    document.querySelector('.pv-entity__secondary-title');
  if (companyEl) {
    contact.organization = companyEl.textContent.trim();
  }

  window.__crmScrapedContact = contact;
})();
