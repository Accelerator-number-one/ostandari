import createDOMPurify from 'dompurify';

let purifier: ReturnType<typeof createDOMPurify> | undefined;

export function sanitizedFragment(html: string): DocumentFragment {
  if (!purifier) {
    purifier = createDOMPurify(window);
    purifier.addHook('uponSanitizeAttribute', (_node, attribute) => {
      // Content is data; it must never register Alpine directives or delegated UI commands.
      if (/^(?:x-|@|:)/i.test(attribute.attrName)) attribute.keepAttr = false;
    });
  }
  return purifier.sanitize(html, {
    RETURN_DOM_FRAGMENT: true,
    ALLOW_DATA_ATTR: false,
    SANITIZE_NAMED_PROPS: true,
    FORBID_TAGS: ['style', 'link', 'iframe', 'object', 'embed', 'form', 'base', 'meta'],
    FORBID_ATTR: ['style'],
  });
}
