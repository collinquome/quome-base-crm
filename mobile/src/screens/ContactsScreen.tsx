import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  Linking,
  Alert,
} from 'react-native';
import { getApiClient } from '../api/client';
import type { Contact } from '../types';

export default function ContactsScreen() {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);

  const fetchContacts = useCallback(async (pageNum = 1, searchTerm = '', append = false) => {
    try {
      const client = getApiClient();
      const params: Record<string, string | number> = { page: pageNum, limit: 20 };
      if (searchTerm) params.search = searchTerm;

      const res = await client.get('/contacts', { params });
      const data = res.data.data || [];
      const meta = res.data.meta;

      if (append) {
        setContacts((prev) => [...prev, ...data]);
      } else {
        setContacts(data);
      }

      setHasMore(meta ? pageNum < meta.last_page : data.length === 20);
      setPage(pageNum);
    } catch (error: any) {
      Alert.alert('Error', 'Failed to load contacts');
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchContacts(1, search).finally(() => setLoading(false));
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchContacts(1, search);
    setRefreshing(false);
  };

  const handleSearch = (text: string) => {
    setSearch(text);
    setLoading(true);
    fetchContacts(1, text).finally(() => setLoading(false));
  };

  const handleLoadMore = () => {
    if (!hasMore || loadingMore) return;
    setLoadingMore(true);
    fetchContacts(page + 1, search, true).finally(() => setLoadingMore(false));
  };

  const getEmail = (contact: Contact): string | null => {
    if (!contact.emails || contact.emails.length === 0) return null;
    const e = contact.emails[0];
    return typeof e === 'object' ? e.value : e;
  };

  const getPhone = (contact: Contact): string | null => {
    if (!contact.contact_numbers || contact.contact_numbers.length === 0) return null;
    const n = contact.contact_numbers[0];
    return typeof n === 'object' ? n.value : n;
  };

  const handleCall = (phone: string) => {
    Linking.openURL(`tel:${phone}`);
  };

  const handleEmail = (email: string) => {
    Linking.openURL(`mailto:${email}`);
  };

  const renderContact = ({ item }: { item: Contact }) => {
    const email = getEmail(item);
    const phone = getPhone(item);

    return (
      <View style={styles.contactCard}>
        <View style={styles.contactInfo}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {item.name?.charAt(0)?.toUpperCase() || '?'}
            </Text>
          </View>
          <View style={styles.contactDetails}>
            <Text style={styles.contactName}>{item.name}</Text>
            {email && <Text style={styles.contactSub}>{email}</Text>}
            {phone && <Text style={styles.contactSub}>{phone}</Text>}
          </View>
        </View>
        <View style={styles.actions}>
          {phone && (
            <TouchableOpacity style={styles.actionBtn} onPress={() => handleCall(phone)}>
              <Text style={styles.actionIcon}>📞</Text>
            </TouchableOpacity>
          )}
          {email && (
            <TouchableOpacity style={styles.actionBtn} onPress={() => handleEmail(email)}>
              <Text style={styles.actionIcon}>✉️</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <TextInput
        style={styles.searchInput}
        placeholder="Search contacts..."
        value={search}
        onChangeText={handleSearch}
        autoCorrect={false}
      />

      {loading && !refreshing ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
        </View>
      ) : (
        <FlatList
          data={contacts}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderContact}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />
          }
          onEndReached={handleLoadMore}
          onEndReachedThreshold={0.3}
          ListFooterComponent={
            loadingMore ? <ActivityIndicator style={styles.footer} color="#2563eb" /> : null
          }
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>No contacts found</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  searchInput: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    margin: 16,
    marginBottom: 8,
  },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  contactCard: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#fff',
    padding: 14,
    marginHorizontal: 16,
    marginVertical: 4,
    borderRadius: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 2,
    elevation: 1,
  },
  contactInfo: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#2563eb',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  avatarText: { color: '#fff', fontSize: 18, fontWeight: '600' },
  contactDetails: { flex: 1 },
  contactName: { fontSize: 16, fontWeight: '600', color: '#111827' },
  contactSub: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  actions: { flexDirection: 'row', gap: 8 },
  actionBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#f3f4f6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  actionIcon: { fontSize: 16 },
  emptyText: { fontSize: 16, color: '#6b7280' },
  footer: { paddingVertical: 16 },
});
