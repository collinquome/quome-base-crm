import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export default function ActionStreamScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.text}>Action Stream</Text>
      <Text style={styles.subtext}>Your prioritized next actions will appear here.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f9fafb' },
  text: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
  subtext: { fontSize: 14, color: '#6b7280', marginTop: 8 },
});
