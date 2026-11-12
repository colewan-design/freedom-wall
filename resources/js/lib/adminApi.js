async function request(path, { method = 'GET', body, isForm = false } = {}) {
  const res = await window.axios.request({
    url: path,
    method,
    data: isForm ? body : body,
    headers: isForm ? { 'Content-Type': 'multipart/form-data' } : undefined,
    validateStatus: () => true,
  });

  if (res.status >= 400) {
    // Validation failures come back as { message, errors } rather than { error }.
    throw new Error(res.data?.error || res.data?.message || `Request failed (${res.status})`);
  }
  return res.data;
}

export const adminApi = {
  getPending: (page = 1, limit = 8) =>
    request(`/admin/submissions?status=pending&page=${page}&limit=${limit}`),

  getApproved: (page = 1, limit = 8) =>
    request(`/admin/submissions?status=approved&page=${page}&limit=${limit}`),

  getStats: () => request('/admin/stats'),

  getVisits: () => request('/admin/visits'),

  approve: (id, content) =>
    request(`/admin/submissions/${id}/approve`, { method: 'POST', body: { content } }),

  approveAll: (edits = {}) =>
    request('/admin/submissions/approve-all', { method: 'POST', body: { edits } }),

  reject: (id, reason = 'other') =>
    request(`/admin/submissions/${id}/reject`, { method: 'POST', body: { reason } }),

  getThreadReports: (limit = 10) => request(`/admin/thread-reports?limit=${limit}`),

  hideThread: (id) => request(`/admin/threads/${id}/hide`, { method: 'POST' }),

  restoreThread: (id) => request(`/admin/threads/${id}/restore`, { method: 'POST' }),

  hideThreadReply: (id) => request(`/admin/thread-replies/${id}/hide`, { method: 'POST' }),

  restoreThreadReply: (id) => request(`/admin/thread-replies/${id}/restore`, { method: 'POST' }),

  dismissThreadReport: (id) => request(`/admin/thread-reports/${id}/resolve`, { method: 'POST' }),

  getJobListings: (status = 'pending', page = 1, limit = 15) =>
    request(`/admin/jobs/listings?status=${status}&page=${page}&limit=${limit}`),

  approveJob: (id, edits = {}) => request(`/admin/jobs/listings/${id}/approve`, { method: 'POST', body: edits }),

  rejectJob: (id) => request(`/admin/jobs/listings/${id}/reject`, { method: 'POST' }),

  closeJob: (id) => request(`/admin/jobs/listings/${id}/close`, { method: 'POST' }),

  getJobSources: () => request('/admin/jobs/sources'),

  createJobSource: (body) => request('/admin/jobs/sources', { method: 'POST', body }),

  updateJobSource: (id, body) => request(`/admin/jobs/sources/${id}`, { method: 'PATCH', body }),

  deleteJobSource: (id) => request(`/admin/jobs/sources/${id}`, { method: 'DELETE' }),

  fetchJobSource: (id) => request(`/admin/jobs/sources/${id}/fetch`, { method: 'POST' }),
};
