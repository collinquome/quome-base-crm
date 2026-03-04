<template>
  <form @submit.prevent="login" class="login-form">
    <div class="field">
      <label>CRM URL</label>
      <input v-model="apiUrl" type="url" placeholder="https://crm.example.com" required />
    </div>
    <div class="field">
      <label>Email</label>
      <input v-model="email" type="email" placeholder="admin@example.com" required />
    </div>
    <div class="field">
      <label>Password</label>
      <input v-model="password" type="password" required />
    </div>
    <button type="submit" :disabled="loading">
      {{ loading ? 'Signing in...' : 'Sign In' }}
    </button>
    <p v-if="error" class="error">{{ error }}</p>
  </form>
</template>

<script>
export default {
  name: 'LoginForm',
  emits: ['login'],

  data() {
    return {
      apiUrl: '',
      email: '',
      password: '',
      loading: false,
      error: '',
    };
  },

  methods: {
    async login() {
      this.loading = true;
      this.error = '';

      try {
        const url = this.apiUrl.replace(/\/$/, '');
        const res = await fetch(`${url}/api/v1/auth/login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ email: this.email, password: this.password }),
        });

        if (!res.ok) {
          this.error = 'Invalid credentials';
          return;
        }

        const body = await res.json();
        const token = body.token || body.data?.token;
        this.$emit('login', { token, apiUrl: url });
      } catch {
        this.error = 'Cannot reach CRM server';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.login-form .field {
  margin-bottom: 10px;
}

.login-form label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
  color: #475569;
}

.login-form input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  box-sizing: border-box;
}

.login-form input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.login-form button {
  width: 100%;
  padding: 9px;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  margin-top: 4px;
}

.login-form button:hover { background: #4f46e5; }
.login-form button:disabled { opacity: 0.6; cursor: not-allowed; }
.error { color: #dc2626; font-size: 12px; margin-top: 8px; }
</style>
