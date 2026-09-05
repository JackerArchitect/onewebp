jQuery(document).ready(function($) {
    let isRunning = false;
    let isScanning = false;
    let scanOffset = 0;

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function updateDashboard(data) {
        if (!data) return;
        $('#stat-total').text(data.total || 0);
        $('#stat-converted').text(data.converted || 0);
        $('#stat-pending').text(data.pending || 0);
        $('#stat-failed').text(data.failed || 0);
        $('#saved-space').text(formatBytes(data.saved_bytes || 0));
        
        let progress = data.progress || 0;
        $('#main-progress-bar').css('width', progress + '%');
        $('#main-progress-text').text(progress + '%');
        
        if (progress >= 100) {
            $('#main-progress-bar').addClass('completed');
            $('#main-progress-text').addClass('completed');
        } else {
            $('#main-progress-bar').removeClass('completed');
            $('#main-progress-text').removeClass('completed');
        }
    }

    function fetchStats() {
        $.post(onewebp_vars.ajaxurl, { 
            action: 'onewebp_get_stats', 
            nonce: onewebp_vars.nonce 
        }, function(res) {
            if (res.success) {
                updateDashboard(res.data);
            } else {
                console.warn('Stats fetch warning:', res.data);
            }
        }).fail(function(xhr) {
            console.warn('Stats fetch failed:', xhr.status, xhr.responseText);
        });
    }

    function scanLibrary(offset) {
        if (isScanning) return;
        isScanning = true;
        
        $('#start-optimize-btn')
            .prop('disabled', true)
            .text(onewebp_vars.text_scanning)
            .css('opacity', '0.6');
        $('#rescan-btn').prop('disabled', true);
        
        $.post(onewebp_vars.ajaxurl, { 
            action: 'onewebp_scan_library', 
            nonce: onewebp_vars.nonce, 
            offset: offset 
        }, function(res) {
            if (res.success) {
                if (res.data.done) {
                    isScanning = false;
                    fetchStats();
                    $('#start-optimize-btn')
                        .prop('disabled', false)
                        .text(onewebp_vars.text_start)
                        .css('opacity', '1');
                    $('#rescan-btn').prop('disabled', false);
                } else {
                    setTimeout(function() {
                        scanLibrary(res.data.next_offset);
                    }, 100);
                }
            } else {
                alert('Scan failed: ' + (res.data || 'Unknown error'));
                isScanning = false;
                $('#start-optimize-btn')
                    .prop('disabled', false)
                    .text(onewebp_vars.text_start)
                    .css('opacity', '1');
                $('#rescan-btn').prop('disabled', false);
            }
        }).fail(function(xhr) {
            alert('AJAX Scan Failed! Status: ' + xhr.status);
            isScanning = false;
            $('#start-optimize-btn')
                .prop('disabled', false)
                .text(onewebp_vars.text_start)
                .css('opacity', '1');
            $('#rescan-btn').prop('disabled', false);
        });
    }

    // Start scan on page load
    scanLibrary(0);

    // Rescan button
    $('#rescan-btn').on('click', function() {
        if (isRunning) return;
        scanLibrary(0);
    });

    // Start optimization
    $('#start-optimize-btn').on('click', function() {
        if (isScanning || $(this).prop('disabled')) return;
        isRunning = true;
        $(this).hide();
        $('#stop-optimize-btn').show();
        runBatch();
    });

    // Stop optimization
    $('#stop-optimize-btn').on('click', function() {
        isRunning = false;
        $(this).hide();
        $('#start-optimize-btn').show().text(onewebp_vars.text_paused);
    });

    function runBatch() {
        if (!isRunning) return;
        
        $.post(onewebp_vars.ajaxurl, { 
            action: 'onewebp_run_batch', 
            nonce: onewebp_vars.nonce 
        }, function(res) {
            if (res.success) {
                fetchStats();
                if (res.data.done) {
                    isRunning = false;
                    $('#start-optimize-btn').show().text(onewebp_vars.text_completed);
                    $('#stop-optimize-btn').hide();
                } else {
                    setTimeout(runBatch, 500);
                }
            } else {
                alert('Batch processing failed: ' + (res.data || 'Unknown error'));
                isRunning = false;
                $('#start-optimize-btn').show().text('Start Optimization');
                $('#stop-optimize-btn').hide();
            }
        }).fail(function(xhr) {
            alert('AJAX Batch Failed! Status: ' + xhr.status);
            isRunning = false;
            $('#start-optimize-btn').show().text('Start Optimization');
            $('#stop-optimize-btn').hide();
        });
    }

    // Copy URL handler
    $(document).on('click', '.onewebp-copy-url', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var url = $btn.data('url');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                var originalText = $btn.text();
                $btn.text('Copied!');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 1500);
            });
        } else {
            // Fallback
            var input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            var originalText = $btn.text();
            $btn.text('Copied!');
            setTimeout(function() {
                $btn.text(originalText);
            }, 1500);
        }
    });
});
