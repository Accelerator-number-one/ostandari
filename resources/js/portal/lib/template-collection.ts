import { all } from './dom';
import type { RecordData } from '@/types/portal';

/** Repeat a supplied card or row without introducing a second visual template. */
export function bindCollection(
  root: ParentNode,
  selector: string,
  records: RecordData[],
  render: (element: HTMLElement, record: RecordData) => void,
): void {
  const originals = all<HTMLElement>(root, selector);
  const sample = originals[0];
  if (!sample?.parentElement) return;
  const template = sample.cloneNode(true) as HTMLElement;
  const anchor = document.createComment('template collection');
  sample.before(anchor);
  originals.forEach((element) => element.remove());
  records.forEach((record) => {
    const element = template.cloneNode(true) as HTMLElement;
    render(element, record);
    anchor.before(element);
  });
  anchor.remove();
}
