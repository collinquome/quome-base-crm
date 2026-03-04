import { useEffect, useRef, useState } from 'react';
import NetInfo from '@react-native-community/netinfo';
import { getApiClient } from '../api/client';
import { getQueue, removeFromQueue } from '../utils/offlineCache';

export function useOfflineSync() {
  const [isOnline, setIsOnline] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const syncRef = useRef(false);

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      const online = !!(state.isConnected && state.isInternetReachable !== false);
      setIsOnline(online);

      // When coming back online, flush the queue
      if (online && !syncRef.current) {
        flushQueue();
      }
    });

    return () => unsubscribe();
  }, []);

  const flushQueue = async () => {
    if (syncRef.current) return;
    syncRef.current = true;
    setSyncing(true);

    try {
      const queue = await getQueue();
      const client = getApiClient();

      for (const action of queue) {
        try {
          switch (action.method) {
            case 'POST':
              await client.post(action.url, action.data);
              break;
            case 'PUT':
              await client.put(action.url, action.data);
              break;
            case 'DELETE':
              await client.delete(action.url);
              break;
          }
          await removeFromQueue(action.id);
        } catch {
          // If individual action fails, skip it and continue
          break;
        }
      }
    } finally {
      syncRef.current = false;
      setSyncing(false);
    }
  };

  return { isOnline, syncing, flushQueue };
}
