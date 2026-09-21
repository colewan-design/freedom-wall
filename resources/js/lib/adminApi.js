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

  getThreadReports: (limit = 10) => request(`/admin/thread-reports?limit=${limit}`),

  hideThread: (id) => request(`/admin/threads/${id}/hide`, { method: 'POST' }),

  restoreThread: (id) => request(`/admin/threads/${id}/restore`, { method: 'POST' }),

  hideThreadReply: (id) => request(`/admin/thread-replies/${id}/hide`, { method: 'POST' }),

  restoreThreadReply: (id) => request(`/admin/thread-replies/${id}/restore`, { method: 'POST' }),

  dismissThreadReport: (id) => request(`/admin/thread-reports/${id}/resolve`, { method: 'POST' }),
};
