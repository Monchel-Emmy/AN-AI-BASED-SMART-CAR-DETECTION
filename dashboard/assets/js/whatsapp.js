/**
 * Browser-side WhatsApp trigger for localhost development.
 *
 * When the PHP API returns a whatsapp_url (because server-side curl was blocked),
 * we fire it from the browser using a hidden iframe — the browser is allowed
 * by CallMeBot, localhost server is not.
 *
 * Usage: call triggerWhatsApp(url) with the url from the API response.
 */
function triggerWhatsApp(url) {
    if (!url) return;

    // Use a hidden iframe so the page doesn't navigate away
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);

    // Clean up after 10 seconds
    setTimeout(() => iframe.remove(), 10000);

    console.log('WhatsApp alert triggered via browser:', url);
}
