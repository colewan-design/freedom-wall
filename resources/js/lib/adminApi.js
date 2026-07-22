async function request(path, { method = 'GET', body, isForm = false } = {}) {
  const res = await window.axios.request({
    url: path,
    method,
    data: isForm ? body : body,
    headers: isForm ? { 'Content-Type': 'multipart/form-data' } : undefined,
    validateStatus: () => true,
  });

  if (res.status >= 400) {
    throw new Error(res.data?.error || `Request failed (${res.status})`);
  }
  return res.data;
}

export const adminApi = {
  getPending: (page = 1, limit = 8) =>
    request(`/admin/submissions?status=pending&page=${page}&limit=${limit}`),

  getApproved: (page = 1, limit = 8) =>
    request(`/admin/submissions?status=approved&page=${page}&limit=${limit}`),

  getStats: () => request('/admin/stats'),

  approve: (id, content) =>
    request(`/admin/submissions/${id}/approve`, { method: 'POST', body: { content } }),

  reject: (id) => request(`/admin/submissions/${id}/reject`, { method: 'POST' }),
};
