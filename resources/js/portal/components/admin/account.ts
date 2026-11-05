import {api} from '@/lib/api/client';

export async function logoutAdmin(_preview=false,loginPath='/panel/admin/login'):Promise<void>{
  await api('auth/logout',{method:'POST'});
  window.location.assign(loginPath);
}
