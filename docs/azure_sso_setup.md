# Azure SSO Setup (Microsoft Entra ID)

This document covers how to enable Microsoft Entra ID (Azure AD) single sign-on for the Union Bay Risk CRM. When enabled, a "Sign in with Microsoft" button appears on the login page, allowing users with authorized Microsoft accounts to log in without entering a password.

## Prerequisites

- A Microsoft Entra ID (Azure AD) tenant
- An app registration in the Entra admin portal
- The CRM deployed and accessible via HTTPS

## Entra App Registration

1. Go to the [Azure Portal](https://portal.azure.com) > Microsoft Entra ID > App registrations
2. Click **New registration**
3. Set the **Redirect URI** to: `https://<your-crm-domain>/admin/auth/azure/callback`
   - For local dev: `http://localhost:8190/admin/auth/azure/callback`
4. Under **Certificates & secrets**, create a new client secret and copy the **Value** (you won't see it again)
5. Note the **Application (client) ID** and **Directory (tenant) ID** from the Overview page

## Environment Variables

Add these to your `.env` or Railway environment:

```env
# --- Azure SSO (Microsoft Entra ID) ---
# Set to true to show "Sign in with Microsoft" on the login page
AZURE_SSO_ENABLED=true

# From your Entra app registration (Overview page)
AZURE_CLIENT_ID=your-application-client-id
AZURE_TENANT_ID=your-directory-tenant-id

# From Certificates & secrets > Client secrets
AZURE_CLIENT_SECRET=your-secret-value

# Must match the Redirect URI registered in Entra exactly
AZURE_REDIRECT_URI=https://cornerstone-crm.quome.dev/admin/auth/azure/callback

# Only allow users with this email domain (optional, recommended)
AZURE_ALLOWED_DOMAIN=unionbayrisk.com
```

### Variable Reference

| Variable | Required | Description |
|----------|----------|-------------|
| `AZURE_SSO_ENABLED` | Yes | `true` to enable, omit or `false` to disable |
| `AZURE_CLIENT_ID` | Yes | Application (client) ID from Entra |
| `AZURE_TENANT_ID` | Yes | Directory (tenant) ID from Entra |
| `AZURE_CLIENT_SECRET` | Yes | Client secret value from Entra |
| `AZURE_REDIRECT_URI` | Yes | Callback URL registered in Entra |
| `AZURE_ALLOWED_DOMAIN` | No | Restrict login to this email domain |

## How It Works

1. User clicks "Sign in with Microsoft" on the login page
2. Browser redirects to Microsoft's login page (scoped to your tenant)
3. User authenticates with their Microsoft account
4. Microsoft redirects back to the callback URL with an auth code
5. The CRM exchanges the code for user info (name, email)
6. If the user's email matches an existing CRM user, they are logged in
7. If no matching user exists but the domain is allowed, a new user account is created automatically with default permissions
8. If the domain doesn't match `AZURE_ALLOWED_DOMAIN`, login is rejected

## User Matching

- SSO matches users by **email address** — the email from Microsoft must match a CRM user's email
- If no CRM user exists with that email, one is created automatically (with default role/permissions)
- The traditional email/password login continues to work alongside SSO
- Users can log in via either method

## Deployment

### Existing deployment (no SSO)
No changes needed. When `AZURE_SSO_ENABLED` is not set or is `false`, the login page behaves exactly as before.

### New deployment with SSO
1. Set the environment variables listed above in Railway
2. Deploy — the "Sign in with Microsoft" button will appear on the login page
3. Test by clicking the button and signing in with a `@unionbayrisk.com` account

### Railway-specific notes
- Add all `AZURE_*` variables in the Railway dashboard under your service's Variables tab
- The `AZURE_REDIRECT_URI` must use the public domain (e.g., `https://cornerstone-crm.quome.dev/admin/auth/azure/callback`)
- No additional Railway services or containers are needed

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "SSO is not configured" error | Verify all `AZURE_*` env vars are set |
| Redirect URI mismatch error from Microsoft | Ensure `AZURE_REDIRECT_URI` exactly matches what's in Entra (including trailing slash or lack thereof) |
| "Email domain not allowed" | Check `AZURE_ALLOWED_DOMAIN` matches the user's email domain |
| "No matching CRM user" with auto-create disabled | Create the user manually in CRM Settings > Users first |
| Button doesn't appear on login | Confirm `AZURE_SSO_ENABLED=true` is set and clear config cache (`php artisan config:clear`) |
