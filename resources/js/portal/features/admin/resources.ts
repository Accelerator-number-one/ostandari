import schemas from '@/data/admin/resource-schema.json';
import { api, ApiError } from '@/lib/api/client';
import type { RecordData } from '@/types/portal';

const resources = schemas as Record<
  string,
  { table: string; scope: Record<string, string> | never[] }
>;

export function resourceScope(resource: string): Record<string, string> {
  const scope = resources[resource]?.scope;
  return scope && !Array.isArray(scope) ? scope : {};
}

export function lookupResource(table: string): string {
  return Object.entries(resources).find(([, resource]) => resource.table === table)?.[0] ?? table;
}

export function resourceRows(resource: string, data: unknown): RecordData[] {
  const rows =
    resource === 'roles' && data && !Array.isArray(data)
      ? (data as { roles?: unknown }).roles
      : data;
  return Array.isArray(rows) ? rows : [];
}

export async function lookupRecords(
  resource: string,
  signal: AbortSignal,
  filters: Record<string, string> = {},
): Promise<RecordData[]> {
  let endpoint = resource === 'media' ? 'media' : 'admin/' + resource;
  const records: RecordData[] = [];
  let page = 1;
  let last = 1;
  do {
    const query = new URLSearchParams({
      ...filters,
      per_page: '100',
      page: String(page),
      ...(resource === 'media' ? { visibility: 'public' } : {}),
    });
    let result;
    try {
      result = await api<unknown>(endpoint + '?' + query, { signal });
    } catch (error) {
      if (
        !(error instanceof ApiError) ||
        error.status !== 403 ||
        resource === 'media' ||
        endpoint.includes('/lookups/')
      )
        throw error;
      endpoint = 'admin/lookups/' + resource;
      result = await api<unknown>(endpoint + '?' + query, { signal });
    }
    records.push(...resourceRows(resource, result.data));
    last = result.meta?.last_page ?? 1;
    page += 1;
  } while (page <= last);
  return records;
}
