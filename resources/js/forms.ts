/** Serialize exactly the published form schema, including empty optional choices. */
export async function dynamicAnswers(form: HTMLFormElement, uploadFile: (file: File) => Promise<number>) {
  const answers: Record<string, unknown> = {};
  for (const field of form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[data-answer-key]')) {
    const key = field.dataset.answerKey!;
    const type = field.dataset.answerType;
    if (type === 'boolean') answers[key] = (field as HTMLInputElement).checked;
    else if (type === 'multi_select') answers[key] = [...(field as HTMLSelectElement).selectedOptions].map(option => option.value).filter(Boolean);
    else if (type === 'file') {
      const file = (field as HTMLInputElement).files?.[0];
      answers[key] = file?.size ? await uploadFile(file) : null;
    } else answers[key] = field.value === '' ? null : (type === 'number' ? Number(field.value) : field.value);
  }
  return answers;
}
