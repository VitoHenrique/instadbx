/**
 * FastGram V2 - Premium Dashboard
 * Frontend Logic for Chrome Extension Popup
 */

// Configuration
const CONFIG = {
  API_URL_KEY: 'fastgram_api_url',
  TOKEN_KEY: 'fastgram_token',
  DEFAULT_API_URL: 'https://fastgram.growsoft.io/api.php'
};

// State
let state = {
  apiUrl: '',
  token: '',
  stats: null,
  clientes: []
};

// DOM Elements
const elements = {
  // Navigation
  navTabs: document.querySelectorAll('.nav-tab'),
  sections: document.querySelectorAll('.section'),
  
  // Stats
  statClientes: document.getElementById('statClientes'),
  statClientesPercent: document.getElementById('statClientesPercent'),
  progressClientes: document.getElementById('progressClientes'),
  statPerfis: document.getElementById('statPerfis'),
  statPerfisPercent: document.getElementById('statPerfisPercent'),
  progressPerfis: document.getElementById('progressPerfis'),
  statClientesRestantes: document.getElementById('statClientesRestantes'),
  statValidade: document.getElementById('statValidade'),
  statValidadeStatus: document.getElementById('statValidadeStatus'),
  tokenValue: document.getElementById('tokenValue'),
  
  // Clientes
  clientesList: document.getElementById('clientesList'),
  clientesCount: document.getElementById('clientesCount'),
  
  // Cadastro
  formCadastro: document.getElementById('formCadastro'),
  inputEmail: document.getElementById('inputEmail'),
  inputMaxPerfis: document.getElementById('inputMaxPerfis'),
  inputValidade: document.getElementById('inputValidade'),
  btnCadastrar: document.getElementById('btnCadastrar'),
  successCard: document.getElementById('successCard'),
  successMessage: document.getElementById('successMessage'),
  
  // Header Actions
  btnRefresh: document.getElementById('btnRefresh'),
  btnSettings: document.getElementById('btnSettings'),
  btnCopyToken: document.getElementById('btnCopyToken'),
  
  // Modal
  modalSettings: document.getElementById('modalSettings'),
  btnCloseSettings: document.getElementById('btnCloseSettings'),
  btnCancelSettings: document.getElementById('btnCancelSettings'),
  btnSaveSettings: document.getElementById('btnSaveSettings'),
  inputApiUrl: document.getElementById('inputApiUrl'),
  inputToken: document.getElementById('inputToken'),
  
  // Toast
  toastContainer: document.getElementById('toastContainer')
};

// Initialize
document.addEventListener('DOMContentLoaded', init);

async function init() {
  await loadConfig();
  setupEventListeners();
  await loadData();
}

// Load saved configuration
async function loadConfig() {
  return new Promise((resolve) => {
    chrome.storage.local.get([CONFIG.API_URL_KEY, CONFIG.TOKEN_KEY], (result) => {
      state.apiUrl = result[CONFIG.API_URL_KEY] || CONFIG.DEFAULT_API_URL;
      state.token = result[CONFIG.TOKEN_KEY] || '';
      
      elements.inputApiUrl.value = state.apiUrl;
      elements.inputToken.value = state.token;
      elements.tokenValue.textContent = state.token || 'Nao configurado';
      
      resolve();
    });
  });
}

// Save configuration
async function saveConfig() {
  state.apiUrl = elements.inputApiUrl.value.trim() || CONFIG.DEFAULT_API_URL;
  state.token = elements.inputToken.value.trim();
  
  return new Promise((resolve) => {
    chrome.storage.local.set({
      [CONFIG.API_URL_KEY]: state.apiUrl,
      [CONFIG.TOKEN_KEY]: state.token
    }, () => {
      elements.tokenValue.textContent = state.token || 'Nao configurado';
      resolve();
    });
  });
}

// Setup event listeners
function setupEventListeners() {
  // Navigation
  elements.navTabs.forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
  });
  
  // Header actions
  elements.btnRefresh.addEventListener('click', handleRefresh);
  elements.btnSettings.addEventListener('click', openSettings);
  elements.btnCopyToken.addEventListener('click', copyToken);
  
  // Modal
  elements.btnCloseSettings.addEventListener('click', closeSettings);
  elements.btnCancelSettings.addEventListener('click', closeSettings);
  elements.btnSaveSettings.addEventListener('click', handleSaveSettings);
  elements.modalSettings.addEventListener('click', (e) => {
    if (e.target === elements.modalSettings) closeSettings();
  });
  
  // Form
  elements.formCadastro.addEventListener('submit', handleCadastro);
}

// Tab navigation
function switchTab(tabId) {
  elements.navTabs.forEach(tab => {
    tab.classList.toggle('active', tab.dataset.tab === tabId);
  });
  
  elements.sections.forEach(section => {
    section.classList.toggle('active', section.id === `section-${tabId}`);
  });
  
  // Load data when switching tabs
  if (tabId === 'clientes' && state.clientes.length === 0) {
    loadClientes();
  }
}

// API Request helper
async function apiRequest(action, method = 'GET', body = null) {
  if (!state.token) {
    showToast('Configure seu token de autenticacao nas configuracoes.', 'warning');
    openSettings();
    throw new Error('Token nao configurado');
  }
  
  const url = `${state.apiUrl}?action=${action}`;
  const options = {
    method,
    headers: {
      'Authorization': `Basic ${state.token}`,
      'Content-Type': 'application/json'
    }
  };
  
  if (body) {
    options.body = JSON.stringify(body);
  }
  
  try {
    const response = await fetch(url, options);
    const data = await response.json();
    
    if (data.error) {
      throw new Error(data.error);
    }
    
    return data;
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}

// Load all data
async function loadData() {
  if (!state.token) {
    renderEmptyStats();
    return;
  }
  
  try {
    await Promise.all([
      loadStats(),
      loadClientes()
    ]);
  } catch (error) {
    showToast('Erro ao carregar dados: ' + error.message, 'error');
  }
}

// Load statistics
async function loadStats() {
  try {
    const response = await apiRequest('stats');
    state.stats = response.data;
    renderStats();
  } catch (error) {
    renderEmptyStats();
    throw error;
  }
}

// Render statistics
function renderStats() {
  const stats = state.stats;
  
  if (!stats) {
    renderEmptyStats();
    return;
  }
  
  // Clientes
  const clientesUsados = stats.clientes_usados || 0;
  const limiteClientes = stats.limite_clientes === 'ilimitado' ? null : parseInt(stats.limite_clientes);
  const clientesRestantes = stats.clientes_restantes === 'ilimitado' ? 'Ilimitado' : stats.clientes_restantes;
  
  elements.statClientes.textContent = limiteClientes 
    ? `${clientesUsados}/${limiteClientes}` 
    : clientesUsados;
  
  if (limiteClientes) {
    const percentClientes = Math.round((clientesUsados / limiteClientes) * 100);
    elements.statClientesPercent.textContent = `${percentClientes}%`;
    elements.progressClientes.style.width = `${percentClientes}%`;
    elements.progressClientes.className = `progress-fill ${getProgressClass(percentClientes)}`;
  } else {
    elements.statClientesPercent.textContent = 'Ilimitado';
    elements.progressClientes.style.width = '100%';
    elements.progressClientes.className = 'progress-fill success';
  }
  
  elements.statClientesRestantes.textContent = clientesRestantes;
  
  // Perfis
  const perfisUsados = stats.perfis_usados || 0;
  const limitePerfis = stats.limite_perfis === 'ilimitado' ? null : parseInt(stats.limite_perfis);
  
  elements.statPerfis.textContent = limitePerfis 
    ? `${perfisUsados}/${limitePerfis}` 
    : perfisUsados;
  
  if (limitePerfis) {
    const percentPerfis = Math.round((perfisUsados / limitePerfis) * 100);
    elements.statPerfisPercent.textContent = `${percentPerfis}%`;
    elements.progressPerfis.style.width = `${percentPerfis}%`;
    elements.progressPerfis.className = `progress-fill ${getProgressClass(percentPerfis)}`;
  } else {
    elements.statPerfisPercent.textContent = 'Ilimitado';
    elements.progressPerfis.style.width = '100%';
    elements.progressPerfis.className = 'progress-fill success';
  }
  
  // Validade
  if (stats.validade) {
    const validade = new Date(stats.validade);
    const hoje = new Date();
    const diasRestantes = Math.ceil((validade - hoje) / (1000 * 60 * 60 * 24));
    
    elements.statValidade.textContent = formatDate(stats.validade);
    
    if (diasRestantes < 0) {
      elements.statValidadeStatus.textContent = 'Expirado';
      elements.statValidadeStatus.style.color = 'var(--danger)';
    } else if (diasRestantes <= 7) {
      elements.statValidadeStatus.textContent = `${diasRestantes} dias restantes`;
      elements.statValidadeStatus.style.color = 'var(--warning)';
    } else {
      elements.statValidadeStatus.textContent = `${diasRestantes} dias restantes`;
      elements.statValidadeStatus.style.color = 'var(--success)';
    }
  } else {
    elements.statValidade.textContent = 'Ilimitado';
    elements.statValidadeStatus.textContent = 'Sem expiracao';
    elements.statValidadeStatus.style.color = 'var(--success)';
  }
}

// Render empty stats
function renderEmptyStats() {
  elements.statClientes.textContent = '-';
  elements.statClientesPercent.textContent = '0%';
  elements.progressClientes.style.width = '0%';
  elements.statPerfis.textContent = '-';
  elements.statPerfisPercent.textContent = '0%';
  elements.progressPerfis.style.width = '0%';
  elements.statClientesRestantes.textContent = '-';
  elements.statValidade.textContent = '-';
  elements.statValidadeStatus.textContent = '-';
}

// Get progress class based on percentage
function getProgressClass(percent) {
  if (percent >= 90) return 'danger';
  if (percent >= 70) return 'warning';
  return '';
}

// Load clients
async function loadClientes() {
  elements.clientesList.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
  
  try {
    const response = await apiRequest('listar_clientes');
    state.clientes = response.data || [];
    renderClientes();
  } catch (error) {
    elements.clientesList.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <div class="empty-title">Erro ao carregar</div>
        <div class="empty-text">${error.message}</div>
      </div>
    `;
  }
}

// Render clients list
function renderClientes() {
  const clientes = state.clientes;
  
  elements.clientesCount.textContent = `${clientes.length} cliente${clientes.length !== 1 ? 's' : ''}`;
  
  if (clientes.length === 0) {
    elements.clientesList.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </div>
        <div class="empty-title">Nenhum cliente</div>
        <div class="empty-text">Cadastre seu primeiro cliente para comecar.</div>
      </div>
    `;
    return;
  }
  
  const html = `
    <div class="client-list">
      ${clientes.map(cliente => renderClienteItem(cliente)).join('')}
    </div>
  `;
  
  elements.clientesList.innerHTML = html;
  
  // Add event listeners to action buttons
  document.querySelectorAll('.btn-toggle-cliente').forEach(btn => {
    btn.addEventListener('click', () => toggleCliente(btn.dataset.id, btn.dataset.active === '1'));
  });
}

// Render single client item
function renderClienteItem(cliente) {
  const isActive = cliente.is_active === 1 || cliente.is_active === '1';
  const statusBadge = isActive 
    ? '<span class="badge success">Ativo</span>'
    : '<span class="badge danger">Inativo</span>';
  
  const validityBadge = getValidityBadge(cliente.expires_at);
  const initials = cliente.email.substring(0, 2).toUpperCase();
  
  return `
    <div class="client-item">
      <div class="client-avatar">${initials}</div>
      <div class="client-info">
        <div class="client-name">${cliente.email}</div>
        <div class="client-meta">
          ${statusBadge}
          ${validityBadge}
          <span>${cliente.perfis_vinculados || 0}/${cliente.max_contas} perfis</span>
        </div>
      </div>
      <div class="client-actions">
        <button class="client-btn btn-toggle-cliente" data-id="${cliente.id}" data-active="${isActive ? 1 : 0}" title="${isActive ? 'Desativar' : 'Ativar'}">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            ${isActive 
              ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>'
              : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'}
          </svg>
        </button>
      </div>
    </div>
  `;
}

// Get validity badge HTML
function getValidityBadge(expiresAt) {
  if (!expiresAt) {
    return '<span class="validity-badge valid">Permanente</span>';
  }
  
  const expiry = new Date(expiresAt);
  const today = new Date();
  const daysLeft = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
  
  if (daysLeft < 0) {
    return '<span class="validity-badge expired">Expirado</span>';
  } else if (daysLeft <= 7) {
    return `<span class="validity-badge expiring">${daysLeft}d restantes</span>`;
  } else {
    return `<span class="validity-badge valid">${formatDate(expiresAt)}</span>`;
  }
}

// Toggle client status
async function toggleCliente(id, currentlyActive) {
  try {
    // Note: This would need a toggle endpoint in the API
    // For now, showing a toast message
    showToast('Funcao de toggle em desenvolvimento', 'info');
    // After implementing: await loadClientes();
  } catch (error) {
    showToast('Erro ao atualizar cliente: ' + error.message, 'error');
  }
}

// Handle form submission
async function handleCadastro(e) {
  e.preventDefault();
  
  const email = elements.inputEmail.value.trim();
  const maxPerfis = parseInt(elements.inputMaxPerfis.value) || 1;
  const validade = elements.inputValidade.value || null;
  
  if (!email) {
    showToast('Informe o email do cliente.', 'warning');
    return;
  }
  
  elements.btnCadastrar.disabled = true;
  elements.btnCadastrar.innerHTML = '<div class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div> Cadastrando...';
  
  try {
    await apiRequest('criar_cliente', 'POST', {
      email,
      max_perfis: maxPerfis,
      validade
    });
    
    // Show success
    elements.successCard.style.display = 'block';
    elements.successMessage.textContent = `${email} cadastrado com sucesso!`;
    
    // Reset form
    elements.formCadastro.reset();
    elements.inputMaxPerfis.value = '1';
    
    // Refresh data
    await Promise.all([loadStats(), loadClientes()]);
    
    showToast('Cliente cadastrado com sucesso!', 'success');
    
    // Hide success card after 3 seconds
    setTimeout(() => {
      elements.successCard.style.display = 'none';
    }, 3000);
    
  } catch (error) {
    showToast('Erro ao cadastrar: ' + error.message, 'error');
  } finally {
    elements.btnCadastrar.disabled = false;
    elements.btnCadastrar.innerHTML = `
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
      </svg>
      Cadastrar Cliente
    `;
  }
}

// Refresh data
async function handleRefresh() {
  elements.btnRefresh.disabled = true;
  elements.btnRefresh.style.animation = 'spin 1s linear infinite';
  
  try {
    await loadData();
    showToast('Dados atualizados!', 'success');
  } catch (error) {
    showToast('Erro ao atualizar: ' + error.message, 'error');
  } finally {
    elements.btnRefresh.disabled = false;
    elements.btnRefresh.style.animation = '';
  }
}

// Settings modal
function openSettings() {
  elements.modalSettings.classList.add('active');
  elements.inputApiUrl.value = state.apiUrl;
  elements.inputToken.value = state.token;
}

function closeSettings() {
  elements.modalSettings.classList.remove('active');
}

async function handleSaveSettings() {
  await saveConfig();
  closeSettings();
  showToast('Configuracoes salvas!', 'success');
  await loadData();
}

// Copy token
function copyToken() {
  if (!state.token) {
    showToast('Nenhum token configurado.', 'warning');
    return;
  }
  
  navigator.clipboard.writeText(state.token).then(() => {
    showToast('Token copiado!', 'success');
    elements.btnCopyToken.textContent = 'Copiado!';
    setTimeout(() => {
      elements.btnCopyToken.textContent = 'Copiar';
    }, 2000);
  }).catch(() => {
    showToast('Erro ao copiar token.', 'error');
  });
}

// Toast notification
function showToast(message, type = 'info') {
  const icons = {
    success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
    error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
    warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
    info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
  };
  
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <svg class="toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      ${icons[type]}
    </svg>
    <span class="toast-message">${message}</span>
  `;
  
  elements.toastContainer.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideIn 0.3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Utility: Format date
function formatDate(dateStr) {
  if (!dateStr) return '-';
  const date = new Date(dateStr);
  return date.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}
