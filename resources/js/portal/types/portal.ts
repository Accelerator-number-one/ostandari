export type RecordData = Record<string, unknown> & { id?: number | string };

export interface ApiResult<T = RecordData> {
  success: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[]>;
  meta?: { total: number; per_page: number; current_page: number; last_page: number };
}

export interface TemplatePage {
  id: string;
  group: string;
  source: string;
  path: string;
  scope: string;
  bodyClass: string;
  title: string;
  lang: string;
  css: string;
  controller: string;
  resource: string;
  defaults: Record<string, string>;
  editor?: { mode: 'create' | 'edit'; id?: string; returnTo: string };
}

export interface PortalWorkspace {
  type: 'central' | 'province' | 'county' | 'citizen' | 'staff';
  staff_slug?: string;
  organization_id: number | null;
  organization_name: string | null;
  county: { id: number; name: string; slug: string } | null;
  can_write: boolean;
  can_manage_annual_slogan?: boolean;
  is_global: boolean;
  dashboard_path: string;
  login_path: string;
  public_path: string;
}

export interface PortalUser extends RecordData {
  portal_context?: PortalWorkspace;
  name: string;
  person_type: string;
  mobile?: string;
  national_code?: string;
  address?: string;
  county_id?: number;
  role?: { name: string; permissions: string[] };
}

export interface PageContext {
  root: HTMLElement;
  page: TemplatePage;
  preview: boolean;
  citizenDemo?: boolean;
  signal: AbortSignal;
  path: string;
  navigate?: (href: string) => void;
}
