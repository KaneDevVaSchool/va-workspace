/**
 * Axios + Sanctum SPA (session/cookie). POST/PUT/DELETE cần CSRF khớp session web.
 * Token: meta csrf-token → làm mới qua /sanctum/csrf-cookie + GET /csrf-token.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

if (import.meta.env.VITE_API_URL) {
    window.axios.defaults.baseURL = import.meta.env.VITE_API_URL;
}

function readMetaCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}

function setPlainCsrfToken(token) {
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
}

const initialCsrf = readMetaCsrfToken();
if (initialCsrf) {
    setPlainCsrfToken(initialCsrf);
}

function applyPlainCsrfHeader(config) {
    const token = window.axios.defaults.headers.common['X-CSRF-TOKEN'];
    if (token) {
        config.headers = config.headers ?? {};
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
}

window.axios.interceptors.request.use(applyPlainCsrfHeader);

let csrfRefreshPromise = null;

export async function ensureCsrfCookie() {
    if (!csrfRefreshPromise) {
        csrfRefreshPromise = (async () => {
            await window.axios.get('/sanctum/csrf-cookie');
            const { data } = await window.axios.get('/csrf-token');
            if (data?.csrf_token) {
                setPlainCsrfToken(data.csrf_token);
            }
        })().finally(() => {
            csrfRefreshPromise = null;
        });
    }

    return csrfRefreshPromise;
}

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const original = error.config;
        const url = String(original?.url ?? '');
        if (
            error.response?.status === 419 &&
            original &&
            !original._csrfRetry &&
            !url.includes('/sanctum/csrf-cookie') &&
            !url.includes('/csrf-token')
        ) {
            original._csrfRetry = true;
            delete window.axios.defaults.headers.common['X-CSRF-TOKEN'];
            await ensureCsrfCookie();
            original.headers = original.headers ?? {};
            original.headers['X-CSRF-TOKEN'] =
                window.axios.defaults.headers.common['X-CSRF-TOKEN'];
            return window.axios.request(original);
        }
        return Promise.reject(error);
    },
);
