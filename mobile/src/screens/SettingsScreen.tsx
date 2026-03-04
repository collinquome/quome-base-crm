import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { useAuthStore } from '../stores/authStore';

export default function SettingsScreen() {
  const logout = useAuthStore((s: any) => s.logout);

  return (
    <View style={styles.container}>
      <Text style={styles.text}>Settings</Text>
      <TouchableOpacity style={styles.logoutButton} onPress={logout}>
        <Text style={styles.logoutText}>Sign Out</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f9fafb' },
  text: { fontSize: 24, fontWeight: 'bold', color: '#111827', marginBottom: 24 },
  logoutButton: {
    backgroundColor: '#ef4444',
    borderRadius: 8,
    paddingHorizontal: 24,
    paddingVertical: 12,
  },
  logoutText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
