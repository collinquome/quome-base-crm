<template>
  <form @submit.prevent="submit" class="capture-form">
    <div class="field">
      <label>Name</label>
      <input v-model="form.name" type="text" placeholder="Full name" required />
    </div>
    <div class="field">
      <label>Email</label>
      <input v-model="form.email" type="email" placeholder="email@example.com" />
    </div>
    <div class="field">
      <label>Phone</label>
      <input v-model="form.phone" type="tel" placeholder="+1 (555) 000-0000" />
    </div>
    <div class="field">
      <label>Company</label>
      <input v-model="form.organization" type="text" placeholder="Company name" />
    </div>
    <div class="field">
      <label>Job Title</label>
      <input v-model="form.jobTitle" type="text" placeholder="Job title" />
    </div>
    <div class="field">
      <label>Tags</label>
      <input v-model="form.tags" type="text" placeholder="tag1, tag2" />
    </div>
    <div class="actions">
      <button type="submit">Save Contact</button>
      <button type="button" class="secondary" @click="$emit('logout')">Logout</button>
    </div>
  </form>
</template>

<script>
export default {
  name: 'CaptureForm',
  props: {
    contact: { type: Object, default: null },
    apiUrl: { type: String, required: true },
    token: { type: String, required: true },
  },
  emits: ['capture', 'logout'],

  data() {
    return {
      form: {
        name: '',
        email: '',
        phone: '',
        organization: '',
        jobTitle: '',
        tags: '',
      },
    };
  },

  watch: {
    contact: {
      immediate: true,
      handler(val) {
        if (val) {
          this.form.name = val.name || '';
          this.form.email = val.email || '';
          this.form.phone = val.phone || '';
          this.form.organization = val.organization || '';
          this.form.jobTitle = val.jobTitle || '';
        }
      },
    },
  },

  methods: {
    submit() {
      const payload = {
        name: this.form.name,
      };

      if (this.form.email) {
        payload.emails = [{ value: this.form.email, label: 'work' }];
      }
      if (this.form.phone) {
        payload.contact_numbers = [{ value: this.form.phone, label: 'work' }];
      }

      this.$emit('capture', payload);
    },
  },
};
</script>

<style scoped>
.capture-form .field {
  margin-bottom: 8px;
}

.capture-form label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 3px;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.capture-form input {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  box-sizing: border-box;
}

.capture-form input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.actions button {
  flex: 1;
  padding: 8px;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.actions button[type="submit"] {
  background: #6366f1;
  color: white;
}

.actions button[type="submit"]:hover { background: #4f46e5; }

.actions button.secondary {
  background: #f1f5f9;
  color: #475569;
}

.actions button.secondary:hover { background: #e2e8f0; }
</style>
