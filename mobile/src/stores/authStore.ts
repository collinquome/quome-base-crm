import { create } from 'zustand';
import { login as apiLogin, logout as apiLogout, getToken, clearToken } from '../api/client';
import type { User } from '../types';

interface AuthState {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  token: null,
  isLoading: true,
  isAuthenticated: false,

  login: async (email: string, password: string) => {
    const token = await apiLogin(email, password);
    set({ token, isAuthenticated: true, isLoading: false });
  },

  logout: async () => {
    await apiLogout();
    set({ user: null, token: null, isAuthenticated: false });
  },

  checkAuth: async () => {
    const token = await getToken();
    if (token) {
      set({ token, isAuthenticated: true, isLoading: false });
    } else {
      set({ isLoading: false, isAuthenticated: false });
    }
  },
}));
