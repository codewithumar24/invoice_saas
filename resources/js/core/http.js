/** Small fetch wrapper: sends the CSRF token and understands the app's JSON shape {success, message, data, errors}. */
const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function request(url, { method = 'GET', body = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token() };
    const options = { method, headers, credentials: 'same-origin' };

    if (body instanceof FormData) {
        options.body = body;
    } else if (body) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    let payload = {};

    try {
        payload = await response.json();
    } catch {
        payload = { success: false, message: 'Something went wrong. Please try again.' };
    }

    if (!response.ok || payload.success === false) {
        const error = new Error(payload.message || 'Request failed.');
        error.errors = payload.errors || {};
        error.status = response.status;
        throw error;
    }

    return payload;
}
