import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  Linking,
  Platform,
} from 'react-native';
import { getApiClient } from '../api/client';
import type { Contact } from '../types';

interface ContactWithAddress extends Contact {
  address?: string;
  selected?: boolean;
}

export default function RoutePlannerScreen() {
  const [contacts, setContacts] = useState<ContactWithAddress[]>([]);
  const [selectedContacts, setSelectedContacts] = useState<ContactWithAddress[]>([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);

  const fetchContacts = useCallback(async () => {
    setLoading(true);
    try {
      const client = getApiClient();
      const res = await client.get('/contacts', { params: { limit: 100 } });
      const data: ContactWithAddress[] = (res.data.data || []).map((c: any) => ({
        ...c,
        address: c.address || c.organization?.address || null,
        selected: false,
      }));
      setContacts(data);
      setSearched(true);
    } catch {
      Alert.alert('Error', 'Failed to load contacts');
    } finally {
      setLoading(false);
    }
  }, []);

  const toggleContact = (contact: ContactWithAddress) => {
    const isSelected = selectedContacts.find((c) => c.id === contact.id);
    if (isSelected) {
      setSelectedContacts((prev) => prev.filter((c) => c.id !== contact.id));
    } else {
      setSelectedContacts((prev) => [...prev, contact]);
    }
  };

  const openInMaps = () => {
    if (selectedContacts.length === 0) {
      Alert.alert('Select Contacts', 'Please select at least one contact to navigate to.');
      return;
    }

    // Build waypoints string for Google Maps or Apple Maps
    const addresses = selectedContacts
      .map((c) => c.address || c.name)
      .filter(Boolean);

    if (addresses.length === 0) {
      Alert.alert('No Addresses', 'Selected contacts have no addresses available.');
      return;
    }

    if (Platform.OS === 'ios') {
      // Apple Maps with waypoints
      const destination = encodeURIComponent(addresses[addresses.length - 1]);
      const waypoints = addresses.slice(0, -1).map(encodeURIComponent).join('&saddr=');
      const url = `maps://?daddr=${destination}${waypoints ? `&saddr=${waypoints}` : ''}`;
      Linking.openURL(url).catch(() => openGoogleMaps(addresses));
    } else {
      openGoogleMaps(addresses);
    }
  };

  const openGoogleMaps = (addresses: string[]) => {
    const destination = encodeURIComponent(addresses[addresses.length - 1]);
    const waypoints = addresses.slice(0, -1).map(encodeURIComponent).join('|');
    const url = `https://www.google.com/maps/dir/?api=1&destination=${destination}${
      waypoints ? `&waypoints=${waypoints}` : ''
    }&travelmode=driving`;
    Linking.openURL(url);
  };

  const renderContact = ({ item }: { item: ContactWithAddress }) => {
    const isSelected = selectedContacts.find((c) => c.id === item.id);
    return (
      <TouchableOpacity
        style={[styles.contactItem, isSelected && styles.contactSelected]}
        onPress={() => toggleContact(item)}
      >
        <View style={[styles.checkbox, isSelected && styles.checkboxChecked]}>
          {isSelected && <Text style={styles.checkmark}>✓</Text>}
        </View>
        <View style={styles.contactInfo}>
          <Text style={styles.contactName}>{item.name}</Text>
          {item.address ? (
            <Text style={styles.contactAddress} numberOfLines={1}>{item.address}</Text>
          ) : (
            <Text style={styles.noAddress}>No address on file</Text>
          )}
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Route Planner</Text>
        <Text style={styles.subtitle}>
          Select contacts to plan your visit route
        </Text>
      </View>

      {!searched ? (
        <View style={styles.center}>
          <TouchableOpacity style={styles.loadButton} onPress={fetchContacts}>
            <Text style={styles.loadButtonText}>Load Contacts</Text>
          </TouchableOpacity>
        </View>
      ) : loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
        </View>
      ) : (
        <FlatList
          data={contacts}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderContact}
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>No contacts found</Text>
            </View>
          }
        />
      )}

      {selectedContacts.length > 0 && (
        <View style={styles.footer}>
          <Text style={styles.selectedCount}>
            {selectedContacts.length} contact{selectedContacts.length > 1 ? 's' : ''} selected
          </Text>
          <TouchableOpacity style={styles.navigateButton} onPress={openInMaps}>
            <Text style={styles.navigateText}>Open in Maps</Text>
          </TouchableOpacity>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header: { padding: 16, borderBottomWidth: 1, borderBottomColor: '#e5e7eb', backgroundColor: '#fff' },
  title: { fontSize: 22, fontWeight: '700', color: '#111827' },
  subtitle: { fontSize: 14, color: '#6b7280', marginTop: 4 },
  loadButton: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    paddingHorizontal: 24,
  },
  loadButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  list: { paddingBottom: 100 },
  contactItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  contactSelected: { backgroundColor: '#eff6ff' },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: '#d1d5db',
    marginRight: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  checkboxChecked: { backgroundColor: '#2563eb', borderColor: '#2563eb' },
  checkmark: { color: '#fff', fontSize: 14, fontWeight: 'bold' },
  contactInfo: { flex: 1 },
  contactName: { fontSize: 15, fontWeight: '600', color: '#111827' },
  contactAddress: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  noAddress: { fontSize: 13, color: '#d1d5db', fontStyle: 'italic', marginTop: 2 },
  emptyText: { fontSize: 16, color: '#6b7280' },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#fff',
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 4,
  },
  selectedCount: { fontSize: 14, color: '#374151', fontWeight: '500' },
  navigateButton: {
    backgroundColor: '#059669',
    borderRadius: 8,
    paddingVertical: 10,
    paddingHorizontal: 20,
  },
  navigateText: { color: '#fff', fontSize: 15, fontWeight: '600' },
});
