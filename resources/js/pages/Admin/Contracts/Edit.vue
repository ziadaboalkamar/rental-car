<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  mode: 'create' | 'edit';
  contract: any | null;
  carData?: Record<string, any> | null;
  currentCarDamages?: Array<Record<string, any>>;
  carDamagesByCar?: Record<number, Array<Record<string, any>>>;
  primaryDriver?: Record<string, any> | null;
  additionalDrivers?: Array<Record<string, any>>;
  reservationOptions: Array<Record<string, any>>;
  reservationFormOptions?: {
    clients: Array<{ id: number; name: string; email: string }>;
    cars: Array<{ id: number; label: string; license_plate: string; branch_name?: string | null; price_per_day: number }>;
  } | null;
  startContractFiles: Array<{ id?: number | null; url?: string | null }>;
  endContractFiles: Array<{ id?: number | null; url?: string | null }>;
  actions: { index: string; store?: string; update?: string; show?: string; extract?: string; extractDriver?: string; reservationStore?: string };
}>();

const documentTypeOptions = [
  { value: '', label: 'Select document type' },
  { value: 'driver_license', label: 'Driver License' },
  { value: 'id_card', label: 'ID Card' },
  { value: 'residency_card', label: 'Residency Card' },
];

const reservationStatusOptions = [
  { value: 'pending', label: 'Pending' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'active', label: 'Active' },
  { value: 'cancelled', label: 'Cancelled' },
];

const allowedFileTypes = [
  'application/pdf',
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/jpg',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

const availableReservations = ref(Array.isArray(props.reservationOptions) ? [...props.reservationOptions] : []);
const showReservationModal = ref(false);
const reservationSubmitting = ref(false);
const manualSnapshot = ref<null | {
  car_data: { car_id: any; car_details: string; plate_number: string; branch_id: any };
  rental_data: { start_date: string; end_date: string; total_amount: any };
}>(null);

function documentSlot(side: 'front' | 'back', docs: any[] = [], type = '') {
  const existing = docs.filter((doc) => side === 'front'
    ? doc?.side === 'front' || doc?.side === 'single'
    : doc?.side === 'back');

  return {
    document_type: type || String(existing[0]?.document_type || ''),
    side,
    temp_folders: [],
    removed_file_ids: [],
    existing_files: existing
      .filter((doc) => doc?.id && doc?.url)
      .map((doc) => ({ id: Number(doc.id), url: String(doc.url) })),
  };
}

function buildDriver(driver: any, role: 'primary' | 'additional') {
  const payload = driver || {};
  const docs = Array.isArray(payload.documents) ? payload.documents : [];
  const type = String(payload.document_type || '');

  return {
    id: payload.id ?? null,
    client_id: payload.client_id ?? null,
    role,
    full_name: String(payload.full_name || ''),
    phone: String(payload.phone || ''),
    nationality: String(payload.nationality || ''),
    date_of_birth: String(payload.date_of_birth || ''),
    identity_number: String(payload.identity_number || ''),
    residency_number: String(payload.residency_number || ''),
    license_number: String(payload.license_number || ''),
    identity_expiry_date: String(payload.identity_expiry_date || ''),
    license_expiry_date: String(payload.license_expiry_date || ''),
    extraction_status: String(payload.extraction_status || 'not_requested'),
    extracted_data: payload.extracted_data || null,
    raw_output: payload.raw_output || null,
    confidence: payload.confidence ?? null,
    notes: String(payload.notes || ''),
    document_type: type,
    documents: [documentSlot('front', docs, type), documentSlot('back', docs, type)],
    extracting: false,
    extract_error: '',
    extract_success: '',
  };
}

function syncDocumentType(driver: any) {
  driver.documents.forEach((doc: any) => {
    doc.document_type = driver.document_type;
  });
  driver.extract_error = '';
  driver.extract_success = '';
}

function onDriverFileRemoved(driver: any, index: number, data: { type: string; fileId?: number }) {
  if (data.type !== 'existing' || !data.fileId) return;
  driver.documents[index].removed_file_ids = [...driver.documents[index].removed_file_ids, data.fileId];
  driver.extract_error = '';
  driver.extract_success = '';
}

function addAdditionalDriver() {
  form.additional_drivers.push(buildDriver(null, 'additional'));
}

function removeAdditionalDriver(index: number) {
  form.additional_drivers.splice(index, 1);
}

function onArchiveFileRemoved(type: 'start' | 'end', data: { type: string; fileId?: number }) {
  if (data.type !== 'existing' || !data.fileId) return;
  if (type === 'start') {
    form.start_contract_removed_files = [...form.start_contract_removed_files, data.fileId];
    return;
  }
  form.end_contract_removed_files = [...form.end_contract_removed_files, data.fileId];
}

const form = useForm({
  reservation_id: props.contract?.reservation_id ?? '',
  contract_number: props.contract?.contract_number ?? '',
  status: props.contract?.status ?? 'draft',
  contract_date: props.contract?.contract_date ?? '',
  start_date: props.contract?.start_date ?? '',
  end_date: props.contract?.end_date ?? '',
  total_amount: props.contract?.total_amount ?? '',
  currency: props.contract?.currency ?? 'USD',
  notes: props.contract?.notes ?? '',
  ai_extracted_data: props.contract?.ai_extracted_data ?? null,
  car_data: {
    car_id: props.carData?.car_id ?? props.contract?.car_data?.car_id ?? '',
    car_details: props.carData?.car_details ?? props.contract?.car_data?.car_details ?? props.contract?.car_details ?? '',
    plate_number: props.carData?.plate_number ?? props.contract?.car_data?.plate_number ?? props.contract?.plate_number ?? '',
    branch_id: props.carData?.branch_id ?? props.contract?.car_data?.branch_id ?? '',
  },
  primary_driver: buildDriver(props.primaryDriver ?? props.contract?.primary_driver ?? {
    full_name: props.contract?.renter_name ?? '',
    identity_number: props.contract?.renter_id_number ?? '',
    phone: props.contract?.renter_phone ?? '',
  }, 'primary'),
  additional_drivers: Array.isArray(props.additionalDrivers)
    ? props.additionalDrivers.map((driver) => buildDriver(driver, 'additional'))
    : Array.isArray(props.contract?.additional_drivers)
      ? props.contract.additional_drivers.map((driver: any) => buildDriver(driver, 'additional'))
      : [],
  contract_archive: {
    temp_folders: [],
    removed_file_ids: [],
  },
  start_contract_temp_folders: [],
  start_contract_removed_files: [],
  end_contract_temp_folders: [],
  end_contract_removed_files: [],
});

const reservationForm = useForm({
  user_id: '',
  car_id: '',
  start_date: '',
  end_date: '',
  pickup_time: '09:00',
  return_time: '18:00',
  pickup_location: '',
  return_location: '',
  discount_amount: 0,
  notes: '',
  status: 'confirmed',
  cancellation_reason: '',
});

syncDocumentType(form.primary_driver);
form.additional_drivers.forEach(syncDocumentType);

const selectedReservation = computed(() => {
  if (!form.reservation_id) return null;
  const selectedId = Number(form.reservation_id);
  return availableReservations.value.find((reservation) => Number(reservation.id) === selectedId) || null;
});
const selectedCarId = computed(() => Number(selectedReservation.value?.car_id || form.car_data.car_id || 0));
const selectedCarDamages = computed(() => {
  if (!selectedCarId.value) {
    return Array.isArray(props.currentCarDamages) ? props.currentCarDamages : [];
  }

  return props.carDamagesByCar?.[selectedCarId.value] || [];
});
const hasLinkedReservation = computed(() => Boolean(selectedReservation.value));
const reservationClients = computed(() => props.reservationFormOptions?.clients ?? []);
const reservationCars = computed(() => props.reservationFormOptions?.cars ?? []);

function snapshotManualState() {
  manualSnapshot.value = {
    car_data: {
      car_id: form.car_data.car_id,
      car_details: form.car_data.car_details,
      plate_number: form.car_data.plate_number,
      branch_id: form.car_data.branch_id,
    },
    rental_data: {
      start_date: form.start_date,
      end_date: form.end_date,
      total_amount: form.total_amount,
    },
  };
}

function applyReservationData(reservation: Record<string, any>) {
  form.car_data.car_id = reservation.car_id ?? '';
  form.car_data.car_details = reservation.car_details ?? reservation.car ?? '';
  form.car_data.plate_number = reservation.plate_number ?? '';
  form.car_data.branch_id = reservation.branch_id ?? '';
  form.start_date = reservation.start_date ?? '';
  form.end_date = reservation.end_date ?? '';
  form.total_amount = reservation.total_amount ?? '';
}

function restoreManualState() {
  if (!manualSnapshot.value) return;
  form.car_data.car_id = manualSnapshot.value.car_data.car_id;
  form.car_data.car_details = manualSnapshot.value.car_data.car_details;
  form.car_data.plate_number = manualSnapshot.value.car_data.plate_number;
  form.car_data.branch_id = manualSnapshot.value.car_data.branch_id;
  form.start_date = manualSnapshot.value.rental_data.start_date;
  form.end_date = manualSnapshot.value.rental_data.end_date;
  form.total_amount = manualSnapshot.value.rental_data.total_amount;
}

watch(
  () => form.reservation_id,
  (newValue, oldValue) => {
    const newReservation = availableReservations.value.find((reservation) => Number(reservation.id) === Number(newValue));
    const oldReservation = availableReservations.value.find((reservation) => Number(reservation.id) === Number(oldValue));

    if (newReservation) {
      if (!oldReservation) {
        snapshotManualState();
      }
      applyReservationData(newReservation);
      return;
    }

    if (oldReservation && !newReservation) {
      restoreManualState();
    }
  },
  { immediate: true },
);

function driverTempFolders(driver: any): string[] {
  return driver.documents.flatMap((doc: any) => Array.isArray(doc.temp_folders) ? doc.temp_folders : []);
}

function applyExtractedFields(driver: any, fields: Record<string, any>) {
  const allowedKeys = [
    'full_name',
    'nationality',
    'date_of_birth',
    'identity_number',
    'residency_number',
    'license_number',
    'identity_expiry_date',
    'license_expiry_date',
  ];

  allowedKeys.forEach((key) => {
    const value = fields[key];
    if (value === null || value === undefined || value === '') return;
    driver[key] = String(value);
  });
}

async function extractDriver(driver: any, role: 'primary' | 'additional', index: number | null = null) {
  driver.extract_error = '';
  driver.extract_success = '';

  if (!props.actions.extractDriver) {
    driver.extract_error = 'Driver extraction endpoint is not configured.';
    return;
  }

  if (!driver.document_type) {
    driver.extract_error = 'Select a document type first.';
    return;
  }

  const tempFolders = driverTempFolders(driver);
  if (tempFolders.length === 0) {
    driver.extract_error = 'Upload at least one document image before extraction.';
    return;
  }

  driver.extracting = true;

  try {
    const response = await fetch(props.actions.extractDriver, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
      },
      body: JSON.stringify({
        driver_role: role,
        driver_index: index,
        document_type: driver.document_type,
        temp_folders: tempFolders,
      }),
    });

    const payload = await response.json();

    if (!response.ok) {
      driver.extract_error = payload.message || 'Driver extraction failed.';
      driver.extraction_status = 'failed';
      return;
    }

    const fields = payload.fields && typeof payload.fields === 'object' ? payload.fields : {};
    applyExtractedFields(driver, fields);
    driver.extracted_data = fields;
    driver.raw_output = payload.raw_output || null;
    driver.confidence = typeof payload.confidence === 'number' ? payload.confidence : null;
    driver.extraction_status = payload.status || 'extracted';
    driver.extract_success = payload.message || 'Document extraction completed.';
  } catch (error) {
    driver.extract_error = error instanceof Error ? error.message : 'Driver extraction failed.';
    driver.extraction_status = 'failed';
  } finally {
    driver.extracting = false;
  }
}

function resetReservationModal() {
  reservationForm.reset();
  reservationForm.clearErrors();
  reservationForm.pickup_time = '09:00';
  reservationForm.return_time = '18:00';
  reservationForm.discount_amount = 0;
  reservationForm.status = 'confirmed';
  reservationForm.cancellation_reason = '';
}

async function submitReservationFromModal() {
  reservationForm.clearErrors();

  if (!props.actions.reservationStore) {
    reservationForm.setError('user_id', 'Reservation store route is not configured.');
    return;
  }

  reservationSubmitting.value = true;

  try {
    const response = await fetch(props.actions.reservationStore, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
      },
      body: JSON.stringify({
        user_id: reservationForm.user_id,
        car_id: reservationForm.car_id,
        start_date: reservationForm.start_date,
        end_date: reservationForm.end_date,
        pickup_time: reservationForm.pickup_time,
        return_time: reservationForm.return_time,
        pickup_location: reservationForm.pickup_location,
        return_location: reservationForm.return_location,
        discount_amount: reservationForm.discount_amount,
        notes: reservationForm.notes,
        status: reservationForm.status,
        cancellation_reason: reservationForm.cancellation_reason,
      }),
    });

    const payload = await response.json();

    if (!response.ok) {
      if (payload.errors && typeof payload.errors === 'object') {
        Object.entries(payload.errors).forEach(([key, value]) => {
          reservationForm.setError(key as any, Array.isArray(value) ? String(value[0]) : String(value));
        });
      } else {
        reservationForm.setError('user_id', payload.message || 'Reservation creation failed.');
      }
      return;
    }

    if (payload.reservation) {
      availableReservations.value = [payload.reservation, ...availableReservations.value.filter((item) => Number(item.id) !== Number(payload.reservation.id))];
      form.reservation_id = payload.reservation.id;
    }

    showReservationModal.value = false;
    resetReservationModal();
  } catch (error) {
    reservationForm.setError('user_id', error instanceof Error ? error.message : 'Reservation creation failed.');
  } finally {
    reservationSubmitting.value = false;
  }
}

function submit() {
  if (props.mode === 'create') {
    form.post(props.actions.store || '/admin/contracts');
    return;
  }
  form.put(props.actions.update || '/admin/contracts');
}
</script>

<template>
  <Head :title="mode === 'create' ? 'Create Contract' : 'Edit Contract'" />
  <AdminLayout>
    <main class="flex-1 space-y-6 p-8">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold">{{ mode === 'create' ? 'Create Contract' : 'Edit Contract' }}</h1>
          <p class="text-sm text-muted-foreground">Primary driver, additional drivers, car data, rental data, and archive.</p>
        </div>
        <Link :href="actions.index"><Button variant="outline">Back</Button></Link>
      </div>

      <form class="space-y-6" @submit.prevent="submit">
        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">Customer Data</h2>
            <p class="text-sm text-muted-foreground">Primary driver details and document uploads.</p>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
              <Label for="primary-document-type">Document Type</Label>
              <select id="primary-document-type" v-model="form.primary_driver.document_type" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2" @change="syncDocumentType(form.primary_driver)">
                <option v-for="option in documentTypeOptions" :key="option.value || 'empty'" :value="option.value">{{ option.label }}</option>
              </select>
            </div>
            <div><Label for="primary-full-name">Full Name</Label><Input id="primary-full-name" v-model="form.primary_driver.full_name" /><InputError :message="form.errors['primary_driver.full_name']" class="mt-1" /></div>
            <div><Label for="primary-phone">Phone</Label><Input id="primary-phone" v-model="form.primary_driver.phone" /></div>
            <div><Label for="primary-nationality">Nationality</Label><Input id="primary-nationality" v-model="form.primary_driver.nationality" /></div>
            <div><Label for="primary-birth-date">Date Of Birth</Label><Input id="primary-birth-date" v-model="form.primary_driver.date_of_birth" type="date" /></div>
            <div><Label for="primary-identity-number">Identity Number</Label><Input id="primary-identity-number" v-model="form.primary_driver.identity_number" /></div>
            <div><Label for="primary-residency-number">Residency Number</Label><Input id="primary-residency-number" v-model="form.primary_driver.residency_number" /></div>
            <div><Label for="primary-license-number">License Number</Label><Input id="primary-license-number" v-model="form.primary_driver.license_number" /></div>
            <div><Label for="primary-identity-expiry">Identity Expiry Date</Label><Input id="primary-identity-expiry" v-model="form.primary_driver.identity_expiry_date" type="date" /></div>
            <div><Label for="primary-license-expiry">License Expiry Date</Label><Input id="primary-license-expiry" v-model="form.primary_driver.license_expiry_date" type="date" /></div>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label class="mb-2 block">Document Front / Single</Label>
              <FileUpload v-model="form.primary_driver.documents[0].temp_folders" :initial-files="form.primary_driver.documents[0].existing_files" :allowed-file-types="allowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_driver_front" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(form.primary_driver, 0, data)" />
            </div>
            <div>
              <Label class="mb-2 block">Document Back</Label>
              <FileUpload v-model="form.primary_driver.documents[1].temp_folders" :initial-files="form.primary_driver.documents[1].existing_files" :allowed-file-types="allowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_driver_back" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(form.primary_driver, 1, data)" />
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <Button type="button" variant="outline" :disabled="form.primary_driver.extracting" @click="extractDriver(form.primary_driver, 'primary')">
              {{ form.primary_driver.extracting ? 'Extracting...' : 'Extract From Document' }}
            </Button>
            <p v-if="form.primary_driver.extract_success" class="text-sm text-emerald-600">{{ form.primary_driver.extract_success }}</p>
            <p v-if="form.primary_driver.extract_error" class="text-sm text-red-600">{{ form.primary_driver.extract_error }}</p>
            <p v-if="form.primary_driver.confidence !== null" class="text-sm text-muted-foreground">Confidence: {{ Number(form.primary_driver.confidence).toFixed(2) }}</p>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold">Additional Drivers</h2>
              <p class="text-sm text-muted-foreground">Independent drivers inside this contract.</p>
            </div>
            <Button type="button" variant="outline" @click="addAdditionalDriver">Add Driver</Button>
          </div>
          <div v-if="form.additional_drivers.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">No additional drivers added.</div>
          <div v-for="(driver, index) in form.additional_drivers" :key="driver.id ?? `driver-${index}`" class="space-y-4 rounded-md border p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="font-semibold">Additional Driver {{ index + 1 }}</h3>
                <p class="text-sm text-muted-foreground">Upload ID or license and review manually.</p>
              </div>
              <Button type="button" variant="ghost" @click="removeAdditionalDriver(index)">Remove</Button>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div>
                <Label :for="`driver-document-type-${index}`">Document Type</Label>
                <select :id="`driver-document-type-${index}`" v-model="driver.document_type" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2" @change="syncDocumentType(driver)">
                  <option v-for="option in documentTypeOptions" :key="`${index}-${option.value || 'empty'}`" :value="option.value">{{ option.label }}</option>
                </select>
              </div>
              <div><Label :for="`driver-full-name-${index}`">Full Name</Label><Input :id="`driver-full-name-${index}`" v-model="driver.full_name" /></div>
              <div><Label :for="`driver-phone-${index}`">Phone</Label><Input :id="`driver-phone-${index}`" v-model="driver.phone" /></div>
              <div><Label :for="`driver-nationality-${index}`">Nationality</Label><Input :id="`driver-nationality-${index}`" v-model="driver.nationality" /></div>
              <div><Label :for="`driver-birth-date-${index}`">Date Of Birth</Label><Input :id="`driver-birth-date-${index}`" v-model="driver.date_of_birth" type="date" /></div>
              <div><Label :for="`driver-identity-number-${index}`">Identity Number</Label><Input :id="`driver-identity-number-${index}`" v-model="driver.identity_number" /></div>
              <div><Label :for="`driver-residency-number-${index}`">Residency Number</Label><Input :id="`driver-residency-number-${index}`" v-model="driver.residency_number" /></div>
              <div><Label :for="`driver-license-number-${index}`">License Number</Label><Input :id="`driver-license-number-${index}`" v-model="driver.license_number" /></div>
              <div><Label :for="`driver-identity-expiry-${index}`">Identity Expiry Date</Label><Input :id="`driver-identity-expiry-${index}`" v-model="driver.identity_expiry_date" type="date" /></div>
              <div><Label :for="`driver-license-expiry-${index}`">License Expiry Date</Label><Input :id="`driver-license-expiry-${index}`" v-model="driver.license_expiry_date" type="date" /></div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <Label class="mb-2 block">Document Front / Single</Label>
                <FileUpload v-model="driver.documents[0].temp_folders" :initial-files="driver.documents[0].existing_files" :allowed-file-types="allowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_additional_driver_front" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(driver, 0, data)" />
              </div>
              <div>
                <Label class="mb-2 block">Document Back</Label>
                <FileUpload v-model="driver.documents[1].temp_folders" :initial-files="driver.documents[1].existing_files" :allowed-file-types="allowedFileTypes" :allow-multiple="false" :max-files="1" collection="contract_additional_driver_back" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onDriverFileRemoved(driver, 1, data)" />
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <Button type="button" variant="outline" :disabled="driver.extracting" @click="extractDriver(driver, 'additional', index)">
                {{ driver.extracting ? 'Extracting...' : 'Extract From Document' }}
              </Button>
              <p v-if="driver.extract_success" class="text-sm text-emerald-600">{{ driver.extract_success }}</p>
              <p v-if="driver.extract_error" class="text-sm text-red-600">{{ driver.extract_error }}</p>
              <p v-if="driver.confidence !== null" class="text-sm text-muted-foreground">Confidence: {{ Number(driver.confidence).toFixed(2) }}</p>
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">Car Data</h2>
            <p class="text-sm text-muted-foreground">Reservation and vehicle details for this contract.</p>
          </div>
          <div v-if="hasLinkedReservation" class="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
            Car details are linked to the selected reservation and cannot be edited here.
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div><Label for="car-details">Car Details</Label><Input id="car-details" v-model="form.car_data.car_details" :disabled="hasLinkedReservation" /><InputError :message="form.errors['car_data.car_details']" class="mt-1" /></div>
            <div><Label for="plate-number">Plate Number</Label><Input id="plate-number" v-model="form.car_data.plate_number" :disabled="hasLinkedReservation" /><InputError :message="form.errors['car_data.plate_number']" class="mt-1" /></div>
          </div>
          <div v-if="selectedCarDamages.length" class="rounded-md border p-4">
            <div class="mb-2 text-sm font-medium">Current Car Damages</div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b text-left text-muted-foreground">
                    <th class="px-2 py-2">Zone</th>
                    <th class="px-2 py-2">View</th>
                    <th class="px-2 py-2">Type</th>
                    <th class="px-2 py-2">Severity</th>
                    <th class="px-2 py-2">Qty</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="damage in selectedCarDamages" :key="damage.id" class="border-b">
                    <td class="px-2 py-2">{{ damage.zone_label }}</td>
                    <td class="px-2 py-2">{{ damage.view_side_label }}</td>
                    <td class="px-2 py-2">{{ damage.damage_type_label }}</td>
                    <td class="px-2 py-2">{{ damage.severity_label }}</td>
                    <td class="px-2 py-2">{{ damage.quantity }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">Rental Data</h2>
            <p class="text-sm text-muted-foreground">Contract lifecycle, rental period, amount, and notes.</p>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="xl:col-span-2">
              <Label for="reservation_id">Linked Reservation</Label>
              <div class="mt-1 flex gap-2">
                <select id="reservation_id" v-model="form.reservation_id" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                  <option value="">No linked reservation</option>
                  <option v-for="reservation in availableReservations" :key="reservation.id" :value="reservation.id" :disabled="reservation.has_contract && reservation.id !== form.reservation_id">
                    {{ reservation.label }}{{ reservation.has_contract ? ' (has contract)' : '' }}
                  </option>
                </select>
                <Button type="button" variant="outline" @click="showReservationModal = true">New Reservation</Button>
              </div>
              <InputError :message="form.errors.reservation_id" class="mt-1" />
              <div v-if="selectedReservation" class="mt-3 rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                <div><span class="font-medium text-foreground">Reservation:</span> {{ selectedReservation.reservation_number }}</div>
                <div><span class="font-medium text-foreground">Client:</span> {{ selectedReservation.user_name || 'N/A' }}</div>
                <div><span class="font-medium text-foreground">Car:</span> {{ selectedReservation.car_details || selectedReservation.car || 'N/A' }}</div>
              </div>
            </div>
            <div><Label for="contract_number">Contract Number</Label><Input id="contract_number" v-model="form.contract_number" readonly /><InputError :message="form.errors.contract_number" class="mt-1" /></div>
            <div>
              <Label for="status">Status</Label>
              <select id="status" v-model="form.status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option value="draft">draft</option>
                <option value="active">active</option>
                <option value="completed">completed</option>
                <option value="cancelled">cancelled</option>
              </select>
              <InputError :message="form.errors.status" class="mt-1" />
            </div>
            <div><Label for="contract_date">Contract Date</Label><Input id="contract_date" v-model="form.contract_date" type="date" /><InputError :message="form.errors.contract_date" class="mt-1" /></div>
            <div><Label for="start_date">Rental Start Date</Label><Input id="start_date" v-model="form.start_date" type="date" :disabled="hasLinkedReservation" /><InputError :message="form.errors.start_date" class="mt-1" /></div>
            <div><Label for="end_date">Rental End Date</Label><Input id="end_date" v-model="form.end_date" type="date" :disabled="hasLinkedReservation" /><InputError :message="form.errors.end_date" class="mt-1" /></div>
            <div><Label for="total_amount">Total Amount</Label><Input id="total_amount" v-model="form.total_amount" type="number" min="0" step="0.01" :disabled="hasLinkedReservation" /><InputError :message="form.errors.total_amount" class="mt-1" /></div>
            <div><Label for="currency">Currency</Label><Input id="currency" v-model="form.currency" maxlength="3" /><InputError :message="form.errors.currency" class="mt-1" /></div>
            <div class="md:col-span-2 xl:col-span-3">
              <Label for="notes">Notes</Label>
              <textarea id="notes" v-model="form.notes" rows="3" class="w-full rounded-md border border-input bg-transparent px-3 py-2" />
              <InputError :message="form.errors.notes" class="mt-1" />
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
          <div>
            <h2 class="text-lg font-semibold">Contract Archive</h2>
            <p class="text-sm text-muted-foreground">Keep contract scans and supporting files here as archive attachments.</p>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label class="mb-2 block">Start Rental Contract File</Label>
              <FileUpload v-model="form.start_contract_temp_folders" :initial-files="startContractFiles || []" :allowed-file-types="allowedFileTypes" :allow-multiple="false" :max-files="1" collection="start_contract" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onArchiveFileRemoved('start', data)" />
              <InputError :message="form.errors.start_contract_temp_folders" class="mt-1" />
            </div>
            <div>
              <Label class="mb-2 block">End Rental Contract File</Label>
              <FileUpload v-model="form.end_contract_temp_folders" :initial-files="endContractFiles || []" :allowed-file-types="allowedFileTypes" :allow-multiple="false" :max-files="1" collection="end_contract" theme="light" width="100%" @file-removed="(data: { type: string; fileId?: number }) => onArchiveFileRemoved('end', data)" />
              <InputError :message="form.errors.end_contract_temp_folders" class="mt-1" />
            </div>
          </div>
        </section>

        <div class="flex gap-3">
          <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Contract' }}</Button>
          <Link :href="actions.index"><Button type="button" variant="outline">Cancel</Button></Link>
        </div>
      </form>

      <Dialog v-model:open="showReservationModal">
        <DialogContent class="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Create Reservation</DialogTitle>
            <DialogDescription>Create a reservation here, then link it directly to this contract.</DialogDescription>
          </DialogHeader>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label for="reservation-user-id">Client</Label>
              <select id="reservation-user-id" v-model="reservationForm.user_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option value="" disabled>Select client</option>
                <option v-for="client in reservationClients" :key="client.id" :value="client.id">{{ client.name }} ({{ client.email }})</option>
              </select>
              <InputError :message="reservationForm.errors.user_id" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-car-id">Car</Label>
              <select id="reservation-car-id" v-model="reservationForm.car_id" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option value="" disabled>Select car</option>
                <option v-for="car in reservationCars" :key="car.id" :value="car.id">{{ car.label }} | {{ car.license_plate }}{{ car.branch_name ? ` | ${car.branch_name}` : '' }}</option>
              </select>
              <InputError :message="reservationForm.errors.car_id" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-start-date">Start Date</Label>
              <Input id="reservation-start-date" v-model="reservationForm.start_date" type="date" />
              <InputError :message="reservationForm.errors.start_date" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-end-date">End Date</Label>
              <Input id="reservation-end-date" v-model="reservationForm.end_date" type="date" />
              <InputError :message="reservationForm.errors.end_date" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-pickup-time">Pickup Time</Label>
              <Input id="reservation-pickup-time" v-model="reservationForm.pickup_time" type="time" />
              <InputError :message="reservationForm.errors.pickup_time" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-return-time">Return Time</Label>
              <Input id="reservation-return-time" v-model="reservationForm.return_time" type="time" />
              <InputError :message="reservationForm.errors.return_time" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-pickup-location">Pickup Location</Label>
              <Input id="reservation-pickup-location" v-model="reservationForm.pickup_location" />
              <InputError :message="reservationForm.errors.pickup_location" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-return-location">Return Location</Label>
              <Input id="reservation-return-location" v-model="reservationForm.return_location" />
              <InputError :message="reservationForm.errors.return_location" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-discount-amount">Discount</Label>
              <Input id="reservation-discount-amount" v-model="reservationForm.discount_amount" type="number" min="0" step="0.01" />
              <InputError :message="reservationForm.errors.discount_amount" class="mt-1" />
            </div>
            <div>
              <Label for="reservation-status">Status</Label>
              <select id="reservation-status" v-model="reservationForm.status" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2">
                <option v-for="status in reservationStatusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
              </select>
              <InputError :message="reservationForm.errors.status" class="mt-1" />
            </div>
            <div class="md:col-span-2">
              <Label for="reservation-notes">Notes</Label>
              <textarea id="reservation-notes" v-model="reservationForm.notes" rows="3" class="w-full rounded-md border border-input bg-transparent px-3 py-2" />
              <InputError :message="reservationForm.errors.notes" class="mt-1" />
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="showReservationModal = false">Cancel</Button>
            <Button type="button" :disabled="reservationSubmitting" @click="submitReservationFromModal">
              {{ reservationSubmitting ? 'Creating...' : 'Create Reservation' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </main>
  </AdminLayout>
</template>
