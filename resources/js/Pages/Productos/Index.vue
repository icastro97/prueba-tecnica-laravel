<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { reactive, ref, computed, watch, nextTick } from 'vue';

const { productos, filters: initialFilters } = usePage().props;

const filters = reactive({
  nombre: initialFilters?.nombre || '',
  sku: initialFilters?.sku || '',
  precio_min: initialFilters?.precio_min || '',
  precio_max: initialFilters?.precio_max || ''
});

const form = reactive({
  id: null,
  sku: '',
  nombre: '',
  descripcion: '',
  cantidad: 1,
  precio: 1,
});

const isEditing = ref(false);
const skuExists = ref(false);
const loading = ref(false);
const message = ref('');
const messageType = ref('success');
const filterTimeout = ref(null);
const formErrors = reactive({});
const exportLoading = ref(false);
const exportType = ref('csv');

const total = computed(() => (form.precio || 0) * (form.cantidad || 0));
const hasErrors = computed(() => Object.keys(formErrors).length > 0);
const canSubmit = computed(() =>
  !skuExists.value &&
  !loading.value &&
  !hasErrors.value &&
  form.sku &&
  form.nombre &&
  form.cantidad >= 1 &&
  form.precio >= 0.01
);

watch(filters, () => {
  if (filterTimeout.value) {
    clearTimeout(filterTimeout.value);
  }
  filterTimeout.value = setTimeout(() => {
    filter();
  }, 500);
}, { deep: true });

function showMessage(text, type = 'success') {
  message.value = text;
  messageType.value = type;
  setTimeout(() => {
    message.value = '';
  }, 4000);
}

function validateForm() {
  const errors = {};

  if (!form.sku) errors.sku = 'El SKU es obligatorio';
  if (!form.nombre) errors.nombre = 'El nombre es obligatorio';
  if (form.cantidad < 1) errors.cantidad = 'La cantidad debe ser mayor a 0';
  if (form.precio < 0.01) errors.precio = 'El precio debe ser mayor a 0';

  Object.assign(formErrors, errors);

  Object.keys(formErrors).forEach(key => {
    if (!errors[key]) {
      delete formErrors[key];
    }
  });

  return Object.keys(errors).length === 0;
}

async function checkSku() {
  if (!form.sku) {
    skuExists.value = false;
    return;
  }

  try {
    const response = await fetch(`/productos/sku/${form.sku}`);
    const data = await response.json();
    skuExists.value = data.exists && (!isEditing.value || data.id !== form.id);

    if (skuExists.value) {
      formErrors.sku = 'Este SKU ya existe';
    } else {
      delete formErrors.sku;
    }
  } catch (error) {
    console.error('Error al verificar SKU:', error);
  }
}

function submitForm() {
  if (!validateForm()) {
    showMessage('Por favor corrige los errores en el formulario', 'error');
    return;
  }

  if (skuExists.value) {
    showMessage('No se puede guardar: el SKU ya existe', 'error');
    return;
  }

  loading.value = true;

  const url = isEditing.value ? `/productos/${form.id}` : '/productos';
  const method = isEditing.value ? 'put' : 'post';

  router[method](url, form, {
    onSuccess: () => {
      const action = isEditing.value ? 'actualizado' : 'creado';
      showMessage(`Producto ${action} exitosamente`);
      resetForm();
      router.get('/productos', filters, {
        preserveState: true,
        preserveScroll: true,
        only: ['productos'],
        onSuccess: () => {
          nextTick(() => {
            document.querySelector('table')?.scrollIntoView({
              behavior: 'smooth',
              block: 'nearest'
            });
          });
        }
      });
    },
    onError: (errors) => {
      if (errors) {
        Object.assign(formErrors, errors);
        showMessage('Error al guardar el producto', 'error');
      } else {
        showMessage('Error interno del servidor', 'error');
      }
    },
    onFinish: () => {
      loading.value = false;
    },
  });
}

function editar(producto) {
  Object.keys(formErrors).forEach(key => delete formErrors[key]);

  Object.assign(form, {
    ...producto,
    cantidad: Number(producto.cantidad),
    precio: Number(producto.precio)
  });

  isEditing.value = true;
  skuExists.value = false;

  nextTick(() => {
    document.querySelector('form').scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  });
}

function resetForm() {
  Object.assign(form, {
    id: null,
    sku: '',
    nombre: '',
    descripcion: '',
    cantidad: 1,
    precio: 1,
  });

  isEditing.value = false;
  skuExists.value = false;

  Object.keys(formErrors).forEach(key => delete formErrors[key]);
}

function eliminar(id, nombre) {
  if (confirm(`¿Estás seguro de eliminar el producto "${nombre}"?`)) {
    router.delete(`/productos/${id}`, {
      onSuccess: () => {
        showMessage('Producto eliminado correctamente');
        router.reload({ only: ['productos'] });
      },
      onError: () => {
        showMessage('Error al eliminar el producto', 'error');
      }
    });
  }
}

function filter() {
  router.get('/productos', filters, {
    preserveState: true,
    preserveScroll: true,
    only: ['productos'],
    onError: () => {
      showMessage('Error al filtrar productos', 'error');
    }
  });
}

function clearFilters() {
  Object.assign(filters, {
    nombre: '',
    sku: '',
    precio_min: '',
    precio_max: ''
  });
}

function goToPage(url) {
  if (!url) return;

  router.get(url, {}, {
    preserveState: true,
    preserveScroll: false,
    only: ['productos'],
    onSuccess: () => {
      nextTick(() => {
        const table = document.querySelector('table');
        if (table) {
          table.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    },
    onError: () => {
      showMessage('Error al cargar la página', 'error');
    }
  });
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(amount || 0);
}

async function exportData() {
  exportLoading.value = true;

  try {
    const params = new URLSearchParams();
    for (const key in filters) {
      if (filters[key]) {
        params.append(key, filters[key]);
      }
    }
    params.append('export_type', exportType.value);

    const url = `/productos/export?${params.toString()}`;

    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `productos_${new Date().toISOString().split('T')[0]}.${exportType.value}`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showMessage(`Exportación completada (${exportType.value.toUpperCase()})`, 'success');
  } catch (error) {
    console.error('Error al exportar:', error);
    showMessage('Error al exportar los datos', 'error');
  } finally {
    exportLoading.value = false;
  }
}
</script>

<template>
  <Head title="Gestión de Productos" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Gestión de Productos
        </h2>
        <div class="flex gap-2">
          <div class="relative">
            <select v-model="exportType" class="appearance-none bg-gray-100 border border-gray-300 rounded-l-lg px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
              <option value="xlsx" selected>Excel</option>
            </select>
          </div>
          <button
            @click="exportData"
            :disabled="exportLoading"
            class="btn-secondary text-sm flex items-center"
            :class="{ 'opacity-50 cursor-not-allowed': exportLoading }"
          >
            <span v-if="exportLoading" class="inline-flex items-center">
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Exportando...
            </span>
            <span v-else>
              📊 Exportar
            </span>
          </button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white shadow-lg sm:rounded-lg overflow-hidden">

          <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 transform -translate-y-2"
            enter-to-class="opacity-100 transform translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 transform translate-y-0"
            leave-to-class="opacity-0 transform -translate-y-2"
          >
            <div
              v-if="message"
              :class="[
                'p-4 border-l-4 mx-6 mt-6 rounded-r-lg',
                messageType === 'error' ? 'bg-red-50 border-red-500 text-red-700' :
                messageType === 'info' ? 'bg-blue-50 border-blue-500 text-blue-700' :
                'bg-green-50 border-green-500 text-green-700'
              ]"
            >
              <div class="flex items-center">
                <span class="mr-2">
                  {{ messageType === 'error' ? '❌' : messageType === 'info' ? 'ℹ️' : '✅' }}
                </span>
                {{ message }}
              </div>
            </div>
          </Transition>

          <div class="p-6 space-y-8">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-xl border">
              <h3 class="text-lg font-semibold mb-4 text-gray-800">
                {{ isEditing ? '✏️ Editar Producto' : '➕ Nuevo Producto' }}
              </h3>

              <form @submit.prevent="submitForm" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div>
                  <label class="block font-semibold text-gray-700 mb-2">SKU *</label>
                  <input
                    v-model="form.sku"
                    @blur="checkSku"
                    @input="() => delete formErrors.sku"
                    type="text"
                    class="input"
                    :class="{
                      'border-red-500 bg-red-50': formErrors.sku || skuExists,
                      'border-green-500 bg-green-50': form.sku && !formErrors.sku && !skuExists
                    }"
                    placeholder="Ej: 12345"
                  />
                  <div v-if="formErrors.sku || skuExists" class="text-red-600 text-sm mt-1">
                    ⚠ {{ formErrors.sku || 'SKU ya existe' }}
                  </div>
                </div>

                <div>
                  <label class="block font-semibold text-gray-700 mb-2">Nombre *</label>
                  <input
                    v-model="form.nombre"
                    @input="() => delete formErrors.nombre"
                    type="text"
                    class="input"
                    :class="{
                      'border-red-500 bg-red-50': formErrors.nombre,
                      'border-green-500 bg-green-50': form.nombre && !formErrors.nombre
                    }"
                    placeholder="Nombre del producto"
                  />
                  <div v-if="formErrors.nombre" class="text-red-600 text-sm mt-1">
                    ⚠ {{ formErrors.nombre }}
                  </div>
                </div>

                <div>
                  <label class="block font-semibold text-gray-700 mb-2">Precio *</label>
                  <input
                    v-model.number="form.precio"
                    @input="() => delete formErrors.precio"
                    type="number"
                    step="0.01"
                    min="0.01"
                    class="input"
                    :class="{
                      'border-red-500 bg-red-50': formErrors.precio,
                      'border-green-500 bg-green-50': form.precio >= 0.01 && !formErrors.precio
                    }"
                    placeholder="0.00"
                  />
                  <div v-if="formErrors.precio" class="text-red-600 text-sm mt-1">
                    ⚠ {{ formErrors.precio }}
                  </div>
                </div>

                <div class="lg:col-span-2">
                  <label class="block font-semibold text-gray-700 mb-2">Descripción</label>
                  <textarea
                    v-model="form.descripcion"
                    class="input resize-none h-20"
                    placeholder="Descripción opcional del producto"
                  ></textarea>
                </div>

                <div class="space-y-4">
                  <div>
                    <label class="block font-semibold text-gray-700 mb-2">Cantidad *</label>
                    <input
                      v-model.number="form.cantidad"
                      @input="() => delete formErrors.cantidad"
                      type="number"
                      min="1"
                      class="input"
                      :class="{
                        'border-red-500 bg-red-50': formErrors.cantidad,
                        'border-green-500 bg-green-50': form.cantidad >= 1 && !formErrors.cantidad
                      }"
                    />
                    <div v-if="formErrors.cantidad" class="text-red-600 text-sm mt-1">
                      ⚠ {{ formErrors.cantidad }}
                    </div>
                  </div>

                  <div>
                    <label class="block font-semibold text-gray-700 mb-2">Total</label>
                    <div class="input bg-blue-50 border-blue-200 font-bold text-blue-800">
                      {{ formatCurrency(total) }}
                    </div>
                  </div>
                </div>

                <div class="lg:col-span-3 flex justify-end gap-3 pt-4 border-t">
                  <button
                    v-if="isEditing"
                    type="button"
                    @click="resetForm"
                    class="btn-secondary"
                  >
                    ❌ Cancelar
                  </button>
                  <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="btn relative"
                    :class="{ 'opacity-50 cursor-not-allowed': !canSubmit }"
                  >
                    <span v-if="loading" class="inline-flex items-center">
                      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Guardando...
                    </span>
                    <span v-else>
                      {{ isEditing ? '💾 Actualizar' : '➕ Guardar' }}
                    </span>
                  </button>
                </div>
              </form>
            </div>

            <div class="bg-white border-2 border-gray-200 p-6 rounded-xl">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">🔍 Filtros</h3>
                <button
                  @click="clearFilters"
                  class="text-sm text-gray-600 hover:text-gray-800 underline"
                >
                  Limpiar filtros
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input
                  v-model="filters.nombre"
                  placeholder="🔤 Filtrar por nombre"
                  class="input"
                />
                <input
                  v-model="filters.sku"
                  placeholder="🏷️ Filtrar por SKU"
                  class="input"
                />
                <input
                  v-model="filters.precio_min"
                  type="number"
                  step="0.01"
                  placeholder="💰 Precio mínimo"
                  class="input"
                />
                <input
                  v-model="filters.precio_max"
                  type="number"
                  step="0.01"
                  placeholder="💰 Precio máximo"
                  class="input"
                />
              </div>
            </div>

            <div class="bg-white border-2 border-gray-200 rounded-xl overflow-hidden">
              <div class="bg-gray-50 px-6 py-4 border-b">
                <div class="flex justify-between items-center">
                  <h3 class="text-lg font-semibold text-gray-800">
                    📦 Productos
                    <span class="text-sm font-normal text-gray-600">
                      ({{ productos.total || 0 }} total{{ productos.total !== 1 ? 'es' : '' }})
                    </span>
                  </h3>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">ID</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">SKU</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Nombre</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Precio</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Total</th>
                      <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Acciones</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr
                      v-for="p in productos.data"
                      :key="p.id"
                      class="hover:bg-gray-50 transition-colors duration-200"
                      :class="{ 'bg-blue-50': isEditing && form.id === p.id }"
                    >
                      <td class="px-4 py-4 text-sm text-gray-900">{{ p.id }}</td>
                      <td class="px-4 py-4 text-sm font-mono text-gray-900">{{ p.sku }}</td>
                      <td class="px-4 py-4 text-sm text-gray-900">
                        <div class="font-medium">{{ p.nombre }}</div>
                        <div v-if="p.descripcion" class="text-xs text-gray-500 truncate max-w-xs">
                          {{ p.descripcion }}
                        </div>
                      </td>
                      <td class="px-4 py-4 text-sm text-right text-gray-900">
                        {{ formatCurrency(p.precio) }}
                      </td>
                      <td class="px-4 py-4 text-sm text-center text-gray-900">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          {{ p.cantidad }}
                        </span>
                      </td>
                      <td class="px-4 py-4 text-sm text-right font-semibold text-gray-900">
                        {{ formatCurrency(p.total) }}
                      </td>
                      <td class="px-4 py-4 text-sm text-center">
                        <div class="flex justify-center gap-2">
                          <button
                            @click="editar(p)"
                            class="text-blue-600 hover:text-blue-800 hover:bg-blue-100 px-2 py-1 rounded transition-colors"
                            title="Editar producto"
                          >
                            ✏️
                          </button>
                          <button
                            @click="eliminar(p.id, p.nombre)"
                            class="text-red-600 hover:text-red-800 hover:bg-red-100 px-2 py-1 rounded transition-colors"
                            title="Eliminar producto"
                          >
                            🗑️
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <div v-if="!productos.data || productos.data.length === 0" class="text-center py-12">
                  <div class="text-gray-400 text-6xl mb-4">📦</div>
                  <h3 class="text-lg font-medium text-gray-900 mb-2">No hay productos</h3>
                  <p class="text-gray-500">Crea tu primer producto usando el formulario de arriba.</p>
                </div>
              </div>

              <div v-if="productos.links && productos.links.length > 3" class="bg-gray-50 px-6 py-4 border-t">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                  <div class="text-sm text-gray-700">
                    Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }}
                    de {{ productos.total || 0 }} resultados
                  </div>

                  <nav class="flex items-center space-x-1">
                    <template v-for="(link, index) in productos.links" :key="index">
                      <button
                        v-if="link.label === '&laquo; Previous'"
                        @click="goToPage(link.url)"
                        :disabled="!link.url"
                        class="pagination-btn"
                        :class="{
                          'pagination-disabled': !link.url,
                          'pagination-enabled': link.url
                        }"
                        title="Página anterior"
                      >
                        ← Anterior
                      </button>

                      <button
                        v-else-if="link.label === 'Next &raquo;'"
                        @click="goToPage(link.url)"
                        :disabled="!link.url"
                        class="pagination-btn"
                        :class="{
                          'pagination-disabled': !link.url,
                          'pagination-enabled': link.url
                        }"
                        title="Página siguiente"
                      >
                        Siguiente →
                      </button>

                      <button
                        v-else
                        @click="goToPage(link.url)"
                        :disabled="!link.url"
                        class="pagination-btn pagination-number"
                        :class="{
                          'pagination-active': link.active,
                          'pagination-disabled': !link.url,
                          'pagination-enabled': link.url && !link.active
                        }"
                        :title="`Ir a página ${link.label}`"
                      >
                        {{ link.label }}
                      </button>
                    </template>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200;
}

.btn {
  @apply inline-flex items-center px-6 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200;
}

.btn-secondary {
  @apply inline-flex items-center px-4 py-2 bg-gray-500 text-white font-medium rounded-lg shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200;
}

.pagination-btn {
  @apply px-3 py-2 text-sm font-medium rounded-md transition-colors duration-200 min-w-[2.5rem] text-center;
}

.pagination-number {
  @apply w-10 h-10 flex items-center justify-center;
}

.pagination-active {
  @apply bg-blue-600 text-white border-blue-600 cursor-default;
}

.pagination-enabled {
  @apply text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400;
}

.pagination-disabled {
  @apply text-gray-400 bg-gray-100 border border-gray-200 cursor-not-allowed;
}
</style>
