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
import type { NextAction } from '../types';

const PRIORITY_COLORS: Record<string, string> = {
  urgent: '#dc2626',
  high: '#f97316',
  normal: '#eab308',
  low: '#22c55e',
};

const ACTION_TYPE_LABELS: Record<string, string> = {
  call: '📞 Call',
  email: '✉️ Email',
  meeting: '🤝 Meeting',
  task: '✅ Task',
  custom: '📋 Action',
};

export default function ActionStreamScreen() {
  const [actions, setActions] = useState<NextAction[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<string | null>(null);

  const fetchActions = useCallback(async () => {
    try {
      const client = getApiClient();
      const params: Record<string, string | number> = { limit: 50 };
      if (filter) params.type = filter;
      const res = await client.get('/action-stream', { params });
      setActions(res.data.data || []);
    } catch {
      Alert.alert('Error', 'Failed to load actions');
    }
  }, [filter]);

  useEffect(() => {
    setLoading(true);
    fetchActions().finally(() => setLoading(false));
  }, [filter]);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchActions();
    setRefreshing(false);
  };

  const handleComplete = async (action: NextAction) => {
    Alert.alert('Complete Action', `Mark "${action.description}" as done?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Complete',
        onPress: async () => {
          try {
            const client = getApiClient();
            await client.put(`/action-stream/${action.id}`, { status: 'completed' });
            setActions((prev) => prev.filter((a) => a.id !== action.id));
          } catch {
            Alert.alert('Error', 'Failed to complete action');
          }
        },
      },
    ]);
  };

  const handleSnooze = async (action: NextAction) => {
    try {
      const client = getApiClient();
      // Snooze to tomorrow
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      await client.put(`/action-stream/${action.id}`, {
        status: 'snoozed',
        snoozed_until: tomorrow.toISOString().split('T')[0],
      });
      setActions((prev) => prev.filter((a) => a.id !== action.id));
    } catch {
      Alert.alert('Error', 'Failed to snooze action');
    }
  };

  const getPriorityColor = (priority: string) => {
    return PRIORITY_COLORS[priority] || '#6b7280';
  };

  const isOverdue = (action: NextAction) => {
    if (!action.due_date) return false;
    return new Date(action.due_date) < new Date(new Date().toDateString());
  };

  const formatDueDate = (action: NextAction) => {
    if (!action.due_date) return 'No due date';
    const date = new Date(action.due_date);
    const today = new Date(new Date().toDateString());
    const diff = Math.floor((date.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (diff < 0) return `Overdue by ${Math.abs(diff)}d`;
    if (diff === 0) return 'Due today';
    if (diff === 1) return 'Due tomorrow';
    if (diff <= 7) return `Due in ${diff}d`;
    return date.toLocaleDateString();
  };

  const renderAction = ({ item }: { item: NextAction }) => {
    const overdue = isOverdue(item);
    const priorityColor = overdue ? '#dc2626' : getPriorityColor(item.priority);

    return (
      <View style={[styles.actionCard, { borderLeftColor: priorityColor }]}>
        <View style={styles.actionHeader}>
          <Text style={styles.actionType}>
            {ACTION_TYPE_LABELS[item.action_type] || item.action_type}
          </Text>
          <Text style={[styles.dueDate, overdue && styles.overdue]}>
            {formatDueDate(item)}
          </Text>
        </View>
        <Text style={styles.actionDesc} numberOfLines={2}>{item.description}</Text>
        <View style={styles.actionFooter}>
          <View style={[styles.priorityBadge, { backgroundColor: priorityColor + '20' }]}>
            <Text style={[styles.priorityText, { color: priorityColor }]}>
              {item.priority}
            </Text>
          </View>
          <View style={styles.actionButtons}>
            <TouchableOpacity style={styles.snoozeBtn} onPress={() => handleSnooze(item)}>
              <Text style={styles.snoozeBtnText}>Snooze</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.completeBtn} onPress={() => handleComplete(item)}>
              <Text style={styles.completeBtnText}>Done</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };

  const filterTypes = [null, 'call', 'email', 'meeting', 'task'];
  const filterLabels: Record<string, string> = {
    null: 'All',
    call: 'Calls',
    email: 'Emails',
    meeting: 'Meetings',
    task: 'Tasks',
  };

  return (
    <View style={styles.container}>
      {/* Filter bar */}
      <View style={styles.filterBar}>
        {filterTypes.map((type) => (
          <TouchableOpacity
            key={type || 'all'}
            style={[styles.filterChip, filter === type && styles.filterChipActive]}
            onPress={() => setFilter(type)}
          >
            <Text style={[styles.filterText, filter === type && styles.filterTextActive]}>
              {filterLabels[String(type)]}
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
          data={actions}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderAction}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />
          }
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>No pending actions</Text>
              <Text style={styles.emptySubtext}>You're all caught up!</Text>
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
  filterBar: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 8,
  },
  filterChip: {
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: '#e5e7eb',
  },
  filterChipActive: { backgroundColor: '#2563eb' },
  filterText: { fontSize: 13, color: '#374151', fontWeight: '500' },
  filterTextActive: { color: '#fff' },
  list: { paddingBottom: 20 },
  actionCard: {
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginVertical: 4,
    borderRadius: 10,
    padding: 14,
    borderLeftWidth: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 2,
    elevation: 1,
  },
  actionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  actionType: { fontSize: 13, fontWeight: '600', color: '#374151' },
  dueDate: { fontSize: 12, color: '#6b7280' },
  overdue: { color: '#dc2626', fontWeight: '600' },
  actionDesc: { fontSize: 15, color: '#111827', marginBottom: 10 },
  actionFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  priorityBadge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 12 },
  priorityText: { fontSize: 11, fontWeight: '600', textTransform: 'uppercase' },
  actionButtons: { flexDirection: 'row', gap: 8 },
  snoozeBtn: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6, backgroundColor: '#f3f4f6' },
  snoozeBtnText: { fontSize: 13, color: '#6b7280', fontWeight: '500' },
  completeBtn: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6, backgroundColor: '#059669' },
  completeBtnText: { fontSize: 13, color: '#fff', fontWeight: '600' },
  emptyText: { fontSize: 18, fontWeight: '600', color: '#111827' },
  emptySubtext: { fontSize: 14, color: '#6b7280', marginTop: 4 },
});
