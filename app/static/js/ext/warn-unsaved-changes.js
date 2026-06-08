const warnOnUnsavedChanges = () => {
  window.addEventListener('beforeunload', (e) => {
    window.unsavedChanges = window.unsavedChanges || false;
    if (window.unsavedChanges) {
      // eslint-disable-next-line max-len
      const confirmationMessage = 'It looks like you have been editing something. If you leave before saving, your changes will be lost.';
      (e || window.event).returnValue = confirmationMessage; // Gecko + IE
      return confirmationMessage; // Gecko + Webkit, Safari, Chrome etc.
    }
  });
}
