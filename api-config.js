window.API_BASE_URL = 'http://18.118.216.108:3000';

window.apiUrl = (path) => {
    const baseUrl = window.API_BASE_URL.replace(/\/+$/, '');
    return `${baseUrl}${path}`;
};
