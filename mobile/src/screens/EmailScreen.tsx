import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  Alert,
} from 'react-native';
import { getApiClient } from '../api/client';

interface Email {
  id: number;
  subject: string;
  name?: string;
  from?: string[];
  reply?: string[];
  is_read: boolean;
  created_at: string;
}

export default function EmailScreen() {
  const [emails, setEmails] = useState<Email[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [folder, setFolder] = useState('inbox');

  const fetchEmails = useCallback(async (currentFolder: string) => {
    try {
      const client = getApiClient();
      const res = await client.get('/emails', { params: { route: currentFolder, limit: 30 } });
      setEmails(res.data.data || []);
    } catch {
      // Email endpoint may not exist, handle gracefully
      setEmails([]);
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchEmails(folder).finally(() => setLoading(false));
  }, [folder]);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchEmails(folder);
    setRefreshing(false);
  };

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const today = new Date();
    if (date.toDateString() === today.toDateString()) {
      return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  };

  const renderEmail = ({ item }: { item: Email }) => (
    <TouchableOpacity style={[styles.emailCard, !item.is_read && styles.unread]}>
      <View style={styles.emailHeader}>
        <Text style={[styles.emailFrom, !item.is_read && styles.bold]} numberOfLines={1}>
          {item.name || (item.from && item.from[0]) || 'Unknown'}
        </Text>
        <Text style={styles.emailDate}>{formatDate(item.created_at)}</Text>
      </View>
      <Text style={[styles.emailSubject, !item.is_read && styles.bold]} numberOfLines={1}>
        {item.subject || '(No subject)'}
      </Text>
    </TouchableOpacity>
  );

  const folders = [
    { key: 'inbox', label: 'Inbox' },
    { key: 'sent', label: 'Sent' },
    { key: 'draft', label: 'Drafts' },
  ];

  return (
    <View style={styles.container}>
      <View style={styles.folderBar}>
        {folders.map((f) => (
          <TouchableOpacity
            key={f.key}
            style={[styles.folderTab, folder === f.key && styles.folderTabActive]}
            onPress={() => setFolder(f.key)}
          >
            <Text style={[styles.folderText, folder === f.key && styles.folderTextActive]}>
              {f.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {loading && !refreshing ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
        </View>
      ) : (
        <FlatList
          data={emails}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderEmail}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />
          }
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>No emails</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 60 },
  folderBar: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  folderTab: {
    flex: 1,
    paddingVertical: 12,
    alignItems: 'center',
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  folderTabActive: { borderBottomColor: '#2563eb' },
  folderText: { fontSize: 14, color: '#6b7280', fontWeight: '500' },
  folderTextActive: { color: '#2563eb', fontWeight: '600' },
  emailCard: {
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  unread: { backgroundColor: '#eff6ff' },
  emailHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 },
  emailFrom: { fontSize: 14, color: '#374151', flex: 1, marginRight: 8 },
  emailDate: { fontSize: 12, color: '#9ca3af' },
  emailSubject: { fontSize: 15, color: '#111827' },
  bold: { fontWeight: '700' },
  emptyText: { fontSize: 16, color: '#6b7280' },
});
