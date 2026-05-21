        </div><!-- .content -->
    </main>

</div><!-- .layout -->

<script src="/accident/web/dashboard/assets/js/main.js"></script>
<script src="/accident/web/dashboard/assets/js/whatsapp.js"></script>
<script>
// Poll every 10 seconds for new urgent/medium accidents and fire WhatsApp from browser
(function pollAlerts() {
    fetch('/accident/web/api/accident/pending_alerts.php')
        .then(r => r.json())
        .then(data => {
            if (data.alerts && data.alerts.length > 0) {
                data.alerts.forEach(function(alert) {
                    console.log('Firing WhatsApp for accident #' + alert.accident_id + ' (' + alert.severity + ')');
                    triggerWhatsApp(alert.whatsapp_url);
                });
            }
        })
        .catch(() => {}); // Silently ignore network errors

    setTimeout(pollAlerts, 10000);
})();
</script>
</body>
</html>
