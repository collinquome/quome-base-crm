import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  TextInput,
  Modal,
  Alert,
} from 'react-native';
import { getApiClient } from '../api/client';
import type { Activity } from '../types';

const ACTIVITY_ICONS: Record<string, string> = {
  call: '📞',
  meeting: '🤝',
  task: '✅',
  note: '📝',
  email: '✉️',
};

export default function ActivitiesScreen() {
  const [activities, setActivities] = useState<Activity[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreate, setShowCreate] = useState(false);
  const [newType, setNewType] = useState<string>('call');
  const [newTitle, setNewTitle] = useState('');
  const [newDesc, setNewDesc] = useState('');
  const [creating, setCreating] = useState(false);

  const fetchActivities = useCallback(async () => {
    try {
      const client = getApiClient();
      const res = await client.get('/activities', { params: { limit: 50 } });
      setActivities(res.data.data || []);
    } catch {
      Alert.alert('Error', 'Failed to load activities');
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchActivities().finally(() => setLoading(false));
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchActivities();
    setRefreshing(false);
  };

  const handleCreate = async () => {
    if (!newTitle.trim()) {
      Alert.alert('Error', 'Please enter a title');
      return;
    }
    setCreating(true);
    try {
      const client = getApiClient();
      await client.post('/activities', {
        title: newTitle.trim(),
        type: newType,
        description: newDesc.trim() || undefined,
        is_done: false,
      });
      setShowCreate(false);
      setNewTitle('');
      setNewDesc('');
      await fetchActivities();
    } catch {
      Alert.alert('Error', 'Failed to create activity');
    } finally {
      setCreating(false);
    }
  };

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  };

  const renderActivity = ({ item }: { item: Activity }) => (
    <View style={[styles.activityCard, item.is_done && styles.activityDone]}>
      <Text style={styles.activityIcon}>{ACTIVITY_ICONS[item.type] || '📋'}</Text>
      <View style={styles.activityInfo}>
        <Text style={[styles.activityTitle, item.is_done && styles.titleDone]} numberOfLines={1}>
          {item.title}
        </Text>
        {item.description ? (
          <Text style={styles.activityDesc} numberOfLines={2}>{item.description}</Text>
        ) : null}
        <Text style={styles.activityDate}>
          {item.type} {item.schedule_from ? `· ${formatDate(item.schedule_from)}` : ''}
        </Text>
      </View>
      {item.is_done && <Text style={styles.doneBadge}>Done</Text>}
    </View>
  );

  const typeOptions = ['call', 'meeting', 'task', 'note', 'email'];

  return (
    <View style={styles.container}>
      {loading && !refreshing ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
        </View>
      ) : (
        <FlatList
          data={activities}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderActivity}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />
          }
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>No activities yet</Text>
            </View>
          }
        />
      )}

      {/* FAB */}
      <TouchableOpacity style={styles.fab} onPress={() => setShowCreate(true)}>
        <Text style={styles.fabText}>+</Text>
      </TouchableOpacity>

      {/* Create Modal */}
      <Modal visible={showCreate} animationType="slide" presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity onPress={() => setShowCreate(false)}>
              <Text style={styles.cancelText}>Cancel</Text>
            </TouchableOpacity>
            <Text style={styles.modalTitle}>New Activity</Text>
            <TouchableOpacity onPress={handleCreate} disabled={creating}>
              <Text style={[styles.saveText, creating && styles.disabled]}>
                {creating ? 'Saving...' : 'Save'}
              </Text>
            </TouchableOpacity>
          </View>

          <View style={styles.typeSelector}>
            {typeOptions.map((type) => (
              <TouchableOpacity
                key={type}
                style={[styles.typeChip, newType === type && styles.typeChipActive]}
                onPress={() => setNewType(type)}
              >
                <Text style={styles.typeEmoji}>{ACTIVITY_ICONS[type]}</Text>
                <Text style={[styles.typeLabel, newType === type && styles.typeLabelActive]}>
                  {type.charAt(0).toUpperCase() + type.slice(1)}
                </Text>
              </TouchableOpacity>
            ))}
          </View>

          <TextInput
            style={styles.modalInput}
            placeholder="Title"
            value={newTitle}
            onChangeText={setNewTitle}
            autoFocus
          />

          <TextInput
            style={[styles.modalInput, styles.modalTextArea]}
            placeholder="Description (optional)"
            value={newDesc}
            onChangeText={setNewDesc}
            multiline
            numberOfLines={4}
          />
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 60 },
  list: { paddingBottom: 80 },
  activityCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginVertical: 4,
    borderRadius: 10,
    padding: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 2,
    elevation: 1,
  },
  activityDone: { opacity: 0.6 },
  activityIcon: { fontSize: 24, marginRight: 12 },
  activityInfo: { flex: 1 },
  activityTitle: { fontSize: 15, fontWeight: '600', color: '#111827' },
  titleDone: { textDecorationLine: 'line-through', color: '#6b7280' },
  activityDesc: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  activityDate: { fontSize: 12, color: '#9ca3af', marginTop: 4, textTransform: 'capitalize' },
  doneBadge: {
    fontSize: 11,
    fontWeight: '600',
    color: '#059669',
    backgroundColor: '#d1fae5',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  emptyText: { fontSize: 16, color: '#6b7280' },
  fab: {
    position: 'absolute',
    bottom: 24,
    right: 24,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#2563eb',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 6,
  },
  fabText: { fontSize: 28, color: '#fff', lineHeight: 30 },
  modalContainer: { flex: 1, backgroundColor: '#f9fafb', paddingTop: 16 },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  cancelText: { fontSize: 16, color: '#6b7280' },
  modalTitle: { fontSize: 17, fontWeight: '600', color: '#111827' },
  saveText: { fontSize: 16, color: '#2563eb', fontWeight: '600' },
  disabled: { opacity: 0.5 },
  typeSelector: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 16,
    gap: 8,
  },
  typeChip: {
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: '#e5e7eb',
    flex: 1,
  },
  typeChipActive: { backgroundColor: '#dbeafe' },
  typeEmoji: { fontSize: 20 },
  typeLabel: { fontSize: 11, color: '#374151', marginTop: 4 },
  typeLabelActive: { color: '#2563eb', fontWeight: '600' },
  modalInput: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 14,
    fontSize: 16,
    marginHorizontal: 16,
    marginBottom: 16,
  },
  modalTextArea: { minHeight: 100, textAlignVertical: 'top' },
});
