import axios, { AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'crm_auth_token';
const BASE_URL_KEY = 'crm_base_url';

const DEFAULT_BASE_URL = 'http://localhost:8190';

let apiClient: AxiosInstance;

export async function getBaseUrl(): Promise<string> {
  const stored = await SecureStore.getItemAsync(BASE_URL_KEY);
  return stored || DEFAULT_BASE_URL;
}

export async function setBaseUrl(url: string): Promise<void> {
  await SecureStore.setItemAsync(BASE_URL_KEY, url);
  apiClient.defaults.baseURL = `${url}/api/v1`;
}

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function setToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function clearToken(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}

function createClient(): AxiosInstance {
  const client = axios.create({
    baseURL: `${DEFAULT_BASE_URL}/api/v1`,
    timeout: 15000,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
  });

  client.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
    const token = await getToken();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  });

  client.interceptors.response.use(
    (response: any) => response,
    async (error: any) => {
      if (error.response?.status === 401) {
        await clearToken();
      }
      return Promise.reject(error);
    },
  );

  return client;
}

export function getApiClient(): AxiosInstance {
  if (!apiClient) {
    apiClient = createClient();
  }
  return apiClient;
}

// Auth API
export async function login(email: string, password: string): Promise<string> {
  const client = getApiClient();
  const response = await client.post('/auth/login', { email, password });
  const token = response.data.token || response.data.data?.token;
  await setToken(token);
  return token;
}

export async function logout(): Promise<void> {
  try {
    const client = getApiClient();
    await client.post('/auth/logout');
  } finally {
    await clearToken();
  }
}
