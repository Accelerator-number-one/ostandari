export function safeDestination(value: unknown, fallback: string): string {
  if (typeof value !== 'string' || !value.trim() || value === '#') return fallback;
  try {
    const url = new URL(value, 'http://127.0.0.1');
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol) ? value : fallback;
  } catch {
    return fallback;
  }
}
