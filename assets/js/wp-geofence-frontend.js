(function() {
    'use strict';
    var doc = window.document,
        wpData = window.wpGeofenceData || {};

    /**
     * Get localized strings or English defaults
     */
    function t(k) {
        return window.wpGeofenceL10n && window.wpGeofenceL10n[k] ? window.wpGeofenceL10n[k] : {
            'no_support': 'Your browser does not support geolocation.',
            'precision_insufficient': 'Insufficient precision:',
            'cache_invalidated': 'Position updated — access revoked.'
        }[k] || k;
    }

    /**
     * Validates if current time is within date/time windows
     * Uses server time offset to prevent user clock tampering
     */
    function isTimeValid(cfg) {
        // Calculate the real time based on server offset
        var now = new Date();
        if (cfg.serverTime) {
            var serverDate = new Date(cfg.serverTime.replace(/-/g, '/')); // Compatibility with Safari
            var localNow = new Date();
            var offset = serverDate.getTime() - localNow.getTime();
            now = new Date(localNow.getTime() + offset);
        }

        var startLimit = null;
        var endLimit = null;

        // --- Start Logic ---
        if (cfg.dateStart || cfg.timeStart) {
            if (cfg.dateStart) {
                var dParts = cfg.dateStart.split('-').map(Number);
                var tParts = cfg.timeStart ? cfg.timeStart.split(':').map(Number) : [0, 0];
                startLimit = new Date(dParts[0], dParts[1] - 1, dParts[2], tParts[0], tParts[1], 0);
            } else if (cfg.timeStart) {
                var tPartsOnly = cfg.timeStart.split(':').map(Number);
                startLimit = new Date(now.getFullYear(), now.getMonth(), now.getDate(), tPartsOnly[0], tPartsOnly[1], 0);
            }
        }

        // --- End Logic ---
        if (cfg.dateEnd || cfg.timeEnd) {
            if (cfg.dateEnd) {
                var dPartsE = cfg.dateEnd.split('-').map(Number);
                var tPartsE = cfg.timeEnd ? cfg.timeEnd.split(':').map(Number) : [23, 59];
                endLimit = new Date(dPartsE[0], dPartsE[1] - 1, dPartsE[2], tPartsE[0], tPartsE[1], 59);
            } else if (cfg.timeEnd) {
                var tPartsOnlyE = cfg.timeEnd.split(':').map(Number);
                endLimit = new Date(now.getFullYear(), now.getMonth(), now.getDate(), tPartsOnlyE[0], tPartsOnlyE[1], 59);
            }
        }

        if (startLimit && now < startLimit) return false;
        if (endLimit && now > endLimit) return false;

        return true;
    }

    /**
     * Haversine formula for distance calculation
     */
    function hav(a, b, c, d) {
        var R = 6371e3,
            phi1 = a * Math.PI / 180,
            phi2 = c * Math.PI / 180,
            dPhi = (c - a) * Math.PI / 180,
            dLamba = (d - b) * Math.PI / 180;
        var x = Math.sin(dPhi / 2) * Math.sin(dPhi / 2) + Math.cos(phi1) * Math.cos(phi2) * Math.sin(dLamba / 2) * Math.sin(dLamba / 2);
        return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
    }

    function setSrcs(node) {
        var ifs = node.querySelectorAll('iframe[data-src]');
        ifs.forEach(function(f) {
            var s = f.getAttribute('data-src');
            if (s) {
                f.setAttribute('src', s);
                f.removeAttribute('data-src');
            }
        });
    }

    function readCache(k) {
        try {
            var r = localStorage.getItem(k);
            return r ? JSON.parse(r) : null;
        } catch (e) { return null; }
    }

    function writeCache(k, v) {
        try {
            localStorage.setItem(k, JSON.stringify({
                timestamp: Date.now(),
                coords: v
            }));
        } catch (e) {}
    }

    function toggleContent(wrap, msg, show) {
        if (!wrap || !msg) return;
        if (show) {
            msg.style.setProperty('display', 'none', 'important');
            wrap.style.setProperty('display', 'block', 'important');
            wrap.style.setProperty('visibility', 'visible', 'important');
            wrap.style.setProperty('opacity', '1', 'important');
        } else {
            wrap.style.setProperty('display', 'none', 'important');
            if (msg.textContent.trim() !== "") {
                msg.style.setProperty('display', 'block', 'important');
            } else {
                msg.style.setProperty('display', 'none', 'important');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        Object.keys(wpData).forEach(function(id) {
            (function(i) {
                try {
                    var cfg = wpData[i];
                    if (!cfg) return;
                    var msg = doc.getElementById('geo_msg_' + i),
                        wrap = doc.getElementById('geo_wrap_' + i);
                    if (!msg || !wrap) return;

                    // 1. Time Validation using Server Reference
                    if (!isTimeValid(cfg)) {
                        msg.textContent = cfg.msgLimit || '';
                        toggleContent(wrap, msg, false);
                        return;
                    }
                    
                    var areas = Array.isArray(cfg.areas) ? cfg.areas : [],
                        lazy = cfg.lazyload === 'yes',
                        minAcc = Number(cfg.minAccuracy) || 0,
                        redirect = cfg.redirectURL || '',
                        msgOut = cfg.msgOut || '',
                        msgErr = cfg.msgError || '',
                        cacheEnabled = (cfg.cacheEnabled === 'yes'),
                        cacheTTL = Number(cfg.cacheTTL) || 60,
                        cacheAcc = Number(cfg.cacheAccuracy) || 50,
                        cacheKey = 'wp_geofence_cache_' + i,
                        checkLocation = cfg.checkLocation; 

                    // 2. Skip Location if checkLocation is "no"
                    if (String(checkLocation).trim() === 'no') {
                        toggleContent(wrap, msg, true);
                        if (lazy && !wrap.innerHTML.trim()) {
                            wrap.innerHTML = cfg.contentSafe || cfg.contentFull || '';
                            setSrcs(wrap);
                        }
                        return;
                    }

                    function evaluate(pos, fromCache) {
                        var lat = pos.latitude, lon = pos.longitude, acc = pos.accuracy || 1e6;
                        
                        if (minAcc > 0 && acc > minAcc) {
                            msg.textContent = t('precision_insufficient') + ' ' + Math.round(acc) + 'm';
                            toggleContent(wrap, msg, false);
                            return false;
                        }

                        var ok = !areas.length; 
                        for (var j = 0; j < areas.length; j++) {
                            if (hav(lat, lon, areas[j].lat, areas[j].lng) <= areas[j].radius) {
                                ok = true;
                                break;
                            }
                        }

                        if (ok) {
                            toggleContent(wrap, msg, true);
                            if (lazy && !wrap.innerHTML.trim()) {
                                wrap.innerHTML = cfg.contentSafe || cfg.contentFull || '';
                                setSrcs(wrap);
                            }
                            try { wrap.dispatchEvent(new Event('geofence:show')); } catch (e) {}
                            return true;
                        } else {
                            msg.textContent = msgOut;
                            toggleContent(wrap, msg, false);
                            if (redirect && !fromCache) location.href = redirect;
                            return false;
                        }
                    }

                    // 3. Cache Logic
                    if (cacheEnabled) {
                        var cached = readCache(cacheKey);
                        if (cached && cached.coords) {
                            var age = (Date.now() - cached.timestamp) / 1000;
                            if (age <= cacheTTL && cached.coords.accuracy <= cacheAcc) {
                                var used = evaluate(cached.coords, true);
                                if (used) {
                                    navigator.geolocation.getCurrentPosition(function(real) {
                                        var rp = { latitude: real.coords.latitude, longitude: real.coords.longitude, accuracy: real.coords.accuracy };
                                        evaluate(rp, false);
                                        writeCache(cacheKey, rp);
                                    }, null, { enableHighAccuracy: true });
                                    return;
                                }
                            }
                        }
                    }

                    if (!navigator.geolocation) {
                        msg.textContent = t('no_support');
                        toggleContent(wrap, msg, false);
                        return;
                    }

                    // 4. Live GPS Logic
                    navigator.geolocation.getCurrentPosition(function(p) {
                        var pos = { latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy };
                        if (cacheEnabled && pos.accuracy <= cacheAcc) writeCache(cacheKey, pos);
                        evaluate(pos, false);
                    }, function() {
                        msg.textContent = msgErr;
                        toggleContent(wrap, msg, false);
                    }, { enableHighAccuracy: true, timeout: 10000 });

                } catch (e) {
                    console.error('WP Geofence Error:', i, e);
                }
            })(id);
        });
    });
})();