// Expone API_URL a todas las páginas a partir de config.js
var API_URL = String((window.APP_CONFIG && window.APP_CONFIG.API_URL) || '').replace(/\/+$/, '');
