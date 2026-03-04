<template>
  <div class="popup">
    <header class="popup-header">
      <h1>CRM Lead Clipper</h1>
    </header>

    <LoginForm v-if="!isAuthenticated" @login="handleLogin" />
    <CaptureForm
      v-else
      :contact="scrapedContact"
      :api-url="apiUrl"
      :token="token"
      @capture="handleCapture"
      @logout="handleLogout"
    />

    <div v-if="statusMessage" class="status" :class="statusType">
      {{ statusMessage }}
    </div>
  </div>
</template>

<script>
import LoginForm from './LoginForm.vue';
import CaptureForm from './CaptureForm.vue';

export default {
  name: 'Popup',
  components: { LoginForm, CaptureForm },

  data() {
    return {
      isAuthenticated: false,
      token: '',
      apiUrl: '',
      scrapedContact: null,
      statusMessage: '',
      statusType: 'info',
    };
  },

  async created() {
    const stored = await chrome.storage.local.get(['token', 'apiUrl']);
    if (stored.token && stored.apiUrl) {
      this.token = stored.token;
      this.apiUrl = stored.apiUrl;
      this.isAuthenticated = true;
    }
    this.requestScrape();
  },

  methods: {
    async handleLogin({ token, apiUrl }) {
      this.token = token;
      this.apiUrl = apiUrl;
      this.isAuthenticated = true;
      await chrome.storage.local.set({ token, apiUrl });
    },

    async handleLogout() {
      this.isAuthenticated = false;
      this.token = '';
      this.apiUrl = '';
      await chrome.storage.local.remove(['token', 'apiUrl']);
    },

    async handleCapture(contact) {
      this.statusMessage = 'Saving contact...';
      this.statusType = 'info';

      try {
        const res = await fetch(`${this.apiUrl}/api/v1/contacts`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify(contact),
        });

        if (res.ok) {
          this.statusMessage = 'Contact saved!';
          this.statusType = 'success';
        } else {
          const body = await res.json();
          this.statusMessage = body.message || 'Failed to save contact';
          this.statusType = 'error';
        }
      } catch (err) {
        this.statusMessage = 'Network error';
        this.statusType = 'error';
      }
    },

    async requestScrape() {
      try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        if (!tab?.id) return;

        const results = await chrome.scripting.executeScript({
          target: { tabId: tab.id },
          func: () => window.__crmScrapedContact || null,
        });

        if (results?.[0]?.result) {
          this.scrapedContact = results[0].result;
        }
      } catch {
        // Content script may not be injected on this page
      }
    },
  },
};
</script>

<style>
.popup {
  width: 360px;
  min-height: 200px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  font-size: 14px;
  color: #1a1a2e;
  padding: 16px;
}

.popup-header h1 {
  font-size: 16px;
  margin: 0 0 12px 0;
  color: #6366f1;
}

.status {
  margin-top: 12px;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 13px;
}

.status.info { background: #eff6ff; color: #1d4ed8; }
.status.success { background: #f0fdf4; color: #16a34a; }
.status.error { background: #fef2f2; color: #dc2626; }
</style>
