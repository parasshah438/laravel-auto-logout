{{-- 
    Include this component in your authenticated layout.
    Handles auto-logout when user closes the tab or browser
    using navigator.sendBeacon() API.
    
    Usage: @include('components.auto-logout')
    Place it before </body> in your layout.
--}}

@auth
<script>
(function() {
    const BEACON_URL = "{{ route('auto-logout.beacon') }}";
    const TAB_KEY = 'auto_logout_tabs_{{ Auth::id() }}';
    let isManualLogout = false;

    // -------------------------------------------------------
    // Tab counting using localStorage (synchronous & reliable)
    // Increment on load, decrement on unload.
    // Beacon fires only when the LAST tab closes (count === 0).
    // -------------------------------------------------------

    function getTabCount() {
        return parseInt(localStorage.getItem(TAB_KEY) || '0', 10);
    }

    function setTabCount(count) {
        localStorage.setItem(TAB_KEY, Math.max(count, 0).toString());
    }

    // Register this tab
    setTabCount(getTabCount() + 1);

    /**
     * Send logout beacon when the LAST tab is closing.
     */
    function sendLogoutBeacon(reason) {
        if (isManualLogout) return;

        // Decrement tab count (synchronous — happens before page dies)
        var remaining = getTabCount() - 1;
        setTabCount(remaining);

        // Only send beacon if this was the last tab
        if (remaining > 0) {
            return;
        }

        // Clean up
        localStorage.removeItem(TAB_KEY);

        const data = new Blob(
            [JSON.stringify({ reason: reason, user_id: {{ Auth::id() }} })],
            { type: 'application/json' }
        );

        navigator.sendBeacon(BEACON_URL, data);
    }

    // Detect manual logout button/form clicks — skip beacon
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href*="logout"], button[type="submit"]');
        if (link) {
            const form = link.closest('form');
            if ((link.href && link.href.indexOf('logout') !== -1) ||
                (form && form.action && form.action.indexOf('logout') !== -1)) {
                isManualLogout = true;
                // Clean up tab counter on manual logout
                localStorage.removeItem(TAB_KEY);
            }
        }
    });

    document.addEventListener('submit', function(e) {
        if (e.target.action && e.target.action.indexOf('logout') !== -1) {
            isManualLogout = true;
            localStorage.removeItem(TAB_KEY);
        }
    });

    // Fires when the tab/window is being closed
    window.addEventListener('beforeunload', function(e) {
        sendLogoutBeacon('tab_or_browser_close');
    });
})();
</script>
@endauth
