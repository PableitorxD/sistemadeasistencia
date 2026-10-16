window.API_BASE_URL = 'http://localhost:3000';

window.apiUrl = (path) => {
    const baseUrl = window.API_BASE_URL.replace(/\/+$/, '');
    return `${baseUrl}${path}`;
};
