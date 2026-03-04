import React, { useState, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  ScrollView,
  Alert,
  ActivityIndicator,
  Image,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { getApiClient } from '../api/client';

interface ScannedContact {
  name: string;
  company: string;
  email: string;
  phone: string;
  title: string;
  address: string;
}

export default function BusinessCardScannerScreen() {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);
  const [capturedUri, setCapturedUri] = useState<string | null>(null);
  const [contact, setContact] = useState<ScannedContact>({
    name: '',
    company: '',
    email: '',
    phone: '',
    title: '',
    address: '',
  });
  const [saving, setSaving] = useState(false);
  const cameraRef = useRef<CameraView>(null);

  const handleCapture = async () => {
    if (!cameraRef.current) return;
    try {
      const photo = await cameraRef.current.takePictureAsync({ quality: 0.8 });
      if (photo) {
        setCapturedUri(photo.uri);
        setScanned(true);
        // In production, this would send the image to an OCR service
        // For now, show empty form for manual entry
        Alert.alert(
          'Card Captured',
          'OCR processing requires a cloud service API key. Please fill in the contact details manually.',
        );
      }
    } catch {
      Alert.alert('Error', 'Failed to capture image');
    }
  };

  const handleSave = async () => {
    if (!contact.name.trim()) {
      Alert.alert('Error', 'Name is required');
      return;
    }
    setSaving(true);
    try {
      const client = getApiClient();
      const data: Record<string, unknown> = { name: contact.name.trim() };

      if (contact.email) {
        data.emails = [{ value: contact.email.trim(), label: 'work' }];
      }
      if (contact.phone) {
        data.contact_numbers = [{ value: contact.phone.trim(), label: 'work' }];
      }
      if (contact.company) {
        data.organization = { name: contact.company.trim() };
      }

      await client.post('/contacts', data);
      Alert.alert('Saved', 'Contact created successfully', [
        { text: 'OK', onPress: resetScanner },
      ]);
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Failed to create contact');
    } finally {
      setSaving(false);
    }
  };

  const resetScanner = () => {
    setScanned(false);
    setCapturedUri(null);
    setContact({ name: '', company: '', email: '', phone: '', title: '', address: '' });
  };

  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#2563eb" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.permissionText}>Camera access is needed to scan business cards</Text>
        <TouchableOpacity style={styles.permissionButton} onPress={requestPermission}>
          <Text style={styles.permissionButtonText}>Grant Permission</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (scanned) {
    return (
      <ScrollView style={styles.formContainer}>
        {capturedUri && (
          <Image source={{ uri: capturedUri }} style={styles.preview} resizeMode="contain" />
        )}

        <Text style={styles.formTitle}>Contact Details</Text>

        {(['name', 'company', 'title', 'email', 'phone', 'address'] as const).map((field) => (
          <View key={field} style={styles.fieldGroup}>
            <Text style={styles.fieldLabel}>
              {field.charAt(0).toUpperCase() + field.slice(1)}
              {field === 'name' ? ' *' : ''}
            </Text>
            <TextInput
              style={styles.fieldInput}
              value={contact[field]}
              onChangeText={(text) => setContact((prev) => ({ ...prev, [field]: text }))}
              placeholder={`Enter ${field}`}
              keyboardType={field === 'email' ? 'email-address' : field === 'phone' ? 'phone-pad' : 'default'}
              autoCapitalize={field === 'email' ? 'none' : 'words'}
            />
          </View>
        ))}

        <View style={styles.formActions}>
          <TouchableOpacity style={styles.retakeButton} onPress={resetScanner}>
            <Text style={styles.retakeText}>Retake</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.saveButton, saving && styles.disabled]}
            onPress={handleSave}
            disabled={saving}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.saveText}>Save Contact</Text>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>
    );
  }

  return (
    <View style={styles.cameraContainer}>
      <CameraView ref={cameraRef} style={styles.camera} facing="back">
        <View style={styles.overlay}>
          <View style={styles.cardFrame} />
          <Text style={styles.instruction}>Position the business card within the frame</Text>
        </View>
      </CameraView>
      <TouchableOpacity style={styles.captureButton} onPress={handleCapture}>
        <View style={styles.captureInner} />
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  permissionText: { fontSize: 16, color: '#374151', textAlign: 'center', marginBottom: 16 },
  permissionButton: { backgroundColor: '#2563eb', borderRadius: 8, paddingVertical: 12, paddingHorizontal: 24 },
  permissionButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  cameraContainer: { flex: 1, backgroundColor: '#000' },
  camera: { flex: 1 },
  overlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.3)',
  },
  cardFrame: {
    width: '85%',
    aspectRatio: 1.75,
    borderWidth: 2,
    borderColor: '#fff',
    borderRadius: 12,
    backgroundColor: 'transparent',
  },
  instruction: { color: '#fff', fontSize: 14, marginTop: 16, textAlign: 'center' },
  captureButton: {
    position: 'absolute',
    bottom: 40,
    alignSelf: 'center',
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: 'rgba(255,255,255,0.3)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  captureInner: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#fff' },
  formContainer: { flex: 1, backgroundColor: '#f9fafb' },
  preview: { width: '100%', height: 200, backgroundColor: '#e5e7eb' },
  formTitle: { fontSize: 20, fontWeight: '700', color: '#111827', padding: 16, paddingBottom: 8 },
  fieldGroup: { paddingHorizontal: 16, marginBottom: 12 },
  fieldLabel: { fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 4 },
  fieldInput: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  formActions: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 24,
    gap: 12,
  },
  retakeButton: {
    flex: 1,
    backgroundColor: '#e5e7eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  retakeText: { fontSize: 16, fontWeight: '600', color: '#374151' },
  saveButton: {
    flex: 2,
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveText: { fontSize: 16, fontWeight: '600', color: '#fff' },
  disabled: { opacity: 0.6 },
});
