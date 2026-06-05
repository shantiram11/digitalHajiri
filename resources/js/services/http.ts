/**
 * Axios client wired for Sanctum SPA cookie auth.
 *
 *  - withCredentials so cookies flow on every request.
 *  - Reads XSRF-TOKEN cookie and sends it as X-XSRF-TOKEN automatically (Axios default behavior).
 *  - On 401: reset the auth store and bounce to /login (unless already there).
 *  - Normalises error responses into the rejected `{ status, title, detail, errors? }` shape.
 *
 * The CSRF cookie endpoint lives at `/sanctum/csrf-cookie` (root, not /api/v1).
 * Use `http.get('/sanctum/csrf-cookie', { baseURL: '' })` to fetch it.
 */
import axios, { type AxiosError, type AxiosInstance } from 'axios';

interface ApiProblem {
    status: number;
    title: string;
    detail?: string;
    errors?: Record<string, string[]>;
}

export const http: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

http.interceptors.request.use((config) => {
    const orgImpersonation = sessionStorage.getItem('org.impersonate');
    if (orgImpersonation) {
        config.headers['X-Organization-Id'] = orgImpersonation;
    }
    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        if (error.response?.status === 401) {
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }

        const data = error.response?.data as Partial<ApiProblem> | undefined;
        const problem: ApiProblem = {
            status: error.response?.status ?? 0,
            title: data?.title ?? error.message ?? 'Request failed',
            detail: data?.detail,
            errors: data?.errors,
        };
        return Promise.reject(problem);
    },
);
